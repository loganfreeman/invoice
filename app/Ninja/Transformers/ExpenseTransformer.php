<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\Expense;
use League\Fractal;

class ExpenseTransformer extends EntityTransformer
{
    public function transform(Expense $expense)
    {

        return [
            'id' => (int) $expense->public_id,
            'private_notes' => $expense->private_notes,
            'public_notes' => $expense->public_notes,
            'should_be_invoiced' => (bool) $expense->should_be_invoiced,
            'updated_at' => $this->getTimestamp($expense->updated_at),
            'archived_at' => $this->getTimestamp($expense->deleted_at),
            'transaction_id' => $expense->transaction_id,
            'bank_id' => $expense->bank_id,
            'expense_currency_id' => (int) $expense->expense_currency_id,
            'account_key' => $this->account->account_key,
            'amount' => (float) $expense->amount,
            'expense_date' => $expense->expense_date,
            'exchange_rate' => (float) $expense->exchange_rate,
            'invoice_currency_id' => (int) $expense->invoice_currency_id,
            'is_deleted' => (bool) $expense->is_deleted,
            'client_id' => isset($expense->client->public_id) ? (int) $expense->client->public_id : null,
            'invoice_id' => isset($expense->invoice->public_id) ? (int) $expense->invoice->public_id : null,
            'vendor_id' => isset($expense->vendor->public_id) ? (int) $expense->vendor->public_id : null,
        ];
    }
}