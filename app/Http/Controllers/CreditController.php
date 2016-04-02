<?php namespace App\Http\Controllers;

use Datatable;
use Input;
use Redirect;
use Session;
use URL;
use Utils;
use View;
use Validator;
use App\Models\Client;
use App\Services\CreditService;
use App\Ninja\Repositories\CreditRepository;
use App\Http\Requests\CreateCreditRequest;

class CreditController extends BaseController
{
    protected $creditRepo;
    protected $creditService;
    protected $model = 'App\Models\Credit';

    public function __construct(CreditRepository $creditRepo, CreditService $creditService)
    {
        // parent::__construct();

        $this->creditRepo = $creditRepo;
        $this->creditService = $creditService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list', array(
            'entityType' => ENTITY_CREDIT,
            'title' => trans('texts.credits'),
            'sortCol' => '4',
            'columns' => Utils::trans([
              'checkbox',
              'client',
              'credit_amount',
              'credit_balance',
              'credit_date',
              'private_notes',
              ''
            ]),
        ));
    }

    public function getDatatable($clientPublicId = null)
    {
        return $this->creditService->getDatatable($clientPublicId, Input::get('sSearch'));
    }

    public function create($clientPublicId = 0)
    {
        if(!$this->checkCreatePermission($response)){
            return $response;
        }
        
        $data = array(
            'clientPublicId' => Input::old('client') ? Input::old('client') : $clientPublicId,
            //'invoicePublicId' => Input::old('invoice') ? Input::old('invoice') : $invoicePublicId,
            'credit' => null,
            'method' => 'POST',
            'url' => 'credits',
            'title' => trans('texts.new_credit'),
            //'invoices' => Invoice::scope()->with('client', 'invoice_status')->orderBy('invoice_number')->get(),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(), );

        return View::make('credits.edit', $data);
    }

    public function edit($publicId)
    {
        $credit = Credit::scope($publicId)->firstOrFail();
        
        if(!$this->checkEditPermission($credit, $response)){
            return $response;
        }
        
        $credit->credit_date = Utils::fromSqlDate($credit->credit_date);

        $data = array(
            'client' => null,
            'credit' => $credit,
            'method' => 'PUT',
            'url' => 'credits/'.$publicId,
            'title' => 'Edit Credit',
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(), );

        return View::make('credit.edit', $data);
    }

    public function store(CreateCreditRequest $request)
    {
        $credit = $this->creditRepo->save($request->input());

        Session::flash('message', trans('texts.created_credit'));

        return redirect()->to($credit->client->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->creditService->bulk($ids, $action);

        if ($count > 0) {
            $message = Utils::pluralize($action.'d_credit', $count);
            Session::flash('message', $message);
        }

        return Redirect::to('credits');
    }
}
