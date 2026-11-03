<?php
namespace GT\Build\Test;

use GT\Build\Configuration\TaskBlock;
use GT\Build\Task;
use GT\Build\Build;
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

	public function testRunOnceWaitsForMatchAndSkipsChangesAfterSuccess():void {
		$directory = $this->createTemporaryDirectory();
		$block = $this->createTaskBlock("*.txt", PHP_BINARY, ["-r", "exit(0);"] , true);
		$task = new Task($block, $directory);
		self::assertFalse($task->build());
		file_put_contents("$directory/first.txt", "first");
		self::assertTrue($task->build());
		file_put_contents("$directory/second.txt", "second");
		touch("$directory/first.txt", time() + 10);
		clearstatcache();
		self::assertFalse($task->build());
		self::assertTrue((new Task($block, $directory))->build());
	}

	public function testRunOnceFailureDoesNotPreventLaterExecution():void {
		$directory = $this->createTemporaryDirectory();
		file_put_contents("$directory/first.txt", "first");
		$task = new Task($this->createTaskBlock("*.txt", PHP_BINARY, [
			"-r", 'exit(file_exists("ready") ? 0 : 1);',
		], true), $directory);
		$errors = [];
		self::assertFalse($task->build($errors));
		self::assertCount(1, $errors);
		file_put_contents("$directory/ready", "");
		file_put_contents("$directory/second.txt", "second");
		self::assertTrue($task->build($errors));
		self::assertFalse($task->build($errors));
	}

	public function testBuildContinuesOrdinaryTasksAfterRunOnceCompletes():void {
		$directory = $this->createTemporaryDirectory();
		file_put_contents("$directory/count.php", '<?php file_put_contents($argv[1], "x", FILE_APPEND);');
		file_put_contents("$directory/first.txt", "first");
		file_put_contents("$directory/build.ini", implode("\n", [
			"[*.txt]",
			'execute="' . PHP_BINARY . '" count.php once.count',
			"options[run-once]=true",
			"[*.{txt,js}]",
			'execute="' . PHP_BINARY . '" count.php repeat.count',
		]));
		$build = new Build("$directory/build.ini", $directory);
		self::assertCount(2, $build->build());
		file_put_contents("$directory/second.txt", "second");
		self::assertCount(1, $build->build());
		self::assertSame("x", file_get_contents("$directory/once.count"));
		self::assertSame("xx", file_get_contents("$directory/repeat.count"));
	}

	private function createTaskBlock(
		string $glob,
		string $command,
		array $arguments,
		bool $runOnce = false,
	):TaskBlock {
		$details = new stdClass();
		$details->options = (object)["run-once" => $runOnce];
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
