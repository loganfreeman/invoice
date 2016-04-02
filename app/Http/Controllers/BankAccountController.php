<?php namespace App\Http\Controllers;

use Cache;
use Auth;
use Datatable;
use DB;
use Input;
use Redirect;
use Session;
use View;
use Validator;
use stdClass;
use Crypt;
use URL;
use Utils;
use App\Models\Gateway;
use App\Models\Account;
use App\Models\BankAccount;
use App\Ninja\Repositories\BankAccountRepository;
use App\Services\BankAccountService;
use App\Http\Requests\CreateBankAccountRequest;

class BankAccountController extends BaseController
{
    protected $bankAccountService;
    protected $bankAccountRepo;

    public function __construct(BankAccountService $bankAccountService, BankAccountRepository $bankAccountRepo)
    {
        //parent::__construct();

        $this->bankAccountService = $bankAccountService;
        $this->bankAccountRepo = $bankAccountRepo;
    }

    public function index()
    {
        return Redirect::to('settings/' . ACCOUNT_BANKS);
    }

    public function getDatatable()
    {
        return $this->bankAccountService->getDatatable(Auth::user()->account_id);
    }

    public function edit($publicId)
    {
        $bankAccount = BankAccount::scope($publicId)->firstOrFail();

        $data = [
            'title' => trans('texts.edit_bank_account'),
            'banks' => Cache::get('banks'),
            'bankAccount' => $bankAccount,
        ];

        return View::make('accounts.bank_account', $data);
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    /**
     * Displays the form for account creation
     *
     */
    public function create()
    {
        $data = [
            'banks' => Cache::get('banks'),
            'bankAccount' => null,
        ];

        return View::make('accounts.bank_account', $data);
    }

    public function bulk()
    {
        $action = Input::get('bulk_action');
        $ids = Input::get('bulk_public_id');
        $count = $this->bankAccountService->bulk($ids, $action);

        Session::flash('message', trans('texts.archived_bank_account'));

        return Redirect::to('settings/' . ACCOUNT_BANKS);
    }

    public function validateAccount()
    {
        $publicId = Input::get('public_id');
        $username = trim(Input::get('bank_username'));
        $password = trim(Input::get('bank_password'));

        if ($publicId) {
            $bankAccount = BankAccount::scope($publicId)->firstOrFail();
            if ($username != $bankAccount->username) {
                // TODO update username
            }
            $username = Crypt::decrypt($username);
            $bankId = $bankAccount->bank_id;
        } else {
            $bankId = Input::get('bank_id');
        }

        return json_encode($this->bankAccountService->loadBankAccounts($bankId, $username, $password, $publicId));
    }

    public function store(CreateBankAccountRequest $request)
    {
        $bankAccount = $this->bankAccountRepo->save(Input::all());

        $bankId = Input::get('bank_id');
        $username = trim(Input::get('bank_username'));
        $password = trim(Input::get('bank_password'));

        return json_encode($this->bankAccountService->loadBankAccounts($bankId, $username, $password, true));
    }

    public function importExpenses($bankId)
    {
        return $this->bankAccountService->importExpenses($bankId, Input::all());
    }

}
