<?php

use Illuminate\Support\Facades\Route;


Route::get('/search', '\MichaelDrennen\Geonames\Controllers\GeonamesController@search')->name('api.geonames.search');


