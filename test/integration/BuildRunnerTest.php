<?php
namespace GT\Build\Test\Integration;

use GT\Build\BuildRunner;
use Gt\Cli\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BuildRunnerTest extends TestCase {
	private string $directory;

	protected function setUp():void {
		$this->directory = sys_get_temp_dir() . "/phpgt-build-" . uniqid();
		mkdir($this->directory);
	}

	protected function tearDown():void {
		foreach(glob("$this->directory/*") as $path) {
			unlink($path);
		}
		rmdir($this->directory);
	}

	#[DataProvider("missingCommands")]
	public function testRun_missingCommandShowsActionableError(string $command):void {
		file_put_contents("$this->directory/source.js", "");
		file_put_contents("$this->directory/build.ini", "[*.js]\nexecute=\"$command\" unused\n");
		$stream = new Stream("php://memory", "php://memory", "php://memory");
		$sut = new BuildRunner($this->directory, $stream);
		$previousCwd = getcwd();
		$previousPath = getenv("PATH");

		$sut->run(false);

		self::assertSame($previousCwd, getcwd());
		self::assertSame($previousPath, getenv("PATH"));
		$stream->getErrorStream()->rewind();
		$output = $stream->getErrorStream()->fread(4096);
		self::assertStringContainsString("Error: The required command can not be found ($command)", $output);
		self::assertStringContainsString("https://docs.npmjs.com/cli/commands/npm-install", $output);
		self::assertStringNotContainsString("proc_open", $output);
		self::assertStringNotContainsString("posix_spawn", $output);
		$stream->getOutStream()->rewind();
		self::assertSame("", $stream->getOutStream()->fread(4096));
	}

	public static function missingCommands():iterable {
		yield "command on PATH" => ["phpgt-missing-build-command"];
		yield "local npm binary" => ["./node_modules/.bin/missing-tool"];
		yield "path containing spaces" => ["./missing tool"];
	}
}
