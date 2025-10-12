<?php

namespace WPSPCORE\Permission\Models;

class DBRolesModel {

	private $user;
	private $roles;

	public function __construct($roles, $user) {
		$this->user  = $user;
		$this->roles = array_values($roles);
	}

	public function getPermissions() {
		global $wpdb;
		$p = $this->user->funcs->_getDBCustomMigrationTablePrefix();

		return $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT pr.name FROM {$p}permissions pr
            JOIN {$p}role_has_permissions rp ON rp.permission_id=pr.id
            JOIN {$p}model_has_roles mr ON mr.role_id=rp.role_id
            WHERE mr.model_id=%d", $this->user->id()));
	}

	public function getRolesAndPermissions() {
		global $wpdb;
		$p = $this->user->funcs->_getDBCustomMigrationTablePrefix();

		if (empty($this->roles)) {
			return [];
		}

		// Tạo placeholders cho roles
		$placeholders = implode(',', array_fill(0, count($this->roles), '%s'));

		// Query để lấy permissions nhóm theo role
		$results = $wpdb->get_results($wpdb->prepare("
			SELECT r.name as role_name, pr.name as permission_name
			FROM {$p}permissions pr
			JOIN {$p}role_has_permissions rp ON rp.permission_id=pr.id
			JOIN {$p}roles r ON r.id=rp.role_id
			JOIN {$p}model_has_roles mr ON mr.role_id=r.id
			WHERE mr.model_id=%d AND r.name IN ($placeholders)
			ORDER BY r.name, pr.name
		", $this->user->id(), ...$this->roles));

		// Nhóm kết quả theo role
		$permissions = [];

		// Khởi tạo array cho tất cả roles (kể cả không có permission)
		foreach ($this->roles as $roleName) {
			$permissions[$roleName] = [];
		}

		// Gán permissions vào từng role
		foreach ($results as $row) {
			$permissions[$row->role_name][] = $row->permission_name;
		}

		return $permissions;
	}

	public function toArray() {
		return $this->roles;
	}

	public function get($index) {
		return $this->roles[$index] ?? null;
	}

	public function all() {
		return $this->roles;
	}

	public function count() {
		return count($this->roles);
	}

	public function isEmpty() {
		return empty($this->roles);
	}

	public function contains($role) {
		return in_array($role, $this->roles, true);
	}

}