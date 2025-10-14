<?php

namespace WPSPCORE\Permission\Traits;

trait PermissionsTrait {

	public function getName() {
		return $this->attributes['name'];
	}

	public function getGuardName() {
		return $this->attributes['guard_name'] ?? null;
	}

	/*
	 *
	 */

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function roles() {
		return $this->belongsToMany(
			$this->funcs->_config('permission.models.role'),
			'cm_role_has_permissions',
			'permission_id',
			'role_id'
		);
	}

	public function users() {
		$guardName = $this->getAttribute('guard_name');
		if ($guardName) {
			$providerName = $this->funcs->_config('auth.guards.' . $guardName . '.provider');
			$relatedModel = $this->funcs->_config('auth.providers.' . $providerName . '.model');
			if ($relatedModel) {
				return $this->morphedByMany(
					$relatedModel,
					'model',
					$this->funcs->_config('permission.table_names.model_has_permissions'),
					'permission_id',
					'model_id'
				);
			}
		}
	}

}