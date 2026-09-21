<?php

declare(strict_types=1);

namespace OCA\Nostalgist;

/**
 * A curated set of libretro core options, offered per core in the personal
 * settings and passed to RetroArch when a game starts.
 *
 * Every option keeps an empty value meaning "leave it to the core", so the
 * defaults of the cores stay in charge unless somebody picks otherwise.
 * Options a core does not know are ignored by RetroArch.
 */
class CoreOptions {
	/**
	 * core => list of options, each with the RetroArch key, a label, and
	 * the values to choose from with their labels.
	 */
	public const OPTIONS = [
		'fceumm' => [
			'fceumm_palette' => [
				'label' => 'Color palette',
				'values' => [
					'default' => 'FCEUmm default',
					'nintendo-vc' => 'Nintendo Virtual Console',
					'wavebeam' => 'Wavebeam',
					'nes-classic-fbx-fs' => 'NES Classic',
					'raw' => 'Raw',
				],
			],
			'fceumm_region' => [
				'label' => 'Region',
				'values' => [
					'Auto' => 'Auto',
					'NTSC' => 'NTSC',
					'PAL' => 'PAL',
					'Dendy' => 'Dendy',
				],
			],
			'fceumm_nospritelimit' => [
				'label' => 'Remove sprite limit',
				'values' => ['disabled' => 'No', 'enabled' => 'Yes'],
			],
		],
		'snes9x' => [
			'snes9x_region' => [
				'label' => 'Region',
				'values' => ['auto' => 'Auto', 'ntsc' => 'NTSC', 'pal' => 'PAL'],
			],
			'snes9x_overscan' => [
				'label' => 'Overscan',
				'values' => ['auto' => 'Auto', 'enabled' => 'Show', 'disabled' => 'Crop'],
			],
			'snes9x_reduce_sprite_flicker' => [
				'label' => 'Reduce sprite flicker',
				'values' => ['disabled' => 'No', 'enabled' => 'Yes'],
			],
			'snes9x_hires_blend' => [
				'label' => 'Blend high resolution modes',
				'values' => ['disabled' => 'No', 'merge' => 'Merge', 'blur' => 'Blur'],
			],
		],
		'gambatte' => [
			'gambatte_gb_hwmode' => [
				'label' => 'Hardware mode',
				'values' => ['Auto' => 'Auto', 'GB' => 'Game Boy', 'GBC' => 'Game Boy Color', 'GBA' => 'Game Boy Advance'],
			],
			'gambatte_gb_colorization' => [
				'label' => 'Colorize Game Boy games',
				'values' => [
					'disabled' => 'No',
					'auto' => 'Auto',
					'internal' => 'Chosen palette below',
					'GBC' => 'Game Boy Color',
					'SGB' => 'Super Game Boy',
				],
			],
			'gambatte_gb_internal_palette' => [
				'label' => 'Palette for colorized games',
				'values' => [
					'GB - DMG' => 'Game Boy',
					'GB - Pocket' => 'Game Boy Pocket',
					'GB - Light' => 'Game Boy Light',
					'GBC - Blue' => 'Blue',
					'GBC - Brown' => 'Brown',
					'GBC - Green' => 'Green',
					'GBC - Red' => 'Red',
					'GBC - Yellow' => 'Yellow',
				],
			],
			'gambatte_mix_frames' => [
				'label' => 'Blend frames',
				'values' => [
					'disabled' => 'No',
					'accurate' => 'Accurate',
					'fast' => 'Fast',
					'lcd_ghosting' => 'LCD ghosting',
					'lcd_ghosting_fast' => 'LCD ghosting, fast',
				],
			],
		],
		'mgba' => [
			'mgba_color_correction' => [
				'label' => 'Color correction',
				'values' => ['OFF' => 'No', 'GBA' => 'Game Boy Advance', 'GBC' => 'Game Boy Color', 'Auto' => 'Auto'],
			],
			'mgba_interframe_blending' => [
				'label' => 'Blend frames',
				'values' => [
					'OFF' => 'No',
					'mix' => 'Mix',
					'mix_smart' => 'Mix, smart',
					'lcd_ghosting' => 'LCD ghosting',
					'lcd_ghosting_fast' => 'LCD ghosting, fast',
				],
			],
			'mgba_frameskip' => [
				'label' => 'Frame skipping',
				'values' => ['disabled' => 'No', 'auto' => 'Auto'],
			],
			'mgba_solar_sensor_level' => [
				'label' => 'Solar sensor level',
				'values' => ['0' => '0, darkest', '3' => '3', '5' => '5', '10' => '10, brightest'],
			],
		],
		'genesis_plus_gx' => [
			'genesis_plus_gx_region_detect' => [
				'label' => 'Region',
				'values' => ['auto' => 'Auto', 'ntsc-u' => 'US', 'pal' => 'Europe', 'ntsc-j' => 'Japan'],
			],
			'genesis_plus_gx_overscan' => [
				'label' => 'Borders',
				'values' => [
					'disabled' => 'Hide',
					'top/bottom' => 'Top and bottom',
					'left/right' => 'Left and right',
					'full' => 'All',
				],
			],
			'genesis_plus_gx_no_sprite_limit' => [
				'label' => 'Remove sprite limit',
				'values' => ['disabled' => 'No', 'enabled' => 'Yes'],
			],
			'genesis_plus_gx_blargg_ntsc_filter' => [
				'label' => 'NTSC video filter',
				'values' => [
					'disabled' => 'No',
					'composite' => 'Composite',
					'svideo' => 'S-Video',
					'rgb' => 'RGB',
					'monochrome' => 'Monochrome',
				],
			],
			'genesis_plus_gx_lcd_filter' => [
				'label' => 'LCD ghosting filter, handhelds',
				'values' => ['disabled' => 'No', 'enabled' => 'Yes'],
			],
		],
		'picodrive' => [
			'picodrive_region' => [
				'label' => 'Region',
				'values' => [
					'Auto' => 'Auto',
					'US' => 'US',
					'Europe' => 'Europe',
					'Japan NTSC' => 'Japan NTSC',
					'Japan PAL' => 'Japan PAL',
				],
			],
			'picodrive_sprlim' => [
				'label' => 'Remove sprite limit',
				'values' => ['disabled' => 'No', 'enabled' => 'Yes'],
			],
			'picodrive_audio_filter' => [
				'label' => 'Audio filter',
				'values' => ['off' => 'No', 'low-pass' => 'Low pass'],
			],
		],
		'mednafen_pce_fast' => [
			'pce_nospritelimit' => [
				'label' => 'Remove sprite limit',
				'values' => ['disabled' => 'No', 'enabled' => 'Yes'],
			],
			'pce_ocmultiplier' => [
				'label' => 'Processor overclock',
				'values' => ['1' => 'None', '2' => '2x', '3' => '3x'],
			],
		],
		'handy' => [
			'handy_rot' => [
				'label' => 'Screen rotation',
				'values' => ['None' => 'None', '90' => '90°', '180' => '180°', '270' => '270°'],
			],
			'handy_gfx_colors' => [
				'label' => 'Color depth',
				'values' => ['16bit' => '16 bit', '24bit' => '24 bit'],
			],
		],
		'mednafen_ngp' => [
			'ngp_language' => [
				'label' => 'Language',
				'values' => ['english' => 'English', 'japanese' => 'Japanese'],
			],
		],
		'mednafen_wswan' => [
			'wswan_rotate_display' => [
				'label' => 'Screen rotation',
				'values' => ['manual' => 'Manual', 'landscape' => 'Landscape', 'portrait' => 'Portrait'],
			],
		],
		'mednafen_vb' => [
			'vb_3dmode' => [
				'label' => '3D mode',
				'values' => [
					'anaglyph' => 'Anaglyph',
					'cyberscope' => 'CyberScope',
					'side-by-side' => 'Side by side',
					'vli' => 'Vertical line interlaced',
					'hli' => 'Horizontal line interlaced',
				],
			],
			'vb_anaglyph_preset' => [
				'label' => 'Anaglyph colors',
				'values' => [
					'disabled' => 'No',
					'red & blue' => 'Red and blue',
					'red & cyan' => 'Red and cyan',
					'red & electric cyan' => 'Red and electric cyan',
					'red & green' => 'Red and green',
					'green & magenta' => 'Green and magenta',
					'yellow & blue' => 'Yellow and blue',
				],
			],
		],
		'vecx' => [
			'vecx_res_multi' => [
				'label' => 'Resolution multiplier',
				'values' => ['1' => '1x', '2' => '2x', '3' => '3x', '4' => '4x'],
			],
		],
		'gearcoleco' => [
			'gearcoleco_timing' => [
				'label' => 'Region',
				'values' => ['Auto' => 'Auto', 'NTSC (60 Hz)' => 'NTSC', 'PAL (50 Hz)' => 'PAL'],
			],
			'gearcoleco_no_sprite_limit' => [
				'label' => 'Remove sprite limit',
				'values' => ['Disabled' => 'No', 'Enabled' => 'Yes'],
			],
			'gearcoleco_up_down_allowed' => [
				'label' => 'Allow opposite directions at once',
				'values' => ['Disabled' => 'No', 'Enabled' => 'Yes'],
			],
		],
	];

	/**
	 * The systems each core runs, to label the settings.
	 *
	 * @return array<string, list<string>> core => system names
	 */
	public static function systemsByCore(): array {
		$systems = [];
		foreach (CoreMap::SYSTEMS as $system) {
			$systems[$system['core']][] = $system['short'];
		}
		return $systems;
	}
}
