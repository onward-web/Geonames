<?php

namespace MichaelDrennen\Geonames\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MichaelDrennen\Geonames\Models\GeoSetting;
use MichaelDrennen\Geonames\Models\Log;
use MichaelDrennen\Geonames\Traits\GeonamesJobTrait;
use MichaelDrennen\Geonames\Models\IsoLanguageCode;
use Illuminate\Support\Arr;

class IsoLanguageCodeJob //implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeonamesJobTrait;

    protected $table;
    protected $countries;
    protected $languages;
    protected $storageSubDir;
    /**
     *
     */
    const LANGUAGE_CODES_FILE_NAME = 'iso-languagecodes.txt';

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeonamesJobTrait;

    public function __construct(array $countries = [], array $languages = [], string $storageSubDir = GeoSetting::DEFAULT_STORAGE_SUBDIR)
    {
        $this->countries = $countries;
        $this->languages = $languages;
        $this->storageSubDir = $storageSubDir;

        $this->table = (new IsoLanguageCode)->getTable();
    }

    public function handle()
    {
        GeoSetting::init(
            $this->countries,
            $this->languages,
            $this->storageSubDir,
        );

        $remotePath = config('geonames.url') . self::LANGUAGE_CODES_FILE_NAME;
        $absoluteLocalFilePathOfIsoLanguageCodesFile = self::downloadFile($remotePath);

        if (!file_exists($absoluteLocalFilePathOfIsoLanguageCodesFile)) {
            throw new \Exception("We were unable to download the file at: " . $absoluteLocalFilePathOfIsoLanguageCodesFile);
        }

        $dataBeforeStart = (string)Carbon::now()->format('Y-m-d H:i:s');

        $this->insertIsoLanguageCodes($absoluteLocalFilePathOfIsoLanguageCodesFile);

        do {
            // выбираем записи которые по дате обновления, более ранние чем запуск процесса обновления($dataStart)
            $isoLanguageCodeIso6393sToDelete = IsoLanguageCode::select('iso_639_3')->where('updated_at', '<', $dataBeforeStart)->limit(1000)->get()->pluck('iso_639_3')->toArray();
            IsoLanguageCode::whereIn('id', $isoLanguageCodeIso6393sToDelete)->delete();

        } while (count($isoLanguageCodeIso6393sToDelete) > 0);


        $this->info("iso_language_codes data was downloaded and inserted");
    }


    /**
     * @param string $localFilePath
     * @throws Exception
     */
    protected function insertIsoLanguageCodes(string $localFilePath)
    {

        $this->line("Inserting insertIsoLanguageCodes: " . $localFilePath);

        $iRow = 0;
        $rows = [];
        $file = fopen($localFilePath, 'r');
        while (($line = fgets($file, null)) !== FALSE) {
            ++$iRow;
            if ($iRow <= 1) {
                continue;
            }
            $row = str_getcsv($line, "\t");

            $iso_639_3 = Arr::get($row, 0);
            $iso_639_2 = Arr::get($row, 1);
            $iso_639_1 = Arr::get($row, 2);
            $languageName = Arr::get($row, 3);

            if (empty($iso_639_3)) {
                $iso_639_3 = $iso_639_2;
            }

            $pdo = DB::connection(GEONAMES_CONNECTION)->getPdo();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $this->table . '` SET
                                    `iso_639_3` = :iso_639_3,
                                    `iso_639_2` = :iso_639_2,
                                    `iso_639_1` = :iso_639_1,  
                                    `language_name` = :language_name,                                
                                    `created_at` = :created_at,
                                    `updated_at` = :updated_at
                                ON DUPLICATE KEY UPDATE         
                                    `iso_639_2` = :update_iso_639_2,
                                    `iso_639_1` = :update_iso_639_1,
                                    `language_name` = :update_language_name,      
                                    `updated_at` = :update_updated_at
                                '
            );
            $stmt->execute(
                [
                    ':iso_639_3' => $iso_639_3,
                    ':iso_639_2' => $iso_639_2,
                    ':iso_639_1' => $iso_639_1,
                    ':language_name' => $languageName,
                    ':created_at' => (string)\Illuminate\Support\Carbon::now()->format('Y-m-d H:i:s'),
                    ':updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),

                    ':update_iso_639_2' => $iso_639_2,
                    ':update_iso_639_1' => $iso_639_1,
                    ':update_language_name' => $languageName,
                    ':update_updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s')
                ]
            );
        }
        fclose($file);

    }


}
