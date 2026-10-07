<?php
namespace GT\Build\Test;

use GT\Build\BuildException;
use GT\Build\ClientSidePackages;
use Gt\Cli\Stream;
use PHPUnit\Framework\TestCase;

class ClientSidePackagesTest extends TestCase {
	private string $directory;

	protected function setUp():void {
		$this->directory = sys_get_temp_dir() . '/' . uniqid('phpgt-packages-', true);
		mkdir($this->directory);
		file_put_contents($this->directory . '/package.json', '{}');
	}

	protected function tearDown():void {
		if(is_file($this->directory . '/package.json')) {
			unlink($this->directory . '/package.json');
		}
		rmdir($this->directory);
	}

	public function testProjectWithoutPackageJsonDoesNotRunNpm():void {
		unlink($this->directory . '/package.json');
		$sut = $this->createPackages([]);
		$sut->installIfNeeded();
		self::assertSame([], $sut->commands);
	}

	public function testMatchingDependenciesDoNotRunInstall():void {
		$sut = $this->createPackages([[0, '{}', '']]);
		$sut->installIfNeeded();
		self::assertCount(1, $sut->commands);
		self::assertSame(['npm', 'ls', '--json', '--depth=0', '--include=dev'], $sut->commands[0]);
	}

	public function testMissingDependenciesAreInstalledAndRechecked():void {
		$this->assertDependenciesInstalled('missing: webpack@^5.0.0');
	}

	public function testInvalidDependenciesAreInstalledAndRechecked():void {
		$this->assertDependenciesInstalled('invalid: webpack@4.0.0');
	}

	public function testExtraneousDependenciesDoNotTriggerInstallation():void {
		$sut = $this->createPackages([[1, '{"problems":["extraneous: other@1.0.0"]}', '']]);
		$sut->installIfNeeded();
		self::assertCount(1, $sut->commands);
	}

	public function testFailedInstallStopsBeforeBuild():void {
		$sut = $this->createPackages([
			[1, '{"problems":["missing: webpack@^5"]}', ''],
			[1, '', 'Network error'],
		]);
		$this->expectException(BuildException::class);
		$this->expectExceptionMessage('Client-side package installation failed');
		$sut->installIfNeeded();
	}

	public function testUnresolvedDependenciesAfterInstallAreReported():void {
		$sut = $this->createPackages([
			[1, '{"problems":["invalid: webpack@4"]}', ''],
			[0, '', ''],
			[1, '{"problems":["invalid: webpack@4"]}', ''],
		]);
		$this->expectException(BuildException::class);
		$sut->installIfNeeded();
	}

	public function testNpmErrorsAreReportedWithoutInstalling():void {
		$sut = $this->createPackages([[1, '{"error":{"code":"EJSONPARSE"}}', 'Invalid package.json']]);
		$this->expectException(BuildException::class);
		$this->expectExceptionMessage('Invalid package.json');
		$sut->installIfNeeded();
	}

	private function assertDependenciesInstalled(string $problem):void {
		$stream = new Stream('php://memory', 'php://memory', 'php://memory');
		$sut = $this->createPackages([
			[1, json_encode(['problems' => [$problem]]), ''],
			[0, 'Installed', ''],
			[0, '{}', ''],
		], $stream);
		$sut->installIfNeeded();
		self::assertCount(3, $sut->commands);
		self::assertSame(['npm', 'install', '--include=dev'], $sut->commands[1]);
		$stream->getOutStream()->rewind();
		self::assertStringContainsString('running `npm install`...', $stream->getOutStream()->fread(4096));
	}

	private function createPackages(array $responses, ?Stream $stream = null):object {
		$stream ??= new Stream('php://memory', 'php://memory', 'php://memory');
		return new class($this->directory, $stream, $responses) extends ClientSidePackages {
			public array $commands = [];

			public function __construct(string $path, Stream $stream, private array $responses) {
				parent::__construct($path, $stream);
			}

			protected function runCommand(array $command, bool $showOutput = false):array {
				$this->commands []= $command;
				return array_shift($this->responses);
			}
		};
	}
}
