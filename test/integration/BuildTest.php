<?php
namespace GT\Build\Test\Integration;

use GT\Build\Build;
use PHPUnit\Framework\TestCase;

class BuildTest extends TestCase {
	private array $temporaryPaths = [];

	protected function tearDown():void {
		foreach(array_reverse($this->temporaryPaths) as $path) {
			$this->removePath($path);
		}
	}

	public function testBuild_continuesOrdinaryTasksAfterRunOnceCompletes():void {
		$directory = $this->createTemporaryDirectory();
		file_put_contents("$directory/count.php", '<?php file_put_contents($argv[1], "x", FILE_APPEND);');
		file_put_contents("$directory/first.txt", "first");
		file_put_contents("$directory/build.ini", implode("\n", [
			"[first.*]",
			'execute="' . PHP_BINARY . '" count.php once.count',
			"run-once=true",
			"[*.txt]",
			'execute="' . PHP_BINARY . '" count.php repeat.count',
		]));
		$sut = new Build("$directory/build.ini", $directory);
		self::assertCount(2, $sut->build());
		touch("$directory/first.txt", time() + 2);
		clearstatcache();
		file_put_contents("$directory/second.txt", "second");
		self::assertCount(1, $sut->build());
		self::assertSame("x", file_get_contents("$directory/once.count"));
		self::assertSame("xx", file_get_contents("$directory/repeat.count"));
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
