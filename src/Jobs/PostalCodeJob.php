<?php

namespace MichaelDrennen\Geonames\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MichaelDrennen\Geonames\Models\GeoSetting;
use MichaelDrennen\Geonames\Models\Log;
use MichaelDrennen\Geonames\Traits\GeonamesJobTrait;
use MichaelDrennen\Geonames\Models\PostalCode;

class PostalCodeJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeonamesJobTrait;

    /**
     *
     */
    protected $table;
    protected $countries;
    protected $languages;
    protected $storageSubDir;


    /**
     *
     */
    const REMOTE_FILE_NAME = 'allCountries.zip';

    /**
     *
     */
    const LOCAL_TXT_FILE_NAME = 'allCountries.txt';


    public function __construct(array $countries = [], array $languages = [], string $storageSubDir = GeoSetting::DEFAULT_STORAGE_SUBDIR)
    {

        $this->table = (new PostalCode())->getTable();

        $this->countries = $countries;
        $this->languages = $languages;
        $this->storageSubDir = $storageSubDir;
    }

    public function handle()
    {
        GeoSetting::init(
            $this->countries,
            $this->languages,
            $this->storageSubDir,
        );

        $downloadLink = config('geonames.postCodesUrl') . config('geonames.allCountriesZipFileName');

        try {
            $localZipFile = $this->downloadFile($downloadLink, 'postcodes');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::error($downloadLink, $e->getMessage(), 'remote');

            return FALSE;
        }


        try {
            $this->line("Unzipping " . $localZipFile);
            $this->unzip($localZipFile, 'postcodes');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::error($localZipFile, $e->getMessage(), 'local');

            return FALSE;
        }

        $localTextFile = GeoSetting::getAbsoluteLocalStoragePath() . DIRECTORY_SEPARATOR . 'postcodes' . DIRECTORY_SEPARATOR . config('geonames.allCountriesTxtFileName');

        if (!file_exists($localTextFile)) {
            throw new \Exception("The unzipped file could not be found. We were looking for: " . $localTextFile);
        }

        $dataBeforeStart = (string)Carbon::now()->format('Y-m-d H:i:s');

        $this->insertPostCodesFromFile($localTextFile);

        do {
            // выбираем записи которые по дате обновления, более ранние чем запуск процесса обновления($dataStart)
            $postalCodeIdToDelete = PostalCode::select('id')->where('updated_at', '<', $dataBeforeStart)->limit(1000)->get()->pluck('id')->toArray();
            PostalCode::whereIn('id', $postalCodeIdToDelete)->delete();

        } while (count($postalCodeIdToDelete) > 0);


        $this->info("The postal code data was downloaded and inserted in");
    }


    /**
     * @param $localFilePath
     * @throws Exception
     */
    protected function insertPostCodesFromFile($localFilePath)
    {
        $file = fopen($localFilePath, 'r');
        while (($line = fgets($file, null)) !== FALSE) {
            $row = str_getcsv($line, "\t");


            $pdo = DB::connection(GEONAMES_CONNECTION)->getPdo();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $this->table . '` SET
                                    `country_code` = :country_code,
                                    `postal_code` = :postal_code,
                                    `place_name` = :place_name,
                                    `admin1_name` = :admin1_name,
                                    `admin1_code` = :admin1_code,
                                    `admin2_name` = :admin2_name,
                                    `admin2_code` = :admin2_code,
                                    `admin3_name` = :admin3_name,
                                    `admin3_code` = :admin3_code,
                                    `latitude` = :latitude,
                                    `longitude` = :longitude,
                                    `accuracy` = :accuracy,
                                    `created_at` = :created_at,
                                    `updated_at` = :updated_at
                                ON DUPLICATE KEY UPDATE
                                    `country_code` = :update_country_code,
                                    `postal_code` = :update_postal_code,
                                    `place_name` = :update_place_name,
                                    `admin1_name` = :update_admin1_name,
                                    `admin1_code` = :update_admin1_code,
                                    `admin2_name` = :update_admin2_name,
                                    `admin2_code` = :update_admin2_code,
                                    `admin3_name` = :update_admin3_name,
                                    `admin3_code` = :update_admin3_code,
                                    `latitude` = :update_latitude,
                                    `longitude` = :update_longitude,
                                    `accuracy` = :update_accuracy,
                                    `updated_at` = :update_updated_at'
            );
            $stmt->execute(
                [
                    ':country_code' => $row[0],
                    ':postal_code' => $row[1],
                    ':place_name' => $row[2],
                    ':admin1_name' => $row[3],
                    ':admin1_code' => $row[4],
                    ':admin2_name' => $row[5],
                    ':admin2_code' => $row[6],
                    ':admin3_name' => $row[7],
                    ':admin3_code' => $row[8],
                    ':latitude' => $row[9],
                    ':longitude' => $row[10],
                    ':accuracy' => $row[11],
                    ':created_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),
                    ':updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),

                    ':update_country_code' => $row[0],
                    ':update_postal_code' => $row[1],
                    ':update_place_name' => $row[2],
                    ':update_admin1_name' => $row[3],
                    ':update_admin1_code' => $row[4],
                    ':update_admin2_name' => $row[5],
                    ':update_admin2_code' => $row[6],
                    ':update_admin3_name' => $row[7],
                    ':update_admin3_code' => $row[8],
                    ':update_latitude' => $row[9],
                    ':update_longitude' => $row[10],
                    ':update_accuracy' => $row[11],
                    ':update_updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),
                ]);
        }
        fclose($file);


    }
}
