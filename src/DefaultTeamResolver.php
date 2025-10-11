<?php

namespace WPSPCORE\Permission;

class DefaultTeamResolver {

	protected $teamId = null;

	public function setPermissionsTeamId($id) {
		if ($id instanceof \Illuminate\Database\Eloquent\Model) {
			$id = $id->getKey();
		}
		$this->teamId = $id;
	}

	public function getPermissionsTeamId() {
		return $this->teamId;
	}

}