<?php namespace App\Ninja\Repositories;

use DB;
use Cache;
use App\Ninja\Repositories\BaseRepository;

use App\Models\Park;

use Log;

class ParkRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Park';
    }

    public function all()
    {

    }

    public function find($filter = null, $exact = false)
    {
      $query = DB::table('parks')
                  ->select(
                      'parks.id',
                      'parks.name',
                      'parks.created_at',
                      'parks.city',
                      'parks.state',
                      'parks.country',
                      'parks.deleted_at'
                  );

      if (!\Session::get('show_trash:park')) {
          $query->where('parks.deleted_at', '=', null);
      }

      if ($filter) {
          $query->where(function ($query) use ($filter) {
              if($exact) {
                $query->where('parks.name', '=', $filter);
              }else {
                $query->where('parks.name', 'like', '%'.$filter.'%');
              }
          });
      }

      return $query;
    }

    public function save($data)
    {
      $park = Park::createSimpleModel();
      $park->fill($data);
      $park->save();
      return $park;
    }

    public function update($id, $data){
      $park = Park::find($id);
      $park->fill($data);
      $park->save();
      return $park;
    }
}
