<?php

use Illuminate\Support\Facades\Route;


Route::get('/geonames-search', '\MichaelDrennen\Geonames\Controllers\GeonamesController@search')->name('api.geonames.search')->middleware(GEONAMES_MIDDLEWARE);


