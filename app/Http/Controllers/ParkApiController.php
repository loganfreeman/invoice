<?php namespace App\Http\Controllers;

use Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Utils;
use Response;
use Input;
use Validator;
use App\Models\Park;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Invitation;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\ParkRepository;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Http\Controllers\BaseAPIController;
use App\Ninja\Transformers\ParkTransformer;
use App\Http\Requests\CreateParkRequest;
use App\Http\Requests\UpdateParkRequest;
use App\Services\ParkService;

class ParkApiController extends BaseAPIController
{
    protected $parkRepo;

    public function __construct(ParkService $parkService, ParkRepository $parkRepo, ClientRepository $clientRepo, PaymentRepository $paymentRepo, Mailer $mailer)
    {
        parent::__construct();

        $this->parkRepo = $parkRepo;
        $this->clientRepo = $clientRepo;
        $this->paymentRepo = $paymentRepo;
        $this->parkService = $parkService;
        $this->mailer = $mailer;
    }

    /**
     * @SWG\Get(
     *   path="/parks",
     *   summary="List of parks",
     *   tags={"park"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with parks",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Park"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $paginator = Park::scope()->withTrashed();
        $parks = Park::scope()->withTrashed()
                        ->with(array_merge(['park_items'], $this->getIncluded()));

        if ($clientPublicId = Input::get('client_id')) {
            $filter = function($query) use ($clientPublicId) {
                $query->where('public_id', '=', $clientPublicId);
            };
            $parks->whereHas('client', $filter);
            $paginator->whereHas('client', $filter);
        }

        $parks = $parks->orderBy('created_at', 'desc')->paginate();

        /*
        // Add the first invitation link to the data
        foreach ($parks as $key => $park) {
            foreach ($park->invitations as $subKey => $invitation) {
                $parks[$key]['link'] = $invitation->getLink();
            }
            unset($park['invitations']);
        }
        */

        $transformer = new ParkTransformer(Auth::user()->account, Input::get('serializer'));
        $paginator = $paginator->paginate();

        $data = $this->createCollection($parks, $transformer, 'parks', $paginator);

        return $this->response($data);
    }

        /**
         * @SWG\Get(
         *   path="/parks/{park_id}",
         *   summary="Individual Park",
         *   tags={"park"},
         *   @SWG\Response(
         *     response=200,
         *     description="A single park",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Park"))
         *   ),
         *   @SWG\Response(
         *     response="default",
         *     description="an ""unexpected"" error"
         *   )
         * )
         */

    public function show($publicId)
    {

        $park = Park::scope($publicId)->withTrashed()->first();

        if(!$park)
            return $this->errorResponse(['message'=>'Park does not exist!'], 404);

        $transformer = new ParkTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($park, $transformer, 'park');

        return $this->response($data);
    }

    /**
     * @SWG\Post(
     *   path="/parks",
     *   tags={"park"},
     *   summary="Create an park",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Park")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New park",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Park"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateParkRequest $request)
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
        $park = $this->parkService->save($data);
        $payment = false;

        // Optionally create payment with park
        if (isset($data['paid']) && $data['paid']) {
            $payment = $this->paymentRepo->save([
                'park_id' => $park->id,
                'client_id' => $client->id,
                'amount' => $data['paid']
            ]);
        }

        if (isset($data['email_park']) && $data['email_park']) {
            if ($payment) {
                $this->mailer->sendPaymentConfirmation($payment);
            } else {
                $this->mailer->sendPark($park);
            }
        }

        $park = Park::scope($park->public_id)->with('client', 'park_items', 'invitations')->first();
        $transformer = new ParkTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($park, $transformer, 'park');

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
            'park_footer' => '',
            'public_notes' => '',
            'po_number' => '',
            'park_design_id' => $account->park_design_id,
            'park_items' => [],
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_taxes1' => false,
            'custom_taxes2' => false,
            'partial' => 0
        ];

        if (!isset($data['park_status_id']) || $data['park_status_id'] == 0) {
            $data['park_status_id'] = INVOICE_STATUS_DRAFT;
        }

        if (!isset($data['park_date'])) {
            $fields['park_date_sql'] = date_create()->format('Y-m-d');
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
            $data['park_items'] = [self::prepareItem($data)];

            // make sure the tax isn't applied twice (for the park and the line item)
            unset($data['park_items'][0]['tax_name']);
            unset($data['park_items'][0]['tax_rate']);
        } else {
            foreach ($data['park_items'] as $index => $item) {
                $data['park_items'][$index] = self::prepareItem($item);
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

    public function emailPark()
    {
        $data = Input::all();
        $error = null;

        $park = Park::scope($data['id'])->withTrashed()->first();

        if(!$park)
            return $this->errorResponse(['message'=>'Park does not exist.'], 400);


        $this->mailer->sendPark($park, false, false);


        if($error) {
            return $this->errorResponse(['message'=>'There was an error sending the park'], 400);
        }
        else {
            $response = json_encode(RESULT_SUCCESS, JSON_PRETTY_PRINT);
        }

        $headers = Utils::getApiHeaders();
        return Response::make($response, $error ? 400 : 200, $headers);
    }


        /**
         * @SWG\Put(
         *   path="/parks",
         *   tags={"park"},
         *   summary="Update an park",
         *   @SWG\Parameter(
         *     in="body",
         *     name="body",
         *     @SWG\Schema(ref="#/definitions/Park")
         *   ),
         *   @SWG\Response(
         *     response=200,
         *     description="Update park",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Park"))
         *   ),
         *   @SWG\Response(
         *     response="default",
         *     description="an ""unexpected"" error"
         *   )
         * )
         */
    public function update(UpdateParkRequest $request, $publicId)
    {
        if ($request->action == ACTION_ARCHIVE) {
            $park = Park::scope($publicId)->firstOrFail();
            $this->parkRepo->archive($park);

            $transformer = new ParkTransformer(\Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($park, $transformer, 'park');

            return $this->response($data);
        }
        else if ($request->action == ACTION_CONVERT) {
            $quote = Park::scope($publicId)->firstOrFail();
            $park = $this->parkRepo->clonePark($quote, $quote->id);

            $transformer = new ParkTransformer(\Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($park, $transformer, 'park');

            return $this->response($data);
        }
        else if ($request->action == ACTION_RESTORE) {
            $park = Park::scope($publicId)->withTrashed()->firstOrFail();
            $this->parkRepo->restore($park);

            $transformer = new ParkTransformer(\Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($park, $transformer, 'park');

            return $this->response($data);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $this->parkService->save($data);

        $park = Park::scope($publicId)->with('client', 'park_items', 'invitations')->firstOrFail();
        $transformer = new ParkTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($park, $transformer, 'park');

        return $this->response($data);
    }

        /**
         * @SWG\Delete(
         *   path="/parks",
         *   tags={"park"},
         *   summary="Delete an park",
         *   @SWG\Parameter(
         *     in="body",
         *     name="body",
         *     @SWG\Schema(ref="#/definitions/Park")
         *   ),
         *   @SWG\Response(
         *     response=200,
         *     description="Delete park",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Park"))
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
        $park = Park::scope($publicId)->firstOrFail();

        $this->parkRepo->delete($park);

        $transformer = new ParkTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($park, $transformer, 'park');

        return $this->response($data);

    }

}
