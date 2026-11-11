<?php
namespace GT\Build\Test\Integration;

use GT\Build\Configuration\TaskBlock;
use GT\Build\Task;
use Gt\Daemon\CommandNotFoundException;
use GT\Build\TaskExecutionFailureException;
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

	public function testBuild_executesOnFileChangesAndSkipsUnchangedFiles():void {
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

		$sut = new Task($this->createTaskBlock(
			"asset/*.js",
			PHP_BINARY,
			[$scriptPath, $outputFile],
		), $workingDirectory);

		self::assertTrue($sut->build());
		self::assertSame("1", file_get_contents($outputFile));

		self::assertFalse($sut->build());
		self::assertSame("1", file_get_contents($outputFile));

		file_put_contents($sourceFile, "console.log('v2');");
		touch($sourceFile, time() + 10);
		clearstatcache();

		self::assertTrue($sut->build());
		self::assertSame("2", file_get_contents($outputFile));
	}

	public function testBuild_returnsFalseWithoutMatchingFiles():void {
		$workingDirectory = $this->createTemporaryDirectory();
		$sut = new Task($this->createTaskBlock(
			"asset/*.js",
			PHP_BINARY,
			["-r", "exit(1);"],
		), $workingDirectory);

		self::assertFalse($sut->build());
	}

	public function testBuild_runOnceWaitsForMatchAndSkipsLaterChanges():void {
		$directory = $this->createTemporaryDirectory();
		$block = $this->createTaskBlock("*.txt", PHP_BINARY, ["-r", "exit(0);"], true);
		$sut = new Task($block, $directory);
		self::assertFalse($sut->build());
		file_put_contents("$directory/first.txt", "first");
		self::assertTrue($sut->build());
		file_put_contents("$directory/second.txt", "second");
		touch("$directory/first.txt", time() + 10);
		clearstatcache();
		self::assertFalse($sut->build());
	}

	public function testBuild_retriesRunOnceAfterFailure():void {
		$directory = $this->createTemporaryDirectory();
		file_put_contents("$directory/first.txt", "first");
		$sut = new Task($this->createTaskBlock("*.txt", PHP_BINARY, [
			"-r", 'exit(file_exists("ready") ? 0 : 1);',
		], true), $directory);
		$errors = [];
		self::assertFalse($sut->build($errors));
		self::assertCount(1, $errors);
		file_put_contents("$directory/ready", "");
		file_put_contents("$directory/second.txt", "second");
		self::assertTrue($sut->build($errors));
		self::assertFalse($sut->build($errors));
	}

	public function testConstruct_newSessionCanRunCompletedTask():void {
		$directory = $this->createTemporaryDirectory();
		file_put_contents("$directory/first.txt", "first");
		$block = $this->createTaskBlock("*.txt", PHP_BINARY, ["-r", "exit(0);"], true);
		$previousTask = new Task($block, $directory);
		$previousTask->build();

		$sut = new Task($block, $directory);
		self::assertTrue($sut->build());
	}

	public function testBuild_missingCommandRestoresWorkingDirectory():void {
		$directory = $this->createTemporaryDirectory();
		file_put_contents("$directory/source.txt", "source");
		$sut = new Task($this->createTaskBlock("*.txt", "./node_modules/.bin/missing-tool", ["unused"]), $directory);
		$previousCwd = getcwd();

		try {
			$sut->build();
			self::fail("Expected a missing command exception");
		}
		catch(CommandNotFoundException $exception) {
			self::assertSame("./node_modules/.bin/missing-tool", $exception->getMessage());
			self::assertSame($previousCwd, getcwd());
		}
		finally {
			chdir($previousCwd);
		}
	}

	public function testBuild_nonZeroExitRemainsAnExecutionFailure():void {
		$directory = $this->createTemporaryDirectory();
		file_put_contents("$directory/source.txt", "source");
		$sut = new Task($this->createTaskBlock("*.txt", PHP_BINARY, ["-r", "exit(1);"]), $directory);
		$previousCwd = getcwd();

		try {
			$sut->build();
			self::fail("Expected a task execution failure");
		}
		catch(TaskExecutionFailureException $exception) {
			self::assertStringContainsString(PHP_BINARY, $exception->getMessage());
			self::assertSame($previousCwd, getcwd());
		}
	}

	private function createTaskBlock(
		string $glob,
		string $command,
		array $arguments,
		bool $runOnce = false,
	):TaskBlock {
		$details = new stdClass();
		$details->{"run-once"} = $runOnce;
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
