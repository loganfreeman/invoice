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
use App\Models\Park;
use App\Models\Account;

use app\Http\Requests\CreateParkRequest;

use App\Ninja\Repositories\ParkRepository;
use App\Services\ParkService;

class ParkController extends BaseController
{
    protected $parkService;
    protected $parkRepo;
    protected $model = 'App\Models\Park';
    public function __construct(ParkRepository $parkRepo, ParkService $parkService){

              $this->parkRepo = $parkRepo;
              $this->parkService = $parkService;
    }
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

    public function store(CreateParkRequest $request){
      $data = $request->input();
      if(!$this->checkUpdatePermission($data, $response)){
          return $response;
      }

      $park = $this->parkService->save($data);

      Session::flash('message', trans('texts.created_park'));

      return redirect()->to($park->getRoute());
    }

    public function create(){
      if(!$this->checkCreatePermission($response)){
          return $response;
      }
      $park = Park::createSimpleModel();
      $data = [
          'entityType' => $park->getEntityType(),
          'invoice' => $park,
          'method' => 'POST',
          'url' => 'parks',
          'title' => trans('texts.new_park'),
      ];
      $data = array_merge($data, self::getViewModel());
      return View::make('parks.edit', $data);
    }

    public function edit(){

    }
    private static function getViewModel(){
      return [];
    }
}
