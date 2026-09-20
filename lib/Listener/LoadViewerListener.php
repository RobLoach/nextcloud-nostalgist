<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Listener;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\CoreMap;
use OCA\Nostalgist\Service\SettingsService;
use OCA\Viewer\Event\LoadViewer;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\Util;

/**
 * Registers the Nostalgist player as a Viewer handler for ROM mimetypes.
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
		$user = $this->userSession->getUser();
		$this->initialState->provideInitialState(
			'settings',
			$user === null
				? $this->settingsService->getDefaults()
				: $this->settingsService->getUserSettings($user->getUID()),
		);
		// Load after the viewer script so OCA.Viewer is available.
		Util::addScript(Application::APP_ID, 'nostalgist-viewer', 'viewer');
	}
}
