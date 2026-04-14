<?php
namespace GT\Build\Test;

use Exception;
use GT\Build\Build;
use PHPUnit\Framework\TestCase;

class BuildTest extends TestCase {
	public function testCheckEmptyJson() {
		$jsonFilePath = tempnam(sys_get_temp_dir(), "phpgt-");
		$workingDirectory = sys_get_temp_dir();
		file_put_contents($jsonFilePath, "[]");

		$exception = null;

		$sut = new Build($jsonFilePath, $workingDirectory);
		try {
			$sut->check();
		}
		catch(Exception $exception) {}

		self::assertNull($exception);
	}
}