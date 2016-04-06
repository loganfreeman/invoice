<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\Libraries\Utils;

class HotelController extends BaseController
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
            'address1',
            'address2',
            'stars',
            ''
        ]),
    ];

    return response()->view('list', $data);
  }
}
