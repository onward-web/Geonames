<?php

namespace MichaelDrennen\Geonames\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use MichaelDrennen\Geonames\Models\FeatureClass;
use MichaelDrennen\Geonames\Models\GeoSetting;
use MichaelDrennen\Geonames\Traits\GeonamesJobTrait;

class FeatureClassJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeonamesJobTrait;

    protected $table;
    protected $countries;
    protected $languages;
    protected $storageSubDir;

    public function __construct(array $countries = [], array $languages = [], string $storageSubDir = GeoSetting::DEFAULT_STORAGE_SUBDIR)
    {
        $this->countries = $countries;
        $this->languages = $languages;
        $this->storageSubDir = $storageSubDir;

        $this->table = (new FeatureClass)->getTable();
    }

    public function handle()
    {
        GeoSetting::init(
            $this->countries,
            $this->languages,
            $this->storageSubDir,
        );

        DB::connection(GEONAMES_CONNECTION)->table($this->table)->truncate();


        DB::connection(GEONAMES_CONNECTION)->table($this->table)->insert(['id' => 'A',
            'description' => 'country, state, region,...',]);

        DB::connection(GEONAMES_CONNECTION)->table($this->table)->insert(['id' => 'H',
            'description' => 'stream, lake, ...',]);

        DB::connection(GEONAMES_CONNECTION)->table($this->table)->insert(['id' => 'L',
            'description' => 'parks,area, ...',]);

        DB::connection(GEONAMES_CONNECTION)->table($this->table)->insert(['id' => 'P',
            'description' => 'city, village,...',]);

        DB::connection(GEONAMES_CONNECTION)->table($this->table)->insert(['id' => 'R',
            'description' => 'road, railroad',]);

        DB::connection(GEONAMES_CONNECTION)->table($this->table)->insert(['id' => 'S',
            'description' => 'spot, building, farm',]);

        DB::connection(GEONAMES_CONNECTION)->table($this->table)->insert(['id' => 'T',
            'description' => 'mountain,hill,rock,...',]);

        DB::connection(GEONAMES_CONNECTION)->table($this->table)->insert(['id' => 'U',
            'description' => 'undersea',]);

        DB::connection(GEONAMES_CONNECTION)->table($this->table)->insert(['id' => 'V',
            'description' => 'forest,heath,...',]);


        $this->info($this->table . " table was truncated and refilled");
    }

   
}
