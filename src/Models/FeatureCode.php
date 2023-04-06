<?php

namespace MichaelDrennen\Geonames\Models;
use Illuminate\Database\Eloquent\Model;

class FeatureCode extends Model {

    protected $table = 'geonames_feature_codes';
    protected $connection = GEONAMES_CONNECTION;
}
