<?php namespace App\Http\Controllers;

use Debugbar;
use DB;
use Auth;
use Datatable;
use Utils;
use View;
use URL;
use Validator;
use Input;
use Session;
use Redirect;
use Cache;
use App\Models\Vendor;
use App\Models\Expense;
use App\Models\Client;
use App\Services\ExpenseService;
use App\Ninja\Repositories\ExpenseRepository;
use App\Http\Requests\CreateExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;

class ExpenseController extends BaseController
{
    // Expenses
    protected $expenseRepo;
    protected $expenseService;
    protected $model = 'App\Models\Expense';

    public function __construct(ExpenseRepository $expenseRepo, ExpenseService $expenseService)
    {
        // parent::__construct();

        $this->expenseRepo = $expenseRepo;
        $this->expenseService = $expenseService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list', array(
            'entityType' => ENTITY_EXPENSE,
            'title' => trans('texts.expenses'),
            'sortCol' => '3',
            'columns' => Utils::trans([
              'checkbox',
              'vendor',
              'client',
              'expense_date',
              'amount',
              'public_notes',
              'status',
              ''
            ]),
        ));
    }

    public function getDatatable($expensePublicId = null)
    {
        return $this->expenseService->getDatatable(Input::get('sSearch'));
    }

    public function getDatatableVendor($vendorPublicId = null)
    {
        return $this->expenseService->getDatatableVendor($vendorPublicId);
    }

    public function create($vendorPublicId = null, $clientPublicId = null)
    {
        if(!$this->checkCreatePermission($response)){
            return $response;
        }
        
        if($vendorPublicId != 0) {
            $vendor = Vendor::scope($vendorPublicId)->with('vendorcontacts')->firstOrFail();
        } else {
            $vendor = null;
        }
        $data = array(
            'vendorPublicId' => Input::old('vendor') ? Input::old('vendor') : $vendorPublicId,
            'expense' => null,
            'method' => 'POST',
            'url' => 'expenses',
            'title' => trans('texts.new_expense'),
            'vendors' => Vendor::scope()->with('vendorcontacts')->orderBy('name')->get(),
            'vendor' => $vendor,
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
            'clientPublicId' => $clientPublicId,
            );

        $data = array_merge($data, self::getViewModel());

        return View::make('expenses.edit', $data);
    }

    public function edit($publicId)
    {
        $expense = Expense::scope($publicId)->firstOrFail();
        
        if(!$this->checkEditPermission($expense, $response)){
            return $response;
        }
        
        $expense->expense_date = Utils::fromSqlDate($expense->expense_date);
        
        $actions = [];
        if ($expense->invoice) {
            $actions[] = ['url' => URL::to("invoices/{$expense->invoice->public_id}/edit"), 'label' => trans("texts.view_invoice")];
        } else {
            $actions[] = ['url' => 'javascript:submitAction("invoice")', 'label' => trans("texts.invoice_expense")];

            /*
            // check for any open invoices
            $invoices = $task->client_id ? $this->invoiceRepo->findOpenInvoices($task->client_id) : [];

            foreach ($invoices as $invoice) {
                $actions[] = ['url' => 'javascript:submitAction("add_to_invoice", '.$invoice->public_id.')', 'label' => trans("texts.add_to_invoice", ["invoice" => $invoice->invoice_number])];
            }
            */
        }

        $actions[] = \DropdownButton::DIVIDER;
        if (!$expense->trashed()) {
            $actions[] = ['url' => 'javascript:submitAction("archive")', 'label' => trans('texts.archive_expense')];
            $actions[] = ['url' => 'javascript:onDeleteClick()', 'label' => trans('texts.delete_expense')];
        } else {
            $actions[] = ['url' => 'javascript:submitAction("restore")', 'label' => trans('texts.restore_expense')];
        }

        $data = array(
            'vendor' => null,
            'expense' => $expense,
            'method' => 'PUT',
            'url' => 'expenses/'.$publicId,
            'title' => 'Edit Expense',
            'actions' => $actions,
            'vendors' => Vendor::scope()->with('vendorcontacts')->orderBy('name')->get(),
            'vendorPublicId' => $expense->vendor ? $expense->vendor->public_id : null,
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
            'clientPublicId' => $expense->client ? $expense->client->public_id : null,
        );

        $data = array_merge($data, self::getViewModel());

        if (Auth::user()->account->isNinjaAccount()) {
            if ($account = Account::whereId($client->public_id)->first()) {
                $data['proPlanPaid'] = $account['pro_plan_paid'];
            }
        }

        return View::make('expenses.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function update(UpdateExpenseRequest $request)
    {
        $expense = $this->expenseService->save($request->input());

        Session::flash('message', trans('texts.updated_expense'));

        $action = Input::get('action');
        if (in_array($action, ['archive', 'delete', 'restore', 'invoice'])) {
            return self::bulk();
        }

        return redirect()->to("expenses/{$expense->public_id}/edit");
    }

    public function store(CreateExpenseRequest $request)
    {
        $expense = $this->expenseService->save($request->input());

        Session::flash('message', trans('texts.created_expense'));

        return redirect()->to("expenses/{$expense->public_id}/edit");
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids    = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        switch($action)
        {
            case 'invoice':
                $expenses = Expense::scope($ids)->with('client')->get();
                $clientPublicId = null;
                $currencyId = null;
                $data = [];

                // Validate that either all expenses do not have a client or if there is a client, it is the same client
                foreach ($expenses as $expense)
                {
                    if ($expense->client) {
                        if (!$clientPublicId) {
                            $clientPublicId = $expense->client->public_id;
                        } elseif ($clientPublicId != $expense->client->public_id) {
                            Session::flash('error', trans('texts.expense_error_multiple_clients'));
                            return Redirect::to('expenses');
                        }
                    }

                    if (!$currencyId) {
                        $currencyId = $expense->invoice_currency_id;
                    } elseif ($currencyId != $expense->invoice_currency_id && $expense->invoice_currency_id) {
                        Session::flash('error', trans('texts.expense_error_multiple_currencies'));
                        return Redirect::to('expenses');
                    }

                    if ($expense->invoice_id) {
                        Session::flash('error', trans('texts.expense_error_invoiced'));
                        return Redirect::to('expenses');
                    }

                    $account = Auth::user()->account;
                    $data[] = [
                        'publicId' => $expense->public_id,
                        'description' => $expense->public_notes,
                        'qty' => 1,
                        'cost' => $expense->present()->converted_amount,
                    ];
                }

                return Redirect::to("invoices/create/{$clientPublicId}")
                        ->with('expenseCurrencyId', $currencyId)
                        ->with('expenses', $data);
                break;

            default:
                $count  = $this->expenseService->bulk($ids, $action);
        }

        if ($count > 0) {
            $message = Utils::pluralize($action.'d_expense', $count);
            Session::flash('message', $message);
        }

        return Redirect::to('expenses');
    }

    private static function getViewModel()
    {
        return [
            'data' => Input::old('data'),
            'account' => Auth::user()->account,
            'sizes' => Cache::get('sizes'),
            'paymentTerms' => Cache::get('paymentTerms'),
            'industries' => Cache::get('industries'),
            'currencies' => Cache::get('currencies'),
            'languages' => Cache::get('languages'),
            'countries' => Cache::get('countries'),
            'customLabel1' => Auth::user()->account->custom_vendor_label1,
            'customLabel2' => Auth::user()->account->custom_vendor_label2,
        ];
    }

    public function show($publicId)
    {
        Session::reflash();

        return Redirect::to("expenses/{$publicId}/edit");
    }
}
