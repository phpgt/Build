<?php
namespace GT\Build\Configuration;

class RequireBlockItem {
	public string $command;
	public string $version;

	public function __construct(string $command, string $version) {
		$this->command = $command;
		$this->version = $version;
	}
}
