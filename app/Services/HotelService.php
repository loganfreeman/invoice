<?php namespace App\Services;

use Auth;
use DB;
use Utils;
use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\hotelRepository;
use App\Models\Hotel;

class HotelService extends BaseService
{
       // Hotels
    protected $hotelRepo;
    protected $datatableService;

    public function __construct(hotelRepository $hotelRepo, DatatableService $datatableService)
    {
        $this->hotelRepo = $hotelRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->hotelRepo;
    }

    public function checkRecordExists($data){
      return Hotel::where('name', $data['name'])->first();
    }

    public function save($data)
    {
        return $this->hotelRepo->save($data);
    }

    public function update($id, $data) {
      return $this->hotelRepo->update($id, $data);
    }

    public function getDatatable($search)
    {
        $query = $this->hotelRepo->find($search);

        return $this->createDatatable(ENTITY_HOTEL, $query);
    }

    public function getDatatableVendor($vendorPublicId)
    {
        $query = $this->hotelRepo->findVendor($vendorPublicId);
        return $this->datatableService->createDatatable(ENTITY_Hotel,
                                                        $query,
                                                        $this->getDatatableColumnsVendor(ENTITY_Hotel,false),
                                                        $this->getDatatableActionsVendor(ENTITY_Hotel),
                                                        false);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
      return [
        [
            'name',
            function ($model) {
                return link_to("parks/{$model->id}", $model->name ?: '')->toHtml();
            }
        ],
        [
            'address1',
            function ($model) {
                return $model->address1;
            }
        ],
        [
            'address2',
            function ($model) {
                return $model->address2;
            }
        ],
        [
            'city',
            function ($model) {
                return $model->city;
            }
        ],
        [
            'state',
            function ($model) {
                return $model->state;
            }
        ],
        [
            'country',
            function ($model) {
                return $model->country;
            }
        ],
        [
            'website',
            function ($model) {
                return $model->website;
            }
        ],
        [
            'phone',
            function ($model) {
                return $model->phone;
            }
        ],
      ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                trans('texts.edit_totel'),
                function ($model) {
                    return URL::to("totels/{$model->id}/edit") ;
                },
                function ($model) {
                    return Hotel::canEditItem($model);
                }
            ],
        ];
    }

}
