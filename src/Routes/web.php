<?php

use Illuminate\Support\Facades\Route;


//Route::get( '/geonames/search-all', '\MichaelDrennen\Geonames\Controllers\GeonamesController@ajaxJquerySearchAll' );

/**
 *
 */
/*
Route::get( '/geonames/{term}', '\MichaelDrennen\Geonames\Controllers\GeonamesController@test' );


Route::get( '/geonames/cities/{asciiNameTerm}', '\MichaelDrennen\Geonames\Controllers\GeonamesController@citiesUsingLocale' );

Route::get( '/geonames/{countryCode}/cities/{asciiNameTerm}', '\MichaelDrennen\Geonames\Controllers\GeonamesController@citiesByCountryCode' );

Route::get( '/geonames/{countryCode}/schools/{asciiNameTerm}', '\MichaelDrennen\Geonames\Controllers\GeonamesController@schoolsByCountryCode' );
*/

Route::get('/geonames', '\MichaelDrennen\Geonames\Controllers\GeonamesController@regionByCountry')->name('api.geonames.search');
Route::get('/geonames_cities', '\MichaelDrennen\Geonames\Controllers\GeonamesController@citiesByRegion')->name('api.geonames.cities_by_region');


/**
 * Uncomment these, but you will not be able to cache your routes as referenced below:
 * @url https://laravel.com/docs/7.x/deployment#optimizing-route-loading
 */
//Route::get( '/geonames/examples/vue/element/autocomplete', function () {
//    return view( 'geonames::vue-element-example' );
//} );

//Route::get( '/geonames/examples/jquery/autocomplete', function () {
//    return view( 'geonames::jquery-example' );
//} );
