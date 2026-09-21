<?php

declare(strict_types=1);

namespace OCA\Nostalgist;

/**
 * Maps retro console systems to their file extensions, mimetypes and
 * available libretro cores from retroarch-emscripten-build.
 */
class CoreMap {
	/**
	 * The first core listed for each system is the default. Aliases are
	 * normalized folder names (lowercase, alphanumeric only) used to detect
	 * the system from the folder a game is stored in.
	 */
	public const SYSTEMS = [
		'nes' => [
			'label' => 'Nintendo Entertainment System',
			'short' => 'Nintendo',
			'platform' => 'Nintendo - Nintendo Entertainment System',
			'mime' => 'application/x-nes-rom',
			'extensions' => ['nes', 'fds', 'unf', 'unif'],
			'core' => 'fceumm',
			'aliases' => ['nes', 'famicom', 'fc', 'nintendoentertainmentsystem', 'fds', 'famicomdisksystem', 'familycomputerdisksystem'],
			'bios' => [],
		],
		'snes' => [
			'label' => 'Super Nintendo',
			'short' => 'Super Nintendo',
			'platform' => 'Nintendo - Super Nintendo Entertainment System',
			'mime' => 'application/x-snes-rom',
			'extensions' => ['sfc', 'smc'],
			'core' => 'snes9x',
			'aliases' => ['snes', 'sfc', 'superfamicom', 'supernintendo', 'supernes', 'supernintendoentertainmentsystem'],
			'bios' => [],
		],
		'gb' => [
			'label' => 'Game Boy',
			'short' => 'Game Boy',
			'platform' => 'Nintendo - Game Boy',
			'mime' => 'application/x-gameboy-rom',
			'extensions' => ['gb'],
			'core' => 'gambatte',
			'aliases' => ['gb', 'gameboy'],
			'bios' => ['gb_bios.bin'],
		],
		'gbc' => [
			'label' => 'Game Boy Color',
			'short' => 'Game Boy Color',
			'platform' => 'Nintendo - Game Boy Color',
			'mime' => 'application/x-gameboy-color-rom',
			'extensions' => ['gbc'],
			'core' => 'gambatte',
			'aliases' => ['gbc', 'gameboycolor'],
			'bios' => ['gbc_bios.bin'],
		],
		'gba' => [
			'label' => 'Game Boy Advance',
			'short' => 'Game Boy Advance',
			'platform' => 'Nintendo - Game Boy Advance',
			'mime' => 'application/x-gba-rom',
			'extensions' => ['gba'],
			'core' => 'mgba',
			'aliases' => ['gba', 'gameboyadvance'],
			'bios' => ['gba_bios.bin'],
		],
		'genesis' => [
			'label' => 'Sega Genesis / Mega Drive',
			'short' => 'Genesis',
			'platform' => 'Sega - Mega Drive - Genesis',
			'mime' => 'application/x-genesis-rom',
			'extensions' => ['md', 'gen', 'smd'],
			'core' => 'genesis_plus_gx',
			'aliases' => ['genesis', 'gen', 'md', 'megadrive', 'segagenesis', 'segamegadrive'],
			'bios' => ['bios_MD.bin'],
		],
		'sms' => [
			'label' => 'Sega Master System',
			'short' => 'Master System',
			'platform' => 'Sega - Master System - Mark III',
			'mime' => 'application/x-sms-rom',
			'extensions' => ['sms'],
			'core' => 'genesis_plus_gx',
			'aliases' => ['sms', 'mastersystem', 'segamastersystem', 'markiii', 'mark3'],
			'bios' => ['bios.sms'],
		],
		'gamegear' => [
			'label' => 'Sega Game Gear',
			'short' => 'Game Gear',
			'platform' => 'Sega - Game Gear',
			'mime' => 'application/x-gamegear-rom',
			'extensions' => ['gg'],
			'core' => 'genesis_plus_gx',
			'aliases' => ['gg', 'gamegear', 'segagamegear'],
			'bios' => ['bios.gg'],
		],
		'sega32x' => [
			'label' => 'Sega 32X',
			'short' => '32X',
			'platform' => 'Sega - 32X',
			'mime' => 'application/x-sega-32x-rom',
			'extensions' => ['32x'],
			'core' => 'picodrive',
			'aliases' => ['32x', 'sega32x'],
			'bios' => ['32X_G_BIOS.BIN', '32X_M_BIOS.BIN', '32X_S_BIOS.BIN'],
		],
		'pce' => [
			'label' => 'PC Engine / TurboGrafx-16',
			'short' => 'TurboGrafx-16',
			'platform' => 'NEC - PC Engine - TurboGrafx 16',
			'mime' => 'application/x-pc-engine-rom',
			'extensions' => ['pce'],
			'core' => 'mednafen_pce_fast',
			'aliases' => ['pce', 'pcengine', 'turbografx', 'turbografx16', 'tg16'],
			'bios' => ['syscard3.pce'],
		],
		'lynx' => [
			'label' => 'Atari Lynx',
			'short' => 'Lynx',
			'platform' => 'Atari - Lynx',
			'mime' => 'application/x-lynx-rom',
			'extensions' => ['lnx'],
			'core' => 'handy',
			'aliases' => ['lynx', 'atarilynx'],
			'bios' => ['lynxboot.img'],
		],
		'ngp' => [
			'label' => 'Neo Geo Pocket',
			'short' => 'Neo Geo Pocket',
			'platform' => 'SNK - Neo Geo Pocket',
			'mime' => 'application/x-neo-geo-pocket-rom',
			'extensions' => ['ngp', 'ngc'],
			'core' => 'mednafen_ngp',
			'aliases' => ['ngp', 'ngpc', 'neogeopocket', 'neogeopocketcolor'],
			'bios' => [],
		],
		'wonderswan' => [
			'label' => 'WonderSwan',
			'short' => 'WonderSwan',
			'platform' => 'Bandai - WonderSwan',
			'mime' => 'application/x-wonderswan-rom',
			'extensions' => ['ws', 'wsc'],
			'core' => 'mednafen_wswan',
			'aliases' => ['ws', 'wsc', 'wonderswan', 'wonderswancolor'],
			'bios' => [],
		],
		'virtualboy' => [
			'label' => 'Virtual Boy',
			'short' => 'Virtual Boy',
			'platform' => 'Nintendo - Virtual Boy',
			'mime' => 'application/x-virtual-boy-rom',
			'extensions' => ['vb'],
			'core' => 'mednafen_vb',
			'aliases' => ['vb', 'virtualboy'],
			'bios' => [],
		],
		'vectrex' => [
			'label' => 'Vectrex',
			'short' => 'Vectrex',
			'platform' => 'GCE - Vectrex',
			'mime' => 'application/x-vectrex-rom',
			'extensions' => ['vec'],
			'core' => 'vecx',
			'aliases' => ['vectrex'],
			'bios' => [],
		],
		'coleco' => [
			'label' => 'ColecoVision',
			'short' => 'ColecoVision',
			'platform' => 'Coleco - ColecoVision',
			'mime' => 'application/x-colecovision-rom',
			'extensions' => ['col'],
			'core' => 'gearcoleco',
			'aliases' => ['coleco', 'colecovision'],
			'bios' => ['colecovision.rom'],
		],
	];

	/**
	 * Detect the system from a folder name like "SNES", "Super Nintendo",
	 * or a No-Intro platform name like
	 * "Nintendo - Super Nintendo Entertainment System", whose dash-separated
	 * segments are matched individually.
	 */
	public static function systemForFolderName(string $name): ?string {
		$candidates = [$name, ...(preg_split('/\s*[-–]\s*/', $name) ?: [])];
		foreach ($candidates as $candidate) {
			$normalized = preg_replace('/[^a-z0-9]/', '', strtolower($candidate));
			if ($normalized === '') {
				continue;
			}
			foreach (self::SYSTEMS as $id => $system) {
				if ($normalized === $id || in_array($normalized, $system['aliases'], true)) {
					return $id;
				}
			}
		}
		return null;
	}

	/**
	 * The system of a game at a path: from its extension, or from the
	 * folders it is stored in when the extension does not tell, as for
	 * zipped ROMs. Null when nothing says.
	 */
	public static function systemForPath(string $path): ?string {
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		$system = self::extensionSystemMap()[$extension] ?? null;
		if ($system !== null) {
			return $system;
		}
		$folders = array_slice(explode('/', trim($path, '/')), 0, -1);
		foreach (array_reverse($folders) as $folder) {
			$fromFolder = self::systemForFolderName($folder);
			if ($fromFolder !== null) {
				return $fromFolder;
			}
		}
		return null;
	}

	/**
	 * The short name of the system of a game, for the folder its saves and
	 * screenshots are filed under. Empty when the system is unknown, so
	 * that those stay where they always were.
	 */
	public static function shortNameForPath(string $path): string {
		$system = self::systemForPath($path);
		return $system === null ? '' : self::SYSTEMS[$system]['short'];
	}

	/**
	 * The BIOS files a system may ask for.
	 *
	 * @return list<string>
	 */
	public static function biosFor(string $systemId): array {
		return self::SYSTEMS[$systemId]['bios'] ?? [];
	}

	/**
	 * @return array<string, string> extension => mimetype
	 */
	public static function extensionMimeMap(): array {
		$map = [];
		foreach (self::SYSTEMS as $system) {
			foreach ($system['extensions'] as $extension) {
				$map[$extension] = $system['mime'];
			}
		}
		return $map;
	}

	/**
	 * @return array<string, string> extension => system id
	 */
	public static function extensionSystemMap(): array {
		$map = [];
		foreach (self::SYSTEMS as $id => $system) {
			foreach ($system['extensions'] as $extension) {
				$map[$extension] = $id;
			}
		}
		return $map;
	}

}
