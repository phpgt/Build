<?php
namespace GT\Build\Test\Integration\Configuration;

use GT\Build\Configuration\MissingConfigurationKeyException;
use GT\Build\Configuration\Manifest;
use GT\Build\Configuration\TaskBlock;
use GT\Build\ConfigurationParseException;
use GT\Build\MissingBuildFileException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ManifestTest extends TestCase {
	#[DataProvider("runOnceConfigurations")]
	public function testConstruct_runOnceConfiguration(string $format, mixed $value):void {
		$path = $this->manifestPath($format);
		file_put_contents($path, $this->manifestContents($format, $value));

		try {
			$sut = new Manifest($path);
			$blocks = iterator_to_array($sut);
			self::assertSame($value === true, $blocks["asset/*"]->isRunOnce());
		}
		finally {
			unlink($path);
		}
	}

	#[DataProvider("invalidRunOnceConfigurations")]
	public function testConstruct_rejectsInvalidRunOnceConfiguration(string $format, mixed $value):void {
		$path = $this->manifestPath($format);
		file_put_contents($path, $this->manifestContents($format, $value));

		try {
			$this->expectException(ConfigurationParseException::class);
			$sut = new Manifest($path);
		}
		finally {
			unlink($path);
		}
	}

	#[DataProvider("runOnceModeOverrides")]
	public function testConstruct_modeOverridesRunOnce(string $format, bool $enabled):void {
		$path = $this->manifestPath($format);
		$modePath = substr($path, 0, -strlen($format)) . "dev.$format";
		file_put_contents($path, $this->manifestContents($format, !$enabled));
		file_put_contents($modePath, $this->manifestContents($format, $enabled));

		try {
			$sut = new Manifest($path, "dev");
			$blocks = iterator_to_array($sut);
			self::assertSame($enabled, $blocks["asset/*"]->isRunOnce());
		}
		finally {
			unlink($path);
			unlink($modePath);
		}
	}

	public static function runOnceConfigurations():iterable {
		foreach(["ini", "json"] as $format) {
			foreach([true, false, null] as $value) {
				yield [$format, $value];
			}
		}
	}

	public static function invalidRunOnceConfigurations():iterable {
		foreach(["ini", "json"] as $format) {
			yield [$format, "invalid"];
			yield [$format, ["nested" => true]];
		}
	}

	public static function runOnceModeOverrides():iterable {
		foreach(["ini", "json"] as $format) {
			foreach([true, false] as $enabled) {
				yield [$format, $enabled];
			}
		}
	}

	private function manifestPath(string $format):string {
		return sys_get_temp_dir() . "/" . uniqid("run-once-", true) . ".$format";
	}

	private function manifestContents(string $format, mixed $value):string {
		if($format === "json") {
			$details = ["execute" => ["command" => "php", "arguments" => ["--version"]]];
			if($value !== null) {
				$details["run-once"] = $value;
			}
			return json_encode(["asset/*" => $details], JSON_THROW_ON_ERROR);
		}

		$content = "[asset/*]\nexecute=php --version\n";
		if($value === null) {
			return $content;
		}
		if(is_array($value)) {
			return $content . "run-once[nested]=true";
		}
		if(is_bool($value)) {
			$value = $value ? "true" : "false";
		}
		return $content . "run-once=$value";
	}

	public function testIterator_ini():void {
		$iniFile = "test/phpunit/Helper/Ini/build.ini";
		$sut = new Manifest($iniFile);
		$taskBlocks = iterator_to_array($sut);

		self::assertCount(2, $taskBlocks);
		self::assertArrayHasKey("script/**/*.es6", $taskBlocks);
		self::assertSame(
			["script/script file.es6", "--bundle", "--sourcemap", "--outfile=www/script.js", "--loader:.es6=js", "--target=chrome105,firefox105,edge105,safari15"],
			$taskBlocks["script/**/*.es6"]->getExecuteBlock()->arguments,
		);
		self::assertSame(
			"./node_modules/.bin/esbuild",
			$taskBlocks["script/**/*.es6"]->getExecuteBlock()->command,
		);
		self::assertSame(
			[
				"node_modules/.bin/esbuild >=1.2.3",
				"babel *",
				"esbuild ^0.17",
			],
			array_map(
				static fn($requirement) => (string)$requirement,
				$taskBlocks["script/**/*.es6"]->getRequireBlock()->getRequirementList(),
			),
		);
		self::assertNull($taskBlocks["style/**/*.scss"]->getRequireBlock());
		self::assertSame(
			["./style/style.scss", "./www/style.css"],
			$taskBlocks["style/**/*.scss"]->getExecuteBlock()->arguments,
		);
	}

	public function testIterator_iniMode():void {
		$iniFile = "test/phpunit/Helper/Ini/build.ini";
		$sut = new Manifest($iniFile, "dev");
		$taskBlocks = iterator_to_array($sut);

		self::assertCount(3, $taskBlocks);
		self::assertSame(
			["./node_modules/.bin/esbuild", "script/dev entry.es6", "--bundle", "--outfile=www/script.dev.js"],
			array_merge(
				[$taskBlocks["script/**/*.es6"]->getExecuteBlock()->command],
				$taskBlocks["script/**/*.es6"]->getExecuteBlock()->arguments,
			),
		);
		self::assertSame(
			["vendor/bin/sitemap ^1.0"],
			array_map(
				static fn($requirement) => (string)$requirement,
				$taskBlocks["page/**/*.php"]->getRequireBlock()->getRequirementList(),
			),
		);
	}

	public function testIterator_iniModeOnlyOverridesSingleProperty():void {
		$iniFile = "test/phpunit/Helper/Ini/build.ini";
		$sut = new Manifest($iniFile, "single-property-override");
		$taskBlocks = iterator_to_array($sut);

		self::assertSame(
			["./node_modules/.bin/esbuild", "script/script.es6", "--bundle", "--minify"],
			array_merge(
				[$taskBlocks["script/**/*.es6"]->getExecuteBlock()->command],
				$taskBlocks["script/**/*.es6"]->getExecuteBlock()->arguments,
			),
		);
		self::assertSame(
			[
				"node_modules/.bin/esbuild >=1.2.3",
				"babel *",
				"esbuild ^0.17",
			],
			array_map(
				static fn($requirement) => (string)$requirement,
				$taskBlocks["script/**/*.es6"]->getRequireBlock()->getRequirementList(),
			),
		);
	}

	public function testIterator_iniNameAndDefaultRequirementVersion():void {
		$iniFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("phpgt-build-", true) . ".ini";
		file_put_contents($iniFilePath, implode(PHP_EOL, [
			"[asset/**/*.js]",
			"name=Bundle JS",
			"require=node_modules/.bin/esbuild",
			"execute=./node_modules/.bin/esbuild asset/app.js --bundle",
			"",
		]));

		try {
			$sut = new Manifest($iniFilePath);
			$taskBlocks = iterator_to_array($sut);

			self::assertSame("Bundle JS", $taskBlocks["asset/**/*.js"]->getName());
			self::assertSame(
				["node_modules/.bin/esbuild *"],
				array_map(
					static fn($requirement) => (string)$requirement,
					$taskBlocks["asset/**/*.js"]->getRequireBlock()->getRequirementList(),
				),
			);
		}
		finally {
			unlink($iniFilePath);
		}
	}

	public function testIterator_iniModeMissingFileThrows():void {
		$this->expectException(MissingBuildFileException::class);
		new Manifest("test/phpunit/Helper/Ini/build.ini", "missing");
	}

	public function testIterator_iniMissingExecuteThrows():void {
		$iniFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("phpgt-build-", true) . ".ini";
		file_put_contents($iniFilePath, implode(PHP_EOL, [
			"[asset/**/*.js]",
			"require=node_modules/.bin/esbuild ^1",
			"",
		]));

		try {
			$this->expectException(MissingConfigurationKeyException::class);
			new Manifest($iniFilePath);
		}
		finally {
			unlink($iniFilePath);
		}
	}

	public function testIterator_iniRejectsForbiddenExecuteCharacters():void {
		$iniFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("phpgt-build-", true) . ".ini";
		file_put_contents($iniFilePath, implode(PHP_EOL, [
			"[asset/**/*.js]",
			"execute=./node_modules/.bin/esbuild asset/app.js --bundle # forbidden",
			"",
		]));

		try {
			$this->expectException(ConfigurationParseException::class);
			new Manifest($iniFilePath);
		}
		finally {
			unlink($iniFilePath);
		}
	}

	public function testIterator_iniRejectsUnterminatedQuotedExecuteArgument():void {
		$iniFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("phpgt-build-", true) . ".ini";
		file_put_contents($iniFilePath, implode(PHP_EOL, [
			"[asset/**/*.js]",
			"execute=./node_modules/.bin/esbuild \"asset/app.js --bundle",
			"",
		]));

		try {
			$this->expectException(ConfigurationParseException::class);
			new Manifest($iniFilePath);
		}
		finally {
			unlink($iniFilePath);
		}
	}

	public function testIterator_iniIgnoresEmptyRequirementsAndDefaultsBlankVersion():void {
		$iniFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("phpgt-build-", true) . ".ini";
		file_put_contents($iniFilePath, implode(PHP_EOL, [
			"[asset/**/*.js]",
			"require=node_modules/.bin/esbuild   , ,vendor/bin/tool ^2",
			"execute=./node_modules/.bin/esbuild asset/app.js --bundle",
			"",
		]));

		try {
			$sut = new Manifest($iniFilePath);
			$taskBlocks = iterator_to_array($sut);

			self::assertSame(
				[
					"node_modules/.bin/esbuild *",
					"vendor/bin/tool ^2",
				],
				array_map(
					static fn($requirement) => (string)$requirement,
					$taskBlocks["asset/**/*.js"]->getRequireBlock()->getRequirementList(),
				),
			);
		}
		finally {
			unlink($iniFilePath);
		}
	}

	public function testIterator_iniRejectsEmptyExecuteCommand():void {
		$iniFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("phpgt-build-", true) . ".ini";
		file_put_contents($iniFilePath, implode(PHP_EOL, [
			"[asset/**/*.js]",
			"execute=   ",
			"",
		]));

		try {
			$this->expectException(MissingConfigurationKeyException::class);
			new Manifest($iniFilePath);
		}
		finally {
			unlink($iniFilePath);
		}
	}

	public function testIterator_iniSyntaxErrorThrowsConfigurationParseException():void {
		$iniFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("phpgt-build-", true) . ".ini";
		file_put_contents($iniFilePath, implode(PHP_EOL, [
			"[asset/**/*.js",
			"execute=./node_modules/.bin/esbuild asset/app.js --bundle",
			"",
		]));

		try {
			$this->expectException(ConfigurationParseException::class);
			new Manifest($iniFilePath);
		}
		finally {
			unlink($iniFilePath);
		}
	}

	public function testIterator():void {
		$jsonFile = "test/phpunit/Helper/Json/build.json";
		$jsonObj = json_decode(file_get_contents($jsonFile), true);
		$sut = new Manifest($jsonFile);
		/** @var TaskBlock $taskBlock */
		foreach($sut as $taskBlock) {
			$currentJsonObj = current($jsonObj);
			self::assertSame($currentJsonObj["name"], $taskBlock->getName());
			self::assertSame($currentJsonObj["execute"]["arguments"], $taskBlock->getExecuteBlock()->arguments);
			next($jsonObj);
		}
	}

	public function testIterator_mode():void {
		$jsonFile = "test/phpunit/Helper/Json/build.json";
		$jsonFileOther = "test/phpunit/Helper/Json/build.other-mode.json";
		$jsonObjOther = json_decode(file_get_contents($jsonFileOther), true);
		$jsonObj = json_decode(file_get_contents($jsonFile), true);
		$jsonObj = array_merge($jsonObj, $jsonObjOther);

		$sut = new Manifest($jsonFile, "other-mode");
		/** @var TaskBlock $taskBlock */
		foreach($sut as $taskBlock) {
			$currentJsonObj = current($jsonObj);
			self::assertSame($currentJsonObj["name"], $taskBlock->getName());
			self::assertSame($currentJsonObj["execute"]["arguments"], $taskBlock->getExecuteBlock()->arguments);
			next($jsonObj);
		}
	}

	public function testIterator_modeOnlyOverridesSingleProperty():void {
		$jsonFile = "test/phpunit/Helper/Json/build.json";
		$jsonObj = json_decode(file_get_contents($jsonFile), true);

		$sut = new Manifest($jsonFile, "single-property-override");
		/** @var TaskBlock $taskBlock */
		foreach($sut as $taskBlock) {
			$currentJsonObj = current($jsonObj);
			self::assertSame($currentJsonObj["name"], $taskBlock->getName());

			if($currentJsonObj["name"] === "Example dev TXT") {
				self::assertSame(["hello", "text", "single", "property"], $taskBlock->getExecuteBlock()->arguments);
			}
			else {
				self::assertSame($currentJsonObj["execute"]["arguments"], $taskBlock->getExecuteBlock()->arguments);
			}
			next($jsonObj);
		}
	}
}
