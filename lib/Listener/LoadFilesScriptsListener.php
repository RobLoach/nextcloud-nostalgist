<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\CoreMap;
use OCA\Nostalgist\Service\SettingsService;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\Util;

/**
 * Registers the "Play with Nostalgist" file action in the Files app.
 *
 * @template-implements IEventListener<LoadAdditionalScriptsEvent>
 */
class LoadFilesScriptsListener implements IEventListener {
	public function __construct(
		private IInitialState $initialState,
		private SettingsService $settingsService,
		private IUserSession $userSession,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}
		$this->initialState->provideInitialState('systems', CoreMap::SYSTEMS);
		$user = $this->userSession->getUser();
		$this->initialState->provideInitialState(
			'settings',
			$user === null
				? $this->settingsService->getDefaults()
				: $this->settingsService->getUserSettings($user->getUID()),
		);
		Util::addStyle(Application::APP_ID, 'player');
		Util::addScript(Application::APP_ID, 'nostalgist-files');
	}
}
