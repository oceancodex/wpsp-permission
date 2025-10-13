<?php

namespace WPSPCORE\Permission\Models;

class DBRolesModel {

	private $authUser;
	private $roles;

	/*
	 *
	 */

	public function __construct($roles, $authUser) {
		$this->authUser = $authUser;
		$this->roles    = array_values($roles);
	}

	/*
	 *
	 */

	public function permissions() {
		global $wpdb;
		$p = $this->authUser->funcs->_getDBCustomMigrationTablePrefix();

		return $wpdb->get_col($wpdb->prepare("
            SELECT pr.name FROM {$p}permissions pr
            JOIN {$p}role_has_permissions rp ON rp.permission_id=pr.id
            JOIN {$p}model_has_roles mr ON mr.role_id=rp.role_id
            WHERE mr.model_id=%d", $this->authUser->id()));
	}

	/*
	 *
	 */

	public function get($index) {
		return $this->roles[$index] ?? null;
	}

	public function all() {
		return $this->roles;
	}

	public function count() {
		return count($this->roles);
	}

	public function toArray() {
		return $this->roles;
	}

	public function isEmpty() {
		return empty($this->roles);
	}

	public function contains($role) {
		return in_array($role, $this->roles, true);
	}

}