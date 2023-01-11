<?php
namespace MichaelDrennen\Geonames\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MichaelDrennen\Geonames\Models\GeonameTranslateText;
use MichaelDrennen\Geonames\Models\Geoname;


class UpdateGeonameByTranslateText //implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        GeonameTranslateText::chunkById(100, function ($translateTexts) {
            foreach($translateTexts as $translateText){

                Geoname::where('alternate_name', $translateText->source_text)
                    ->where('isolanguage', $translateText->source_lang)
                    ->update(['alternate_name' => $translateText->target_text]);
            }

        });
    }
}
