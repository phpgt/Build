<?php
namespace Gt\Build;

use Webmozart\Glob\Glob;

class Build {
	/** @var TaskList */
	protected $taskList;
	protected $baseDir;

	public function __construct(
		string $jsonFilePath,
		string $workingDirectory,
		?string $mode = null
	) {
		$this->taskList = new TaskList(
			$jsonFilePath,
			$workingDirectory,
			$mode,
		);
	}

	/**
	 * For each task, ensure all requirements are met.
	 * @SuppressWarnings(PHPMD.StaticAccess)
	 */
	public function check(?array &$errors = null):int {
		$count = 0;

		foreach($this->taskList as $pathMatch => $task) {
			$absolutePathMatch = implode(DIRECTORY_SEPARATOR, [
				getcwd(),
				$pathMatch,
			]);
			$fileList = Glob::glob($absolutePathMatch);
			if(!empty($fileList)) {
				$task->check($errors);
			}

			$count++;
		}

		return $count;
	}

	/**
	 * Executes the commands associated with each build task.
	 * @return Task[] List of tasks built (some may not need building due to
	 * having no changes).
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
