<?php
namespace Gt\Build;

use Gt\Build\Configuration\Manifest;
use Iterator;

class TaskList implements Iterator {
	/** @var Task[] */
	protected $taskList = [];
	/** @var string|null Null if the index is out of bounds */
	protected $iteratorKey;
	/** @var int Numerical index to use in iteration */
	protected $iteratorIndex;

	public function __construct(
		string $jsonFilePath,
		?string $baseDir = null,
		?string $mode = null
	) {
		if(is_null($baseDir)) {
			$baseDir = dirname($jsonFilePath);
		}

		$specification = new Manifest($jsonFilePath, $mode);
		foreach($specification as $glob => $taskBlock) {
			$this->taskList[$glob] = new Task($taskBlock);
		}
	}

	/** @link https://php.net/manual/en/iterator.rewind.php */
	public function rewind():void {
		$this->iteratorIndex = 0;
		$this->setIteratorKey();
	}

	/** @link https://php.net/manual/en/iterator.next.php */
	public function next():void {
		$this->iteratorIndex++;
		$this->setIteratorKey();
	}

	/** @link https://php.net/manual/en/iterator.valid.php */
	public function valid():bool {
		return !is_null($this->iteratorKey);
	}

	/** @link https://php.net/manual/en/iterator.key.php */
	public function key():string {
		return $this->iteratorKey;
	}

	/** @link https://php.net/manual/en/iterator.current.php */
	public function current():Task {
		return $this->taskList[$this->iteratorKey];
	}

	protected function setIteratorKey():void {
		$keys = array_keys($this->taskList);
		$this->iteratorKey = $keys[$this->iteratorIndex] ?? null;
	}
}
