<?php

namespace WPSPCORE\Permission\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait PermissionTrait {

	protected function resolveRoleIds(array $roles): array {
		$flat = collect($roles)->flatten()->filter()->all();
		if (!$flat) return [];
		$names = array_map(fn($r) => is_string($r) ? $r : ($r->name ?? null), $flat);
		$names = array_filter($names);
		if (!$names) return [];
		return $this->roleModel::query()->whereIn('name', $names)->pluck('id')->all();
	}

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

	public function roles(): MorphToMany {
		return $this->morphToMany(
			$this->roleModel,
			'model',
			'cm_model_has_roles',
			'model_id',
			'role_id'
		)->withTimestamps();
	}

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

	public function assignRole(...$roles): self {
		$roleIds = $this->resolveRoleIds($roles);
		if ($roleIds) $this->roles()->syncWithoutDetaching($roleIds);
		return $this;
	}

	public function removeRole(...$roles): self {
		$roleIds = $this->resolveRoleIds($roles);
		if ($roleIds) $this->roles()->detach($roleIds);
		return $this;
	}

	public function syncRoles(...$roles): self {
		$roleIds = $this->resolveRoleIds($roles);
		$this->roles()->sync($roleIds);
		return $this;
	}

	public function hasRole($roles): bool {
		$names = is_array($roles) ? $roles : [$roles];
		return $this->roles()->whereIn('name', $names)->exists();
	}

	public function givePermissionTo(...$permissions): self {
		$ids = $this->resolvePermissionIds($permissions);
		if ($ids) $this->permissions()->syncWithoutDetaching($ids);
		return $this;
	}

	public function revokePermissionTo(...$permissions): self {
		$ids = $this->resolvePermissionIds($permissions);
		if ($ids) $this->permissions()->detach($ids);
		return $this;
	}

	public function syncPermissions(...$permissions): self {
		$ids = $this->resolvePermissionIds($permissions);
		$this->permissions()->sync($ids);
		return $this;
	}

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

	public function can($permission, $arguments = []): bool {
		return $this->hasPermissionTo((string)$permission);
	}

}