<?php

namespace MichaelDrennen\Geonames;

use Illuminate\Console\Scheduling\Schedule;

class GeonamesServiceProvider extends \Illuminate\Support\ServiceProvider
{


    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {

        // There are a number of tables that need to be created for our Geonames package.
        // Feel free to create your own additional migrations to create indexes that are appropriate for your application.
        $this->loadMigrationsFrom(__DIR__ . '/Migrations');

        $this->loadViewsFrom(__DIR__ . '/Views', 'geonames');

        $this->loadRoutesFrom(__DIR__ . '/Routes/web.php');

        $this->publishes([
            __DIR__ . '/Migrations/' => database_path('migrations'),
        ], 'migrations');
    }


    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Register the config publish path
        $configPath = __DIR__ . '/config/geonames.php';
        $this->mergeConfigFrom($configPath, 'geonames');
        $this->publishes([$configPath => config_path('geonames.php')], 'config');
    }
}
