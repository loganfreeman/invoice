<?php namespace App\Http\Controllers;

use Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Utils;
use Response;
use Input;
use Validator;
use App\Models\Itinerary;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Invitation;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\ItineraryRepository;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Http\Controllers\BaseAPIController;
use App\Ninja\Transformers\ItineraryTransformer;
use App\Http\Requests\CreateItineraryRequest;
use App\Http\Requests\UpdateItineraryRequest;
use App\Services\ItineraryService;

class ItineraryApiController extends BaseAPIController
{
    protected $itineraryRepo;

    public function __construct(ItineraryService $itineraryService, ItineraryRepository $itineraryRepo, ClientRepository $clientRepo, PaymentRepository $paymentRepo, Mailer $mailer)
    {
        parent::__construct();

        $this->itineraryRepo = $itineraryRepo;
        $this->clientRepo = $clientRepo;
        $this->paymentRepo = $paymentRepo;
        $this->itineraryService = $itineraryService;
        $this->mailer = $mailer;
    }

    /**
     * @SWG\Get(
     *   path="/itinerarys",
     *   summary="List of itinerarys",
     *   tags={"itinerary"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with itinerarys",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Itinerary"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $paginator = Itinerary::scope()->withTrashed();
        $itinerarys = Itinerary::scope()->withTrashed()
                        ->with(array_merge(['itinerary_items'], $this->getIncluded()));

        if ($clientPublicId = Input::get('client_id')) {
            $filter = function($query) use ($clientPublicId) {
                $query->where('public_id', '=', $clientPublicId);
            };
            $itinerarys->whereHas('client', $filter);
            $paginator->whereHas('client', $filter);
        }

        $itinerarys = $itinerarys->orderBy('created_at', 'desc')->paginate();

        /*
        // Add the first invitation link to the data
        foreach ($itinerarys as $key => $itinerary) {
            foreach ($itinerary->invitations as $subKey => $invitation) {
                $itinerarys[$key]['link'] = $invitation->getLink();
            }
            unset($itinerary['invitations']);
        }
        */

        $transformer = new ItineraryTransformer(Auth::user()->account, Input::get('serializer'));
        $paginator = $paginator->paginate();

        $data = $this->createCollection($itinerarys, $transformer, 'itinerarys', $paginator);

        return $this->response($data);
    }

        /**
         * @SWG\Get(
         *   path="/itinerarys/{itinerary_id}",
         *   summary="Individual Itinerary",
         *   tags={"itinerary"},
         *   @SWG\Response(
         *     response=200,
         *     description="A single itinerary",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Itinerary"))
         *   ),
         *   @SWG\Response(
         *     response="default",
         *     description="an ""unexpected"" error"
         *   )
         * )
         */

    public function show($publicId)
    {

        $itinerary = Itinerary::scope($publicId)->withTrashed()->first();

        if(!$itinerary)
            return $this->errorResponse(['message'=>'Itinerary does not exist!'], 404);

        $transformer = new ItineraryTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($itinerary, $transformer, 'itinerary');

        return $this->response($data);
    }

    /**
     * @SWG\Post(
     *   path="/itinerarys",
     *   tags={"itinerary"},
     *   summary="Create an itinerary",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Itinerary")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New itinerary",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Itinerary"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateItineraryRequest $request)
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
        $itinerary = $this->itineraryService->save($data);
        $payment = false;

        // Optionally create payment with itinerary
        if (isset($data['paid']) && $data['paid']) {
            $payment = $this->paymentRepo->save([
                'itinerary_id' => $itinerary->id,
                'client_id' => $client->id,
                'amount' => $data['paid']
            ]);
        }

        if (isset($data['email_itinerary']) && $data['email_itinerary']) {
            if ($payment) {
                $this->mailer->sendPaymentConfirmation($payment);
            } else {
                $this->mailer->sendItinerary($itinerary);
            }
        }

        $itinerary = Itinerary::scope($itinerary->public_id)->with('client', 'itinerary_items', 'invitations')->first();
        $transformer = new ItineraryTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($itinerary, $transformer, 'itinerary');

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
            'itinerary_footer' => '',
            'public_notes' => '',
            'po_number' => '',
            'itinerary_design_id' => $account->itinerary_design_id,
            'itinerary_items' => [],
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_taxes1' => false,
            'custom_taxes2' => false,
            'partial' => 0
        ];

        if (!isset($data['itinerary_status_id']) || $data['itinerary_status_id'] == 0) {
            $data['itinerary_status_id'] = INVOICE_STATUS_DRAFT;
        }

        if (!isset($data['itinerary_date'])) {
            $fields['itinerary_date_sql'] = date_create()->format('Y-m-d');
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
            $data['itinerary_items'] = [self::prepareItem($data)];

            // make sure the tax isn't applied twice (for the itinerary and the line item)
            unset($data['itinerary_items'][0]['tax_name']);
            unset($data['itinerary_items'][0]['tax_rate']);
        } else {
            foreach ($data['itinerary_items'] as $index => $item) {
                $data['itinerary_items'][$index] = self::prepareItem($item);
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

    public function emailItinerary()
    {
        $data = Input::all();
        $error = null;

        $itinerary = Itinerary::scope($data['id'])->withTrashed()->first();

        if(!$itinerary)
            return $this->errorResponse(['message'=>'Itinerary does not exist.'], 400);


        $this->mailer->sendItinerary($itinerary, false, false);


        if($error) {
            return $this->errorResponse(['message'=>'There was an error sending the itinerary'], 400);
        }
        else {
            $response = json_encode(RESULT_SUCCESS, JSON_PRETTY_PRINT);
        }

        $headers = Utils::getApiHeaders();
        return Response::make($response, $error ? 400 : 200, $headers);
    }


        /**
         * @SWG\Put(
         *   path="/itinerarys",
         *   tags={"itinerary"},
         *   summary="Update an itinerary",
         *   @SWG\Parameter(
         *     in="body",
         *     name="body",
         *     @SWG\Schema(ref="#/definitions/Itinerary")
         *   ),
         *   @SWG\Response(
         *     response=200,
         *     description="Update itinerary",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Itinerary"))
         *   ),
         *   @SWG\Response(
         *     response="default",
         *     description="an ""unexpected"" error"
         *   )
         * )
         */
    public function update(UpdateItineraryRequest $request, $publicId)
    {
        if ($request->action == ACTION_ARCHIVE) {
            $itinerary = Itinerary::scope($publicId)->firstOrFail();
            $this->itineraryRepo->archive($itinerary);

            $transformer = new ItineraryTransformer(\Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($itinerary, $transformer, 'itinerary');

            return $this->response($data);
        }
        else if ($request->action == ACTION_CONVERT) {
            $quote = Itinerary::scope($publicId)->firstOrFail();
            $itinerary = $this->itineraryRepo->cloneItinerary($quote, $quote->id);

            $transformer = new ItineraryTransformer(\Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($itinerary, $transformer, 'itinerary');

            return $this->response($data);
        }
        else if ($request->action == ACTION_RESTORE) {
            $itinerary = Itinerary::scope($publicId)->withTrashed()->firstOrFail();
            $this->itineraryRepo->restore($itinerary);

            $transformer = new ItineraryTransformer(\Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($itinerary, $transformer, 'itinerary');

            return $this->response($data);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $this->itineraryService->save($data);

        $itinerary = Itinerary::scope($publicId)->with('client', 'itinerary_items', 'invitations')->firstOrFail();
        $transformer = new ItineraryTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($itinerary, $transformer, 'itinerary');

        return $this->response($data);
    }

        /**
         * @SWG\Delete(
         *   path="/itinerarys",
         *   tags={"itinerary"},
         *   summary="Delete an itinerary",
         *   @SWG\Parameter(
         *     in="body",
         *     name="body",
         *     @SWG\Schema(ref="#/definitions/Itinerary")
         *   ),
         *   @SWG\Response(
         *     response=200,
         *     description="Delete itinerary",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Itinerary"))
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
        $itinerary = Itinerary::scope($publicId)->firstOrFail();

        $this->itineraryRepo->delete($itinerary);

        $transformer = new ItineraryTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($itinerary, $transformer, 'itinerary');

        return $this->response($data);

    }

}
