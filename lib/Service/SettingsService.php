<?php

declare(strict_types=1);

namespace OCA\Arcade\Service;

use OCA\Arcade\AppInfo\Application;
use OCA\Arcade\Controls;
use OCA\Arcade\CoreMap;
use OCA\Arcade\CoreOptions;
use OCP\IAppConfig;
use OCP\IConfig;

class SettingsService {
	/** The kinds of image a libretro thumbnail pack holds. */
	public const THUMBNAIL_TYPES = ['boxart', 'title', 'snap', 'logo'];

	/** How often a game may save itself, in seconds. 0 leaves it to you. */
	public const AUTOSAVE_INTERVALS = [0, 30, 60, 120, 300, 600];

	/** The folder settings an administrator can set for everyone. */
	public const INSTANCE_DEFAULTS = [
		'library_folder',
		'thumbnails_folder',
		'screenshots_folder',
		'saves_folder',
		'system_folder',
	];

	/**
	 * Settings are read on nearly every request, sometimes several times.
	 * They cannot change within one, so they are parsed once.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $settings = [];

	public function __construct(
		private IConfig $config,
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * The defaults of the app, with what the administrator set for the
	 * instance on top.
	 *
	 * @return array<string, mixed>
	 */
	public function getDefaults(): array {
		$defaults = $this->appDefaults();
		foreach (self::INSTANCE_DEFAULTS as $key) {
			$value = $this->appConfig->getValueString(Application::APP_ID, $key);
			if ($value !== '') {
				$defaults[$key] = $value;
			}
		}
		// The options of a core are the same for everybody playing it, and
		// so is the kind of picture a system is shown with.
		$defaults['core_options'] = $this->getCoreOptions();
		$defaults['thumbnail_types'] = $this->getThumbnailTypes();
		return $defaults;
	}

	/**
	 * @return array<string, string> system id => kind of image
	 */
	public function getThumbnailTypes(): array {
		$stored = $this->appConfig->getValueString(Application::APP_ID, 'thumbnail_types');
		if ($stored === '') {
			return [];
		}
		$types = json_decode($stored, true);
		return is_array($types) ? $this->sanitizeThumbnailTypes($types) : [];
	}

	/**
	 * @param array<string, mixed> $types
	 * @return array<string, string>
	 */
	private function sanitizeThumbnailTypes(array $types): array {
		$sanitized = [];
		foreach ($types as $system => $type) {
			if (isset(CoreMap::SYSTEMS[$system]) && is_string($type) && in_array($type, self::THUMBNAIL_TYPES, true)) {
				$sanitized[$system] = $type;
			}
		}
		return $sanitized;
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	public function getCoreOptions(): array {
		$stored = $this->appConfig->getValueString(Application::APP_ID, 'core_options');
		if ($stored === '') {
			return [];
		}
		$options = json_decode($stored, true);
		return is_array($options) ? $this->sanitizeCoreOptions($options) : [];
	}

	/**
	 * What an administrator can set for the instance.
	 *
	 * @return array<string, string>
	 */
	public function getInstanceDefaults(): array {
		$defaults = [];
		foreach (self::INSTANCE_DEFAULTS as $key) {
			$defaults[$key] = $this->appConfig->getValueString(Application::APP_ID, $key);
		}
		return $defaults;
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, string>
	 */
	public function setInstanceDefaults(array $settings): array {
		$sanitized = $this->sanitize($settings);
		foreach (self::INSTANCE_DEFAULTS as $key) {
			if (array_key_exists($key, $sanitized)) {
				$this->appConfig->setValueString(Application::APP_ID, $key, (string)$sanitized[$key]);
			}
		}
		foreach (['core_options', 'thumbnail_types'] as $key) {
			if (array_key_exists($key, $sanitized)) {
				$this->appConfig->setValueString(Application::APP_ID, $key, json_encode($sanitized[$key]));
			}
		}
		$this->settings = [];
		return $this->getInstanceDefaults();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function appDefaults(): array {
		return [
			'video_smooth' => false,
			'scale_integer' => false,
			'fastforward_ratio' => 3,
			'audio_volume' => 0,
			'audio_latency' => 64,
			'respond_to_global_events' => true,
			'pause_when_hidden' => true,
			'autosave_on_close' => true,
			'autoload_on_start' => false,
			'autosave_interval' => 0,
			'library_folder' => '/Games',
			'thumbnails_folder' => '',
			'screenshots_folder' => '',
			'saves_folder' => '',
			'system_folder' => '',
			'core_options' => [],
			'buttons' => Controls::defaultButtons(),
			'hotkeys' => Controls::defaultHotkeys(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getUserSettings(string $userId): array {
		return $this->settings[$userId] ??= $this->readUserSettings($userId);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function readUserSettings(string $userId): array {
		$defaults = $this->getDefaults();
		$stored = $this->config->getUserValue($userId, Application::APP_ID, 'settings', '');
		if ($stored === '') {
			return $defaults;
		}
		$settings = json_decode($stored, true);
		if (!is_array($settings)) {
			return $defaults;
		}
		return array_merge($defaults, $this->sanitize($settings));
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed> the sanitized, effective settings
	 */
	public function setUserSettings(string $userId, array $settings): array {
		$sanitized = $this->sanitize($settings);
		// Those belong to the instance, not to whoever is playing.
		unset($sanitized['core_options'], $sanitized['thumbnail_types']);
		$this->config->setUserValue($userId, Application::APP_ID, 'settings', json_encode($sanitized));
		unset($this->settings[$userId]);
		return $this->getUserSettings($userId);
	}

	/**
	 * Keep only known settings keys with valid values.
	 *
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function sanitize(array $settings): array {
		$sanitized = [];
		foreach ([
			'video_smooth',
			'scale_integer',
			'respond_to_global_events',
			'pause_when_hidden',
			'autosave_on_close',
			'autoload_on_start',
		] as $key) {
			if (array_key_exists($key, $settings)) {
				$sanitized[$key] = filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN);
			}
		}
		// Clamping can hand back the bound itself, so the types are forced
		// to stay the same.
		if (array_key_exists('fastforward_ratio', $settings) && is_numeric($settings['fastforward_ratio'])) {
			$sanitized['fastforward_ratio'] = (float)max(1, min(5, (float)$settings['fastforward_ratio']));
		}
		if (array_key_exists('audio_volume', $settings) && is_numeric($settings['audio_volume'])) {
			// RetroArch takes a gain in decibels, where 0 is as recorded.
			$sanitized['audio_volume'] = (float)max(-20, min(10, (float)$settings['audio_volume']));
		}
		if (array_key_exists('autosave_interval', $settings)
			&& is_numeric($settings['autosave_interval'])
			&& in_array((int)$settings['autosave_interval'], self::AUTOSAVE_INTERVALS, true)) {
			$sanitized['autosave_interval'] = (int)$settings['autosave_interval'];
		}
		if (array_key_exists('audio_latency', $settings) && is_numeric($settings['audio_latency'])) {
			$sanitized['audio_latency'] = max(16, min(256, (int)$settings['audio_latency']));
		}
		// An empty folder means the feature is disabled; the library folder
		// always has one.
		foreach (['library_folder', 'thumbnails_folder', 'screenshots_folder', 'saves_folder', 'system_folder'] as $key) {
			if (!array_key_exists($key, $settings) || !is_string($settings[$key])) {
				continue;
			}
			$folder = trim(trim($settings[$key]), '/');
			if ($folder === '' && $key !== 'library_folder') {
				$sanitized[$key] = '';
			} elseif ($folder !== '' && !str_contains($folder, '..')) {
				$sanitized[$key] = '/' . $folder;
			}
		}
		if (isset($settings['core_options']) && is_array($settings['core_options'])) {
			$sanitized['core_options'] = $this->sanitizeCoreOptions($settings['core_options']);
		}
		if (isset($settings['thumbnail_types']) && is_array($settings['thumbnail_types'])) {
			$sanitized['thumbnail_types'] = $this->sanitizeThumbnailTypes($settings['thumbnail_types']);
		}
		// A binding that is left out keeps whatever it had, so a page that
		// knows nothing of the keyboard cannot wipe it.
		if (isset($settings['buttons']) && is_array($settings['buttons'])) {
			$sanitized['buttons'] = array_merge(
				Controls::defaultButtons(),
				Controls::sanitize($settings['buttons'], Controls::BUTTONS),
			);
		}
		if (isset($settings['hotkeys']) && is_array($settings['hotkeys'])) {
			$sanitized['hotkeys'] = array_merge(
				Controls::defaultHotkeys(),
				Controls::sanitize($settings['hotkeys'], Controls::HOTKEYS),
			);
		}
		return $sanitized;
	}

	/**
	 * Keep only known cores, options and values. An empty value means the
	 * core decides, so it is dropped rather than stored.
	 *
	 * @param array<string, mixed> $coreOptions
	 * @return array<string, array<string, string>>
	 */
	private function sanitizeCoreOptions(array $coreOptions): array {
		$sanitized = [];
		foreach ($coreOptions as $core => $options) {
			if (!isset(CoreOptions::OPTIONS[$core]) || !is_array($options)) {
				continue;
			}
			foreach ($options as $key => $value) {
				if (isset(CoreOptions::OPTIONS[$core][$key])
					&& is_string($value)
					&& isset(CoreOptions::OPTIONS[$core][$key]['values'][$value])) {
					$sanitized[$core][$key] = $value;
				}
			}
		}
		return $sanitized;
	}
}
