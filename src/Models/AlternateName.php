<?php

namespace MichaelDrennen\Geonames\Models;

use Carbon\Carbon;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\DB;

class AlternateName extends Model
{

    use Filterable, HasUuids;

    protected $appends = ['alternate_name_after_check'];

    protected $table = 'geonames_alternate_names';
    protected $primaryKey = 'alternateNameId';

    protected $connection = GEONAMES_CONNECTION;

    public $incrementing = false;

    // original field alternateNameId  transform to string, custom use Uuid
    protected $keyType = 'string';

    /**
     * @var array An empty array, because I want all of the fields mass assignable.
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'alternateNameId' => 'string',
        'geonameid' => 'string',
        'isolanguage' => 'string',
        'alternate_name' => 'string',
        'isPreferredName' => 'boolean',
        'isShortName' => 'boolean',
        'isColloquial' => 'boolean',
        'isHistoric' => 'boolean',
    ];



    public function modelFilter()
    {
        return $this->provideFilter(\MichaelDrennen\Geonames\ModelFilters\AlternateNameFilter::class);
    }


    public function geoname()
    {
        return $this->belongsTo(Geoname::class, 'geonameid', 'geonameid');
    }

    public function getAlternateNameAfterCheckAttribute()
    {
        if ($this->alternate_name_edited && !empty($this->alternate_name_edited)) {
            return $this->alternate_name_edited;
        }
        return $this->alternate_name;

    }

    /**
     * @param int $geonameid
     * @param string $isolanguage
     * @param string $alternate_name
     * @param bool $isPreferredName
     * @param bool $isShortName
     * @param $isColloquial
     * @param $isHistoric
     * @param $isEnable
     * @param $uniqueStr
     * @return void
     */
    public static function addCustomAlternateNameRecord(string $geonameid, string $isolanguage, string $alternate_name, bool $isPreferredName, bool $isShortName, $isColloquial, $isHistoric, $isEnable = 1, $uniqueStr = '' ){

        $tmpItem = new self();
        $table = $tmpItem->getTable();
        $alternateNameUuid = $tmpItem->newUniqueId();

        $pdo = DB::connection(GEONAMES_CONNECTION)->getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO `' . $table. '` SET
                                    `alternateNameId` = :alternateNameId,
                                    `geonameid` = :geonameid,
                                    `isolanguage` = :isolanguage,
                                    `alternate_name` = :alternate_name,
                                    `isPreferredName` = :isPreferredName,
                                    `isShortName` = :isShortName,
                                    `isColloquial` = :isColloquial,
                                    `isHistoric` = :isHistoric, 
                                    `isCustom` = :isCustom,
                                    `isEnable` = :isEnable,
                                    `uniqueStr` = :uniqueStr,
                                    `created_at` = :created_at,
                                    `updated_at` = :updated_at
                                '
        );
        $stmt->execute(
            [
                ':alternateNameId' => (string)$alternateNameUuid,
                ':geonameid' => (int)$geonameid,
                ':isolanguage' => (string)$isolanguage,
                ':alternate_name' => (string)$alternate_name,
                ':isPreferredName' => (bool)$isPreferredName,
                ':isShortName' => (bool)$isShortName,
                ':isColloquial' => (bool)$isColloquial,
                ':isHistoric' => (bool)$isHistoric,
                ':isCustom' => 1,
                ':isEnable' => $isEnable,
                ':uniqueStr' => $uniqueStr,
                ':created_at' => (string)Carbon::now()->format('Y-m-d H:i:s'),
                ':updated_at' => (string)Carbon::now()->format('Y-m-d H:i:s')
            ]
        );

        return $alternateNameUuid;
    }

}
