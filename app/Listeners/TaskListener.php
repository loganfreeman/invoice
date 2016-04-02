<?php namespace app\Listeners;

use App\Models\Task;
use App\Events\InvoiceWasDeleted;

class TaskListener
{
    public function deletedInvoice(InvoiceWasDeleted $event)
    {
        // Release any tasks associated with the deleted invoice
        Task::where('invoice_id', '=', $event->invoice->id)
                ->update(['invoice_id' => null]);
    }
}
