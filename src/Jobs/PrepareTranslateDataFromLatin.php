<?php

namespace MichaelDrennen\Geonames\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MichaelDrennen\Geonames\Models\AlternateName;
use MichaelDrennen\Geonames\Models\TranslateText;

class PrepareTranslateDataFromLatin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // выбираем кирилические языки, типа русского и английского, для которых по идее не может быть английских и латинских символов, и записываем в базу для переводов

    public $langsFrom;

    public $countries = [];

    public $translateTextTable;


    public function __construct(array $langsFrom, array $countries)
    {
        $this->langsFrom = $langsFrom;
        $this->countries = $countries;

        $this->translateTextTable = (new TranslateText)->getTable();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $pdo = \Illuminate\Support\Facades\DB::connection(env( 'DB_GEONAMES_CONNECTION' ))->getPdo();
        $stmt = $pdo->prepare('INSERT INTO `'.$this->translateTextTable.'` SET
                                    `source_text` = :source_text,       
                                    `source_lang` = :source_lang,  
                                    `target_lang` = :target_lang                                                                
                                ON DUPLICATE KEY UPDATE 
                                    `id`=LAST_INSERT_ID(id)                                                                       
                                ');


        foreach($this->langsFrom as $langFrom){


            AlternateName::on( env( 'DB_GEONAMES_CONNECTION' ) )
                ->join('geonames', function($join) use($langFrom)
                {
                    $join->on('geonames.geonameid', '=', 'geonames_alternate_names.geonameid');
                    $join->where('geonames_alternate_names.isolanguage', '=', (string)$langFrom);
                })
                ->whereIn('geonames.country_code', $this->countries)
                ->chunkById(50, function($geonames) use($stmt, $langFrom){

                    foreach($geonames as $geonameItem){

                        if (preg_match('/[A-Za-z]/', (string)$geonameItem->alternate_name)) // '/[^a-z\d]/i' should also work.
                        {
                            $stmt->execute(
                                [

                                    ':source_text' => (string)$geonameItem->alternate_name,
                                    ':source_lang' => (string)$langFrom,
                                    ':target_lang' => (string)$langFrom,
                                ]);
                        }
                    }

                });
        }




    }
}
