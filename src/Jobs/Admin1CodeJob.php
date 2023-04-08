<?php

namespace MichaelDrennen\Geonames\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MichaelDrennen\Geonames\Models\GeoSetting;
use MichaelDrennen\Geonames\Models\IsoLanguageCodeWorking;
use MichaelDrennen\Geonames\Models\Log;
use MichaelDrennen\Geonames\Traits\GeonamesJobTrait;
use MichaelDrennen\Geonames\Models\Admin1Code;
use MichaelDrennen\LocalFile\LocalFile;

class Admin1CodeJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeonamesJobTrait;

    protected $table;
    protected $countries;
    protected $languages;
    protected $storageSubDir;

    /**
     *
     */
    const REMOTE_FILE_NAME = 'admin1CodesASCII.txt';

    /**
     * The name of our temporary/working table in our database.
     */
    const TABLE_WORKING = 'geonames_admin_1_codes_working';


    public function __construct(array $countries = [], array $languages = [], string $storageSubDir = GeoSetting::DEFAULT_STORAGE_SUBDIR)
    {
        $this->countries = $countries;
        $this->languages = $languages;
        $this->storageSubDir = $storageSubDir;

        $this->table = (new Admin1Code)->getTable();
    }

    public function handle()
    {
        GeoSetting::init(
            $this->countries,
            $this->languages,
            $this->storageSubDir,
        );

        $remoteUrl = config('geonames.url') . self::REMOTE_FILE_NAME;

        DB::connection(GEONAMES_CONNECTION)->table($this->table)->truncate();

        try {
            $absoluteLocalPath = $this->downloadFile($remoteUrl);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::error($remoteUrl, $e->getMessage(), 'remote');
            return -2;
        }

        $dataBeforeStart = (string)Carbon::now()->format('Y-m-d H:i:s');

        try {
            $this->insertAdmin1Codes($absoluteLocalPath);

            do {
                // выбираем записи которые по дате обновления, более ранние чем запуск процесса обновления($dataStart)
                $admin1CodeGeonameIdsToDelete = Admin1Code::select('geonameid')->where('updated_at', '<', $dataBeforeStart)->limit(1000)->get()->pluck('geonameid')->toArray();
                Admin1Code::whereIn('geonameid', $admin1CodeGeonameIdsToDelete)->delete();

            } while (count($admin1CodeGeonameIdsToDelete) > 0);

            $this->info("The admin_1_codes data was downloaded and inserted.");

        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
            return -3;
        }


    }

    /**
     * Using Eloquent instead of LOAD DATA INFILE, because the rows in the downloaded file need to
     * be munged before they can be inserted.
     * Sample row:
     * US.CO    Colorado    Colorado    5417618
     *
     * @param string $localFilePath
     *
     * @throws \Exception
     */
    protected function insertAdmin1Codes(string $localFilePath)
    {

        $file = fopen($localFilePath, 'r');
        while (($line = fgets($file, null)) !== FALSE) {
            $row = str_getcsv($line, "\t");  // US.CO    Colorado    Colorado    5417618

            list($countryCode, $admin1Code) = explode('.', Arr::get($row, 0)); // US.CO
            $name = Arr::get($row, 1);     // Colorado
            $asciiName = Arr::get($row, 2);                    // Colorado
            $geonameId = Arr::get($row, 3);                    // 5417618


            $pdo = DB::connection(GEONAMES_CONNECTION)->getPdo();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $this->table . '` SET
                                    `geonameid` = :geonameid,
                                    `country_code` = :country_code,
                                    `admin1_code` = :admin1_code,  
                                    `name` = :name, 
                                    `asciiname` = :asciiname,   
                                    `created_at` = :created_at,
                                    `updated_at` = :updated_at
                                ON DUPLICATE KEY UPDATE         
                                    `country_code` = :update_country_code,
                                    `admin1_code` = :update_admin1_code,  
                                    `name` = :update_name, 
                                    `asciiname` = :update_asciiname,   
                                    `updated_at` = :update_updated_at
                                '
            );
            $stmt->execute(
                [
                    ':geonameid' => (string)$geonameId,
                    ':country_code' => $countryCode,
                    ':admin1_code' => $admin1Code,
                    ':name' => $name,
                    ':asciiname' => $asciiName,
                    ':created_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),
                    ':updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),

                    ':update_country_code' => $countryCode,
                    ':update_admin1_code' => $admin1Code,
                    ':update_name' => $name,
                    ':update_asciiname' => $asciiName,
                    ':update_updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s')
                ]
            );
        }
    }


}
