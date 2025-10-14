<?php
namespace WPSPCORE\Permission\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use WPSPCORE\Database\Base\BaseModel;
use WPSPCORE\Permission\Traits\RolesTrait;
use WPSPCORE\Traits\ObserversTrait;

class RolesModel extends BaseModel {

	use SoftDeletes, ObserversTrait, RolesTrait;

	protected $connection = 'wordpress';
	protected $prefix     = 'wp_wpsp_';
	protected $table      = 'cm_roles';
//	protected $primaryKey = 'id';

//	protected $appends;
//	protected $attributeCastCache;
//	protected $attributes;
//	protected $casts;
//	protected $changes;
//	protected $classCastCache;
//	protected $dateFormat;
//	protected $dispatchesEvents;
//	protected $escapeWhenCastingToString;
//	protected $fillable   = [];
//	protected $forceDeleting;
	protected $guarded    = [];
//	protected $hidden;
//	protected $keyType;
//	protected $observables;
//	protected $original;
//	protected $perPage;
//	protected $relations;
//	protected $touches;
//	protected $visible;
//	protected $with;
//	protected $withCount;

//	public    $exists;
//	public    $incrementing;
//	public    $preventsLazyLoading;
//	public    $timestamps;
//	public    $usesUniqueIds;
//	public    $wasRecentlyCreated;

//	protected static $observers = [
//		\WPSP\app\Observers\SettingsObserver::class,
//	];

//	public function __construct($attributes = []) {
//		$this->getConnection()->setTablePrefix('wp_wpsp_');
//		$this->setConnection(Funcs::instance()->_getDBTablePrefix(false) . 'mysql');
//		parent::__construct($attributes);
//	}

}
