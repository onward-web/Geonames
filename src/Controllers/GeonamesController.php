<?php

namespace MichaelDrennen\Geonames\Controllers;

use App\Http\Controllers\GeneralController;
use Illuminate\Http\Request;
use Locale;
use MichaelDrennen\Geonames\Models\Geoname;
use MichaelDrennen\Geonames\Repositories\GeonameRepository;
use DB;

class GeonamesController extends GeneralController
{


    protected $geoname;

    public function __construct(GeonameRepository $geoname)
    {
        $this->geoname = $geoname;
    }

    public function searchAll(Request $request)
    {

    }

    public function ajaxJquerySearchAll(Request $request)
    {
        $term = $request->input('term');
        $results = $this->geoname->getPlacesStartingWithTerm($term)
            ->take(100);

        return response()->json($results);
    }

    public function test($term = '')
    {
        $results = $this->geoname->getPlacesStartingWithTerm($term);

        return response()->json($results);
    }

    public function citiesUsingLocale(Request $request, string $term = ''): string
    {

        $http_accept_language = $request->server('HTTP_ACCEPT_LANGUAGE');
        $parts = explode(',', $http_accept_language);
        $http_accept_language = $parts[0]; // en-US

        $language = Locale::getPrimaryLanguage($http_accept_language);
        $countryCode = Locale::getRegion($http_accept_language);
        $localeParts = Locale::parseLocale($http_accept_language);

        $geonamesInCountry = $this->geoname->getCitiesFromCountryStartingWithTerm($countryCode, $term, 10);
        $geonamesInCountry = $geonamesInCountry->sortBy('admin_2_name');

        $geonamesNotInCountry = $this->geoname->getCitiesNotFromCountryStartingWithTerm($countryCode, $term, 10);
        $geonamesNotInCountry = $geonamesNotInCountry->sortBy(['country_code',
            'asciiname']);
        $mergedGeonames = $geonamesInCountry->merge($geonamesNotInCountry);

        $mergedGeonames = $mergedGeonames->slice(0, 10);

        $rows = [];
        foreach ($mergedGeonames as $geoname) {
            $newRow = ['geonameid' => $geoname->geonameid,
                'name' => $geoname->asciiname,
                'country_code' => $geoname->country_code,
                'admin_1_code' => $geoname->admin1_code,
                //           'admin_2_name' => $geoname->admin_2_name
            ];
            $rows[] = $newRow;
        }

        return response()->json($rows);
    }

    /**
     * @param Request $request
     * @param string $countryCode
     * @param string $term
     * @return string
     */
    public function citiesByCountryCode(Request $request, string $countryCode = '', string $term = ''): string
    {

        $geonames = $this->geoname->getCitiesFromCountryStartingWithTerm($countryCode, $term);

        $rows = [];
        foreach ($geonames as $geoname) {
            $newRow = ['geonameid' => $geoname->geonameid,
                'name' => $geoname->asciiname,
                'country_code' => $geoname->country_code,
                'admin_1_code' => $geoname->admin1_code,
                'admin_2_name' => $geoname->admin_2_name];
            $rows[] = $newRow;
        }

        return response()->json($rows);
    }

    /**
     * @param Request $request
     * @return string
     */
    public function regionByCountry(Request $request)
    {
        $lang = $request->input('lang', sc_tecdoc_lang());

        $obj = Geoname::select(DB::raw('geonames.*'))
            ->with(['alternateName'])
            ->join('geonames_alternate_names', function ($join) use ($lang) {
                $join->on('geonames.geonameid', '=', 'geonames_alternate_names.geonameid');
                $join->where('geonames_alternate_names.isolanguage', '=', (string)$lang);
            })
            ->where('is_enable', 1)
            ->where('isEnable', 1)
            ->filter($request->all())
            ->distinct()
            ->orderByRaw('
                IFNULL(
                `alternate_name_edited`,
                `alternate_name`
                ) ASC'
            )
            ->paginateFilter();
        return response()->json($obj);
    }

    /**
     * @param Request $request
     * @return string
     */
    public function citiesByRegion(Request $request){

        $lang = $request->input('lang', sc_tecdoc_lang());

        $obj = Geoname::selectRaw('
                    MAX(`geonames`.`geonameid`) as geonameid,  
                    IF(alternate_name_edited IS NULL or `alternate_name_edited` = "", `alternate_name`, `alternate_name_edited`) as alternate_name_checked ')
            ->with(['alternateName'])
            ->join('geonames_alternate_names', function ($join) use ($lang) {
                $join->on('geonames.geonameid', '=', 'geonames_alternate_names.geonameid');
                $join->where('geonames_alternate_names.isolanguage', '=', (string)$lang);
            })
            ->where('is_enable', 1)
            ->where('isEnable', 1)
            ->filter($request->all())
            ->groupBy('alternate_name_checked')
            ->orderByRaw('
                IFNULL(
                `alternate_name_edited`,
                `alternate_name`
                ) ASC'
            )
            ->paginateFilter();
        return response()->json($obj);
    }


    /**
     * @param Request $request
     * @param string $countryCode
     * @param string $term
     * @return string
     */
    public function search(Request $request)
    {
        $lang = $request->input('lang', sc_tecdoc_lang());

        $obj = Geoname::select(DB::raw('geonames.*'))
            ->with(['alternateName'])
            ->join('geonames_alternate_names', function ($join) use ($lang) {
                $join->on('geonames.geonameid', '=', 'geonames_alternate_names.geonameid');
                $join->where('geonames_alternate_names.isolanguage', '=', (string)$lang);
            })
            ->where('is_enable', 1)
            ->where('isEnable', 1)
            ->filter($request->all())
            ->distinct()
            ->orderByRaw('
                IFNULL(
                `alternate_name_edited`,
                `alternate_name`
                ) ASC'
            )
            ->paginateFilter();
        return response()->json($obj);
    }


    /**
     * @param string $countryCode
     * @param string $asciinameTerm
     * @return \Illuminate\Http\JsonResponse
     */
    public function schoolsByCountryCode(string $countryCode = '', string $asciinameTerm = '')
    {
        $results = $this->geoname->getSchoolsFromCountryStartingWithTerm($countryCode, $asciinameTerm);

        return response()->json($results);
    }


}
