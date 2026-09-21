<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Settings;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\CoreMap;
use OCA\Nostalgist\CoreOptions;
use OCA\Nostalgist\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

/**
 * The folders new users start with, and the options of the cores, which
 * hold for everybody playing them.
 */
class Admin implements ISettings {
	public function __construct(
		private SettingsService $settingsService,
	) {
	}

	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'nostalgist-settings');
		Util::addStyle(Application::APP_ID, 'settings');

		return new TemplateResponse(Application::APP_ID, 'admin', [
			'defaults' => $this->settingsService->getInstanceDefaults(),
			'coreOptions' => CoreOptions::OPTIONS,
			'systemsByCore' => CoreOptions::systemsByCore(),
			'storedCoreOptions' => $this->settingsService->getCoreOptions(),
			'systems' => CoreMap::SYSTEMS,
			'thumbnailTypes' => [
				'boxart' => 'Box art',
				'title' => 'Title screen',
				'snap' => 'Screenshot',
				'logo' => 'Logo',
			],
			'storedThumbnailTypes' => $this->settingsService->getThumbnailTypes(),
		]);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 50;
	}
}
