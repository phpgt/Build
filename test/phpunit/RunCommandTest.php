<?php
namespace Gt\Build\Test;

use Gt\Build\Cli\RunCommand;
use Gt\Cli\Argument\ArgumentValueList;
use PHPUnit\Framework\TestCase;

class RunCommandTest extends TestCase {
	public function testRunWithoutModeDoesNotThrow():void {
		$cwd = getcwd();
		$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("phpgt-build-", true);
		mkdir($tempDir);
		file_put_contents($tempDir . DIRECTORY_SEPARATOR . "build.json", "[]");
		chdir($tempDir);

		try {
			$sut = new RunCommand();
			$sut->setStream();
			$arguments = new ArgumentValueList();
			$exitCode = $sut->run($arguments);

			self::assertSame(0, $exitCode);
		}
		finally {
			chdir($cwd);
			unlink($tempDir . DIRECTORY_SEPARATOR . "build.json");
			rmdir($tempDir);
		}
	}
}
