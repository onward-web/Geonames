<?php

namespace MichaelDrennen\Geonames;

use Illuminate\Console\Scheduling\Schedule;

class GeonamesServiceProvider extends \Illuminate\Support\ServiceProvider {


    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot() {

        // There are a number of tables that need to be created for our Geonames package.
        // Feel free to create your own additional migrations to create indexes that are appropriate for your application.
        $this->loadMigrationsFrom( __DIR__ . '/Migrations' );

        $this->loadViewsFrom( __DIR__ . '/Views', 'geonames' );


        $this->commands( [ Console\Install::class,
            Console\Geoname::class,
            Console\DownloadGeonames::class,
            Console\InsertGeonames::class,
            Console\NoCountry::class,

            Console\AlternateName::class,
            Console\IsoLanguageCode::class,
            Console\FeatureClass::class,
            Console\FeatureCode::class,

            Console\Admin1Code::class,
            Console\Admin2Code::class,

            Console\PostalCode::class,

            Console\UpdateGeonames::class,
            Console\Status::class,
            Console\Test::class ] );


        $this->loadRoutesFrom( __DIR__ . '/Routes/web.php' );

        $this->publishes( [
                              __DIR__ . '/Migrations/' => database_path( 'migrations' ),
                          ], 'migrations' );
    }


    /**
     * Register the application services.
     *
     * @return void
     */
    public function register() {

    }
}
