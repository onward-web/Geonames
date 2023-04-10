<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGeonamesPostalCodesTable extends Migration
{

    const TABLE = 'geonames_postal_codes';

    /**
     * Run the migrations.
     * Source of data: http://download.geonames.org/export/zip/allCountries.zip
     * Sample data:
     * US    99553    Akutan    Alaska    AK    Aleutians East    013            54.143    -165.7854    1
     *
     * @return void
     */
    public function up()
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('source_text', 700);
            $table->string('source_lang', 3);
            $table->string('target_text', 700);
            $table->string('target_lang', 3);

            $table->timestamps();

            $table->index('source_text');
            $table->index('source_lang');
            $table->unique(['source_text', 'source_lang', 'target_lang']);
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(self::TABLE);
    }
}
