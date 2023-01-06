<?php

namespace MichaelDrennen\Geonames\Models;

use Illuminate\Database\Eloquent\Model;
use Jeidison\CompositeKey\CompositeKey;

class Admin2Code extends Model {
    use CompositeKey;

    protected $primaryKey = 'geonameid';
    protected $table      = 'geonames_admin_2_codes';
    protected $guarded    = [];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [ 'geonameid' => 'integer' ];

    public function geoname() {
        return $this->hasOne( Geoname::class, 'geonameid', 'geonameid' );
    }


}
