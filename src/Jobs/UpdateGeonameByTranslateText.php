<?php

namespace MichaelDrennen\Geonames\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MichaelDrennen\Geonames\Models\AlternateName;
use MichaelDrennen\Geonames\Models\GeonameTranslateText;

class UpdateGeonameByTranslateText
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        GeonameTranslateText::chunkById(100, function ($translateTexts) {
            foreach ($translateTexts as $translateText) {

                AlternateName::where('alternate_name', $translateText->source_text)
                    ->where('isolanguage', $translateText->source_lang)
                    ->update(['alternate_name_edited' => $translateText->target_text]);
            }

        });
    }
}
