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

class ParkController extends BaseController
{
    public function index(){
      $data = [
          'title' => trans('texts.parks'),
          'entityType' => 'park',
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
}
