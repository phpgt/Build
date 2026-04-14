<?php
namespace GT\Build;

use Gt\Cli\Stream;

/** Responsible for running all build tasks and optionally watching for changes */
class BuildRunner {
	protected string $defaultPath;
	protected string $workingDirectory;
	protected Stream $stream;

	public function __construct(?string $path = null, ?Stream $stream = null) {
		if(is_null($path)) {
			$path = getcwd();
		}
		if(is_null($stream)) {
			$stream = new Stream(
				"php://stdin",
				"php://stdout",
				"php://stderr"
			);
		}
		$this->defaultPath = implode(DIRECTORY_SEPARATOR, [
			getcwd(),
			"build.ini",
		]);
		$this->workingDirectory = $path;
		$this->stream = $stream;
	}

	/** @SuppressWarnings(PHPMD.ExitExpression) */
	public function run(
		bool $continue = true,
		?string $mode = null
	):void {
		$workingDirectory = $this->formatWorkingDirectory();
		$jsonPath = $this->getJsonPath($workingDirectory);

		$startTime = microtime(true);

// Check that the developer has all the necessary requirements.
// $errors will be passed by reference to Build::check. Passing an array by
// reference will suppress exceptions, instead filling the array with error
// strings for output back to the terminal.
		$errors = [];
		$build = $this->checkRequirements($jsonPath, $workingDirectory, $errors, $mode);

		if(!empty($errors)) {
			$this->showErrors($errors);
			return;
		}

		$this->build($build, $continue);
		$this->logElapsedTime($startTime);
	}

	public function setDefaultPath(string $path):void {
		$this->defaultPath = $path;
	}

	protected function formatWorkingDirectory():string {
		if(is_file($this->workingDirectory)) {
			$this->workingDirectory = dirname($this->workingDirectory);
		}

		return rtrim($this->workingDirectory, "/\\");
	}

	/** @SuppressWarnings(PHPMD.ExitExpression) */
	protected function getJsonPath(string $workingDirectory):string {
		$jsonPath = $this->resolveConfigPath($workingDirectory)
			?? $this->resolveConfigPath($this->defaultPath);

		if(is_null($jsonPath)) {
			$whichPath = $this->defaultPath === $workingDirectory
				? "user"
				: "default";

			$errorString = "No build config found. Trying $whichPath path: $this->defaultPath";
			$this->stream->writeLine(
				$errorString,
				Stream::ERROR
			);
// TODO: Dynamic exit code https://github.com/PhpGt/Cli/issues/13
// phpcs:ignore
			throw new BuildException($errorString);
		}

		return $jsonPath;
	}

	protected function resolveConfigPath(string $path):?string {
		if(is_dir($path)) {
			$iniPath = $path . DIRECTORY_SEPARATOR . "build.ini";
			if(is_file($iniPath)) {
				return $iniPath;
			}

			$jsonPath = $path . DIRECTORY_SEPARATOR . "build.json";
			if(is_file($jsonPath)) {
				return $jsonPath;
			}

			return null;
		}

		if(is_file($path)) {
			return $path;
		}

		return null;
	}

	/**
	 * @SuppressWarnings(PHPMD.ExitExpression)
	 * @param array<int, string> $errors
	 */
	protected function checkRequirements(
		string $jsonPath,
		string $workingDirectory,
		array &$errors,
		?string $mode
	):Build {
		try {
			$build = new Build(
				$jsonPath,
				$workingDirectory,
				$mode,
			);
		} catch(ConfigurationParseException $exception) {
			$this->stream->writeLine("Syntax error in $jsonPath", Stream::ERROR);
// TODO: Dynamic exit code https://github.com/PhpGt/Cli/issues/13
// phpcs:ignore
			exit(1);
		}

		$build->check($errors);
		return $build;
	}

	protected function build(Build $build, bool $continue = true):void {
		$watchMessage = $continue ? "Watching for changes..." : null;
		do {
			$errors = [];
			$updates = $build->build($errors);
			$this->logUpdatesAndErrors($updates, $errors);

			usleep(250000);

			if($watchMessage) {
				$this->stream->writeLine($watchMessage);
				$watchMessage = null;
			}
		}
		while($continue);
	}

	/**
	 * @param array<int, Task> $updates
	 * @param array<int, string> $errors
	 */
	protected function logUpdatesAndErrors(array $updates, array $errors):void {
		foreach($updates as $update) {
			$this->logMessage("Success: $update");
		}

		foreach($errors as $error) {
			$this->logMessage("Error: $error", Stream::ERROR);
		}
	}

	protected function logMessage(string $message, string $severity = Stream::OUT):void {
		$message = date("Y-m-d H:i:s") . "\t" . $message;
		$this->stream->writeLine($message, $severity);
	}

	/** @param array<int, string> $errors */
	protected function showErrors(array $errors): void {
		$this->stream->writeLine("The following errors occurred:", Stream::ERROR);
		foreach($errors as $e) {
			$this->stream->writeLine(" • " . $e);
		}
	}

	protected function logElapsedTime(float $startTime): void {
		$deltaTime = round(microtime(true) - $startTime, 1);
		$this->stream->writeLine("Build script completed in $deltaTime seconds.");
	}
}
