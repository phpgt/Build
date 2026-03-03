<?php
namespace Gt\Build;

use Composer\Semver\Semver;

class Requirement {
	const VERSION_ARGUMENTS = [
		"*" => "--version",
	];

	const VERSION_REGEXES = [
		"*" => "/([\d\.]+)/",
	];

	protected string $name;
	protected string $version;

	public function __construct(string $name, string $version) {
		$this->name = $name;
		$this->version = $version;
	}

	public function __toString():string {
		return implode(" ", [
			$this->name,
			$this->version,
		]);
	}

	/** @param array<int, string>|null $errors */
	public function check(?array &$errors = null):bool {
		$arg = $this->getArgumentToCheckVersionOfCommand();
		$versionCommand = implode(" ", [
			$this->name,
			$arg,
		]);

		$descriptor = [
			0 => ["pipe", "r"],
			1 => ["pipe", "w"],
			2 => ["pipe", "w"],
		];
		$proc = proc_open($versionCommand, $descriptor, $pipes);

		do {
			$status = proc_get_status($proc);
		}
		while($status["running"]);
		$return = $status["exitcode"];
		$output = "";

		$output .= stream_get_contents($pipes[1]);
		$output .= stream_get_contents($pipes[2]);
		proc_close($proc);

		if($return !== 0) {
			if(is_null($errors)) {
				throw new RequirementMissingException($this->name);
			}
			else {
				$errors []= "Requirement missing: " . $this->name;
			}
		}

		$versionInstalled = $this->getVersionFromVersionString($output);
		return $this->isVersionStringValid($versionInstalled);
	}

	protected function getArgumentToCheckVersionOfCommand():string {
		if(array_key_exists($this->name, self::VERSION_ARGUMENTS)) {
			return self::VERSION_ARGUMENTS[$this->name];
		}

		return self::VERSION_ARGUMENTS["*"];
	}

	protected function getVersionFromVersionString(string $string):string {
		if(array_key_exists($this->name, self::VERSION_REGEXES)) {
			$pattern = self::VERSION_REGEXES[$this->name];
		}
		else {
			$pattern = self::VERSION_REGEXES["*"];
		}

		preg_match($pattern, $string, $matches);
		return $matches[1] ?? "0.0.0";
	}

	/** @SuppressWarnings(PHPMD.StaticAccess) */
	protected function isVersionStringValid(string $versionInstalled):bool {
		if($this->version === "*") {
			return true;
		}

		return Semver::satisfies($versionInstalled, $this->version);
	}
}
