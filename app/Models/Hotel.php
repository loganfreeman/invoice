<?php namespace App\Models;

use Utils;
use DB;
use Carbon;
use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hotel extends EntityModel
{
    use PresentableTrait;
    use SoftDeletes;

    protected $presenter    = 'App\Ninja\Presenters\HotelPresenter';
    protected $dates        = ['deleted_at'];
    protected $fillable     = [
        'name',
        'phone',
        'stars',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country',
        'website',
    ];

    public static $fieldName        = 'name';
    public static $fieldPhone       = 'phone';
    public static $fieldAddress1    = 'address1';
    public static $fieldAddress2    = 'address2';
    public static $fieldCity        = 'city';
    public static $fieldState       = 'state';
    public static $fieldPostalCode  = 'postal_code';
    public static $fieldCountry     = 'country';

    public static function getImportColumns()
    {
        return [
            Hotel::$fieldName,
            Hotel::$fieldPhone,
            Hotel::$fieldAddress1,
            Hotel::$fieldAddress2,
            Hotel::$fieldCity,
            Hotel::$fieldState,
            Hotel::$fieldPostalCode,
            Hotel::$fieldCountry,
        ];
    }

    public static function getImportMap()
    {
        return [
            'mobile|phone' => 'phone',
            'name|organization' => 'name',
            'street2|address2' => 'address2',
            'street|address|address1' => 'address1',
            'city' => 'city',
            'state|province' => 'state',
            'zip|postal|code' => 'postal_code',
            'country' => 'country',
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
        return 'hotel';
    }

    public function hasAddress()
    {
        $fields = [
            'address1',
            'address2',
            'city',
            'state',
            'postal_code',
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
