<?php
namespace GT\Build\Test;

use GT\Build\Requirement;
use GT\Build\RequirementMissingException;
use PHPUnit\Framework\TestCase;

class RequirementTest extends TestCase {
	public function testCheckReturnsTrueWhenInstalledVersionSatisfiesConstraint():void {
		$sut = new Requirement(PHP_BINARY, ">=8.0");

		self::assertTrue($sut->check());
	}

	public function testCheckReturnsFalseWhenInstalledVersionDoesNotSatisfyConstraint():void {
		$sut = new Requirement(PHP_BINARY, ">99.0");

		self::assertFalse($sut->check());
	}

	public function testCheckCollectsMissingRequirementErrorWhenErrorsArrayProvided():void {
		$errors = [];
		$sut = new Requirement("phpgt-command-that-does-not-exist", ">0.0.0");

		$sut->check($errors);
		self::assertSame(
			["Requirement missing: phpgt-command-that-does-not-exist"],
			$errors,
		);
	}

	public function testCheckThrowsWhenRequirementIsMissingAndNoErrorArrayProvided():void {
		$sut = new Requirement("phpgt-command-that-does-not-exist", "*");

		$this->expectException(RequirementMissingException::class);
		$sut->check();
	}
}
