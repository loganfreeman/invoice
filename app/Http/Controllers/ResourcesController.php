<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

use Auth;
use DB;
use View;

class ResourcesController extends BaseController
{
    public function index()
    {
      $view_all = !Auth::user()->hasPermission('view_all');
      $user_id = Auth::user()->id;
      $data = [
          'account' => Auth::user()->account,
          'title' => trans('texts.resources'),
      ];

      return View::make('resources', $data);
    }
}
