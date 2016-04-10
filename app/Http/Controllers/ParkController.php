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

use App\Http\Requests\CreateParkRequest;

use App\Ninja\Repositories\ParkRepository;
use App\Services\ParkService;

use Log;
use Image;

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

    public function getDatatable(){
      return $this->parkService->getDatatable(Input::get('sSearch'));
    }

    public function store(CreateParkRequest $request){
      $data = $request->input();

      if(!$this->parkService->canCreate($data)){
        $errorMessage = trans('texts.park_name_unique');
        Session::flash('error', $errorMessage);
        return redirect()->back()->withInput();
      }

      $park = $this->parkService->save($data);

      $this->saveParkImage($park, $request);

      Session::flash('message', trans('texts.created_park'));

      return redirect()->to($park->getRoute());
    }

    public function show($id){
      $park = Park::find($id);

      Utils::trackViewed($park->getDisplayName(), 'park');

      $actionLinks = [
      ];

      $data = array(
          'actionLinks'           => $actionLinks,
          'showBreadcrumbs'       => false,
          'park'                => $park,
          'title'                 => trans('texts.view_park'),
      );

      return View::make('parks.show', $data);
    }

    public function create(){
      if(!$this->checkCreatePermission($response)){
          return $response;
      }
      $data = [
          'park' => null,
          'method' => 'POST',
          'url' => 'parks',
          'title' => trans('texts.new_park'),
      ];
      $data = array_merge($data, self::getViewModel());
      return View::make('parks.edit', $data);
    }

    public function edit($id){
      $park = Park::find($id);

      $data = [
          'park' => $park,
          'method' => 'PUT',
          'url' => 'parks/'.$id,
          'title' => trans('texts.edit_park'),
      ];

      $data = array_merge($data, self::getViewModel());

      return View::make('parks.edit', $data);
    }

    public function update(CreateParkRequest $request, $id){
      $data = $request->input();

      $park = $this->parkService->update($id, $data);

      $this->saveParkImage($park, $request);

      Session::flash('message', trans('texts.updated_park'));

      return redirect()->to($park->getRoute());
    }

    private function saveParkImage($park, $request){
      if ($request->hasFile('photo_path')) {
        $the_file = \File::get($request->file('photo_path')->getRealPath());
        $file_name = 'park_image-'.md5(microtime()).'.'.strtolower($request->file('photo_path')->getClientOriginalExtension());

        $relative_path_to_file = rtrim(config('ninja.park_photo_path'), '/').'/'.$file_name;
        $full_path_to_file = rtrim(public_path(), '/').'/'.$relative_path_to_file;

        $img = Image::make($the_file);

        $img->resize(1000, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        $img->save($full_path_to_file);
        $park->photo_path = $full_path_to_file;
        $park->save();
      }
    }

    public function bulk() {

    }
    private static function getViewModel(){
      return [
        'data' => Input::old('data'),
      ];
    }
}
