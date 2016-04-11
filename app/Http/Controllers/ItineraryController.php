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
use App\Models\Account;

use App\Http\Requests\CreateItineraryRequest;

use App\Ninja\Repositories\ItineraryRepository;
use App\Services\ItineraryService;

use Log;
use Image;

class ItineraryController extends BaseController
{
    protected $itineraryService;
    protected $itineraryRepo;
    protected $model = 'App\Models\Itinerary';
    public function __construct(ItineraryRepository $itineraryRepo, ItineraryService $itineraryService){

              $this->itineraryRepo = $itineraryRepo;
              $this->itineraryService = $itineraryService;
    }
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

    public function getDatatable(){
      return $this->itineraryService->getDatatable(Input::get('sSearch'));
    }

    public function store(CreateItineraryRequest $request){
      $data = $request->input();

      if($this->itineraryService->checkRecordExists($data)){
        $errorMessage = trans('texts.itinerary_name_unique');
        Session::flash('error', $errorMessage);
        return redirect()->back()->withInput();
      }

      $itinerary = $this->itineraryService->save($data);

      $this->saveItineraryImage($itinerary, $request);

      Session::flash('message', trans('texts.created_itinerary'));

      return redirect()->to($itinerary->getRoute());
    }

    public function show($id){
      $itinerary = Itinerary::find($id);

      Utils::trackViewed($itinerary->getDisplayName(), 'itinerary');

      $actionLinks = [
      ];

      $data = array(
          'actionLinks'           => $actionLinks,
          'showBreadcrumbs'       => false,
          'itinerary'                => $itinerary,
          'title'                 => trans('texts.view_itinerary'),
      );

      return View::make('itineraries.show', $data);
    }

    public function create(){
      if(!$this->checkCreatePermission($response)){
          return $response;
      }
      $data = [
          'itinerary' => null,
          'method' => 'POST',
          'url' => 'itineraries',
          'title' => trans('texts.new_itinerary'),
      ];
      $data = array_merge($data, self::getViewModel());
      return View::make('itineraries.edit', $data);
    }

    public function edit($id){
      $itinerary = Itinerary::find($id);

      $data = [
          'itinerary' => $itinerary,
          'method' => 'PUT',
          'url' => 'itineraries/'.$id,
          'title' => trans('texts.edit_itinerary'),
      ];

      $data = array_merge($data, self::getViewModel());

      return View::make('itineraries.edit', $data);
    }

    public function update(CreateItineraryRequest $request, $id){
      $data = $request->input();

      $itinerary = $this->itineraryService->update($id, $data);

      $this->saveItineraryImage($itinerary, $request);

      Session::flash('message', trans('texts.updated_itinerary'));

      return redirect()->to($itinerary->getRoute());
    }

    private function saveItineraryImage($itinerary, $request){
      if ($request->hasFile('photo_path')) {
        $the_file = \File::get($request->file('photo_path')->getRealPath());
        $file_name = 'itinerary_image-'.md5(microtime()).'.'.strtolower($request->file('photo_path')->getClientOriginalExtension());

        $relative_path_to_file = rtrim(config('ninja.itinerary_photo_path'), '/').'/'.$file_name;
        $full_path_to_file = rtrim(public_path(), '/').'/'.$relative_path_to_file;

        $img = Image::make($the_file);

        $img->resize(1000, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        $img->save($full_path_to_file);
        $itinerary->photo_path = $full_path_to_file;
        $itinerary->save();
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
