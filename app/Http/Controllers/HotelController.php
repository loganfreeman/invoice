<?php

namespace App\Http\Controllers;

use Auth;
use Session;
use Utils;
use View;
use Input;
use Cache;
use Redirect;
use DB;
use Event;
use URL;
use Datatable;
use Request;
use DropdownButton;

use App\Models\Hotel;

class HotelController extends BaseController
{
  public function index(){
    $data = [
        'title' => trans('texts.hotels'),
        'entityType' => 'hotel',
        'sortCol' => '3',
        'columns' => Utils::trans([
            'checkbox',
            'name',
            'city',
            'state',
            'country',
            'address1',
            'address2',
            'stars',
            ''
        ]),
    ];

    return response()->view('list', $data);
  }
  public function create(){
    if(!$this->checkCreatePermission($response)){
        return $response;
    }
    $hotel = Hotel::createSimpleModel();
    $data = [
        'entityType' => $hotel->getEntityType(),
        'hotel' => $hotel,
        'method' => 'POST',
        'url' => 'hotels',
        'title' => trans('texts.new_hotel'),
    ];
    $data = array_merge($data, self::getViewModel());
    return View::make('hotels.edit', $data);
  }

  public function edit(){

  }
  private static function getViewModel(){
    return [];
  }
}
