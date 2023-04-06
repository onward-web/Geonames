<?php
namespace MichaelDrennen\Geonames\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use MichaelDrennen\Geonames\Models\GeoSetting;
use MichaelDrennen\Geonames\Traits\GeonamesJobTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use MichaelDrennen\Geonames\Jobs\FeatureCodeJob;
use MichaelDrennen\Geonames\Jobs\IsoLanguageCodeJob;
use MichaelDrennen\Geonames\Jobs\Admin1CodeJob;
use MichaelDrennen\Geonames\Jobs\Admin2CodeJob;
use MichaelDrennen\Geonames\Jobs\FeatureClassJob;
use MichaelDrennen\Geonames\Jobs\AlternateNameJob;
use MichaelDrennen\Geonames\Jobs\DownloadGeonames;
use MichaelDrennen\Geonames\Jobs\InsertGeonamesJob;
use MichaelDrennen\Geonames\Jobs\UpdateGeonameByTranslateText;
use MichaelDrennen\Geonames\Jobs\PostalCodeJob;
use MichaelDrennen\Geonames\Jobs\NoCountryJob;

class InstallJob //implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeonamesJobTrait;

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

        //$emptyDirResult = GeoSetting::emptyTheStorageDirectory();
        $emptyDirResult = true;
        if ( $emptyDirResult === TRUE ):
            $this->line( "This storage dir has been emptied: " . GeoSetting::getAbsoluteLocalStoragePath() );
        endif;

        $this->line( "Starting " . self::class);


        $featureCodeJobInstance = new FeatureCodeJob($this->languages);
        dispatch_sync($featureCodeJobInstance);

        $isoLanguageCodeJobInstance = new IsoLanguageCodeJob($this->countries, $this->languages, $this->storageSubDir);
        dispatch_sync($isoLanguageCodeJobInstance);


        $admin1CodeJobInstance = new Admin1CodeJob($this->countries, $this->languages, $this->storageSubDir);
        dispatch_sync($admin1CodeJobInstance);


        $admin1CodeJobInstance = new Admin2CodeJob($this->countries, $this->languages, $this->storageSubDir);
        dispatch_sync($admin1CodeJobInstance);


        $featureClassJobInstance = new FeatureClassJob();
        dispatch_sync($featureClassJobInstance);

        $alternateNameJobInstance = new AlternateNameJob($this->countries, $this->languages, $this->storageSubDir);
        dispatch_sync($alternateNameJobInstance);

        $downloadGeonamesInstance = new DownloadGeonames();
        dispatch_sync($downloadGeonamesInstance);


        $insertGeonamesInstance = new InsertGeonamesJob();
        dispatch_sync($insertGeonamesInstance);

        $updateGeonameByTranslateTextInstance = new UpdateGeonameByTranslateText();
        dispatch_sync($updateGeonameByTranslateTextInstance);

        $postalCodeJobInstance = new PostalCodeJob($this->countries, $this->languages, $this->storageSubDir);
        dispatch_sync($postalCodeJobInstance);


        $postalCodeJobInstance = new NoCountryJob($this->countries, $this->languages, $this->storageSubDir);
        dispatch_sync($postalCodeJobInstance);



    }
}
