<?php

namespace MichaelDrennen\Geonames\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use MichaelDrennen\Geonames\Models\GeoSetting;
use MichaelDrennen\Geonames\Models\Geoname;
use MichaelDrennen\Geonames\Models\Log;
use MichaelDrennen\Geonames\Traits\GeonamesJobTrait;

class NoCountryJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeonamesJobTrait;


    /**
     *
     */
    const LOCAL_TXT_FILE_NAME = 'no-country.txt';

    public function __construct(array $countries = [], array $languages = [], string $storageSubDir = GeoSetting::DEFAULT_STORAGE_SUBDIR)
    {
        $this->countries = $countries;
        $this->languages = $languages;
        $this->storageSubDir = $storageSubDir;

        $this->table = (new Geoname)->getTable();
    }

    public function handle()
    {
        GeoSetting::init(
            $this->countries,
            $this->languages,
            $this->storageSubDir,
        );

        $downloadLink = config('geonames.url') . config('geonames.noCountriesZipFileName');


        try {
            $localZipFile = $this->downloadFile($downloadLink, 'no-country');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::error($downloadLink, $e->getMessage(), 'remote');

            return FALSE;
        }


        try {
            $this->line("Unzipping " . $localZipFile);
            $this->unzip($localZipFile, 'no-country');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::error($localZipFile, $e->getMessage(), 'local');

            return FALSE;
        }

        $localTextFile = GeoSetting::getAbsoluteLocalStoragePath() . DIRECTORY_SEPARATOR . 'no-country' . DIRECTORY_SEPARATOR . config('geonames.noCountriesTextFileName');

        if (!file_exists($localTextFile)) {
            throw new \Exception("The unzipped file could not be found. We were looking for: " . $localTextFile);
        }

        $dataBeforeStart = (string)Carbon::now()->format('Y-m-d H:i:s');


        $this->insertNoCountry($localTextFile);

        do {
            // выбираем записи которые по дате обновления, более ранние чем запуск процесса обновления($dataStart)
            $geonameIdsToDelete = Geoname::select('geonameid')->where('updated_at', '<', $dataBeforeStart)
                ->orWhere(function ($query) {
                    $query->whereNull('country_code')
                        ->where('country_code', '');
                })
                ->limit(1000)
                ->get()
                ->pluck('geonameid')
                ->toArray();
            Geoname::whereIn('geonameid', $geonameIdsToDelete)->delete();

        } while (count($geonameIdsToDelete) > 0);


        $this->info("The no-country data was downloaded and inserted in seconds.");
    }


    /**
     * @param $localFilePath
     * @throws Exception
     */
    protected function insertNoCountry($localFilePath)
    {

        $file = fopen($localFilePath, 'r');

        while (($line = fgets($file, null)) !== FALSE) {
            $row = str_getcsv($line, "\t");


            $pdo = DB::connection(GEONAMES_CONNECTION)->getPdo();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $this->table . '` SET
                                    `geonameid` = :geonameid,
                                    `name` = :name,
                                    `asciiname` = :asciiname,
                                    `alternatenames` = :alternatenames,
                                    `latitude` = :latitude,
                                    `longitude` = :longitude,
                                    `feature_class` = :feature_class,
                                    `feature_code` = :feature_code,
                                    `country_code` = :country_code,
                                    `cc2` = :cc2,
                                    `admin1_code` = :admin1_code,
                                    `admin2_code` = :admin2_code,
                                    `admin3_code` = :admin3_code,
                                    `admin4_code` = :admin4_code,
                                    `population` = :population,
                                    `elevation` = :elevation,
                                    `dem` = :dem,
                                    `timezone` = :timezone,
                                    `modification_date` = :modification_date,
                                    `created_at` = :created_at,
                                    `updated_at` = :updated_at
                                ON DUPLICATE KEY UPDATE
                                    `geonameid` = :update_geonameid,
                                    `name` = :update_name,
                                    `asciiname` = :update_asciiname,
                                    `alternatenames` = :update_alternatenames,
                                    `latitude` = :update_latitude,
                                    `longitude` = :update_longitude,
                                    `feature_class` = :update_feature_class,
                                    `feature_code` = :update_feature_code,
                                    `country_code` = :update_country_code,
                                    `cc2` = :update_cc2,
                                    `admin1_code` = :update_admin1_code,
                                    `admin2_code` = :update_admin2_code,
                                    `admin3_code` = :update_admin3_code,
                                    `admin4_code` = :update_admin4_code,
                                    `population` = :update_population,
                                    `elevation` = :update_elevation,
                                    `dem` = :update_dem,
                                    `timezone` = :update_timezone,
                                    `modification_date` = :update_modification_date,
                                    `updated_at` = :update_updated_at'
            );
            $stmt->execute(
                [
                    ':geonameid' => $row[0],
                    ':name' => $row[1],
                    ':asciiname' => $row[2],
                    ':alternatenames' => $row[3],
                    ':latitude' => $row[4],
                    ':longitude' => $row[5],
                    ':feature_class' => $row[6],
                    ':feature_code' => $row[7],
                    ':country_code' => $row[8],
                    ':cc2' => $row[9],
                    ':admin1_code' => $row[10],
                    ':admin2_code' => $row[11],
                    ':admin3_code' => $row[12],
                    ':admin4_code' => $row[13],
                    ':population' => $row[14],
                    ':elevation' => $row[15],
                    ':dem' => $row[16],
                    ':timezone' => $row[17],
                    ':modification_date' => $row[18],
                    ':created_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),
                    ':updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),

                    ':update_geonameid' => $row[0],
                    ':update_name' => $row[1],
                    ':update_asciiname' => $row[2],
                    ':update_alternatenames' => $row[3],
                    ':update_latitude' => $row[4],
                    ':update_longitude' => $row[5],
                    ':update_feature_class' => $row[6],
                    ':update_feature_code' => $row[7],
                    ':update_country_code' => $row[8],
                    ':update_cc2' => $row[9],
                    ':update_admin1_code' => $row[10],
                    ':update_admin2_code' => $row[11],
                    ':update_admin3_code' => $row[12],
                    ':update_admin4_code' => $row[13],
                    ':update_population' => $row[14],
                    ':update_elevation' => $row[15],
                    ':update_dem' => $row[16],
                    ':update_timezone' => $row[17],
                    ':update_modification_date' => $row[18],
                    ':update_updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),

                ]);
        }
        fclose($file);

    }
}
