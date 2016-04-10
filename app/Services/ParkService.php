<?php namespace App\Services;

use Auth;
use DB;
use Utils;
use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\parkRepository;
use App\Models\Park;

class ParkService extends BaseService
{
       // Parks
    protected $parkRepo;
    protected $datatableService;

    public function __construct(parkRepository $parkRepo, DatatableService $datatableService)
    {
        $this->parkRepo = $parkRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->parkRepo;
    }

    public function save($data)
    {
        return $this->parkRepo->save($data);
    }

    public function update($id, $data) {
      return $this->parkRepo->update($id, $data);
    }

    public function getDatatable($search)
    {
        $query = $this->parkRepo->find($search);

        return $this->createDatatable(ENTITY_PARK, $query);
    }

    protected function getDatatableColumns($entityType, $hidePark)
    {
        return [
          [
              'name',
              function ($model) {
                  return link_to("parks/{$model->id}", $model->name ?: '')->toHtml();
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
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                trans('texts.edit_park'),
                function ($model) {
                    return URL::to("parks/{$model->id}/edit") ;
                },
                function ($model) {
                    return Park::canEditItem($model);
                }
            ],
        ];
    }

}
