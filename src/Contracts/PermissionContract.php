<?php

namespace WPSPCORE\Permission\Contracts;

interface PermissionContract {
	public function getName(): string;
	public function getGuardName(): ?string;
}