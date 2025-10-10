<?php

namespace WPSPCORE\Permission\Contracts;

interface RoleContract {

	public function getName(): string;

	public function getGuardName(): ?string;

}