<?php
namespace Gt\Build;

use Gt\Build\Configuration\ExecuteBlock;
use Gt\Build\Configuration\RequireBlockItem;
use Gt\Build\Configuration\TaskBlock;
use Gt\Daemon\Process;
use Webmozart\Glob\Glob;
use Webmozart\PathUtil\Path;

class Task {
	protected string $absolutePath;
	protected string $basePath;
	protected string $glob;

	protected string $name;
	/** @var Requirement[] */
	protected $requirementList = [];
	protected ExecuteBlock $execute;

	/** @var array<string, int> */
	protected array $fileHashList = [];

	/**
	 * @SuppressWarnings(PHPMD.StaticAccess)
	 */
	public function __construct(
		TaskBlock $taskBlock,
		?string $basePath = null,
	) {
		$this->glob = $taskBlock->getGlob();
		$this->basePath = $basePath ?? getcwd();
		$this->absolutePath = implode(DIRECTORY_SEPARATOR, [
			$this->basePath,
			$this->glob,
		]);
		$this->absolutePath = Path::canonicalize($this->absolutePath);
		$this->setDetails($taskBlock);
	}

	public function __toString():string {
		return $this->name ?? $this->execute->command;
	}

	/** @param array<int, string>|null $errors */
	public function check(?array &$errors = null):void {
		foreach($this->requirementList as $requirement) {
			if(!$requirement->check($errors)) {
				if(is_null($errors)) {
					throw new UnsatisfiedRequirementVersion($requirement);
				}
				else {
					$errors []= "Unsatisfied version: " . $requirement;
				}
			}
		}
	}

	/**
	 * @SuppressWarnings(PHPMD.StaticAccess)
	 * @param array<int, string>|null $errors
	 */
	public function build(?array &$errors = null):bool {
		$hashMiss = false;

		foreach(Glob::glob($this->absolutePath) as $matchedPath) {
			$hash = filemtime($matchedPath);
			if($hash === false) {
				continue;
			}
			$existingHash = $this->fileHashList[$matchedPath] ?? null;

			if($hash !== $existingHash) {
				$hashMiss = true;
			}

			$this->fileHashList[$matchedPath] = $hash;
		}

		if($hashMiss) {
			return $this->execute($errors);
		}
		return false;
	}

	public function requirementFromRequireBlockItem(
		RequireBlockItem $item
	):Requirement {
		return new Requirement(
			$item->command,
			$item->version
		);
	}

	protected function setDetails(TaskBlock $details):void {
		$this->execute = $details->getExecuteBlock();
		$this->name = $details->getName();

		if($details->getRequireBlock()) {
			$this->requirementList = $details->getRequireBlock()->getRequirementList();
		}
	}

	/** @param array<int, string>|null $errors */
	protected function execute(?array &$errors = null):bool {
		$previousCwd = getcwd();
		chdir($this->basePath);

		$process = new Process(
			$this->execute->command,
			...$this->execute->arguments
		);
		$process->exec();

		do {
			$output = $process->getOutput();
			$errorOutput = $process->getErrorOutput();

			if(!empty($output)) {
				fwrite(STDOUT, $output);
			}
			if(!empty($errorOutput)) {
				fwrite(STDERR, $errorOutput);
			}

		}
		while($process->isRunning());

		chdir($previousCwd);

		if($process->getExitCode() !== 0) {
			if(is_null($errors)) {
				throw new TaskExecutionFailureException(
					implode(" ", array_merge(
						[$this->execute->command],
						$this->execute->arguments
					))
				);
			}
			else {
				$errors []= $this->execute->command
					. PHP_EOL
					. $output;
			}

			return false;
		}

		return true;
	}

	protected function expandRelativePath(string $basePath):string {
		if(!$this->isAbsolutePath($basePath)) {
			$basePath = getcwd() . substr(
				$basePath,
				1
			);
		}

		return $basePath;
	}

	protected function isAbsolutePath(string $path):bool {
		if($path === '') {
			return false;
		}

		// Either unix path starts from "/" or Windows path starts with "X:\"
		return $path[0] === DIRECTORY_SEPARATOR	|| preg_match('~\A[A-Z]:(?![^/\\\\])~i', $path) > 0;
	}
}
