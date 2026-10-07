<?php
namespace GT\Build;

use Gt\Cli\Stream;
use Gt\Daemon\Process;

/** Uses npm's dependency validation rather than comparing file timestamps. */
class ClientSidePackages {
	public function __construct(
		protected string $workingDirectory,
		protected Stream $stream,
	) {}

	public function installIfNeeded():void {
		if(!is_file($this->workingDirectory . DIRECTORY_SEPARATOR . "package.json")) {
			return;
		}

		if(!$this->needsInstallation()) {
			return;
		}

		$this->stream->writeLine(
			"Client-side packages are missing or do not match package.json, running `npm install`..."
		);
		[$exitCode] = $this->runCommand(["npm", "install", "--include=dev"], true);
		if($exitCode !== 0 || $this->needsInstallation()) {
			throw new BuildException(
				"Client-side package installation failed. Run `npm install` in $this->workingDirectory."
			);
		}
	}

	/** @phpstan-impure */
	protected function needsInstallation():bool {
		[$exitCode, $output, $error] = $this->runCommand([
			"npm", "ls", "--json", "--depth=0", "--include=dev",
		]);
		$result = json_decode($output, true);
		if(!is_array($result)) {
			throw new BuildException("Unable to check client-side packages: $error$output");
		}

		foreach($result["problems"] ?? [] as $problem) {
			if(str_starts_with($problem, "missing:") || str_starts_with($problem, "invalid:")) {
				return true;
			}
		}

// Extraneous packages do not require an install. Other npm errors must be
// reported rather than assuming an install will fix them.
		if($exitCode !== 0 && empty($result["problems"])) {
			throw new BuildException("Unable to check client-side packages: $error$output");
		}
		return false;
	}

	/**
	 * @param array<int, string> $command
	 * @return array{int|null, string, string}
	 */
	protected function runCommand(array $command, bool $showOutput = false):array {
		$process = new Process(...$command);
		$process->setExecCwd($this->workingDirectory);
		$process->exec();
		$output = "";
		$error = "";
		do {
			$stdout = $process->getOutput();
			$stderr = $process->getErrorOutput();
			$output .= $stdout;
			$error .= $stderr;
			if($showOutput) {
				$this->stream->write($stdout);
				$this->stream->write($stderr, Stream::ERROR);
			}
			usleep(10000);
		}
		while($process->isRunning());
		// Drain output written between the last read and process exit.
		$stdout = $process->getOutput();
		$stderr = $process->getErrorOutput();
		if($showOutput) {
			$this->stream->write($stdout);
			$this->stream->write($stderr, Stream::ERROR);
		}
		return [$process->getExitCode(), $output . $stdout, $error . $stderr];
	}
}
