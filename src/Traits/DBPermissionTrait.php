<?php

namespace WPSPCORE\Permission\Traits;

trait DBPermissionTrait {

	public function can(string $perm): bool {
		global $wpdb;
		$p   = $this->funcs->_getDBCustomMigrationTablePrefix();
		$uid = $this->id();

		// Lấy guard_name từ thuộc tính hoặc mặc định là 'web'
		$guardName = $this->guard_name ?? 'web';

		$sql = $wpdb->prepare("
            SELECT 1 FROM {$p}permissions pr
            WHERE pr.name=%s AND pr.guard_name=%s AND (
                EXISTS (SELECT 1 FROM {$p}model_has_permissions mp WHERE mp.model_id=%d AND mp.permission_id=pr.id)
                OR EXISTS (SELECT 1 FROM {$p}model_has_roles mr
                           JOIN {$p}roles r ON r.id=mr.role_id
                           JOIN {$p}role_has_permissions rp ON rp.role_id=mr.role_id
                           WHERE mr.model_id=%d AND rp.permission_id=pr.id AND r.guard_name=%s)
            ) LIMIT 1", $perm, $guardName, $uid, $uid, $guardName);
		return (bool)$wpdb->get_var($sql);
	}

	public function hasRole(string $role): bool {
		global $wpdb;
		$p = $this->funcs->_getDBCustomMigrationTablePrefix();

		// Lấy guard_name từ thuộc tính hoặc mặc định là 'web'
		$guardName = $this->guard_name ?? 'web';

		$sql = $wpdb->prepare("
            SELECT 1 FROM {$p}roles r
            WHERE r.name=%s AND r.guard_name=%s AND EXISTS (
                SELECT 1 FROM {$p}model_has_roles mr WHERE mr.model_id=%d AND mr.role_id=r.id
            ) LIMIT 1", $role, $guardName, $this->id());
		return (bool)$wpdb->get_var($sql);
	}

	public function roles(): array {
		global $wpdb;
		$p = $this->funcs->_getDBCustomMigrationTablePrefix();

		// Guard của user (mặc định 'web')
		$guardName = isset($this->guard_name) && $this->guard_name ? $this->guard_name : 'web';

		$sql = "
            SELECT r.name
            FROM {$p}roles r
            JOIN {$p}model_has_roles mr ON mr.role_id = r.id
            WHERE mr.model_id = %d
              AND r.guard_name = %s
        ";

		$params = array($this->id(), $guardName);
		$prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params));

		$roles = $wpdb->get_col($prepared);
		return is_array($roles) ? $roles : array();
	}

	public function permissions(): array {
		global $wpdb;
		$p = $this->funcs->_getDBCustomMigrationTablePrefix();

		// Guard của user (mặc định 'web')
		$guardName = isset($this->guard_name) && $this->guard_name ? $this->guard_name : 'web';

		// SQL: quyền trực tiếp + khớp guard_name
		$sql = "
            SELECT DISTINCT pr.name
            FROM {$p}permissions pr
            JOIN {$p}model_has_permissions mp ON mp.permission_id=pr.id
            WHERE mp.model_id=%d
              AND pr.guard_name=%s
        ";

		$params = array($this->id(), $guardName);

		$prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params));
		$direct = $wpdb->get_col($prepared);

		// SQL: quyền qua roles + khớp guard_name cho cả role và permission
//		$sqlVia = "
//            SELECT DISTINCT pr.name
//            FROM {$p}permissions pr
//            JOIN {$p}role_has_permissions rp ON rp.permission_id=pr.id
//            JOIN {$p}roles r ON r.id = rp.role_id
//            JOIN {$p}model_has_roles mr ON mr.role_id = r.id
//            WHERE mr.model_id=%d
//              AND r.guard_name=%s
//              AND pr.guard_name=%s
//        ";
//
//		$paramsVia = array($this->id(), $guardName, $guardName);
//		$preparedVia = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sqlVia), $paramsVia));
//		$via = $wpdb->get_col($preparedVia);

		$direct = is_array($direct) ? $direct : array();
		$via    = (isset($via) && is_array($via)) ? $via : array();

		return array_values(array_unique(array_merge($direct, $via)));
	}

	public function rolesAndPermissions(): array {
		global $wpdb;
		$p = $this->funcs->_getDBCustomMigrationTablePrefix();

		if (empty($this->roles) || !is_array($this->roles)) {
			return [];
		}

		// Guard của user (mặc định 'web')
		$guardName = isset($this->guard_name) && $this->guard_name ? $this->guard_name : 'web';

		// Tạo placeholders cho roles
		$roleCount = count($this->roles);
		$placeholders = implode(',', array_fill(0, $roleCount, '%s'));

		// Câu SQL
		$sql = "
			SELECT r.name as role_name, pr.name as permission_name
			FROM {$p}permissions pr
			JOIN {$p}role_has_permissions rp ON rp.permission_id=pr.id
			JOIN {$p}roles r ON r.id=rp.role_id
			JOIN {$p}model_has_roles mr ON mr.role_id=r.id
			WHERE mr.model_id=%d
			  AND r.name IN ($placeholders)
			  AND r.guard_name=%s
			  AND pr.guard_name=%s
			ORDER BY r.name, pr.name
		";

		// Tham số theo đúng thứ tự placeholder
		$params = array_merge(
			array($this->id()),
			array_values($this->roles),
			array($guardName, $guardName)
		);

		// Chuẩn bị và thực thi
		$prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params));
		$results = $wpdb->get_results($prepared);

		// Nhóm kết quả theo role
		$permissions = array();

		// Khởi tạo array cho tất cả roles (kể cả không có permission)
		foreach ($this->roles as $roleName) {
			$permissions[$roleName] = array();
		}

		// Gán permissions vào từng role
		if (is_array($results)) {
			foreach ($results as $row) {
				$permissions[$row->role_name][] = $row->permission_name;
			}
		}

		return $permissions;
	}

	public function assignRole(string|array $roles): void {
		global $wpdb;
		$p      = $this->funcs->_getDBCustomMigrationTablePrefix();
		$userId = $this->id();

		$roleNames = is_array($roles) ? $roles : [$roles];
		$roleNames = array_values(array_filter(array_map('trim', $roleNames)));

		if (!$roleNames) return;

		// Lấy id của các role theo name
		$placeholders = implode(',', array_fill(0, count($roleNames), '%s'));
		$sqlRoles     = $wpdb->prepare("SELECT id, name FROM {$p}roles WHERE name IN ($placeholders)", ...$roleNames);
		$rows         = $wpdb->get_results($sqlRoles, ARRAY_A);

		if (!$rows) return;

		$roleIds = array_unique(array_map(static fn($r) => (int)$r['id'], $rows));

		// Chèn nếu chưa tồn tại
		foreach ($roleIds as $rid) {
			$exists = (int)$wpdb->get_var($wpdb->prepare("
				SELECT 1 FROM {$p}model_has_roles WHERE model_id=%d AND role_id=%d LIMIT 1
			", $userId, $rid));
			if (!$exists) {
				$wpdb->query($wpdb->prepare("
					INSERT INTO {$p}model_has_roles (model_id, role_id) VALUES (%d, %d)
				", $userId, $rid));
			}
		}
	}

	public function givePermissionTo(string|array $perms): void {
		global $wpdb;
		$p      = $this->funcs->_getDBCustomMigrationTablePrefix();
		$userId = $this->id();

		$permNames = is_array($perms) ? $perms : [$perms];
		$permNames = array_values(array_filter(array_map('trim', $permNames)));

		if (!$permNames) return;

		// Lấy id của các permission theo name
		$placeholders = implode(',', array_fill(0, count($permNames), '%s'));
		$sqlPerms     = $wpdb->prepare("SELECT id, name FROM {$p}permissions WHERE name IN ($placeholders)", ...$permNames);
		$rows         = $wpdb->get_results($sqlPerms, ARRAY_A);

		if (!$rows) return;

		$permIds = array_unique(array_map(static fn($r) => (int)$r['id'], $rows));

		// Chèn nếu chưa tồn tại
		foreach ($permIds as $pid) {
			$exists = (int)$wpdb->get_var($wpdb->prepare("
				SELECT 1 FROM {$p}model_has_permissions WHERE model_id=%d AND permission_id=%d LIMIT 1
			", $userId, $pid));
			if (!$exists) {
				$wpdb->query($wpdb->prepare("
					INSERT INTO {$p}model_has_permissions (model_id, permission_id) VALUES (%d, %d)
				", $userId, $pid));
			}
		}
	}

}