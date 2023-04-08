<?php

namespace MichaelDrennen\Geonames\Models;

use Illuminate\Database\Eloquent\Model;
use Jeidison\CompositeKey\CompositeKey;

class Admin2Code extends Model
{
    use CompositeKey;

    protected $primaryKey = 'geonameid';
    protected $table = 'geonames_admin_2_codes';
    protected $guarded = [];
    protected $connection = GEONAMES_CONNECTION;
    public $incrementing = false;

    // original field alternateNameId  transform to string, custom use Uuid
    protected $keyType = 'string';

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = ['geonameid' => 'integer'];

    public function geoname()
    {
        return $this->hasOne(Geoname::class, 'geonameid', 'geonameid');
    }


}
