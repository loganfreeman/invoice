<?php namespace App\Models;

use Utils;
use DB;
use Carbon;
use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class Itinerary extends EntityModel
{
    use PresentableTrait;
    use SoftDeletes;

    protected $presenter    = 'App\Ninja\Presenters\ItineraryPresenter';
    protected $dates        = ['deleted_at'];
    protected $fillable     = [
        'name',
        'city',
        'state',
        'website',
    ];

    public static $fieldName        = 'name';
    public static $fieldCity        = 'city';
    public static $fieldState       = 'state';
    public static $fieldWebsite     = 'website';

    public static function getImportColumns()
    {
        return [
            Itinerary::$fieldName,
            Itinerary::$fieldCity,
            Itinerary::$fieldState,
            Itinerary::$fieldWebsite,
        ];
    }

    public static function getImportMap()
    {
        return [
            'name|organization' => 'name',
            'city' => 'city',
            'state|province' => 'state',
            'website' => 'website',
        ];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDisplayName()
    {
        return $this->getName();
    }

    public function getCityState()
    {
        $swap = $this->country && $this->country->swap_postal_code;
        return Utils::cityStateZip($this->city, $this->state, $this->postal_code, $swap);
    }

    public function getEntityType()
    {
        return 'itinerary';
    }

    public function hasAddress()
    {
        $fields = [
            'city',
            'state',
            'country',
        ];

        foreach ($fields as $field) {
            if ($this->$field) {
                return true;
            }
        }

        return false;
    }

    public function getDateCreated()
    {
        if ($this->created_at == '0000-00-00 00:00:00') {
            return '---';
        } else {
            return $this->created_at->format('m/d/y h:i a');
        }
    }

}
