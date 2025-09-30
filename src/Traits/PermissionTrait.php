<?php

namespace WPSPCORE\Permission\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait PermissionTrait {

	/**
	 * Lấy guard_name của model hiện tại.
	 *
	 * @return string
	 */
	protected function getGuardName(): string {
		return $this->guard_name ?? 'web';
	}

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

		$guardName = $this->getGuardName();
		return $this->roleModel::query()
			->whereIn('name', $names)
			->where('guard_name', $guardName)
			->pluck('id')
			->all();
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

		$guardName = $this->getGuardName();
		return $this->permissionModel::query()
			->whereIn('name', $names)
			->where('guard_name', $guardName)
			->pluck('id')
			->all();
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
		$guardName = $this->getGuardName();

		return $this->morphToMany(
			$this->roleModel,
			'model',
			'cm_model_has_roles',
			'model_id',
			'role_id'
		)->where('cm_roles.guard_name', $guardName)
			->withTimestamps();
	}

	/**
	 * Định nghĩa quan hệ Many-to-Many polymorphic giữa model hiện tại và permissions.
	 *
	 * @return MorphToMany
	 */
	public function permissions(): MorphToMany {
		$guardName = $this->getGuardName();

		return $this->morphToMany(
			$this->permissionModel,
			'model',
			'cm_model_has_permissions',
			'model_id',
			'permission_id'
		)->where('cm_permissions.guard_name', $guardName)
			->withTimestamps();
	}

	/*
	 *
	 */

	/**
	 * Gán roles cho user mà không xóa các roles đã có.
	 * Validate guard_name trước khi gán.
	 * @throws \Exception
	 */
	public function assignRole(...$roles): self {
		$roleIds = $this->resolveRoleIds($roles);

		if ($roleIds) {
			// Validate guard_name của roles phải khớp với user
			$guardName = $this->getGuardName();
			$invalidRoles = $this->roleModel::query()
				->whereIn('id', $roleIds)
				->where('guard_name', '!=', $guardName)
				->exists();

			if ($invalidRoles) {
				throw new \Exception("Cannot assign roles with different guard_name. Expected guard: {$guardName}");
			}

			$this->roles()->syncWithoutDetaching($roleIds);
		}

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
	 * Validate guard_name trước khi sync.
	 * @throws \Exception
	 */
	public function syncRoles(...$roles): self {
		$roleIds = $this->resolveRoleIds($roles);

		// Validate guard_name của roles phải khớp với user
		if ($roleIds) {
			$guardName = $this->getGuardName();
			$invalidRoles = $this->roleModel::query()
				->whereIn('id', $roleIds)
				->where('guard_name', '!=', $guardName)
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
	public function hasRole($roles): bool {
		$names = is_array($roles) ? $roles : [$roles];
		$guardName = $this->getGuardName();
		return $this->roles()
			->whereIn('name', $names)
			->where('guard_name', $guardName)
			->exists();
	}

	/**
	 * Cấp permissions trực tiếp cho user hoặc role.
	 * Validate guard_name trước khi gán.
	 * @throws \Exception
	 */
	public function givePermissionTo(...$permissions): self {
		$ids = $this->resolvePermissionIds($permissions);

		if ($ids) {
			// Validate guard_name của permissions phải khớp với model hiện tại
			$guardName = $this->getGuardName();
			$invalidPermissions = $this->permissionModel::query()
				->whereIn('id', $ids)
				->where('guard_name', '!=', $guardName)
				->exists();

			if ($invalidPermissions) {
				throw new \Exception("Cannot assign permissions with different guard_name. Expected guard: {$guardName}");
			}

			$this->permissions()->syncWithoutDetaching($ids);
		}

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
	 * Validate guard_name trước khi sync.
	 * @throws \Exception
	 */
	public function syncPermissions(...$permissions): self {
		$ids = $this->resolvePermissionIds($permissions);

		// Validate guard_name của permissions phải khớp với model hiện tại
		if ($ids) {
			$guardName = $this->getGuardName();
			$invalidPermissions = $this->permissionModel::query()
				->whereIn('id', $ids)
				->where('guard_name', '!=', $guardName)
				->exists();

			if ($invalidPermissions) {
				throw new \Exception("Cannot sync permissions with different guard_name. Expected guard: {$guardName}");
			}
		}

		$this->permissions()->sync($ids);
		return $this;
	}

	/**
	 * Kiểm tra user có permission cụ thể không.
	 */
	public function hasPermissionTo(string $permissionName, ?string $guardName = null): bool {
		// Lấy guard_name từ model hiện tại nếu không được truyền vào
		if ($guardName === null) {
			$guardName = $this->guard_name ?? 'web';
		}

		// Kiểm tra permission trực tiếp với guard_name
		if ($this->permissions()->where('name', $permissionName)->where('guard_name', $guardName)->exists()) {
			return true;
		}

		// Kiểm tra permission thông qua roles với guard_name
		return $this->roles()
			->where('guard_name', $guardName)
			->whereHas('permissions', function($q) use ($permissionName, $guardName) {
				$q->where('name', $permissionName)->where('guard_name', $guardName);
			})
			->exists();
	}

	/**
	 * Beauty function - Kiểm tra user có permission cụ thể không.
	 */
	public function can($permission, $arguments = []): bool {
		// Lấy guard_name từ model hiện tại
		$guardName = $this->guard_name ?? 'web';
		return $this->hasPermissionTo((string)$permission, $guardName);
	}

}