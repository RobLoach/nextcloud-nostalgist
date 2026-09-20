<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Settings;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\CoreMap;
use OCA\Nostalgist\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IUserSession;
use OCP\Settings\ISettings;
use OCP\Util;

class Personal implements ISettings {
	public function __construct(
		private IInitialState $initialState,
		private SettingsService $settingsService,
		private IUserSession $userSession,
	) {
	}

	public function getForm(): TemplateResponse {
		$user = $this->userSession->getUser();
		$settings = $user === null
			? $this->settingsService->getDefaults()
			: $this->settingsService->getUserSettings($user->getUID());
		$this->initialState->provideInitialState('settings', $settings);
		$this->initialState->provideInitialState('systems', CoreMap::SYSTEMS);
		Util::addScript(Application::APP_ID, 'nostalgist-settings');

		return new TemplateResponse(Application::APP_ID, 'settings', [
			'settings' => $settings,
			'systems' => CoreMap::SYSTEMS,
		]);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 50;
	}
}
