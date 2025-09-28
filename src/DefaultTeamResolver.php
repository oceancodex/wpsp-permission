<?php

namespace WPSPCORE\Permission;

class DefaultTeamResolver {

	protected int|string|null $teamId = null;

	public function setPermissionsTeamId($id): void {
		if ($id instanceof \Illuminate\Database\Eloquent\Model) {
			$id = $id->getKey();
		}
		$this->teamId = $id;
	}

	public function getPermissionsTeamId(): int|string|null {
		return $this->teamId;
	}

}