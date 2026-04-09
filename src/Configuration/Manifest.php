<?php
namespace Gt\Build\Configuration;

use Gt\Build\ConfigurationParseException;
use Gt\Build\MissingBuildFileException;
use Iterator;
use stdClass;

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

	public function __construct(string $configFilePath, ?string $mode = null) {
		if(!is_file($configFilePath)) {
			throw new MissingBuildFileException($configFilePath);
		}

		$json = $this->parseConfigurationFile($configFilePath);

		if($mode) {
			$modeJsonFilePath = substr(
				$configFilePath,
				0,
				-strlen(pathinfo($configFilePath, PATHINFO_EXTENSION)) - 1,
			);
			$modeJsonFilePath .= ".$mode." . pathinfo($configFilePath, PATHINFO_EXTENSION);
			if(!is_file($modeJsonFilePath)) {
				throw new MissingBuildFileException($modeJsonFilePath);
			}
			$modeJson = $this->parseConfigurationFile($modeJsonFilePath);
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

	private function parseConfigurationFile(string $configFilePath):object {
		return match(strtolower(pathinfo($configFilePath, PATHINFO_EXTENSION))) {
			"ini" => $this->parseIniFile($configFilePath),
			"json" => $this->parseJsonFile($configFilePath),
			default => $this->parseJsonFile($configFilePath),
		};
	}

	private function parseJsonFile(string $configFilePath):object {
		$json = json_decode(file_get_contents($configFilePath));
		if(is_null($json)) {
			throw new ConfigurationParseException(json_last_error_msg());
		}

		if(is_array($json)) {
			return (object)$json;
		}

		return $json;
	}

	private function parseIniFile(string $configFilePath):object {
		$ini = @parse_ini_file($configFilePath, true, INI_SCANNER_RAW);
		if($ini === false) {
			throw new ConfigurationParseException("Syntax error");
		}

		$manifest = new stdClass();
		foreach($ini as $glob => $details) {
			$taskBlock = new stdClass();

			if(isset($details["name"])) {
				$taskBlock->name = $details["name"];
			}

			if(isset($details["require"])) {
				$taskBlock->require = $this->parseRequireString($details["require"]);
			}

			if(!isset($details["execute"])) {
				throw new MissingConfigurationKeyException("execute");
			}

			$taskBlock->execute = $this->parseExecuteString($details["execute"]);
			$manifest->$glob = $taskBlock;
		}

		return $manifest;
	}

	private function parseRequireString(string $requireString):object {
		$require = new stdClass();
		foreach(explode(",", $requireString) as $requirementDefinition) {
			$requirementDefinition = trim($requirementDefinition);
			if($requirementDefinition === "") {
				continue;
			}

			$requirementParts = preg_split("/\s+/", $requirementDefinition, 2);
			$command = $requirementParts[0];
			$version = trim($requirementParts[1] ?? "*");
			if($version === "") {
				$version = "*";
			}

			$require->$command = $version;
		}

		return $require;
	}

	private function parseExecuteString(string $executeString):object {
		if(strpbrk($executeString, ";#") !== false || preg_match("/[\r\n]/", $executeString)) {
			throw new ConfigurationParseException(
				"Forbidden character in execute command"
			);
		}

		$tokens = [];
		$currentToken = "";
		$quote = null;
		$tokenInProgress = false;
		$length = strlen($executeString);

		for($i = 0; $i < $length; $i++) {
			$char = $executeString[$i];

			if($quote !== null) {
				if($char === $quote) {
					$quote = null;
				}
				else {
					$currentToken .= $char;
				}
				$tokenInProgress = true;
				continue;
			}

			if($char === "'" || $char === '"') {
				$quote = $char;
				$tokenInProgress = true;
				continue;
			}

			if(ctype_space($char)) {
				if($tokenInProgress) {
					$tokens []= $currentToken;
					$currentToken = "";
					$tokenInProgress = false;
				}
				continue;
			}

			$currentToken .= $char;
			$tokenInProgress = true;
		}

		if($quote !== null) {
			throw new ConfigurationParseException("Unterminated quote in execute command");
		}

		if($tokenInProgress) {
			$tokens []= $currentToken;
		}

		if(empty($tokens)) {
			throw new MissingConfigurationKeyException("execute.command");
		}

		$execute = new stdClass();
		$execute->command = array_shift($tokens);
		$execute->arguments = $tokens;
		return $execute;
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
