<?php

declare(strict_types=1);

namespace OCA\Nostalgist;

/**
 * Maps retro console systems to their file extensions, mimetypes and
 * available libretro cores from retroarch-emscripten-build.
 */
class CoreMap {
	/**
	 * The first core listed for each system is the default.
	 */
	public const SYSTEMS = [
		'nes' => [
			'label' => 'Nintendo Entertainment System',
			'mime' => 'application/x-nes-rom',
			'extensions' => ['nes', 'fds', 'unf', 'unif'],
			'cores' => ['fceumm', 'nestopia', 'quicknes'],
		],
		'snes' => [
			'label' => 'Super Nintendo',
			'mime' => 'application/x-snes-rom',
			'extensions' => ['sfc', 'smc'],
			'cores' => ['snes9x', 'snes9x2010', 'snes9x2005', 'snes9x2002'],
		],
		'gb' => [
			'label' => 'Game Boy',
			'mime' => 'application/x-gameboy-rom',
			'extensions' => ['gb'],
			'cores' => ['gambatte', 'gearboy', 'tgbdual', 'mgba'],
		],
		'gbc' => [
			'label' => 'Game Boy Color',
			'mime' => 'application/x-gameboy-color-rom',
			'extensions' => ['gbc'],
			'cores' => ['gambatte', 'gearboy', 'tgbdual', 'mgba'],
		],
		'gba' => [
			'label' => 'Game Boy Advance',
			'mime' => 'application/x-gba-rom',
			'extensions' => ['gba'],
			'cores' => ['mgba', 'vba_next'],
		],
		'genesis' => [
			'label' => 'Sega Genesis / Mega Drive',
			'mime' => 'application/x-genesis-rom',
			'extensions' => ['md', 'gen', 'smd'],
			'cores' => ['genesis_plus_gx', 'picodrive'],
		],
		'sms' => [
			'label' => 'Sega Master System',
			'mime' => 'application/x-sms-rom',
			'extensions' => ['sms'],
			'cores' => ['genesis_plus_gx', 'gearsystem', 'picodrive'],
		],
		'gamegear' => [
			'label' => 'Sega Game Gear',
			'mime' => 'application/x-gamegear-rom',
			'extensions' => ['gg'],
			'cores' => ['genesis_plus_gx', 'gearsystem'],
		],
		'sega32x' => [
			'label' => 'Sega 32X',
			'mime' => 'application/x-sega-32x-rom',
			'extensions' => ['32x'],
			'cores' => ['picodrive'],
		],
		'pce' => [
			'label' => 'PC Engine / TurboGrafx-16',
			'mime' => 'application/x-pc-engine-rom',
			'extensions' => ['pce'],
			'cores' => ['mednafen_pce_fast', 'geargrafx'],
		],
		'lynx' => [
			'label' => 'Atari Lynx',
			'mime' => 'application/x-lynx-rom',
			'extensions' => ['lnx'],
			'cores' => ['handy', 'mednafen_lynx'],
		],
		'ngp' => [
			'label' => 'Neo Geo Pocket',
			'mime' => 'application/x-neo-geo-pocket-rom',
			'extensions' => ['ngp', 'ngc'],
			'cores' => ['mednafen_ngp'],
		],
		'wonderswan' => [
			'label' => 'WonderSwan',
			'mime' => 'application/x-wonderswan-rom',
			'extensions' => ['ws', 'wsc'],
			'cores' => ['mednafen_wswan'],
		],
		'virtualboy' => [
			'label' => 'Virtual Boy',
			'mime' => 'application/x-virtual-boy-rom',
			'extensions' => ['vb'],
			'cores' => ['mednafen_vb'],
		],
		'vectrex' => [
			'label' => 'Vectrex',
			'mime' => 'application/x-vectrex-rom',
			'extensions' => ['vec'],
			'cores' => ['vecx'],
		],
		'coleco' => [
			'label' => 'ColecoVision',
			'mime' => 'application/x-colecovision-rom',
			'extensions' => ['col'],
			'cores' => ['gearcoleco'],
		],
	];

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
