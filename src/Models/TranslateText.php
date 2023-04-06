<?php


namespace MichaelDrennen\Geonames\Models;


use Illuminate\Database\Eloquent\Model;

class TranslateText extends Model
{
    protected $table      = 'geonames_translate_texts';
    protected $connection = GEONAMES_CONNECTION;
}
