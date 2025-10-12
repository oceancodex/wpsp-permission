<?php
namespace WPSPCORE\Permission\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use WPSPCORE\Database\Base\BaseModel;
use WPSPCORE\Permission\Contracts\RoleContract;
use WPSPCORE\Permission\Traits\PermissionTrait;
use WPSPCORE\Traits\ObserversTrait;

class RolesModel extends BaseModel implements RoleContract {

	use SoftDeletes, ObserversTrait, PermissionTrait;

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

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function permissions() {
		return $this->belongsToMany(
			$this->funcs->_config('permission.models.permission'),
			'cm_role_has_permissions',
			'role_id',
			'permission_id'
		);
	}

	/*
	 *
	 */

	public function getName() {
		return $this->attributes['name'];
	}

	public function getGuardName() {
		return $this->attributes['guard_name'] ?? null;
	}

}
