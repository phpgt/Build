<?php
namespace Gt\Build\Cli;

use Gt\Build\BuildRunner;
use Gt\Cli\Argument\ArgumentValueList;
use Gt\Cli\Command\Command;
use Gt\Cli\Parameter\NamedParameter;
use Gt\Cli\Parameter\Parameter;

class RunCommand extends Command {
	public function run(?ArgumentValueList $arguments = null):int {
		$buildRunner = new BuildRunner(getcwd(), $this->stream);
		if($arguments->contains("default")) {
			$buildRunner->setDefaultPath($arguments->get("default"));
		}
		$mode = null;
		if($arguments->contains("mode")) {
			$mode = (string)$arguments->get("mode");
		}
		$buildRunner->run(
			$arguments->contains("watch"),
			$mode,
		);

		return 0;
	}

	public function getName():string {
		return "run";
	}

	public function getDescription():string {
		return "Compile client-side assets";
	}

	/** @return  NamedParameter[] */
	public function getRequiredNamedParameterList():array {
		return [];
	}

	/** @return  NamedParameter[] */
	public function getOptionalNamedParameterList():array {
		return [];
	}

	/** @return  Parameter[] */
	public function getRequiredParameterList():array {
		return [];
	}

	/** @return  Parameter[] */
	public function getOptionalParameterList():array {
		return [
			new Parameter(
				false,
				"watch",
				"w",
				"Pass this flag to continue the build runner after first build. Any changes to the filesystem will be rebuilt automatically."
			),
			new Parameter(
				true,
				"config",
				"c",
				"Path to the build.json. This defaults to the project root directory."
			),
			new Parameter(
				true,
				"default",
				"d",
				"Path to a default build.json to use if there is no build.json in the project root."
			),
			new Parameter(
				true,
				"mode",
				"m",
				"Which version of build.json to build (e.g. production/development)."
			),
		];
	}
}
