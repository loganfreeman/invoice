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

use App\Models\Itinerary;

class ItineraryController extends BaseController
{
    public function index(){
      $data = [
          'title' => trans('texts.itineraries'),
          'entityType' => 'itinerary',
          'sortCol' => '3',
          'columns' => Utils::trans([
              'checkbox',
              'name',
              'city',
              'state',
              'country',
              ''
          ]),
      ];

      return response()->view('list', $data);
    }

    public function create(){
      if(!$this->checkCreatePermission($response)){
          return $response;
      }
      $itinerary = Itinerary::createNew();
      $data = [
          'entityType' => $itinerary->getEntityType(),
          'invoice' => $itinerary,
          'method' => 'POST',
          'url' => 'itinerarys',
          'title' => trans('texts.new_itinerary'),
      ];
      $data = array_merge($data, self::getViewModel());
      return View::make('itineraries.edit', $data);
    }

    public function edit(){

    }
    private static function getViewModel(){
      return [];
    }
}
