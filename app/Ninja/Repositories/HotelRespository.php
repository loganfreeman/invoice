<?php namespace App\Ninja\Repositories;

use DB;
use Cache;
use App\Ninja\Repositories\BaseRepository;

use App\Models\Hotel;

class HotelRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Hotel';
    }

    public function all()
    {

    }

    public function find($filter = null, $exact = false)
    {
      $query = DB::table('hotels')
                  ->select(
                      'hotels.id',
                      'hotels.name',
                      'hotels.created_at',
                      'hotels.phone',
                      'hotels.address1',
                      'hotels.address2',
                      'hotels.city',
                      'hotels.state',
                      'hotels.country',
                      'hotels.deleted_at',
                      'hotels.website'
                  );

      if (!\Session::get('show_trash:hotel')) {
          $query->where('hotels.deleted_at', '=', null);
      }

      if ($filter) {
          $query->where(function ($query) use ($filter) {
              if($exact) {
                $query->where('hotels.name', '=', $filter);
              }else {
                $query->where('hotels.name', 'like', '%'.$filter.'%');
              }
          });
      }

      return $query;
    }

    public function save($data)
    {
      $hotel = Hotel::createSimpleModel();
      $hotel->fill($data);
      $hotel->save();
      return $hotel;
    }

    public function update($id, $data){
      $hotel = Hotel::find($id);
      $hotel->fill($data);
      $hotel->save();
      return $hotel;
    }
}
