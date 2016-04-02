<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\InvoiceItem;
use League\Fractal;

class InvoiceItemTransformer extends EntityTransformer
{
    public function transform(InvoiceItem $item)
    {
        return [
            'id' => (int) $item->public_id,
            'product_key' => $item->product_key,
            'account_key' => $this->account->account_key,
            'user_id' => (int) $item->user_id,
            'updated_at' => $this->getTimestamp($item->updated_at),
            'archived_at' => $this->getTimestamp($item->deleted_at),
            'product_key' => $item->product_key,
            'notes' => $item->notes,
            'cost' => (float) $item->cost,
            'qty' => (float) $item->qty,
            'tax_name' => $item->tax_name,
            'tax_rate' => (float) $item->tax_rate
        ];
    }
}