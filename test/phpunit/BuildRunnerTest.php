<?php
namespace GT\Build\Test;

use GT\Build\BuildException;
use Gt\Cli\Stream;
use PHPUnit\Framework\TestCase;

class BuildRunnerTest extends TestCase {
	private array $temporaryPaths = [];

	protected function tearDown():void {
		foreach(array_reverse($this->temporaryPaths) as $path) {
			if(is_file($path)) {
				unlink($path);
			}
			elseif(is_dir($path)) {
				rmdir($path);
			}
		}

		$this->temporaryPaths = [];
	}

	public function testGetJsonPathPrefersIniConfigInWorkingDirectory():void {
		$workingDirectory = $this->createTemporaryDirectory();
		$iniPath = $workingDirectory . DIRECTORY_SEPARATOR . "build.ini";
		$jsonPath = $workingDirectory . DIRECTORY_SEPARATOR . "build.json";
		file_put_contents($iniPath, "");
		file_put_contents($jsonPath, "[]");
		$this->temporaryPaths []= $iniPath;
		$this->temporaryPaths []= $jsonPath;

		$sut = $this->createRunnerProxy($workingDirectory);

		self::assertSame($iniPath, $sut->exposedGetJsonPath($workingDirectory));
	}

	public function testGetJsonPathFallsBackToDefaultPath():void {
		$workingDirectory = $this->createTemporaryDirectory();
		$defaultDirectory = $this->createTemporaryDirectory();
		$defaultPath = $defaultDirectory . DIRECTORY_SEPARATOR . "build.ini";
		file_put_contents($defaultPath, "");
		$this->temporaryPaths []= $defaultPath;

		$sut = $this->createRunnerProxy($workingDirectory);
		$sut->setDefaultPath($defaultPath);

		self::assertSame($defaultPath, $sut->exposedGetJsonPath($workingDirectory));
	}

	public function testGetJsonPathThrowsWhenNoConfigExists():void {
		$workingDirectory = $this->createTemporaryDirectory();
		$sut = $this->createRunnerProxy($workingDirectory);
		$sut->setDefaultPath($workingDirectory . DIRECTORY_SEPARATOR . "missing.ini");

		$this->expectException(BuildException::class);
		$sut->exposedGetJsonPath($workingDirectory);
	}

	public function testFormatWorkingDirectoryNormalisesFilePath():void {
		$workingDirectory = $this->createTemporaryDirectory();
		$configPath = $workingDirectory . DIRECTORY_SEPARATOR . "build.ini";
		file_put_contents($configPath, "");
		$this->temporaryPaths []= $configPath;

		$sut = $this->createRunnerProxy($configPath);

		self::assertSame($workingDirectory, $sut->exposedFormatWorkingDirectory());
	}

	public function testMissingCommandShowsDocumentationAndRestoresEnvironment():void {
		$workingDirectory = $this->createTemporaryDirectory();
		$this->createTemporaryFile($workingDirectory . "/source.js", "");
		$this->createTemporaryFile($workingDirectory . "/build.ini", "[*.js]\nexecute=phpgt-missing-build-command unused\n");
		$stream = $this->createStream();
		$runner = new \GT\Build\BuildRunner($workingDirectory, $stream);
		$previousCwd = getcwd();
		$previousPath = getenv("PATH");

		$runner->run(false);

		self::assertSame($previousCwd, getcwd());
		self::assertSame($previousPath, getenv("PATH"));
		$stream->getErrorStream()->rewind();
		$output = $stream->getErrorStream()->fread(4096);
		self::assertStringContainsString("Command not found: phpgt-missing-build-command", $output);
		self::assertStringContainsString("https://docs.npmjs.com/cli/commands/npm-install", $output);
	}

	public function testLocalBinaryIsAvailableToRequirementsAndBuild():void {
		$workingDirectory = $this->createTemporaryDirectory();
		foreach(["/node_modules", "/node_modules/.bin"] as $directory) {
			mkdir($workingDirectory . $directory);
			$this->temporaryPaths []= $workingDirectory . $directory;
		}
		$command = $workingDirectory . "/node_modules/.bin/phpgt-local-tool";
		$this->createTemporaryFile($command, "#!" . PHP_BINARY . "\n<?php if(in_array('--version', \$argv)) { echo '1.2.3'; }");
		chmod($command, 0755);
		$this->createTemporaryFile($workingDirectory . "/source.js", "");
		$this->createTemporaryFile($workingDirectory . "/build.ini",
			"[*.js]\nexecute=phpgt-local-tool unused\nrequire=phpgt-local-tool ^1.0\n");
		$stream = $this->createStream();
		$runner = new \GT\Build\BuildRunner($workingDirectory, $stream);
		$previousPath = getenv("PATH");

		$runner->run(false);

		self::assertSame($previousPath, getenv("PATH"));
		$stream->getOutStream()->rewind();
		self::assertStringContainsString("Success:", $stream->getOutStream()->fread(4096));
		$stream->getErrorStream()->rewind();
		self::assertSame("", $stream->getErrorStream()->fread(4096));
	}

	public function testInstallationFailurePreventsBuildAndShowsHelp():void {
		$workingDirectory = $this->createTemporaryDirectory();
		$this->createTemporaryFile($workingDirectory . "/build.ini", "");
		$stream = $this->createStream();
		$runner = new class($workingDirectory, $stream) extends \GT\Build\BuildRunner {
			protected function installClientSidePackages(string $workingDirectory):void {
				throw new BuildException("Client-side package installation failed");
			}
			protected function build(\GT\Build\Build $build, bool $continue = true):void {
				throw new \LogicException("Build must not start after a failed install");
			}
		};
		$runner->run(false);
		$stream->getErrorStream()->rewind();
		$output = $stream->getErrorStream()->fread(4096);
		self::assertStringContainsString("Client-side package installation failed", $output);
		self::assertStringContainsString("https://docs.npmjs.com/cli/commands/npm-install", $output);
	}

	public function testMissingNpmShowsNodeInstallationHelp():void {
		$workingDirectory = $this->createTemporaryDirectory();
		$this->createTemporaryFile($workingDirectory . "/build.ini", "");
		$this->createTemporaryFile($workingDirectory . "/package.json", "{}");
		$stream = $this->createStream();
		$runner = new \GT\Build\BuildRunner($workingDirectory, $stream);
		$previousPath = getenv("PATH");
		putenv("PATH=" . $workingDirectory);
		try {
			$runner->run(false);
			self::assertSame($workingDirectory, getenv("PATH"));
		}
		finally {
			putenv($previousPath === false ? "PATH" : "PATH=$previousPath");
		}
		$stream->getErrorStream()->rewind();
		$output = $stream->getErrorStream()->fread(4096);
		self::assertStringContainsString("Command not found: npm", $output);
		self::assertStringContainsString("https://docs.npmjs.com/downloading-and-installing-node-js-and-npm", $output);
	}

	private function createTemporaryFile(string $path, string $content):void {
		file_put_contents($path, $content);
		$this->temporaryPaths []= $path;
	}

	private function createTemporaryDirectory():string {
		$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("phpgt-build-", true);
		mkdir($path);
		$this->temporaryPaths []= $path;
		return $path;
	}

	private function createStream():Stream {
		return new Stream("php://memory", "php://memory", "php://memory");
	}

	private function createRunnerProxy(string $path):object {
		return new class($path, $this->createStream()) extends \GT\Build\BuildRunner {
			public function exposedFormatWorkingDirectory():string {
				return $this->formatWorkingDirectory();
			}

			public function exposedGetJsonPath(string $workingDirectory):string {
				return $this->getJsonPath($workingDirectory);
			}
		};
	}
}
