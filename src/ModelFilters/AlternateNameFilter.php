<?php


namespace MichaelDrennen\Geonames\ModelFilters;


use EloquentFilter\ModelFilter;

class AlternateNameFilter  extends ModelFilter
{
    public function alternateNameContains(string $name){
        return $this->where('alternate_name', 'like',  $name . '%')
            ->orWhere('alternate_name_edited', 'like',  $name . '%');
    }

}
