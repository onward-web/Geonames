<?php
namespace MichaelDrennen\Geonames\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MichaelDrennen\Geonames\Models\GeoSetting;
use MichaelDrennen\Geonames\Traits\GeonamesConsoleTrait;

class Install implements ShouldQueue{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeonamesConsoleTrait;

    protected $countries;
    protected $languages;
    protected $storageSubDir;

    public function __construct(array $countries = [], array $languages = [], string $storageSubDir = GeoSetting::DEFAULT_STORAGE_SUBDIR)
    {
        $this->countries = $countries;
        $this->languages = $languages;
        $this->storageSubDir = $storageSubDir;
    }

    public function handle()
    {
        GeoSetting::install(
            $this->countries,
            $this->languages,
            $this->storageSubDir
        );

        GeoSetting::setStatus( GeoSetting::STATUS_INSTALLING);

        $emptyDirResult = GeoSetting::emptyTheStorageDirectory();
        if ( $emptyDirResult === TRUE ):
            $this->line( "This storage dir has been emptied: " . GeoSetting::getAbsoluteLocalStoragePath() );
        endif;
    }
}
