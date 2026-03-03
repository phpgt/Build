<?php
namespace Gt\Build\Configuration;

use Gt\Build\Requirement;

class RequireBlock {
	/** @var RequireBlockItem[] */
	protected $requireBlockItemList;

	public function __construct(object $commandVersionRequirements) {
		$this->requireBlockItemList = [];

		foreach($commandVersionRequirements as $command => $version) {
			$this->requireBlockItemList []= new RequireBlockItem(
				$command,
				$version
			);
		}
	}

	/** @return array<int, RequireBlockItem> */
	public function getAllRequireBlockItems():array {
		return $this->requireBlockItemList;
	}

	/** @return Requirement[] */
	public function getRequirementList():array {
		$list = [];

		foreach($this->requireBlockItemList as $item) {
			$list []= new Requirement(
				$item->command,
				$item->version
			);
		}

		return $list;
	}
}
