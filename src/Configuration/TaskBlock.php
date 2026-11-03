<?php
namespace GT\Build\Configuration;

use GT\Build\ConfigurationParseException;

/**
 * Represents a single path pattern block in the build.json and specified
 * the properties that are allowed/expected.
 */
class TaskBlock {
	/** @var string|null Name property if set; defaults to execution command */
	protected $name;
	/** @var string */
	protected $glob;
	/** @var RequireBlock|null */
	protected $require;
	/** @var ExecuteBlock */
	protected $execute;
	protected bool $runOnce = false;

	public function __construct(string $glob, object $details) {
		$this->glob = $glob;
		if(property_exists($details, "options")) {
			if(!is_object($details->options)) {
				throw new ConfigurationParseException("options must be an object");
			}
			if(property_exists($details->options, "run-once")) {
				$value = $details->options->{"run-once"};
				if(!is_bool($value)) {
					throw new ConfigurationParseException("options.run-once must be a boolean");
				}
				$this->runOnce = $value;
			}
		}

		if(isset($details->require)) {
			$this->require = new RequireBlock($details->require);

		}
		else {
			$this->require = null;
		}

		if(is_null($details->execute)) {
			throw new MissingConfigurationKeyException("execute");
		}
		$this->execute = new ExecuteBlock($details->execute);
		$this->name = $details->name ?? $this->execute->command;
	}

	public function isRunOnce():bool {
		return $this->runOnce;
	}

	public function getName():string {
		return $this->name;
	}

	public function getGlob():string {
		return $this->glob;
	}

	public function getRequireBlock():?RequireBlock {
		return $this->require;
	}

	public function getExecuteBlock():?ExecuteBlock {
		return $this->execute;
	}
}
