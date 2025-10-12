<?php
namespace WPSPCORE\Permission\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use WPSPCORE\Database\Base\BaseModel;
use WPSPCORE\Permission\Traits\PermissionsTrait;
use WPSPCORE\Traits\ObserversTrait;

class PermissionsModel extends BaseModel {

	use SoftDeletes, ObserversTrait, PermissionsTrait;

	protected $connection = 'wordpress';
	protected $prefix     = 'wp_wpsp_';
	protected $table      = 'cm_permissions';
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
//		\WPSP\app\Observers\PermissionsModelObserver::class,
//	];

//	public function __construct($attributes = []) {
//		$this->getConnection()->setTablePrefix('wp_wpsp_');
//		$this->setConnection('wordpress');
//		parent::__construct($attributes);
//	}

}
