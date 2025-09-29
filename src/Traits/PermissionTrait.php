<?php

namespace WPSPCORE\Permission\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait PermissionTrait {

	/**
	 * Chuyển đổi mảng roles thành mảng ID của roles.
	 *
	 * @param array $roles
	 *
	 * @return array
	 */
	protected function resolveRoleIds(array $roles): array {
		$flat = collect($roles)->flatten()->filter()->all();
		if (!$flat) return [];
		$names = array_map(fn($r) => is_string($r) ? $r : ($r->name ?? null), $flat);
		$names = array_filter($names);
		if (!$names) return [];
		return $this->roleModel::query()->whereIn('name', $names)->pluck('id')->all();
	}

	/**
	 * Chuyển đổi mảng permissions thành mảng ID của permissions.
	 *
	 * @param array $permissions
	 *
	 * @return array
	 */
	protected function resolvePermissionIds(array $permissions): array {
		$flat = collect($permissions)->flatten()->filter()->all();
		if (!$flat) return [];
		$names = array_map(fn($p) => is_string($p) ? $p : ($p->name ?? null), $flat);
		$names = array_filter($names);
		if (!$names) return [];
		return $this->permissionModel::query()->whereIn('name', $names)->pluck('id')->all();
	}

	/*
	 *
	 */


	/**
	 * Định nghĩa quan hệ Many-to-Many polymorphic giữa model hiện tại và roles.
	 *
	 * @return MorphToMany
	 */
	public function roles(): MorphToMany {
		return $this->morphToMany(
			$this->roleModel,
			'model',
			'cm_model_has_roles',
			'model_id',
			'role_id'
		)->withTimestamps();
	}

	/**
	 * Định nghĩa quan hệ Many-to-Many polymorphic giữa model hiện tại và permissions.
	 *
	 * @return MorphToMany
	 */
	public function permissions(): MorphToMany {
		return $this->morphToMany(
			$this->permissionModel,
			'model',
			'cm_model_has_permissions',
			'model_id',
			'permission_id'
		)->withTimestamps();
	}

	/*
	 *
	 */

	/**
	 * Gán roles cho user mà không xóa các roles đã có.
	 *
	 */
	public function assignRole(...$roles): self {
		$roleIds = $this->resolveRoleIds($roles);
		if ($roleIds) $this->roles()->syncWithoutDetaching($roleIds);
		return $this;
	}

	/**
	 * Loại bỏ roles khỏi user.
	 */
	public function removeRole(...$roles): self {
		$roleIds = $this->resolveRoleIds($roles);
		if ($roleIds) $this->roles()->detach($roleIds);
		return $this;
	}

	/**
	 * Đồng bộ roles - thay thế tất cả roles hiện tại bằng roles mới.
	 */
	public function syncRoles(...$roles): self {
		$roleIds = $this->resolveRoleIds($roles);
		$this->roles()->sync($roleIds);
		return $this;
	}

	/**
	 * Kiểm tra user có role nào đó không.
	 */
	public function hasRole($roles): bool {
		$names = is_array($roles) ? $roles : [$roles];
		return $this->roles()->whereIn('name', $names)->exists();
	}

	/**
	 * Cấp permissions trực tiếp cho user.
	 */
	public function givePermissionTo(...$permissions): self {
		$ids = $this->resolvePermissionIds($permissions);
		if ($ids) $this->permissions()->syncWithoutDetaching($ids);
		return $this;
	}

	/**
	 * Thu hồi permissions trực tiếp từ user.
	 */
	public function revokePermissionTo(...$permissions): self {
		$ids = $this->resolvePermissionIds($permissions);
		if ($ids) $this->permissions()->detach($ids);
		return $this;
	}

	/**
	 * Đồng bộ permissions - thay thế tất cả permissions hiện tại.
	 */
	public function syncPermissions(...$permissions): self {
		$ids = $this->resolvePermissionIds($permissions);
		$this->permissions()->sync($ids);
		return $this;
	}

	/**
	 * Kiểm tra user có permission cụ thể không.
	 */
	public function hasPermissionTo(string $permissionName): bool {
		if ($this->permissions()->where('name', $permissionName)->exists()) {
			return true;
		}
		return $this->roles()
			->whereHas('permissions', function($q) use ($permissionName) {
				$q->where('name', $permissionName);
			})
			->exists();
	}

	/**
	 * Beauty function - Kiểm tra user có permission cụ thể không.
	 */
	public function can($permission, $arguments = []): bool {
		return $this->hasPermissionTo((string)$permission);
	}

}