<?php

namespace MichaelDrennen\Geonames\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use MichaelDrennen\Geonames\Models\Admin2Code;
use MichaelDrennen\Geonames\Models\GeoSetting;
use MichaelDrennen\Geonames\Models\Log;
use MichaelDrennen\Geonames\Traits\GeonamesJobTrait;
use MichaelDrennen\LocalFile\LocalFile;

class Admin2CodeJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeonamesJobTrait;

    protected $table;
    protected $countries;
    protected $languages;
    protected $storageSubDir;

    /**
     *
     */
    const REMOTE_FILE_NAME = 'admin2Codes.txt';


    public function __construct(array $countries = [], array $languages = [], string $storageSubDir = GeoSetting::DEFAULT_STORAGE_SUBDIR)
    {
        $this->countries = $countries;
        $this->languages = $languages;
        $this->storageSubDir = $storageSubDir;

        $this->table = (new Admin2Code)->getTable();
    }

    public function handle()
    {
        GeoSetting::init(
            $this->countries,
            $this->languages,
            $this->storageSubDir,
        );

        $remoteUrl = config('geonames.url') . self::REMOTE_FILE_NAME;

        DB::connection( GEONAMES_CONNECTION)->table( $this->table  )->truncate();

        try {
            $absoluteLocalPath = $this->downloadFile( $remoteUrl );
        } catch ( \Exception $e ) {
            $this->error( $e->getMessage() );
            Log::error( $remoteUrl, $e->getMessage(), 'remote');
            return FALSE;
        }

        $dataBeforeStart = (string)Carbon::now()->format('Y-m-d H:i:s');

        try {
            $this->insertAdmin2Codes($absoluteLocalPath);

            do {
                // выбираем записи которые по дате обновления, более ранние чем запуск процесса обновления($dataStart)
                $admin2CodeGeonameIdsToDelete = Admin2Code::select('geonameid')->where('updated_at', '<', $dataBeforeStart)->limit(1000)->get()->pluck('geonameid')->toArray();
                Admin2Code::whereIn('geonameid', $admin2CodeGeonameIdsToDelete)->delete();

            } while (count($admin2CodeGeonameIdsToDelete) > 0);

            $this->info( "The admin_2_codes data was downloaded and inserted" );

        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
            return -3;
        }




    }

    /**
     * Using Eloquent instead of LOAD DATA INFILE, because the rows in the downloaded file need to
     * be munged before they can be inserted.
     * Sample row:
     * US.CO.107    Routt County    Routt County    5581553
     *
     * @param string $localFilePath
     *
     * @throws \MichaelDrennen\LocalFile\Exceptions\UnableToOpenFile
     */
    protected function insertAdmin2Codes( string $localFilePath ) {
        $file = fopen($localFilePath, 'r');
        while (($line = fgets($file, null)) !== FALSE) {

            $row = str_getcsv($line, "\t");  // US.CO.107	Routt County	Routt County	5581553

            $arrCol1 = explode('.', Arr::get($row, 0)); // US.CO.107

            $countryCode = Arr::get($arrCol1, 0, null); //US
            $admin1Code = Arr::get($arrCol1, 1, null); // CO
            $admin2Code = Arr::get($arrCol1, 2, null);  // 107

            $name = Arr::get($row, 1);     // Routt County
            $asciiName = Arr::get($row, 2);                    // Routt County
            $geonameId = Arr::get($row, 3);                    // 5581553


            $pdo = DB::connection(GEONAMES_CONNECTION)->getPdo();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $this->table . '` SET
                                    `geonameid` = :geonameid,
                                    `country_code` = :country_code,
                                    `admin1_code` = :admin1_code,
                                    `admin2_code` = :admin2_code,   
                                    `name` = :name, 
                                    `asciiname` = :asciiname,   
                                    `created_at` = :created_at,
                                    `updated_at` = :updated_at
                                ON DUPLICATE KEY UPDATE         
                                    `country_code` = :update_country_code,
                                    `admin1_code` = :update_admin1_code,  
                                    `admin2_code` = :update_admin2_code,  
                                    `name` = :update_name, 
                                    `asciiname` = :update_asciiname,   
                                    `updated_at` = :update_updated_at
                                '
            );
            $stmt->execute(
                [
                    ':geonameid' => $geonameId,
                    ':country_code' => $countryCode,
                    ':admin1_code' => $admin1Code,
                    ':admin2_code' => $admin2Code,
                    ':name' => $name,
                    ':asciiname' => $asciiName,
                    ':created_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),
                    ':updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),

                    ':update_country_code' => $countryCode,
                    ':update_admin1_code' => $admin1Code,
                    ':update_admin2_code' => $admin2Code,
                    ':update_name' => $name,
                    ':update_asciiname' => $asciiName,
                    ':update_updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s')
                ]
            );


        }






    }
}
