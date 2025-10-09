<?php

namespace WPSPCORE\Permission\Traits;

trait DBPermissionTrait {

	public function roles(): array {
		global $wpdb;
		$p = $this->funcs->_getDBCustomMigrationTablePrefix();

		// Guard của user (mặc định ['web'])
		$guardName = $this->guard_name ?? ['web'];

		// Ép thành mảng nếu không phải mảng
		if (!is_array($guardName)) {
			$guardName = [$guardName];
		}

		// Tạo placeholders cho mệnh đề IN (...)
		$placeholders = implode(',', array_fill(0, count($guardName), '%s'));

		// Viết lại SQL
		$sql = "
	        SELECT r.name
	        FROM {$p}roles r
	        JOIN {$p}model_has_roles mr ON mr.role_id = r.id
	        WHERE mr.model_id = %d
	          AND r.guard_name IN ($placeholders)
	    ";

		// Ghép các tham số vào đúng thứ tự
		$params = array_merge([$this->id()], $guardName);

		// Chuẩn bị và thực thi
		$prepared = call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $params));
		$roles = $wpdb->get_col($prepared);

		return is_array($roles) ? $roles : [];
	}

	public function permissions(): array {
		global $wpdb;
		$p = $this->funcs->_getDBCustomMigrationTablePrefix();

		// Guard của user (mặc định ['web'])
		$guardName = $this->guard_name ?? ['web'];

		// Ép về mảng nếu là chuỗi
		if (!is_array($guardName)) {
			$guardName = [$guardName];
		}

		// Tạo placeholders cho mệnh đề IN (...)
		$placeholders = implode(',', array_fill(0, count($guardName), '%s'));

		// --- 1️⃣ Quyền trực tiếp ---
		$sqlDirect = "
	        SELECT pr.name
	        FROM {$p}permissions pr
	        JOIN {$p}model_has_permissions mp ON mp.permission_id = pr.id
	        WHERE mp.model_id = %d
	          AND pr.guard_name IN ($placeholders)
	    ";

		$paramsDirect = array_merge([$this->id()], $guardName);
		$preparedDirect = call_user_func_array([$wpdb, 'prepare'], array_merge([$sqlDirect], $paramsDirect));
		$direct = $wpdb->get_col($preparedDirect);

		// --- 2️⃣ Quyền thông qua roles ---
//		$sqlVia = "
//	        SELECT DISTINCT pr.name
//	        FROM {$p}permissions pr
//	        JOIN {$p}role_has_permissions rp ON rp.permission_id = pr.id
//	        JOIN {$p}roles r ON r.id = rp.role_id
//	        JOIN {$p}model_has_roles mr ON mr.role_id = r.id
//	        WHERE mr.model_id = %d
//	          AND r.guard_name IN ($placeholders)
//	          AND pr.guard_name IN ($placeholders)
//	    ";

//		$paramsVia = array_merge([$this->id()], $guardName, $guardName);
//		$preparedVia = call_user_func_array([$wpdb, 'prepare'], array_merge([$sqlVia ?? []], $paramsVia));
//		$via = $wpdb->get_col($preparedVia);

		// --- 3️⃣ Hợp nhất và loại trùng ---
		$direct = is_array($direct) ? $direct : [];
//		$via = is_array($via) ? $via : [];

		return array_values(array_unique(array_merge($direct, $via ?? [])));
	}

	public function rolesAndPermissions(): array {
		global $wpdb;
		$p = $this->funcs->_getDBCustomMigrationTablePrefix();

		if (empty($this->roles) || !is_array($this->roles)) {
			return [];
		}

		// Guard của user (mặc định ['web'])
		$guardName = $this->guard_name ?? ['web'];

		// Ép về mảng nếu là chuỗi
		if (!is_array($guardName)) {
			$guardName = [$guardName];
		}

		// Tạo placeholders cho roles
		$roleCount = count($this->roles);
		if ($roleCount === 0) {
			return [];
		}
		$rolePlaceholders = implode(',', array_fill(0, $roleCount, '%s'));

		// Tạo placeholders cho guard_name
		$guardPlaceholders = implode(',', array_fill(0, count($guardName), '%s'));

		// Câu SQL
		$sql = "
			SELECT r.name AS role_name, pr.name AS permission_name
			FROM {$p}permissions pr
			JOIN {$p}role_has_permissions rp ON rp.permission_id = pr.id
			JOIN {$p}roles r ON r.id = rp.role_id
			JOIN {$p}model_has_roles mr ON mr.role_id = r.id
			WHERE mr.model_id = %d
			  AND r.name IN ($rolePlaceholders)
			  AND r.guard_name IN ($guardPlaceholders)
			  AND pr.guard_name IN ($guardPlaceholders)
			ORDER BY r.name, pr.name
		";

		// Tham số theo đúng thứ tự placeholder
		$params = array_merge(
			[$this->id()],
			array_values($this->roles),
			$guardName, // cho r.guard_name IN (...)
			$guardName  // cho pr.guard_name IN (...)
		);

		// Chuẩn bị và thực thi
		$prepared = call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $params));
		$results = $wpdb->get_results($prepared);

		// Nhóm kết quả theo role
		$permissions = [];

		// Khởi tạo array cho tất cả roles (kể cả không có permission)
		foreach ($this->roles as $roleName) {
			$permissions[$roleName] = [];
		}

		// Gán permissions vào từng role
		if (is_array($results)) {
			foreach ($results as $row) {
				$permissions[$row->role_name][] = $row->permission_name;
			}
		}

		return $permissions;
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
	public function assignRole($roles): void {
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

	/**
	 * Kiểm tra user có role nào đó không.
	 */
	public function hasRole(string $role): bool {
		global $wpdb;
		$p = $this->funcs->_getDBCustomMigrationTablePrefix();

		// Lấy guard_name từ thuộc tính hoặc mặc định là ['web']
		$guardName = $this->guard_name ?? ['web'];

		// Nếu là chuỗi thì ép về mảng
		if (!is_array($guardName)) {
			$guardName = [$guardName];
		}

		// Tạo placeholders tương ứng với số lượng guard_name
		$placeholders = implode(',', array_fill(0, count($guardName), '%s'));

		// Chuẩn bị SQL
		$sql = $wpdb->prepare("
		    SELECT 1 FROM {$p}roles r
		    WHERE r.name = %s
		      AND r.guard_name IN ($placeholders)
		      AND EXISTS (
		          SELECT 1
		          FROM {$p}model_has_roles mr
		          WHERE mr.model_id = %d
		            AND mr.role_id = r.id
		      )
		    LIMIT 1
		", array_merge([$role], $guardName, [$this->id()]));

		// Trả về true nếu tồn tại ít nhất 1 bản ghi
		return (bool) $wpdb->get_var($sql);
	}

	/**
	 * Cấp permissions trực tiếp cho user hoặc role.
	 *
	 * @param mixed ...$permissions
	 * @param bool $force Nếu true, bỏ qua kiểm tra guard_name
	 * @throws \Exception
	 */
	public function givePermissionTo($perms): void {
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

	/*
	 *
	 */

	/**
	 * Beauty function - Kiểm tra user có permission cụ thể không.
	 */
	public function can(string $perm): bool {
		global $wpdb;
		$p   = $this->funcs->_getDBCustomMigrationTablePrefix();
		$uid = $this->id();

		// Lấy guard_name từ thuộc tính hoặc mặc định là ['web']
		$guardName = $this->guard_name ?? ['web'];

		// Ép về mảng nếu là chuỗi
		if (!is_array($guardName)) {
			$guardName = [$guardName];
		}

		// Tạo placeholders cho IN (...)
		$placeholders = implode(',', array_fill(0, count($guardName), '%s'));

		// Chuẩn bị SQL
		$sql = $wpdb->prepare("
		    SELECT 1
		    FROM {$p}permissions pr
		    WHERE pr.name = %s
		      AND pr.guard_name IN ($placeholders)
		      AND (
		          EXISTS (
		              SELECT 1
		              FROM {$p}model_has_permissions mp
		              WHERE mp.model_id = %d
		                AND mp.permission_id = pr.id
		          )
		          OR EXISTS (
		              SELECT 1
		              FROM {$p}model_has_roles mr
		              JOIN {$p}roles r ON r.id = mr.role_id
		              JOIN {$p}role_has_permissions rp ON rp.role_id = mr.role_id
		              WHERE mr.model_id = %d
		                AND rp.permission_id = pr.id
		                AND r.guard_name IN ($placeholders)
		          )
		      )
		    LIMIT 1
		", array_merge([$perm], $guardName, [$uid, $uid], $guardName));

		return (bool) $wpdb->get_var($sql);
	}

}