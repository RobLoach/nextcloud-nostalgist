<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\CoreMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CoreMapTest extends TestCase {
	public static function folderNames(): array {
		return [
			// Short names.
			['NES', 'nes'],
			['snes', 'snes'],
			['Super Nintendo', 'snes'],
			['Game Boy Color', 'gbc'],
			['32X', 'sega32x'],
			// No-Intro platform names, where the vendor comes first.
			['Nintendo - Nintendo Entertainment System', 'nes'],
			['Nintendo - Super Nintendo Entertainment System', 'snes'],
			['Nintendo - Game Boy Advance', 'gba'],
			['Sega - Mega Drive - Genesis', 'genesis'],
			['Sega - Master System - Mark III', 'sms'],
			['NEC - PC Engine - TurboGrafx 16', 'pce'],
			['SNK - Neo Geo Pocket', 'ngp'],
			['Bandai - WonderSwan', 'wonderswan'],
			['GCE - Vectrex', 'vectrex'],
			['Coleco - ColecoVision', 'coleco'],
			['Atari - Lynx', 'lynx'],
			// Not a system.
			['Games', null],
			['', null],
			['Roms', null],
		];
	}

	#[DataProvider('folderNames')]
	public function testSystemForFolderName(string $folder, ?string $expected): void {
		$this->assertSame($expected, CoreMap::systemForFolderName($folder));
	}

	public function testEverySystemIsComplete(): void {
		foreach (CoreMap::SYSTEMS as $id => $system) {
			foreach (['label', 'short', 'platform', 'mime', 'extensions', 'core', 'aliases'] as $key) {
				$this->assertArrayHasKey($key, $system, "$id misses $key");
			}
			$this->assertNotEmpty($system['extensions'], "$id has no extensions");
			$this->assertNotEmpty($system['core'], "$id has no core");
		}
	}

	public function testPlatformNamesResolveBackToTheirSystem(): void {
		foreach (CoreMap::SYSTEMS as $id => $system) {
			$this->assertSame(
				$id,
				CoreMap::systemForFolderName($system['platform']),
				"{$system['platform']} does not resolve back to $id",
			);
		}
	}

	public function testExtensionsAreUnique(): void {
		$seen = [];
		foreach (CoreMap::SYSTEMS as $id => $system) {
			foreach ($system['extensions'] as $extension) {
				$this->assertArrayNotHasKey($extension, $seen, "$extension is claimed twice");
				$seen[$extension] = $id;
			}
		}
	}

	public function testMapsCoverEveryExtension(): void {
		$mimes = CoreMap::extensionMimeMap();
		$systems = CoreMap::extensionSystemMap();
		foreach (CoreMap::SYSTEMS as $id => $system) {
			foreach ($system['extensions'] as $extension) {
				$this->assertSame($system['mime'], $mimes[$extension] ?? null);
				$this->assertSame($id, $systems[$extension] ?? null);
			}
		}
	}
}
