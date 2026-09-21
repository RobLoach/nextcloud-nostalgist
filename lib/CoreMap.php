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
			'cores' => ['fceumm', 'nestopia', 'quicknes'],
			'aliases' => ['nes', 'famicom', 'fc', 'nintendoentertainmentsystem', 'fds', 'famicomdisksystem', 'familycomputerdisksystem'],
		],
		'snes' => [
			'label' => 'Super Nintendo',
			'short' => 'Super Nintendo',
			'platform' => 'Nintendo - Super Nintendo Entertainment System',
			'mime' => 'application/x-snes-rom',
			'extensions' => ['sfc', 'smc'],
			'cores' => ['snes9x', 'snes9x2010', 'snes9x2005', 'snes9x2002'],
			'aliases' => ['snes', 'sfc', 'superfamicom', 'supernintendo', 'supernes', 'supernintendoentertainmentsystem'],
		],
		'gb' => [
			'label' => 'Game Boy',
			'short' => 'Game Boy',
			'platform' => 'Nintendo - Game Boy',
			'mime' => 'application/x-gameboy-rom',
			'extensions' => ['gb'],
			'cores' => ['gambatte', 'gearboy', 'tgbdual', 'mgba'],
			'aliases' => ['gb', 'gameboy'],
		],
		'gbc' => [
			'label' => 'Game Boy Color',
			'short' => 'Game Boy Color',
			'platform' => 'Nintendo - Game Boy Color',
			'mime' => 'application/x-gameboy-color-rom',
			'extensions' => ['gbc'],
			'cores' => ['gambatte', 'gearboy', 'tgbdual', 'mgba'],
			'aliases' => ['gbc', 'gameboycolor'],
		],
		'gba' => [
			'label' => 'Game Boy Advance',
			'short' => 'Game Boy Advance',
			'platform' => 'Nintendo - Game Boy Advance',
			'mime' => 'application/x-gba-rom',
			'extensions' => ['gba'],
			'cores' => ['mgba', 'vba_next'],
			'aliases' => ['gba', 'gameboyadvance'],
		],
		'genesis' => [
			'label' => 'Sega Genesis / Mega Drive',
			'short' => 'Genesis',
			'platform' => 'Sega - Mega Drive - Genesis',
			'mime' => 'application/x-genesis-rom',
			'extensions' => ['md', 'gen', 'smd'],
			'cores' => ['genesis_plus_gx', 'picodrive'],
			'aliases' => ['genesis', 'gen', 'md', 'megadrive', 'segagenesis', 'segamegadrive'],
		],
		'sms' => [
			'label' => 'Sega Master System',
			'short' => 'Master System',
			'platform' => 'Sega - Master System - Mark III',
			'mime' => 'application/x-sms-rom',
			'extensions' => ['sms'],
			'cores' => ['genesis_plus_gx', 'gearsystem', 'picodrive'],
			'aliases' => ['sms', 'mastersystem', 'segamastersystem', 'markiii', 'mark3'],
		],
		'gamegear' => [
			'label' => 'Sega Game Gear',
			'short' => 'Game Gear',
			'platform' => 'Sega - Game Gear',
			'mime' => 'application/x-gamegear-rom',
			'extensions' => ['gg'],
			'cores' => ['genesis_plus_gx', 'gearsystem'],
			'aliases' => ['gg', 'gamegear', 'segagamegear'],
		],
		'sega32x' => [
			'label' => 'Sega 32X',
			'short' => '32X',
			'platform' => 'Sega - 32X',
			'mime' => 'application/x-sega-32x-rom',
			'extensions' => ['32x'],
			'cores' => ['picodrive'],
			'aliases' => ['32x', 'sega32x'],
		],
		'pce' => [
			'label' => 'PC Engine / TurboGrafx-16',
			'short' => 'TurboGrafx-16',
			'platform' => 'NEC - PC Engine - TurboGrafx 16',
			'mime' => 'application/x-pc-engine-rom',
			'extensions' => ['pce'],
			'cores' => ['mednafen_pce_fast', 'geargrafx'],
			'aliases' => ['pce', 'pcengine', 'turbografx', 'turbografx16', 'tg16'],
		],
		'lynx' => [
			'label' => 'Atari Lynx',
			'short' => 'Lynx',
			'platform' => 'Atari - Lynx',
			'mime' => 'application/x-lynx-rom',
			'extensions' => ['lnx'],
			'cores' => ['handy', 'mednafen_lynx'],
			'aliases' => ['lynx', 'atarilynx'],
		],
		'ngp' => [
			'label' => 'Neo Geo Pocket',
			'short' => 'Neo Geo Pocket',
			'platform' => 'SNK - Neo Geo Pocket',
			'mime' => 'application/x-neo-geo-pocket-rom',
			'extensions' => ['ngp', 'ngc'],
			'cores' => ['mednafen_ngp'],
			'aliases' => ['ngp', 'ngpc', 'neogeopocket', 'neogeopocketcolor'],
		],
		'wonderswan' => [
			'label' => 'WonderSwan',
			'short' => 'WonderSwan',
			'platform' => 'Bandai - WonderSwan',
			'mime' => 'application/x-wonderswan-rom',
			'extensions' => ['ws', 'wsc'],
			'cores' => ['mednafen_wswan'],
			'aliases' => ['ws', 'wsc', 'wonderswan', 'wonderswancolor'],
		],
		'virtualboy' => [
			'label' => 'Virtual Boy',
			'short' => 'Virtual Boy',
			'platform' => 'Nintendo - Virtual Boy',
			'mime' => 'application/x-virtual-boy-rom',
			'extensions' => ['vb'],
			'cores' => ['mednafen_vb'],
			'aliases' => ['vb', 'virtualboy'],
		],
		'vectrex' => [
			'label' => 'Vectrex',
			'short' => 'Vectrex',
			'platform' => 'GCE - Vectrex',
			'mime' => 'application/x-vectrex-rom',
			'extensions' => ['vec'],
			'cores' => ['vecx'],
			'aliases' => ['vectrex'],
		],
		'coleco' => [
			'label' => 'ColecoVision',
			'short' => 'ColecoVision',
			'platform' => 'Coleco - ColecoVision',
			'mime' => 'application/x-colecovision-rom',
			'extensions' => ['col'],
			'cores' => ['gearcoleco'],
			'aliases' => ['coleco', 'colecovision'],
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

	/**
	 * @return array<string, string> system id => default core
	 */
	public static function defaultCores(): array {
		$cores = [];
		foreach (self::SYSTEMS as $id => $system) {
			$cores[$id] = $system['cores'][0];
		}
		return $cores;
	}
}
