<?php
namespace GT\Build\Configuration;

class ExecuteBlock {
	public string $command;
	/** @var array<string> */
	public array $arguments;

	public function __construct(object $details) {
		if(empty($details->command)) {
			throw new MissingConfigurationKeyException("execute.command");
		}
		if(empty($details->arguments)) {
			throw new MissingConfigurationKeyException("execute.arguments");
		}

		$this->command = $details->command;
		$this->arguments = $details->arguments;
	}
}
