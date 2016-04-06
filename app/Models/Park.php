<?php namespace App\Models;

use Utils;
use DB;
use Carbon;
use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class Park extends EntityModel
{
    use PresentableTrait;
    use SoftDeletes;

    protected $presenter    = 'App\Ninja\Presenters\ParkPresenter';
    protected $dates        = ['deleted_at'];
    protected $fillable     = [
        'name',
        'id_number',
        'vat_number',
        'work_phone',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'private_notes',
        'currency_id',
        'website',
        'transaction_name',
    ];

    public static $fieldName        = 'name';
    public static $fieldPhone       = 'work_phone';
    public static $fieldAddress1    = 'address1';
    public static $fieldAddress2    = 'address2';
    public static $fieldCity        = 'city';
    public static $fieldState       = 'state';
    public static $fieldPostalCode  = 'postal_code';
    public static $fieldNotes       = 'notes';
    public static $fieldCountry     = 'country';

    public static function getImportColumns()
    {
        return [
            Park::$fieldName,
            Park::$fieldPhone,
            Park::$fieldAddress1,
            Park::$fieldAddress2,
            Park::$fieldCity,
            Park::$fieldState,
            Park::$fieldPostalCode,
            Park::$fieldCountry,
            Park::$fieldNotes,
        ];
    }

    public static function getImportMap()
    {
        return [
            'first' => 'first_name',
            'last' => 'last_name',
            'email' => 'email',
            'mobile|phone' => 'phone',
            'name|organization' => 'name',
            'street2|address2' => 'address2',
            'street|address|address1' => 'address1',
            'city' => 'city',
            'state|province' => 'state',
            'zip|postal|code' => 'postal_code',
            'country' => 'country',
            'note' => 'notes',
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
        return 'park';
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
