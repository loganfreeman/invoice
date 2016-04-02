<?php namespace app\Ninja\Repositories;

use DB;
use Utils;
use App\Models\Expense;
use App\Models\Vendor;
use App\Ninja\Repositories\BaseRepository;
use Session;

class ExpenseRepository extends BaseRepository
{
    // Expenses
    public function getClassName()
    {
        return 'App\Models\Expense';
    }

    public function all()
    {
        return Expense::scope()
                ->with('user')
                ->withTrashed()
                ->where('is_deleted', '=', false)
                ->get();
    }

    public function findVendor($vendorPublicId)
    {
        $vendorId = Vendor::getPrivateId($vendorPublicId);
        $accountid = \Auth::user()->account_id;
        $query = DB::table('expenses')
                    ->join('accounts', 'accounts.id', '=', 'expenses.account_id')
                    ->where('expenses.account_id', '=', $accountid)
                    ->where('expenses.vendor_id', '=', $vendorId)
                    ->select(
                        'expenses.id',
                        'expenses.expense_date',
                        'expenses.amount',
                        'expenses.public_notes',
                        'expenses.public_id',
                        'expenses.deleted_at',
                        'expenses.should_be_invoiced',
                        'expenses.created_at',
                        'expenses.user_id'
                    );

        return $query;
    }

    public function find($filter = null)
    {
        $accountid = \Auth::user()->account_id;
        $query = DB::table('expenses')
                    ->join('accounts', 'accounts.id', '=', 'expenses.account_id')
                    ->leftjoin('clients', 'clients.id', '=', 'expenses.client_id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->leftjoin('vendors', 'vendors.id', '=', 'expenses.vendor_id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'expenses.invoice_id')
                    ->where('expenses.account_id', '=', $accountid)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('vendors.deleted_at', '=', null)
                    ->where('clients.deleted_at', '=', null)
                    ->where(function ($query) {
                        $query->where('contacts.is_primary', '=', true)
                              ->orWhere('contacts.is_primary', '=', null);
                    })
                    ->select(
                        DB::raw('COALESCE(expenses.invoice_id, expenses.should_be_invoiced) expense_status_id'),
                        'expenses.account_id',
                        'expenses.amount',
                        'expenses.deleted_at',
                        'expenses.exchange_rate',
                        'expenses.expense_date',
                        'expenses.id',
                        'expenses.is_deleted',
                        'expenses.private_notes',
                        'expenses.public_id',
                        'expenses.invoice_id',
                        'expenses.public_notes',
                        'expenses.should_be_invoiced',
                        'expenses.vendor_id',
                        'expenses.expense_currency_id',
                        'expenses.invoice_currency_id',
                        'expenses.user_id',
                        'invoices.public_id as invoice_public_id',
                        'invoices.user_id as invoice_user_id',
                        'vendors.name as vendor_name',
                        'vendors.public_id as vendor_public_id',
                        'vendors.user_id as vendor_user_id',
                        'clients.name as client_name',
                        'clients.public_id as client_public_id',
                        'clients.user_id as client_user_id',
                        'contacts.first_name',
                        'contacts.email',
                        'contacts.last_name',
                        'clients.country_id as client_country_id'
                    );

        $showTrashed = \Session::get('show_trash:expense');

        if (!$showTrashed) {
            $query->where('expenses.deleted_at', '=', null);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('expenses.public_notes', 'like', '%'.$filter.'%')
                      ->orWhere('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('vendors.name', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function save($input)
    {
        $publicId = isset($input['public_id']) ? $input['public_id'] : false;

        if ($publicId) {
            $expense = Expense::scope($publicId)->firstOrFail();
        } else {
            $expense = Expense::createNew();
        }

        // First auto fill
        $expense->fill($input);

        $expense->expense_date = Utils::toSqlDate($input['expense_date']);

        if (isset($input['private_notes'])) {
            $expense->private_notes = trim($input['private_notes']);
        }
        $expense->public_notes = trim($input['public_notes']);
        $expense->should_be_invoiced = isset($input['should_be_invoiced']) || $expense->client_id ? true : false;

        if ( ! $expense->expense_currency_id) {
            $expense->expense_currency_id = \Auth::user()->account->getCurrencyId();
        }
        if ( ! $expense->invoice_currency_id) {
            $expense->invoice_currency_id = \Auth::user()->account->getCurrencyId();
        }

        $rate = isset($input['exchange_rate']) ? Utils::parseFloat($input['exchange_rate']) : 1;
        $expense->exchange_rate = round($rate, 4);
        $expense->amount = round(Utils::parseFloat($input['amount']), 2);

        $expense->save();

        return $expense;
    }

    public function bulk($ids, $action)
    {
        $expenses = Expense::withTrashed()->scope($ids)->get();

        foreach ($expenses as $expense) {
            if ($action == 'restore') {
                $expense->restore();

                $expense->is_deleted = false;
                $expense->save();
            } else {
                if ($action == 'delete') {
                    $expense->is_deleted = true;
                    $expense->save();
                }

                $expense->delete();
            }
        }

        return count($tasks);
    }
}
