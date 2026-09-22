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
			// The maker in front, with or without the dashes.
			['Nintendo Game Boy Advance', 'gba'],
			['Sega Genesis', 'genesis'],
			['SNK Neo Geo Pocket', 'ngp'],
			['NEC TurboGrafx-16', 'pce'],
			['Bandai WonderSwan Color', 'wonderswan'],
			// A word hung off the end, or the front.
			['SNES Roms', 'snes'],
			['NES Games', 'nes'],
			['Game Boy Color ROMs', 'gbc'],
			['My GBA Games', 'gba'],
			['Virtual Boy Games', 'virtualboy'],
			['Master System Collection', 'sms'],
			// "Game" in the middle of a name is the system, not padding.
			['Game Gear', 'gamegear'],
			['Nintendo Game Boy', 'gb'],
			// Not a system.
			['Games', null],
			['Advance Wars', null],
			['Nintendo', null],
			['Downloads', null],
			['Unsorted', null],
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

	public function testAnAmbiguousExtensionIsClaimedByNobody(): void {
		// A .bin is a Mega Drive game, a 32X game, a ColecoVision game or
		// something else entirely, so no system may have it.
		$claimed = CoreMap::extensionSystemMap();
		foreach (CoreMap::AMBIGUOUS as $extension) {
			$this->assertTrue(CoreMap::isAmbiguous($extension));
			$this->assertArrayNotHasKey($extension, $claimed, "$extension cannot belong to one system");
		}
	}

	public function testALibraryListsWhatItCannotPlaceYet(): void {
		$extensions = CoreMap::libraryExtensions();
		$this->assertSame('nes', $extensions['nes'] ?? null, 'a name that says which system');
		foreach ([...CoreMap::AMBIGUOUS, 'zip'] as $extension) {
			$this->assertSame('', $extensions[$extension] ?? null, "$extension is listed, unplaced");
		}
	}

	public function testAZipTakesTheSystemOfItsFolder(): void {
		// The library and the player both go by the folder for an archive:
		// nothing outside it says what is in it.
		$this->assertSame('genesis', CoreMap::systemForPath('/Games/MegaDrive/Sonic.zip'));
		$this->assertSame('genesis', CoreMap::systemForPath('/Games/Sega - Mega Drive - Genesis/Sonic.zip'));
		$this->assertSame('snes', CoreMap::systemForPath('/Games/SNES Roms/NHL 96.zip'));
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
