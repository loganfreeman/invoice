<?php namespace App\Http\Controllers;

use Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Utils;
use Response;
use Input;
use Validator;
use App\Models\Hotel;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Invitation;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\HotelRepository;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Http\Controllers\BaseAPIController;
use App\Ninja\Transformers\HotelTransformer;
use App\Http\Requests\CreateHotelRequest;
use App\Http\Requests\UpdateHotelRequest;
use App\Services\HotelService;

class HotelApiController extends BaseAPIController
{
    protected $hotelRepo;

    public function __construct(HotelService $hotelService, HotelRepository $hotelRepo, ClientRepository $clientRepo, PaymentRepository $paymentRepo, Mailer $mailer)
    {
        parent::__construct();

        $this->hotelRepo = $hotelRepo;
        $this->clientRepo = $clientRepo;
        $this->paymentRepo = $paymentRepo;
        $this->hotelService = $hotelService;
        $this->mailer = $mailer;
    }

    /**
     * @SWG\Get(
     *   path="/hotels",
     *   summary="List of hotels",
     *   tags={"hotel"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with hotels",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Hotel"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $paginator = Hotel::scope()->withTrashed();
        $hotels = Hotel::scope()->withTrashed()
                        ->with(array_merge(['hotel_items'], $this->getIncluded()));

        if ($clientPublicId = Input::get('client_id')) {
            $filter = function($query) use ($clientPublicId) {
                $query->where('public_id', '=', $clientPublicId);
            };
            $hotels->whereHas('client', $filter);
            $paginator->whereHas('client', $filter);
        }

        $hotels = $hotels->orderBy('created_at', 'desc')->paginate();

        /*
        // Add the first invitation link to the data
        foreach ($hotels as $key => $hotel) {
            foreach ($hotel->invitations as $subKey => $invitation) {
                $hotels[$key]['link'] = $invitation->getLink();
            }
            unset($hotel['invitations']);
        }
        */

        $transformer = new HotelTransformer(Auth::user()->account, Input::get('serializer'));
        $paginator = $paginator->paginate();

        $data = $this->createCollection($hotels, $transformer, 'hotels', $paginator);

        return $this->response($data);
    }

        /**
         * @SWG\Get(
         *   path="/hotels/{hotel_id}",
         *   summary="Individual Hotel",
         *   tags={"hotel"},
         *   @SWG\Response(
         *     response=200,
         *     description="A single hotel",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Hotel"))
         *   ),
         *   @SWG\Response(
         *     response="default",
         *     description="an ""unexpected"" error"
         *   )
         * )
         */

    public function show($publicId)
    {

        $hotel = Hotel::scope($publicId)->withTrashed()->first();

        if(!$hotel)
            return $this->errorResponse(['message'=>'Hotel does not exist!'], 404);

        $transformer = new HotelTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($hotel, $transformer, 'hotel');

        return $this->response($data);
    }

    /**
     * @SWG\Post(
     *   path="/hotels",
     *   tags={"hotel"},
     *   summary="Create an hotel",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Hotel")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New hotel",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Hotel"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateHotelRequest $request)
    {
        $data = Input::all();
        $error = null;

        if (isset($data['email'])) {
            $email = $data['email'];
            $client = Client::scope()->whereHas('contacts', function($query) use ($email) {
                $query->where('email', '=', $email);
            })->first();

            if (!$client) {
                $validator = Validator::make(['email'=>$email], ['email' => 'email']);
                if ($validator->fails()) {
                    $messages = $validator->messages();
                    return $messages->first();
                }

                $clientData = ['contact' => ['email' => $email]];
                foreach ([
                    'name',
                    'address1',
                    'address2',
                    'city',
                    'state',
                    'postal_code',
                    'private_notes',
                ] as $field) {
                    if (isset($data[$field])) {
                        $clientData[$field] = $data[$field];
                    }
                }
                foreach ([
                    'first_name',
                    'last_name',
                    'phone',
                ] as $field) {
                    if (isset($data[$field])) {
                        $clientData['contact'][$field] = $data[$field];
                    }
                }

                $client = $this->clientRepo->save($clientData);
            }
        } else if (isset($data['client_id'])) {
            $client = Client::scope($data['client_id'])->firstOrFail();
        }

        $data = self::prepareData($data, $client);
        $data['client_id'] = $client->id;
        $hotel = $this->hotelService->save($data);
        $payment = false;

        // Optionally create payment with hotel
        if (isset($data['paid']) && $data['paid']) {
            $payment = $this->paymentRepo->save([
                'hotel_id' => $hotel->id,
                'client_id' => $client->id,
                'amount' => $data['paid']
            ]);
        }

        if (isset($data['email_hotel']) && $data['email_hotel']) {
            if ($payment) {
                $this->mailer->sendPaymentConfirmation($payment);
            } else {
                $this->mailer->sendHotel($hotel);
            }
        }

        $hotel = Hotel::scope($hotel->public_id)->with('client', 'hotel_items', 'invitations')->first();
        $transformer = new HotelTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($hotel, $transformer, 'hotel');

        return $this->response($data);
    }

    private function prepareData($data, $client)
    {
        $account = Auth::user()->account;
        $account->loadLocalizationSettings($client);

        // set defaults for optional fields
        $fields = [
            'discount' => 0,
            'is_amount_discount' => false,
            'terms' => '',
            'hotel_footer' => '',
            'public_notes' => '',
            'po_number' => '',
            'hotel_design_id' => $account->hotel_design_id,
            'hotel_items' => [],
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_taxes1' => false,
            'custom_taxes2' => false,
            'partial' => 0
        ];

        if (!isset($data['hotel_status_id']) || $data['hotel_status_id'] == 0) {
            $data['hotel_status_id'] = INVOICE_STATUS_DRAFT;
        }

        if (!isset($data['hotel_date'])) {
            $fields['hotel_date_sql'] = date_create()->format('Y-m-d');
        }
        if (!isset($data['due_date'])) {
            $fields['due_date_sql'] = false;
        }

        foreach ($fields as $key => $val) {
            if (!isset($data[$key])) {
                $data[$key] = $val;
            }
        }

        // initialize the line items
        if (isset($data['product_key']) || isset($data['cost']) || isset($data['notes']) || isset($data['qty'])) {
            $data['hotel_items'] = [self::prepareItem($data)];

            // make sure the tax isn't applied twice (for the hotel and the line item)
            unset($data['hotel_items'][0]['tax_name']);
            unset($data['hotel_items'][0]['tax_rate']);
        } else {
            foreach ($data['hotel_items'] as $index => $item) {
                $data['hotel_items'][$index] = self::prepareItem($item);
            }
        }

        return $data;
    }

    private function prepareItem($item)
    {
        // if only the product key is set we'll load the cost and notes
        if (!empty($item['product_key']) && empty($item['cost']) && empty($item['notes'])) {
            $product = Product::findProductByKey($item['product_key']);
            if ($product) {
                if (empty($item['cost'])) {
                    $item['cost'] = $product->cost;
                }
                if (empty($item['notes'])) {
                    $item['notes'] = $product->notes;
                }
            }
        }

        $fields = [
            'cost' => 0,
            'product_key' => '',
            'notes' => '',
            'qty' => 1
        ];

        foreach ($fields as $key => $val) {
            if (!isset($item[$key])) {
                $item[$key] = $val;
            }
        }

        return $item;
    }

    public function emailHotel()
    {
        $data = Input::all();
        $error = null;

        $hotel = Hotel::scope($data['id'])->withTrashed()->first();

        if(!$hotel)
            return $this->errorResponse(['message'=>'Hotel does not exist.'], 400);


        $this->mailer->sendHotel($hotel, false, false);


        if($error) {
            return $this->errorResponse(['message'=>'There was an error sending the hotel'], 400);
        }
        else {
            $response = json_encode(RESULT_SUCCESS, JSON_PRETTY_PRINT);
        }

        $headers = Utils::getApiHeaders();
        return Response::make($response, $error ? 400 : 200, $headers);
    }


        /**
         * @SWG\Put(
         *   path="/hotels",
         *   tags={"hotel"},
         *   summary="Update an hotel",
         *   @SWG\Parameter(
         *     in="body",
         *     name="body",
         *     @SWG\Schema(ref="#/definitions/Hotel")
         *   ),
         *   @SWG\Response(
         *     response=200,
         *     description="Update hotel",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Hotel"))
         *   ),
         *   @SWG\Response(
         *     response="default",
         *     description="an ""unexpected"" error"
         *   )
         * )
         */
    public function update(UpdateHotelRequest $request, $publicId)
    {
        if ($request->action == ACTION_ARCHIVE) {
            $hotel = Hotel::scope($publicId)->firstOrFail();
            $this->hotelRepo->archive($hotel);

            $transformer = new HotelTransformer(\Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($hotel, $transformer, 'hotel');

            return $this->response($data);
        }
        else if ($request->action == ACTION_CONVERT) {
            $quote = Hotel::scope($publicId)->firstOrFail();
            $hotel = $this->hotelRepo->cloneHotel($quote, $quote->id);

            $transformer = new HotelTransformer(\Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($hotel, $transformer, 'hotel');

            return $this->response($data);
        }
        else if ($request->action == ACTION_RESTORE) {
            $hotel = Hotel::scope($publicId)->withTrashed()->firstOrFail();
            $this->hotelRepo->restore($hotel);

            $transformer = new HotelTransformer(\Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($hotel, $transformer, 'hotel');

            return $this->response($data);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $this->hotelService->save($data);

        $hotel = Hotel::scope($publicId)->with('client', 'hotel_items', 'invitations')->firstOrFail();
        $transformer = new HotelTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($hotel, $transformer, 'hotel');

        return $this->response($data);
    }

        /**
         * @SWG\Delete(
         *   path="/hotels",
         *   tags={"hotel"},
         *   summary="Delete an hotel",
         *   @SWG\Parameter(
         *     in="body",
         *     name="body",
         *     @SWG\Schema(ref="#/definitions/Hotel")
         *   ),
         *   @SWG\Response(
         *     response=200,
         *     description="Delete hotel",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Hotel"))
         *   ),
         *   @SWG\Response(
         *     response="default",
         *     description="an ""unexpected"" error"
         *   )
         * )
         */

    public function destroy($publicId)
    {
        $data['public_id'] = $publicId;
        $hotel = Hotel::scope($publicId)->firstOrFail();

        $this->hotelRepo->delete($hotel);

        $transformer = new HotelTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($hotel, $transformer, 'hotel');

        return $this->response($data);

    }

}
