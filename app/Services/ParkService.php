<?php namespace App\Services;

use Auth;
use DB;
use Utils;
use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\ParkRepository;
use App\Models\Park;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Vendor;

class ParkService extends BaseService
{
       // Parks
    protected $ParkRepo;
    protected $datatableService;

    public function __construct(ParkRepository $ParkRepo, DatatableService $datatableService)
    {
        $this->ParkRepo = $ParkRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->ParkRepo;
    }

    public function save($data)
    {
        return $this->ParkRepo->save($data);
    }

    public function getDatatable($search)
    {
        $query = $this->ParkRepo->find($search);

        if(!Utils::hasPermission('view_all')){
            $query->where('Parks.user_id', '=', Auth::user()->id);
        }

        return $this->createDatatable(ENTITY_Park, $query);
    }

    public function getDatatableVendor($vendorPublicId)
    {
        $query = $this->ParkRepo->findVendor($vendorPublicId);
        return $this->datatableService->createDatatable(ENTITY_Park,
                                                        $query,
                                                        $this->getDatatableColumnsVendor(ENTITY_Park,false),
                                                        $this->getDatatableActionsVendor(ENTITY_Park),
                                                        false);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'vendor_name',
                function ($model)
                {
                    if ($model->vendor_public_id) {
                        if(!Vendor::canViewItemByOwner($model->vendor_user_id)){
                            return $model->vendor_name;
                        }

                        return link_to("vendors/{$model->vendor_public_id}", $model->vendor_name)->toHtml();
                    } else {
                        return '';
                    }
                }
            ],
            [
                'client_name',
                function ($model)
                {
                    if ($model->client_public_id) {
                        if(!Client::canViewItemByOwner($model->client_user_id)){
                            return Utils::getClientDisplayName($model);
                        }

                        return link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml();
                    } else {
                        return '';
                    }
                }
            ],
            [
                'Park_date',
                function ($model) {
                    if(!Park::canEditItemByOwner($model->user_id)){
                        return Utils::fromSqlDate($model->Park_date);
                    }

                    return link_to("Parks/{$model->public_id}/edit", Utils::fromSqlDate($model->Park_date))->toHtml();
                }
            ],
            [
                'amount',
                function ($model) {
                    // show both the amount and the converted amount
                    if ($model->exchange_rate != 1) {
                        $converted = round($model->amount * $model->exchange_rate, 2);
                        return Utils::formatMoney($model->amount, $model->Park_currency_id) . ' | ' .
                            Utils::formatMoney($converted, $model->invoice_currency_id);
                    } else {
                        return Utils::formatMoney($model->amount, $model->Park_currency_id);
                    }
                }
            ],
            [
                'public_notes',
                function ($model) {
                    return $model->public_notes != null ? substr($model->public_notes, 0, 100) : '';
                }
            ],
            [
                'Park_status_id',
                function ($model) {
                    return self::getStatusLabel($model->invoice_id, $model->should_be_invoiced);
                }
            ],
        ];
    }

    protected function getDatatableColumnsVendor($entityType, $hideClient)
    {
        return [
            [
                'Park_date',
                function ($model) {
                    return Utils::dateToString($model->Park_date);
                }
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, false, false);
                }
            ],
            [
                'public_notes',
                function ($model) {
                    return $model->public_notes != null ? $model->public_notes : '';
                }
            ],
            [
                'invoice_id',
                function ($model) {
                    return '';
                }
            ],
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                trans('texts.edit_Park'),
                function ($model) {
                    return URL::to("Parks/{$model->public_id}/edit") ;
                },
                function ($model) {
                    return Park::canEditItem($model);
                }
            ],
            [
                trans('texts.view_invoice'),
                function ($model) {
                    return URL::to("/invoices/{$model->invoice_public_id}/edit");
                },
                function ($model) {
                    return $model->invoice_public_id && Invoice::canEditItemByOwner($model->invoice_user_id);
                }
            ],
            [
                trans('texts.invoice_Park'),
                function ($model) {
                    return "javascript:invoiceEntity({$model->public_id})";
                },
                function ($model) {
                    return ! $model->invoice_id && (!$model->deleted_at || $model->deleted_at == '0000-00-00') && Invoice::canCreate();
                }
            ],
        ];
    }

    protected function getDatatableActionsVendor($entityType)
    {
        return [];
    }

    private function getStatusLabel($invoiceId, $shouldBeInvoiced)
    {
        if ($invoiceId) {
            $label = trans('texts.invoiced');
            $class = 'success';
        } elseif ($shouldBeInvoiced) {
            $label = trans('texts.pending');
            $class = 'warning';
        } else {
            $label = trans('texts.logged');
            $class = 'primary';
        }

        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }

}
