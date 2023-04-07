<?php

namespace MichaelDrennen\Geonames\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use MichaelDrennen\Geonames\Models\GeoSetting;
use MichaelDrennen\Geonames\Models\Log;
use MichaelDrennen\Geonames\Traits\GeonamesJobTrait;
use MichaelDrennen\Geonames\Models\FeatureCode;
use Illuminate\Support\Facades\Schema;

class FeatureCodeJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeonamesJobTrait;

    /**
     * The name of our feature codes table in our database. Using constants here, so I don't need
     * to worry about typos in my code. My IDE will warn me if I'm sloppy.
     */
    protected $table;

    protected $languages;

    public function __construct($languages = [])
    {
        $this->languages = $languages;

        $this->table = (new FeatureCode())->getTable();
    }

    public function handle()
    {
        try {
            // Get all of the feature code lines from the geonames.org download page, or an array that you specify.
            $featureCodeFileDownloadLinks = $this->getFeatureCodeFileDownloadLinks(
                array_filter($this->languages)
            );

            // Download each of the files that we found.
            $localPathsToFeatureCodeFiles = self::downloadFiles($featureCodeFileDownloadLinks,);
        } catch (\Exception $exception) {
            Log::error('', $exception->getMessage(), 'general');
            $this->error($exception->getMessage());
            throw $exception;
        }

        // Now that we have all of the feature code files stored locally, we need to prepare
        // the data to be inserted into our database. Convert each tab delimited row into a php
        // array, and add the language code from the file name as another field for each row.
        // Also, we do a check to see if the row holds valid data. See the comments for
        // isValidRow() for details.
        $validRows = $this->getValidRowsFromFiles($localPathsToFeatureCodeFiles);

        $dataBeforeStart = (string)Carbon::now()->format('Y-m-d H:i:s');

        // Now that we have our rows, let's insert them into our working table.
        $allRowsInserted = false;
        try {
            $allRowsInserted = $this->insertValidRows($validRows);
        } catch (\Exception $exception) {
            // An additional log line to help determine the cause of the failure for the developer.
            Log::error('', $exception->getMessage(), 'database');
        }

        if ($allRowsInserted) {
            $this->line("Updated: " . self::class);

            do {
                // выбираем записи которые по дате обновления, более ранние чем запуск процесса обновления($dataStart)
                $fаeatureCodeIdsToDelete = FeatureCode::select('id')->where('updated_at', '<', $dataBeforeStart)->limit(1000)->get()->pluck('id')->toArray();
                FeatureCode::whereIn('id', $fаeatureCodeIdsToDelete)->delete();

            } while (count($fаeatureCodeIdsToDelete) > 0);

        }


    }

    /**
     * There are feature code files on geonames.org for a few different languages. The file names
     * all start with 'featureCodes_', so we get all of the links from the page, and only return
     * ones that start with that string.
     * @param array $languageCodes A list of the language codes you want to get links for. Really only used for testing.
     * @return array A list of all of the featureCode files from the geonames.org site.
     * @throws \ErrorException
     */
    protected function getFeatureCodeFileDownloadLinks(array $languageCodes = []): array
    {
        $links = $this->getAllLinksOnDownloadPage();


        $featureCodeFileDownloadLinks = [];
        foreach ($links as $link) {
            $string = 'featureCodes_';
            $length = strlen($string);
            // If the link starts with the string in $string, then I know its a featureCodes file.
            if (substr($link, 0, $length) === $string) {
                // Either add the links for all of the languages, or just the ones specified in $languageCodes.
                $languageCode = $this->getLanguageCodeFromFeatureCodeDownloadLink($link);
                if (empty($languageCodes) || in_array($languageCode, $languageCodes)):
                    $featureCodeFileDownloadLinks[] = config('geonames.url') . $link;
                endif;
            }
        }

        return $featureCodeFileDownloadLinks;
    }

    private function getLanguageCodeFromFeatureCodeDownloadLink(string $link): string
    {
        return str_replace(['featureCodes_', '.txt'], '', $link);
    }

    /**
     * Each feature code file downloaded from geonames.org has a language code as part of the file name.
     * We insert the feature code rows from all of the files into the same table.
     * We manually add a language_code field to the table, and use the code from the file name
     * to populate it.
     *
     * @param string $absoluteLocalFilePath The absolute local file path to the feature code file.
     *
     * @return string   The two character language code for the feature code file.
     */
    protected function getLanguageCodeFromFileName(string $absoluteLocalFilePath): string
    {
        $basename = basename($absoluteLocalFilePath, '.txt');
        $nameParts = explode('_', $basename);
        $languageCode = $nameParts[1];

        return $languageCode;
    }


    /**
     * The geonames.org featureCodes file has an ending row:
     * null    not available
     * I'm not sure if it will always be there. (If it is, then I could just pop it off the end.)
     * Since I can't necessarily count on that, then let's do a more robust check to
     * make sure the row is valid.
     * Basically make sure that whatever data is in that row can be inserted into the database.
     * A valid row will look like this:
     * H.CNL    canal    an artificial watercourse
     * ...and by the time we run this function, we may have already appended the language code
     * to the end of the row. But in this function, we only check the value in the first field.
     * @param array $row
     * @return boolean
     */
    protected function isValidRow(array $row): bool
    {
        $classAndCode = explode('.', $row[0]);
        if (count($classAndCode) != 2) {
            return FALSE;
        }
        if (empty($classAndCode[0]) || empty($classAndCode[1])) {
            return FALSE;
        }

        return TRUE;
    }


    /**
     * @param array $localPathsToFeatureCodeFiles
     *
     * @return array
     */
    protected function getValidRowsFromFiles(array $localPathsToFeatureCodeFiles): array
    {
        $validRows = [];
        foreach ($localPathsToFeatureCodeFiles as $i => $file) {
            $languageCode = $this->getLanguageCodeFromFileName($file);
            $dataRows = self::csvFileToArray($file);
            foreach ($dataRows as $j => $row) {
                if ($this->isValidRow($row)) {
                    $dataRows[$j][] = $languageCode;
                    $validRows[] = $dataRows[$j];
                }
            }
        }

        return $validRows;
    }


    /**
     * Insert all of the data into our database. We insert these rows into a 'working' table, not the
     * live table. We do this so users can safely update the geonames_feature_codes table on a production box
     * without any significant downtime.
     *
     * @param array $validRows An associative array of data from the feature code files from geonames.org
     *
     * @return bool Returns true if all of the rows were inserted. False otherwise.
     */
    protected function insertValidRows(array $validRows): bool
    {
        $numRowsInserted = 0;
        $numRowsNotInserted = 0;
        $numRowsToBeInserted = count($validRows);


        foreach ($validRows as $rowNumber => $row) {


            list($feature_class, $feature_code) = explode('.', $row[0]);


            $pdo = DB::connection(GEONAMES_CONNECTION)->getPdo();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $this->table . '` SET
                                    `language_code` = :language_code,
                                    `feature_class` = :feature_class,
                                    `feature_code` = :feature_code,
                                    `name` = :name,
                                    `description` = :description,
                                    `created_at` = :created_at,
                                    `updated_at` = :updated_at
                                ON DUPLICATE KEY UPDATE     
                                    id=LAST_INSERT_ID(id),                             
                                    `name` = :update_name,
                                    `description` = :update_description,                                    
                                    `updated_at` = :update_updated_at
                                '
            );
            $stmt->execute(
                [
                    ':language_code' => $row[3],
                    ':feature_class' => $feature_class,
                    ':feature_code' => $feature_code,
                    ':name' => $row[1],
                    ':description' => $row[2],
                    ':created_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),
                    ':updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),

                    ':update_name' => $row[1],
                    ':update_description' => $row[2],
                    ':update_updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s')
                ]
            );


            if ((int)$pdo->lastInsertId() > 0) {
                $numRowsInserted++;
            } else {
                $numRowsNotInserted++;
                $this->error("\nRow " . $rowNumber . " of " . $numRowsToBeInserted . " was NOT inserted.");
            }
        }

        if ($numRowsInserted != $numRowsToBeInserted) {
            return FALSE;
        }
        return TRUE;
    }


}
