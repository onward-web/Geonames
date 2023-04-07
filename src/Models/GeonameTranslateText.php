<?php

namespace MichaelDrennen\Geonames\Models;


use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use MichaelDrennen\Geonames\Events\GeonameUpdated;
use MichaelDrennen\Geonames\Models\AlternateName;
use EloquentFilter\Filterable;
use Jeidison\CompositeKey\CompositeKey;


class GeonameTranslateText extends Model
{

    use Filterable;
    use CompositeKey;

    protected $table = 'geonames_translate_texts';

    protected $primaryKey = 'id';

    protected $connection = GEONAMES_CONNECTION;


    /**
     * @var array An empty array, because I want all of the fields mass assignable.
     */
    protected $guarded = [];


}
