<?php

namespace MichaelDrennen\Geonames\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MichaelDrennen\Geonames\Models\Geoname;
use MichaelDrennen\Geonames\Models\GeonameTranslateText;
use MichaelDrennen\Geonames\Models\GeoSetting;
use MichaelDrennen\Geonames\Models\Log;
use MichaelDrennen\Geonames\Traits\GeonamesJobTrait;
use MichaelDrennen\LocalFile\LocalFile;

class InsertGeonamesJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeonamesJobTrait;

    protected $table;

    /**
     * @var string The name of the txt file that contains data from all of the countries.
     */
    protected $allCountriesZipFileName = 'allCountries.zip';

    /**
     * @var string The name of the txt file that contains data from all of the countries.
     */
    protected $allCountriesTxtFileName = 'allCountries.txt';


    public function __construct()
    {
        $this->table = (new Geoname)->getTable();
    }

    public function handle()
    {
        $zipFileNames = $this->getLocalCountryZipFileNames();

        try {
            $this->unzipFiles($zipFileNames, 'geonames_files');
        } catch (\Exception $e) {
            $this->error("Unable to unzip at least one of the country zip files.");
            Log::error('', "We were unable to unzip at least one of the country zip files.", 'local');
            throw $e;
        }

        $textFiles = $this->getLocalCountryTxtFileNames();

        $dataBeforeStart = (string)Carbon::now()->format('Y-m-d H:i:s');

        try {
            $this->insertGeonames($textFiles);

            do {
                // выбираем записи которые по дате обновления, более ранние чем запуск процесса обновления($dataStart)
                $geonameIds = Geoname::select('geonameid')->where('updated_at', '<', $dataBeforeStart)->where('is_custom', 0)->limit(1000)->get()->pluck('geonameid')->toArray();
                Geoname::whereIn('geonameid', $geonameIds)->delete();

            } while (count($geonameIds) > 0);

        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::error('', $e->getMessage(), 'database');
        }

    }


    /**
     * Every country has a zip file with all of their current geonames records. Also, there is
     * an allCountries.zip file that contains all of the records.
     *
     * @return array    All of the zip file names we downloaded from geonames.org that contain
     *                  records for our geonames table.
     * @throws \Exception
     */
    private function getLocalCountryZipFileNames()
    {
        $storagePath = GeoSetting::getAbsoluteLocalStoragePath() . DIRECTORY_SEPARATOR . 'geonames_files';

        $zipFileNames = [];
        foreach (new \DirectoryIterator($storagePath) as $fileInfo) {
            if ($fileInfo->isDot()) continue;

            if ($fileInfo->getFilename() === config('geonames.allCountriesZipFileName')) {
                return [$fileInfo->getPathName()];
            } else if ($fileInfo->getExtension() === 'zip' && preg_match('/^[A-Z]{2}\.zip$/', $fileInfo->getFilename()) === 1) {
                $zipFileNames[] = $fileInfo->getPathName();
            }

        }

        return $zipFileNames;
    }

    /**
     * After all of the country zip files have been downloaded and unzipped, we need to
     * gather up all of the resulting txt files.
     *
     * @return array    An array of all the unzipped country text files.
     * @throws \Exception
     */
    protected function getLocalCountryTxtFileNames(): array
    {
        $storagePath = GeoSetting::getAbsoluteLocalStoragePath() . DIRECTORY_SEPARATOR . 'geonames_files';

        $txtFiles = [];
        foreach (new \DirectoryIterator($storagePath) as $fileInfo) {
            if ($fileInfo->isDot()) continue;

            if ($fileInfo->getFilename() === config('geonames.allCountriesTxtFileName')) {
                return [$fileInfo->getPathName()];
            } else if ($fileInfo->getExtension() === 'txt' && preg_match('/^[A-Z]{2}\.txt$/', $fileInfo->getFilename()) === 1) {
                $txtFiles[] = $fileInfo->getPathName();
            }
        }

        return $txtFiles;
    }


    /**
     * @param $localFilePaths []
     * @throws \MichaelDrennen\LocalFile\Exceptions\UnableToOpenFile
     */
    protected function insertGeonames($textFiles)
    {
        $coutryCodes = [];


        foreach ($textFiles as $textFile) {
            $file = fopen($textFile, 'r');

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
                                    `is_custom` = :is_custom,
                                    `is_enable` = :is_enable,
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
                        ':geonameid' => (string)$row[0],
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
                        ':is_custom' => 0,
                        ':is_enable' => 1,
                        ':created_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),
                        ':updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),

                        ':update_geonameid' => (string)$row[0],
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


        GeoSetting::setCountriesFromCountriesToBeAdded(GEONAMES_CONNECTION);

    }


    /**
     * If the allCountries file is found in the geonames storage dir on this box, then we can just use that and
     * ignore any other text files.
     *
     * @param array $txtFiles An array of text file names that we found in the geonames storage dir on this box.
     *
     * @return bool
     */
    private function allCountriesInLocalTxtFiles(array $txtFiles): bool
    {
        if (in_array($this->allCountriesTxtFileName, $txtFiles)) {
            return TRUE;
        }

        return FALSE;
    }

}
