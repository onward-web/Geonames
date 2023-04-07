<?php


namespace MichaelDrennen\Geonames\ModelFilters;


use EloquentFilter\ModelFilter;

class GeonameFilter extends ModelFilter
{
    public $relations = [
        'alternateName' => ['alternate_name_contains'],
    ];

    public function featureClass(string $featureСlass)
    {
        return $this->where('feature_class', $featureСlass);
    }

    public function featureCode(string $featureСode)
    {
        return $this->where('feature_code', $featureСode);
    }

    public function countryCode(string $countryCode)
    {
        return $this->where('country_code', $countryCode);
    }

    public function admin1Code(string $admin1Code)
    {
        return $this->where('admin1_code', $admin1Code);
    }

}
