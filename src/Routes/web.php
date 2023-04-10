<?php

use Illuminate\Support\Facades\Route;


Route::get('/search', '\MichaelDrennen\Geonames\Controllers\GeonamesController@regionByCountry')->name('api.geonames.search');


