<?php
namespace GT\Build\Test;

use GT\Build\Configuration\TaskBlock;
use GT\Build\Task;
use PHPUnit\Framework\TestCase;
use stdClass;

class TaskTest extends TestCase {
	private array $temporaryPaths = [];

	protected function tearDown():void {
		foreach(array_reverse($this->temporaryPaths) as $path) {
			$this->removePath($path);
		}

		$this->temporaryPaths = [];
	}

	public function testBuildExecutesWhenMatchingFilesChangeAndSkipsWhenNothingChanged():void {
		$workingDirectory = $this->createTemporaryDirectory();
		$sourceDir = $workingDirectory . DIRECTORY_SEPARATOR . "asset";
		mkdir($sourceDir);
		$this->temporaryPaths []= $sourceDir;

		$sourceFile = $sourceDir . DIRECTORY_SEPARATOR . "app.js";
		file_put_contents($sourceFile, "console.log('v1');");
		$outputFile = $workingDirectory . DIRECTORY_SEPARATOR . "build-count.txt";

		$scriptPath = $workingDirectory . DIRECTORY_SEPARATOR . "write-build-count.php";
		file_put_contents($scriptPath, <<<'PHP'
<?php
$countFile = $argv[1];
$currentCount = is_file($countFile) ? (int)file_get_contents($countFile) : 0;
file_put_contents($countFile, (string)($currentCount + 1));
PHP);

		$task = new Task($this->createTaskBlock(
			"asset/*.js",
			PHP_BINARY,
			[$scriptPath, $outputFile],
		), $workingDirectory);

		self::assertTrue($task->build());
		self::assertSame("1", file_get_contents($outputFile));

		self::assertFalse($task->build());
		self::assertSame("1", file_get_contents($outputFile));

		sleep(1);
		file_put_contents($sourceFile, "console.log('v2');");

		self::assertTrue($task->build());
		self::assertSame("2", file_get_contents($outputFile));
	}

	public function testBuildReturnsFalseWhenNoFilesMatchGlob():void {
		$workingDirectory = $this->createTemporaryDirectory();
		$task = new Task($this->createTaskBlock(
			"asset/*.js",
			PHP_BINARY,
			["-r", "exit(1);"],
		), $workingDirectory);

		self::assertFalse($task->build());
	}

	private function createTaskBlock(
		string $glob,
		string $command,
		array $arguments,
	):TaskBlock {
		$details = new stdClass();
		$details->execute = (object)[
			"command" => $command,
			"arguments" => $arguments,
		];

		return new TaskBlock($glob, $details);
	}

	private function createTemporaryDirectory():string {
		$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("phpgt-build-", true);
		mkdir($path);
		$this->temporaryPaths []= $path;
		return $path;
	}

	private function removePath(string $path):void {
		if(is_file($path)) {
			unlink($path);
			return;
		}

		if(!is_dir($path)) {
			return;
		}

		$children = scandir($path);
		if($children === false) {
			return;
		}

		foreach($children as $child) {
			if($child === "." || $child === "..") {
				continue;
			}

			$this->removePath($path . DIRECTORY_SEPARATOR . $child);
		}

		rmdir($path);
	}
}
