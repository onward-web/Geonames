<?php

namespace MichaelDrennen\Geonames\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use MichaelDrennen\Geonames\Models\AlternateName;
use MichaelDrennen\Geonames\Models\AlternateNamesWorking;
use MichaelDrennen\Geonames\Models\GeoSetting;
use MichaelDrennen\Geonames\Models\Log;
use MichaelDrennen\Geonames\Traits\GeonamesJobTrait;
use MichaelDrennen\LocalFile\LocalFile;
use Illuminate\Support\Facades\DB;
class AlternateNameJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeonamesJobTrait;

    const LINES_PER_SPLIT_FILE = 50000;
    /**
     *
     */
    const REMOTE_FILE_NAME_FOR_ALL = 'alternateNames.zip';

    /**
     *
     */
    const LOCAL_ALTERNATE_NAMES_FILE_NAME_FOR_ALL = 'alternateNames.txt';

    protected $table;
    protected $tableWorking;
    protected $countries;
    protected $languages;
    protected $storageSubDir;

    public function __construct(array $countries = [], array $languages = [], string $storageSubDir = GeoSetting::DEFAULT_STORAGE_SUBDIR)
    {
        $this->countries = $countries;
        $this->languages = $languages;
        $this->storageSubDir = $storageSubDir;

        $this->table = (new AlternateName)->getTable();

    }

    public function handle()
    {
        GeoSetting::init(
            $this->countries,
            $this->languages,
            $this->storageSubDir,
        );



        $urlsToAlternateNamesZipFiles = $this->getAlternateNameDownloadLinks( $this->countries );

        $absoluteLocalFilePathsOfAlternateNamesZipFiles = [];
        foreach ( $urlsToAlternateNamesZipFiles as $countryCode => $urlsToAlternateNamesZipFile ) {
            try {
                $absoluteLocalFilePathsOfAlternateNamesZipFiles[ $countryCode ] = $this->downloadFile($urlsToAlternateNamesZipFile);
            } catch ( \Exception $e ) {
                $this->error( $e->getMessage() );
                Log::error( $urlsToAlternateNamesZipFiles, $e->getMessage(), 'remote', $this->connectionName );

                return FALSE;
            }
        }

        $this->info( "Done downloading alternate zip files." );

        $dataBeforeStart = (string)Carbon::now()->format('Y-m-d H:i:s');

        foreach ( $absoluteLocalFilePathsOfAlternateNamesZipFiles as $countryCode => $absoluteLocalFilePathOfAlternateNamesZipFile ) {
            try {
                $this->unzip( $absoluteLocalFilePathOfAlternateNamesZipFile, 'alternateNames');
                $this->info( "Unzipped " . $absoluteLocalFilePathOfAlternateNamesZipFile );
            } catch ( \Exception $e ) {
                $this->error( $e->getMessage() );
                Log::error( $absoluteLocalFilePathOfAlternateNamesZipFile, $e->getMessage(), 'local');

                return FALSE;
            }

            $absoluteLocalFilePathOfAlternateNamesFile = $this->getLocalAbsolutePathToAlternateNamesTextFile( $countryCode );

            if ( ! file_exists( $absoluteLocalFilePathOfAlternateNamesFile ) ) {
                throw new \Exception( "The unzipped file could not be found. We were looking for: " . $absoluteLocalFilePathOfAlternateNamesFile );
            }

            $this->insertAlternateNames( $absoluteLocalFilePathOfAlternateNamesFile );

        }

        do {
            // выбираем записи которые по дате обновления, более ранние чем запуск процесса обновления($dataStart)
            $geonamesAlternateNameIds = AlternateName::select('alternateNameId')->where('updated_at', '<', $dataBeforeStart)->limit(1000)->get()->pluck('alternateNameId')->toArray();
            AlternateName::whereIn('alternateNameId', $geonamesAlternateNameIds)->delete();

        } while (count($geonamesAlternateNameIds) > 0);



    }

    /**
     * @param array $countryCodes The two character country code, if specified by the user.
     *
     * @return array   The absolute paths to the remote alternate names zip files.
     */
    protected function getAlternateNameDownloadLinks( array $countryCodes = [] ): array {
        if ( empty( $countryCodes ) || array_search('*', $countryCodes, true) !== FALSE  ):
            return [ '*' => config('geonames.url') . self::REMOTE_FILE_NAME_FOR_ALL ];
        endif;

        $alternateNameDownloadLinks = [];
        foreach ( $countryCodes as $i => $countryCode ) {
            $alternateNameDownloadLinks[ $countryCode ] = config('geonames.url') . 'alternatenames/' . strtoupper( $countryCode ) . '.zip';
        }

        return $alternateNameDownloadLinks;
    }

    /**
     * This function is used in debugging only. The main block of code has no need for this function, since the
     * downloadFile() function returns this exact path as it's return value. The alternate names file takes a while
     * to download on my slow connection, so I save a copy of it for testing, and use this function to point to it.
     *
     * @return string The absolute local path to the downloaded zip file.
     * @throws \Exception
     */
    protected function getLocalAbsolutePathToAlternateNamesZipFile(): string {
        return GeoSetting::getAbsoluteLocalStoragePath( ) . DIRECTORY_SEPARATOR . self::REMOTE_FILE_NAME_FOR_ALL;
    }

    /**
     * @param string $countryCode The two character country code that a user can optionally pass in.
     *
     * @return string
     * @throws \Exception
     */
    protected function getLocalAbsolutePathToAlternateNamesTextFile( string $countryCode = NULL ): string {
        if ( '*' == $countryCode || is_null( $countryCode ) ):
            return GeoSetting::getAbsoluteLocalStoragePath() . DIRECTORY_SEPARATOR . 'alternateNames' . DIRECTORY_SEPARATOR .self::LOCAL_ALTERNATE_NAMES_FILE_NAME_FOR_ALL;
        endif;
        return GeoSetting::getAbsoluteLocalStoragePath() . DIRECTORY_SEPARATOR . 'alternateNames' . DIRECTORY_SEPARATOR . strtoupper( $countryCode ) . '.txt';
    }


    /**
     *
     * @param $localFilePath
     *
     * @return int
     * @throws \Exception
     */
    protected function insertAlternateNames( $localFilePath ) {



        $file = fopen($localFilePath, 'r');
        while (($line = fgets($file, null)) !== FALSE) {
            $row = str_getcsv($line, "\t");
            /*
            * alternateNameId   : the id of this alternate name, int
            * geonameid         : geonameId referring to id in table 'geoname', int
            * isolanguage       : iso 639 language code 2- or 3-characters; 4-characters 'post' for postal codes and 'iata','icao' and faac for airport codes, fr_1793 for French Revolution names,  abbr for abbreviation, link to a website (mostly to wikipedia), wkdt for the wikidataid, varchar(7)
            * alternate name    : alternate name or name variant, varchar(400)
            * isPreferredName   : '1', if this alternate name is an official/preferred name
            * isShortName       : '1', if this is a short name like 'California' for 'State of California'
                                                                                     * isColloquial      : '1', if this alternate name is a colloquial or slang term. Example: 'Big Apple' for 'New York'.
             * isHistoric        : '1', if this alternate name is historic and was used in the past. Example 'Bombay' for 'Mumbai'.
             */

            $alternateNameId = $row[ 0 ];
            $geonameid       = $row[ 1 ];
            $isolanguage     = empty( $row[ 2 ] ) ? '' : $row[ 2 ];
            $alternate_name  = empty( $row[ 3 ] ) ? '' : $row[ 3 ];
            $isPreferredName = empty( $row[ 4 ] ) ? FALSE : $row[ 4 ];
            $isShortName     = empty( $row[ 5 ] ) ? FALSE : $row[ 5 ];
            $isColloquial    = empty( $row[ 6 ] ) ? FALSE : $row[ 6 ];
            $isHistoric      = empty( $row[ 7 ] ) ? FALSE : $row[ 7 ];

            $pdo = DB::connection(GEONAMES_CONNECTION)->getPdo();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $this->table . '` SET
                                    `alternateNameId` = :alternateNameId,
                                    `geonameid` = :geonameid,
                                    `isolanguage` = :isolanguage,
                                    `alternate_name` = :alternate_name,
                                    `isPreferredName` = :isPreferredName,
                                    `isShortName` = :isShortName,
                                    `isColloquial` = :isColloquial,
                                    `isHistoric` = :isHistoric,   
                                    `created_at` = :created_at,
                                    `updated_at` = :updated_at
                                ON DUPLICATE KEY UPDATE                                    
                                    `geonameid` = :update_geonameid,
                                    `isolanguage` = :update_isolanguage,
                                    `alternate_name` = :update_alternate_name,
                                    `isPreferredName` = :update_isPreferredName,
                                    `isShortName` = :update_isShortName,
                                    `isColloquial` = :update_isColloquial,
                                    `isHistoric` = :update_isHistoric,   
                                    `updated_at` = :update_updated_at
                                '
            );
            $stmt->execute(
                [
                    ':alternateNameId' => (int)$alternateNameId,
                    ':geonameid' =>  (int)$geonameid,
                    ':isolanguage' => (string)$isolanguage,
                    ':alternate_name' => (string)$alternate_name,
                    ':isPreferredName' => (bool)$isPreferredName,
                    ':isShortName' => (bool)$isShortName,
                    ':isColloquial' => (bool)$isColloquial,
                    ':isHistoric' => (bool)$isHistoric,
                    ':created_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),
                    ':updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),

                    ':update_geonameid' =>  (int)$geonameid,
                    ':update_isolanguage' => (string)$isolanguage,
                    ':update_alternate_name' => (string)$alternate_name,
                    ':update_isPreferredName' => (bool)$isPreferredName,
                    ':update_isShortName' => (bool)$isShortName,
                    ':update_isColloquial' => (bool)$isColloquial,
                    ':update_isHistoric' => (bool)$isHistoric,
                    ':update_updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s')
                ]
            );

        }
    }


}
