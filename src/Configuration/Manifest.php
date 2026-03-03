<?php
namespace Gt\Build\Configuration;

use Gt\Build\JsonParseException;
use Gt\Build\MissingBuildFileException;
use Iterator;

/**
 * Represents the entire JSON configuration file, build.json
 * Each path pattern in the JSON is represented with a PathPattern object.
 * @implements Iterator<string, TaskBlock>
 */
class Manifest implements Iterator {
	/** @var array<string, TaskBlock> */
	protected array $taskBlockList;
	/** @var string|null Null if the index is out of bounds */
	protected ?string $iteratorKey = null;
	/** @var int Numerical index to use in iteration */
	protected int $iteratorIndex = 0;

	public function __construct(string $jsonFilePath, ?string $mode = null) {
		if(!is_file($jsonFilePath)) {
			throw new MissingBuildFileException($jsonFilePath);
		}

		$json = json_decode(file_get_contents($jsonFilePath));
		if(is_null($json)) {
			throw new JsonParseException(json_last_error_msg());
		}

		if($mode) {
			$modeJsonFilePath = substr(
				$jsonFilePath,
				0,
				-strlen(".json"),
			);
			$modeJsonFilePath .= ".$mode.json";
			if(!is_file($modeJsonFilePath)) {
				throw new MissingBuildFileException($modeJsonFilePath);
			}
			$modeJson = json_decode(file_get_contents($modeJsonFilePath));
// For legacy reasons, stdClass is used to represent the block details.
// This code might look weird, but it remains backwards compatible until an OOP
// refactoring is made.
			$json = $this->recursiveMerge($json, $modeJson);
		}

		$this->taskBlockList = [];
		foreach($json as $glob => $details) {
			$this->taskBlockList[$glob] = new TaskBlock(
				$glob,
				$details
			);
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
	public function current():TaskBlock {
		return $this->taskBlockList[$this->iteratorKey];
	}

	protected function setIteratorKey():void {
		$keys = array_keys($this->taskBlockList);
		$this->iteratorKey = $keys[$this->iteratorIndex] ?? null;
	}

	private function recursiveMerge(object $json, object $diff):object {
		foreach($diff as $key => $value) {
			if(property_exists($json, $key)) {
				if(is_object($value)) {
					$json->$key = $this->recursiveMerge($json->$key, $value);
				}
				else {
					$json->$key = $value;
				}
			}
			else {
				$json->$key = $value;
			}
		}

		return $json;
	}
}
