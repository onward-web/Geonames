<?php

namespace MichaelDrennen\Geonames\Models;


use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use MichaelDrennen\Geonames\Events\GeonameUpdated;
use MichaelDrennen\Geonames\Models\AlternateName;
use EloquentFilter\Filterable;
use Jeidison\CompositeKey\CompositeKey;


class Geoname extends Model
{

    use Filterable;
    use CompositeKey;

    protected $table = 'geonames';

    protected $primaryKey = 'geonameid';

    protected $connection = GEONAMES_CONNECTION;


    /**
     * @var array An empty array, because I want all of the fields mass assignable.
     */
    protected $guarded = [];


    /**
     * The accessors to append to the model's array form.
     * @var array
     */
    /*
    protected $appends = ['admin_1_name',
                          'admin_2_name'];
    */

    public function modelFilter()
    {
        return $this->provideFilter(\MichaelDrennen\Geonames\ModelFilters\GeonameFilter::class);
    }


    /**
     * @var string
     */
    //protected $dateFormat = 'Y-m-d';

    /**
     * @var array
     */
    protected $dates = ['modification_date'];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = ['population' => 'integer',
        'dem' => 'integer',
        'latitude' => 'double',
        'longitude' => 'double',];

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $events = ['updated' => GeonameUpdated::class];

    /**
     * Not all countries use the admin2_code values. The admin2_code references another table of what we call
     * 'counties' in the United States. Few countries use that value in a meaningful way. So if the geoname record
     * does not have a country code that appears in this array, we skip looking up the admin 2 name value from the
     * geonames_admin_2_codes table.
     * @var array
     */
    protected $countryCodesThatUseAdmin2Codes = ['US'];


    /**
     * @param string $countryCode
     * @return bool
     */
    protected function thisCountryUsesAdmin2Codes(string $countryCode): bool
    {
        if (in_array($countryCode, $this->countryCodesThatUseAdmin2Codes)) {
            return true;
        }

        return false;
    }

    public function admin1Code()
    {
        return $this->hasMany(Admin1Code::class, ['country_code', 'admin1_code'], ['country_code', 'admin1_code']);
    }

    public function admin2Code()
    {
        return $this->hasMany(Admin2Code::class, ['country_code', 'admin1_code', 'admin2_code'], ['country_code', 'admin1_code', 'admin2_code']);
    }


    public function alternateNames()
    {
        return $this->hasMany(AlternateName::class, 'geonameid', 'geonameid')->where('isEnable', 1);
    }

    public function alternateName($lang = null)
    {
        if ($lang == null) {
            $lang = sc_tecdoc_lang();
        }
        return $this->hasOne(AlternateName::class, 'geonameid', 'geonameid')->where('isolanguage', $lang);
    }

}
