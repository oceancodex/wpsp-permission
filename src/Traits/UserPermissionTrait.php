<?php

namespace WPSPCORE\Permission\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait UserPermissionTrait {

	/**
	 * Lấy guard_name của model hiện tại.
	 *
	 * @return string|array|null
	 */
	protected function getGuardName() {
		return $this->guard_name ?? ['web'];
	}

	/*
	 *
	 */

	protected function roleModel() {
		return $this->funcs->_config('permission.models.role');
	}

	protected function permissionModel() {
		return $this->funcs->_config('permission.models.permission');
	}

	/*
	 *
	 */

	/**
	 * Chuyển đổi mảng roles thành mảng ID của roles.
	 *
	 * @param array $roles
	 * @param bool $force Nếu true, không kiểm tra guard_name
	 *
	 * @return array
	 */
	protected function resolveRoleIds($roles, $force = false) {
		$flat = collect($roles)->flatten()->filter()->all();
		if (!$flat) return [];
		$names = array_map(fn($r) => is_string($r) ? $r : ($r->name ?? null), $flat);
		$names = array_filter($names);
		if (!$names) return [];

		$query = $this->roleModel()::query()->whereIn('name', $names);

		if (!$force) {
			$guardName = $this->getGuardName();
			$query->whereIn('guard_name', is_array($guardName) ? $guardName : [$guardName]);
		}

		return $query->pluck('id')->all();
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

	/*
	 *
	 */


	/**
	 * Định nghĩa quan hệ Many-to-Many polymorphic giữa model hiện tại và roles.
	 *
	 * @return MorphToMany
	 */
	public function roles() {
		$guardName = $this->getGuardName();

		return $this->morphToMany(
			$this->roleModel(),
			'model',
			'cm_model_has_roles',
			'model_id',
			'role_id'
		)->whereIn('cm_roles.guard_name', is_array($guardName) ? $guardName : [$guardName])
			->withTimestamps();
	}

	/**
	 * Định nghĩa quan hệ Many-to-Many polymorphic giữa model hiện tại và permissions.
	 *
	 * @return MorphToMany
	 */
	public function permissions() {
		$guardName = $this->getGuardName();

		return $this->morphToMany(
			$this->permissionModel(),
			'model',
			'cm_model_has_permissions',
			'model_id',
			'permission_id'
		)->whereIn('cm_permissions.guard_name', is_array($guardName) ? $guardName : [$guardName])
			->withTimestamps();
	}

	/*
	 *
	 */

	/**
	 * Gán roles cho user mà không xóa các roles đã có.
	 *
	 * @param mixed ...$roles
	 * @param bool $force Nếu true, bỏ qua kiểm tra guard_name
	 * @throws \Exception
	 */
	public function assignRole(...$roles) {
		// Kiểm tra nếu tham số cuối là boolean $force
		$force = false;
		if (!empty($roles) && is_bool(end($roles))) {
			$force = array_pop($roles);
		}

		$roleIds = $this->resolveRoleIds($roles, $force);

		if ($roleIds) {
			if (!$force) {
				// Validate guard_name của roles phải khớp với user
				$guardName = $this->getGuardName();
				$invalidRoles = $this->roleModel()::query()
					->whereIn('id', $roleIds)
					->whereNotIn('guard_name', is_array($guardName) ? $guardName : [$guardName])
					->exists();

				if ($invalidRoles) {
					throw new \Exception("Cannot assign roles with different guard_name. Expected guard: {$guardName}");
				}
			}

			$this->roles()->syncWithoutDetaching($roleIds);
		}

		return $this;
	}

	/**
	 * Loại bỏ roles khỏi user.
	 */
	public function removeRole(...$roles) {
		// Kiểm tra nếu tham số cuối là boolean $force
		$force = false;
		if (!empty($roles) && is_bool(end($roles))) {
			$force = array_pop($roles);
		}

		$roleIds = $this->resolveRoleIds($roles, $force);
		if ($roleIds) $this->roles()->detach($roleIds);
		return $this;
	}

	/**
	 * Đồng bộ roles - thay thế tất cả roles hiện tại bằng roles mới.
	 *
	 * @param mixed ...$roles
	 * @param bool $force Nếu true, bỏ qua kiểm tra guard_name
	 * @throws \Exception
	 */
	public function syncRoles(...$roles) {
		// Kiểm tra nếu tham số cuối là boolean $force
		$force = false;
		if (!empty($roles) && is_bool(end($roles))) {
			$force = array_pop($roles);
		}

		$roleIds = $this->resolveRoleIds($roles, $force);

		if ($roleIds && !$force) {
			// Validate guard_name của roles phải khớp với user
			$guardName = $this->getGuardName();
			$invalidRoles = $this->roleModel()::query()
				->whereIn('id', $roleIds)
				->whereNotIn('guard_name', is_array($guardName) ? $guardName : [$guardName])
				->exists();

			if ($invalidRoles) {
				throw new \Exception("Cannot sync roles with different guard_name. Expected guard: {$guardName}");
			}
		}

		$this->roles()->sync($roleIds);
		return $this;
	}

	/**
	 * Kiểm tra user có role nào đó không.
	 */
	public function hasRole($roles) {
		$names = is_array($roles) ? $roles : [$roles];
		$guardName = $this->getGuardName();
		return $this->roles()
			->whereIn('name', $names)
			->whereIn('guard_name', is_array($guardName) ? $guardName : [$guardName])
			->exists();
	}

	/*
	 *
	 */

	/**
	 * Beauty function - Kiểm tra user có permission cụ thể không.
	 */
	public function can($permission, $arguments = []) {
		// Lấy guard_name từ model hiện tại
		$guardName = $this->guard_name ?? ['web'];
		return $this->hasPermissionTo($permission, $guardName);
	}

}