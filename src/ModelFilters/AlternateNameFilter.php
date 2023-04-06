<?php


namespace MichaelDrennen\Geonames\ModelFilters;


use EloquentFilter\ModelFilter;

class AlternateNameFilter  extends ModelFilter
{
    public function alternateNameContains(string $name){
        return
            $this->where(function ($query) use($name) {
                $query->where('alternate_name_edited', 'like',  $name . '%')
                    ->orWhere(function ($query) use($name) {
                        $query->whereNull('alternate_name_edited')
                            ->where('alternate_name', 'like',  $name . '%');

                    });
            });

    }
}
