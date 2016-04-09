<?php namespace App\Ninja\Repositories;

use DB;
use Cache;
use App\Ninja\Repositories\BaseRepository;

use App\Models\Park;

class ParkRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Park';
    }

    public function all()
    {

    }

    public function find($filter = null)
    {

    }

    public function save($data)
    {
      $park = Park::createSimpleModel();
      $park->fill($data);
      $park->save();
      return $park;
    }
}
