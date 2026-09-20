<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Service;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\CoreMap;
use OCP\IConfig;

class SettingsService {
	public function __construct(
		private IConfig $config,
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getDefaults(): array {
		return [
			'cores' => CoreMap::defaultCores(),
			'video_smooth' => false,
			'fastforward_ratio' => 10,
			'respond_to_global_events' => true,
			'library_folder' => '/Games',
			'thumbnails_folder' => '',
			'screenshots_folder' => '',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getUserSettings(string $userId): array {
		$defaults = $this->getDefaults();
		$stored = $this->config->getUserValue($userId, Application::APP_ID, 'settings', '');
		if ($stored === '') {
			return $defaults;
		}
		$settings = json_decode($stored, true);
		if (!is_array($settings)) {
			return $defaults;
		}
		$settings = $this->sanitize($settings);
		$settings['cores'] = array_merge($defaults['cores'], $settings['cores'] ?? []);
		return array_merge($defaults, $settings);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed> the sanitized, effective settings
	 */
	public function setUserSettings(string $userId, array $settings): array {
		$sanitized = $this->sanitize($settings);
		$this->config->setUserValue($userId, Application::APP_ID, 'settings', json_encode($sanitized));
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
		foreach (['video_smooth', 'respond_to_global_events'] as $key) {
			if (array_key_exists($key, $settings)) {
				$sanitized[$key] = filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN);
			}
		}
		if (array_key_exists('fastforward_ratio', $settings) && is_numeric($settings['fastforward_ratio'])) {
			// 0 means unlimited in RetroArch.
			$sanitized['fastforward_ratio'] = max(0, min(50, (float)$settings['fastforward_ratio']));
		}
		// An empty folder means the feature is disabled; the library folder
		// always has one.
		foreach (['library_folder', 'thumbnails_folder', 'screenshots_folder'] as $key) {
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
		if (isset($settings['cores']) && is_array($settings['cores'])) {
			$cores = [];
			foreach ($settings['cores'] as $system => $core) {
				if (isset(CoreMap::SYSTEMS[$system]) && in_array($core, CoreMap::SYSTEMS[$system]['cores'], true)) {
					$cores[$system] = $core;
				}
			}
			$sanitized['cores'] = $cores;
		}
		return $sanitized;
	}
}
