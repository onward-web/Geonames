<?php
namespace MichaelDrennen\Geonames\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MichaelDrennen\Geonames\Models\GeoSetting;
use MichaelDrennen\Geonames\Models\Log;
use Curl\Curl;
use MichaelDrennen\RemoteFile\RemoteFile;
use Symfony\Component\DomCrawler\Crawler;
use ZipArchive;
use Illuminate\Support\Facades\Storage;


trait GeonamesJobTrait {

    public function line($line){
        echo $line.'<br>';
    }

    public function error($error){
        echo '<b>'.$error.'<b><br>';
    }

    public function info($error){
        echo '<i>'.$error.'</i><br>';
    }

    protected function fixDirectorySeparatorForWindows( string $path ): string {
        if ( '\\' === DIRECTORY_SEPARATOR ):
            $path = str_replace( DIRECTORY_SEPARATOR, '\\\\', $path );
        endif;
        return $path;
    }


    /**
     * @return array An array of all the anchor tag href attributes on the given url parameter.
     * @throws \ErrorException
     */
    public static function getAllLinksOnDownloadPage(): array {
        $curl = new Curl();

        $curl->get( config('geonames.url') );
        $html = $curl->response;

        $crawler = new Crawler( $html );

        return $crawler->filter( 'a' )->each( function ( Crawler $node ) {
            return $node->attr( 'href' );
        } );
    }


    /**
     * @param array   $downloadLinks
     *
     * @return array
     * @throws \Exception
     */
    public static function downloadFiles( array $downloadLinks, $subDir = null): array {
        $localFilePaths = [];
        foreach ( $downloadLinks as $link ) {
            $localFilePaths[] = self::downloadFile( $link, $subDir );
        }

        return $localFilePaths;
    }

    /**
     * @param string  $link           The absolute path to the remote file we want to download.
     *
     * @return string           The absolute local path to the file we just downloaded.
     * @throws Exception
     */
    public static function downloadFile( string $link, $subDir = null): string {
        $curl = new Curl();

        $basename      = basename( $link );
        $localFilePath = GeoSetting::getAbsoluteLocalStoragePath();

        if($subDir){
            $localFilePath .= DIRECTORY_SEPARATOR.$subDir;
        }

        if(!is_dir($localFilePath) || !file_exists($localFilePath)) {
            mkdir($localFilePath, 0775, true);
        }

        $localFilePath .= DIRECTORY_SEPARATOR . $basename;

        // Display a progress bar if we can get the remote file size.
        $fileSize = RemoteFile::getFileSize( $link );

        $curl->get( $link );

        if ( $curl->error ) {
            Log::error( $link, $curl->error_message, $curl->error_code );
            throw new \Exception( "Unable to download the file at [" . $link . "]\n" . $curl->error_message );
        }

        $data         = $curl->response;
        $bytesWritten = file_put_contents( $localFilePath, $data );
        if ( $bytesWritten === FALSE ) {
            Log::error( $link,
                "Unable to create the local file at '" . $localFilePath . "', file_put_contents() returned false. Disk full? Permission problem?",
                    'local'
                    );
            throw new \Exception( "Unable to create the local file at '" . $localFilePath . "', file_put_contents() returned false. Disk full? Permission problem?" );
        }

        return $localFilePath;
    }


    /**
     * Given a csv file on disk, this function converts it to a php array.
     *
     * @param   string $localFilePath The absolute path to a csv file in storage.
     * @param   string $delimiter     In a csv file, the character between fields.
     *
     * @return  array     A multi-dimensional made of the data in the csv file.
     */
    public static function csvFileToArray( string $localFilePath, $delimiter = "\t" ): array {
        $rows = [];
        if ( ( $handle = fopen( $localFilePath, "r" ) ) !== FALSE ) {
            while ( ( $data = fgetcsv( $handle, 0, $delimiter ) ) !== FALSE ) {
                $rows[] = $data;
            }
            fclose( $handle );
        }

        return $rows;
    }


    /**
     * Unzips the zip file into our geonames storage dir that is set in GeoSettings.
     *
     * @param   string $localFilePath Absolute local path to the zip archive.
     * @throws  Exception
     */
    public static function unzip( $localFilePath, $subForlder = null) {
        $extractTo      = GeoSetting::getAbsoluteLocalStoragePath();

        if($subForlder){
            $extractTo .= DIRECTORY_SEPARATOR.$subForlder;
        }


        $zip           = new ZipArchive;
        $zipOpenResult = $zip->open( $localFilePath );
        if ( TRUE !== $zipOpenResult ) {
            throw new \Exception( "Error [" . $zipOpenResult . "] Unable to unzip the archive at " . $localFilePath );
        }
        $extractResult = $zip->extractTo( $extractTo );
        if ( FALSE === $extractResult ) {
            throw new \Exception( "Unable to unzip the file at " . $localFilePath );
        }
        $closeResult = $zip->close();
        if ( FALSE === $closeResult ) {
            throw new \Exception( "After unzipping unable to close the file at " . $localFilePath );
        }

        return;
    }


    /**
     * Pass in an array of absolute local file paths, and this function will extract
     * them to our geonames storage directory.
     *
     * @param array  $absoluteFilePaths
     * @param string $connection
     *
     * @throws Exception
     */
    public static function unzipFiles( array $absoluteFilePaths, $subForlder = null) {
        try {
            foreach ( $absoluteFilePaths as $absoluteFilePath ) {
                self::unzip( $absoluteFilePath, $subForlder);
            }
        } catch ( \Exception $e ) {
            throw $e;
        }
    }



    /**
     * SQLITE doesn't support a lot of the functionality that MySQL supports.
     * @return bool
     */
    protected function isRobustDriver() {
        $driver = $this->getDriver();
        switch ( $driver ):
            case 'mysql':
                return true;

            case 'sqlite':
                return false;

            default:
                return false;
        endswitch;
    }




}
