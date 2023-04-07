<?php

namespace MichaelDrennen\Geonames\Console;

use MichaelDrennen\Geonames\Jobs\InstallJob;

class Install extends Command
{
    protected $signature = 'geonames:install
        {--connection= : If you want to specify the name of the database connection you want used.} 
        {--country=* : Add the 2 digit code for each country. One per option.}      
        {--language=* : Add the 2 character language code.} 
        {--storage=geonames : The name of the directory, rooted in the storage_dir() path, where we store all downloaded files.}
        {--test : Call this boolean switch if you want to install just enough records to test the system. Makes it fast.}';

    public function handle()
    {

        $jobInstanse = new InstallJob($this->option('country'), $this->option('language'), $this->option('storage'));
        dispatch($jobInstanse);
    }
}
