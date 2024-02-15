<?php

namespace MichaelDrennen\Geonames\Controllers;

use App\Http\Controllers\GeneralController;
use Illuminate\Http\Request;
use Locale;
use MichaelDrennen\Geonames\Models\Geoname;
use DB;

class GeonamesController extends GeneralController
{
    /**
     * @param Request $request
     * @return string
     */
    public function search(Request $request)
    {
        $lang = $request->input('lang', sc_get_locale());

        $obj = Geoname::select(DB::raw('distinct geonames.geonameid as geonameid'))
            ->with(['alternateName', 'geoname'])
            ->join('geonames_alternate_names', function ($join) use ($lang) {
                $join->on('geonames.geonameid', '=', 'geonames_alternate_names.geonameid');
                $join->where('geonames_alternate_names.isolanguage', '=', (string)$lang);
            })
            ->where('is_enable', 1)
            ->where('isEnable', 1)
            ->filter($request->all())
            ->orderByRaw('
                IFNULL(
                `alternate_name_edited`,
                `alternate_name`
                ) ASC'
            )
            ->paginateFilter();
        return response()->json($obj);
    }



}
