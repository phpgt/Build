<?php
namespace Gt\Build;

use Webmozart\Glob\Glob;

class Build {
	protected TaskList $taskList;
	protected string $workingDirectory;

	public function __construct(
		string $jsonFilePath,
		string $workingDirectory,
		?string $mode = null
	) {
		$this->workingDirectory = $workingDirectory;
		$this->taskList = new TaskList(
			$jsonFilePath,
			$workingDirectory,
			$mode,
		);
	}

	/**
	 * For each task, ensure all requirements are met.
	 * @param array<int, string>|null $errors
	 * @SuppressWarnings(PHPMD.StaticAccess)
	 */
	public function check(?array &$errors = null):int {
		$count = 0;
		$previousCwd = getcwd();
		chdir($this->workingDirectory);

		try {
			foreach($this->taskList as $pathMatch => $task) {
				$absolutePathMatch = implode(DIRECTORY_SEPARATOR, [
					$this->workingDirectory,
					$pathMatch,
				]);
				$fileList = Glob::glob($absolutePathMatch);
				if(!empty($fileList)) {
					$task->check($errors);
				}

				$count++;
			}
		}
		finally {
			chdir($previousCwd);
		}

		return $count;
	}

	/**
	 * Executes the commands associated with each build task.
	 * @return Task[] List of tasks built (some may not need building due to
	 * having no changes).
	 * @param array<int, string>|null $errors
	 */
	public function build(?array &$errors = null):array {
		$updatedTasks = [];
		foreach($this->taskList as $task) {
			if($task->build($errors)) {
				$updatedTasks []= $task;
			}
		}

		return $updatedTasks;
	}
}
