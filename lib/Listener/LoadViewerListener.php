<?php

declare(strict_types=1);

namespace OCA\Arcade\Listener;

use OCA\Arcade\AppInfo\Application;
use OCA\Arcade\CoreMap;
use OCA\Arcade\Service\SettingsService;
use OCA\Viewer\Event\LoadViewer;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\Util;

/**
 * Registers the Arcade player as a Viewer handler for ROM mimetypes.
 *
 * @template-implements IEventListener<LoadViewer>
 */
class LoadViewerListener implements IEventListener {
	public function __construct(
		private IInitialState $initialState,
		private SettingsService $settingsService,
		private IUserSession $userSession,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadViewer) {
			return;
		}
		$this->initialState->provideInitialState('systems', CoreMap::SYSTEMS);
		// The words that say nothing about a system, so the browser can
		// read a folder name the way the server does without keeping a
		// copy of the lists.
		$this->initialState->provideInitialState('folderWords', [
			'noise' => CoreMap::NOISE,
			'vendors' => CoreMap::VENDORS,
		]);
		$user = $this->userSession->getUser();
		$this->initialState->provideInitialState(
			'settings',
			$user === null
				? $this->settingsService->getDefaults()
				: $this->settingsService->getUserSettings($user->getUID()),
		);
		Util::addStyle(Application::APP_ID, 'player');
		// Load after the viewer script so OCA.Viewer is available.
		Util::addScript(Application::APP_ID, 'arcade-viewer', 'viewer');
	}
}
