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
