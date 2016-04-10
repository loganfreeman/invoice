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

use App\Http\Requests\CreateHotelRequest;

use App\Ninja\Repositories\HotelRepository;
use App\Services\HotelService;

use Log;
use Image;

class HotelController extends BaseController
{

  public function __construct(HotelRepository $hotelRepo, HotelService $hotelService){

            $this->hotelRepo = $hotelRepo;
            $this->hotelService = $hotelService;
  }
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
  public function store(CreateHotelRequest $request){
    $data = $request->input();

    if($this->hotelService->checkRecordExists($data)){
      $errorMessage = trans('texts.hotel_name_unique');
      Session::flash('error', $errorMessage);
      return redirect()->back()->withInput();
    }

    $hotel = $this->hotelService->save($data);

    $this->saveHotelImage($hotel, $request);

    Session::flash('message', trans('texts.created_hotel'));

    return redirect()->to($hotel->getRoute());
  }

  public function show($id){
    $hotel = Hotel::find($id);

    Utils::trackViewed($hotel->getDisplayName(), 'hotel');

    $actionLinks = [
    ];

    $data = array(
        'actionLinks'           => $actionLinks,
        'showBreadcrumbs'       => false,
        'hotel'                => $hotel,
        'title'                 => trans('texts.view_hotel'),
    );

    return View::make('hotels.show', $data);
  }


  public function getDatatable(){
    return $this->hotelService->getDatatable(Input::get('sSearch'));
  }


      public function create(){
        $data = [
            'hotel' => null,
            'method' => 'POST',
            'url' => 'hotels',
            'title' => trans('texts.new_hotel'),
        ];
        $data = array_merge($data, self::getViewModel());
        return View::make('hotels.edit', $data);
      }

      public function edit($id){
        $hotel = Hotel::find($id);

        $data = [
            'hotel' => $hotel,
            'method' => 'PUT',
            'url' => 'hotels/'.$id,
            'title' => trans('texts.edit_hotel'),
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('hotels.edit', $data);
      }

      public function update(CreateHotelRequest $request, $id){
        $data = $request->input();

        $hotel = $this->hotelService->update($id, $data);

        $this->saveHotelImage($hotel, $request);

        Session::flash('message', trans('texts.updated_hotel'));

        return redirect()->to($hotel->getRoute());
      }

      private function saveHotelImage($hotel, $request){
        if ($request->hasFile('photo_path')) {
          $the_file = \File::get($request->file('photo_path')->getRealPath());
          $file_name = 'hotel_image-'.md5(microtime()).'.'.strtolower($request->file('photo_path')->getClientOriginalExtension());

          $relative_path_to_file = rtrim(config('ninja.hotel_photo_path'), '/').'/'.$file_name;
          $full_path_to_file = rtrim(public_path(), '/').'/'.$relative_path_to_file;

          $img = Image::make($the_file);

          $img->resize(1000, null, function ($constraint) {
              $constraint->aspectRatio();
              $constraint->upsize();
          });

          $img->save($full_path_to_file);
          $hotel->photo = $full_path_to_file;
          $hotel->save();
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
