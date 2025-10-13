<?php

namespace WPSPCORE\Permission\Traits;

trait RolesTrait {

	public function getName() {
		return $this->attributes['name'];
	}

	public function getGuardName() {
		return $this->attributes['guard_name'] ?? null;
	}

	/*
	 * Models.
	 */

	protected function roleModel() {
		return $this->funcs->_config('permission.models.role');
	}

	protected function permissionModel() {
		return $this->funcs->_config('permission.models.permission');
	}

	/*
	 * Relationships.
	 */

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

	/**
	 * Kiểm tra user hoặc role có permission cụ thể không.
	 */
	public function hasPermissionTo($permissionName, $guardName = null) {
		// Lấy guard_name từ model hiện tại nếu không được truyền vào
		if ($guardName === null) {
			$guardName = $this->guard_name ?? ['web'];
		}

		// Kiểm tra permission trực tiếp với guard_name
		if ($this->permissions()->where('name', $permissionName)->whereIn('guard_name', is_array($guardName) ? $guardName : [$guardName])->exists()) {
			return true;
		}

		// Kiểm tra permission thông qua roles với guard_name
		return $this->roles()
			->whereIn('guard_name', is_array($guardName) ? $guardName : [$guardName])
			->whereHas('permissions', function($q) use ($permissionName, $guardName) {
				$q->where('name', $permissionName)->whereIn('guard_name', is_array($guardName) ? $guardName : [$guardName]);
			})
			->exists();
	}

	/**
	 * Cấp permissions trực tiếp cho user hoặc role.
	 *
	 * @param mixed ...$permissions
	 * @param bool  $force Nếu true, bỏ qua kiểm tra guard_name
	 *
	 * @throws \Exception
	 */
	public function givePermissionTo(...$permissions) {
		// Kiểm tra nếu tham số cuối là boolean $force
		$force = false;
		if (!empty($permissions) && is_bool(end($permissions))) {
			$force = array_pop($permissions);
		}

		$ids = $this->resolvePermissionIds($permissions, $force);

		if ($ids) {
			if (!$force) {
				// Validate guard_name của permissions phải khớp với model hiện tại
				$guardName          = $this->getGuardName();
				$invalidPermissions = $this->permissionModel()::query()
					->whereIn('id', $ids)
					->whereNotIn('guard_name', is_array($guardName) ? $guardName : [$guardName])
					->exists();

				if ($invalidPermissions) {
					throw new \Exception("Cannot assign permissions with different guard_name. Expected guard: {$guardName}");
				}
			}

			$this->permissions()->syncWithoutDetaching($ids);
		}

		return $this;
	}

	/**
	 * Thu hồi permissions trực tiếp từ user hoặc role.
	 */
	public function revokePermissionTo(...$permissions) {
		// Kiểm tra nếu tham số cuối là boolean $force
		$force = false;
		if (!empty($permissions) && is_bool(end($permissions))) {
			$force = array_pop($permissions);
		}

		$ids = $this->resolvePermissionIds($permissions, $force);
		if ($ids) $this->permissions()->detach($ids);
		return $this;
	}

	/**
	 * Đồng bộ permissions - thay thế tất cả permissions hiện tại của user hoặc role.
	 *
	 * @param mixed ...$permissions
	 * @param bool  $force Nếu true, bỏ qua kiểm tra guard_name
	 *
	 * @throws \Exception
	 */
	public function syncPermissions(...$permissions) {
		// Kiểm tra nếu tham số cuối là boolean $force
		$force = false;
		if (!empty($permissions) && is_bool(end($permissions))) {
			$force = array_pop($permissions);
		}

		$ids = $this->resolvePermissionIds($permissions, $force);

		if ($ids && !$force) {
			// Validate guard_name của permissions phải khớp với model hiện tại
			$guardName          = $this->getGuardName();
			$invalidPermissions = $this->permissionModel()::query()
				->whereIn('id', $ids)
				->whereNotIn('guard_name', is_array($guardName) ? $guardName : [$guardName])
				->exists();

			if ($invalidPermissions) {
				throw new \Exception("Cannot sync permissions with different guard_name. Expected guard: {$guardName}");
			}
		}

		$this->permissions()->sync($ids);
		return $this;
	}

	/**
	 * Chuyển đổi mảng permissions thành mảng ID của permissions.
	 *
	 * @param array $permissions
	 * @param bool $force Nếu true, không kiểm tra guard_name
	 *
	 * @return array
	 */
	protected function resolvePermissionIds($permissions, $force = false) {
		$flat = collect($permissions)->flatten()->filter()->all();
		if (!$flat) return [];
		$names = array_map(fn($p) => is_string($p) ? $p : ($p->name ?? null), $flat);
		$names = array_filter($names);
		if (!$names) return [];

		$query = $this->permissionModel()::query()->whereIn('name', $names);

		if (!$force) {
			$guardName = $this->getGuardName();
			$query->whereIn('guard_name', is_array($guardName) ? $guardName : [$guardName]);
		}

		return $query->pluck('id')->all();
	}

}