<?php

namespace MichaelDrennen\Geonames\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MichaelDrennen\Geonames\Models\Geoname;
use MichaelDrennen\Geonames\Models\GeoSetting;
use MichaelDrennen\Geonames\Models\Log;
use MichaelDrennen\Geonames\Traits\GeonamesJobTrait;

class DownloadGeonames
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeonamesJobTrait;

    /**
     * @var array List of absolute local file paths to downloaded geonames files.
     */
    protected $localFiles = [];

    public function __construct()
    {
        $this->table = (new Geoname)->getTable();
    }

    public function handle()
    {
        $countries = GeoSetting::getCountriesToBeAdded();


        $remoteFilePaths = $this->getRemoteFilePathsToDownloadForGeonamesTable($countries);

        try {
            $this->downloadFiles($remoteFilePaths, 'geonames_files');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::error('', $e->getMessage(), 'remote');

            return FALSE;
        }
    }

    /**
     * Returns an array of absolute remote paths to geonames country files we need to download.
     * @param array $countries The value from GeoSetting countries_to_be_added
     * @return array
     */
    protected function getRemoteFilePathsToDownloadForGeonamesTable(array $countries): array
    {
        // If the config setting for countries has the wildcard symbol "*", then the user wants data for all countries.
        if (array_search("*", $countries) !== FALSE) {
            return [config('geonames.url') . config('geonames.allCountriesZipFileName')];
        }

        $files = [];
        foreach ($countries as $country) {
            // 20190527:mdd A lowercase country in this URL will give you a 404.
            $files[] = config('geonames.url') . strtoupper($country) . '.zip';
        }
        return $files;
    }
}
