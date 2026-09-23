<?php

declare(strict_types=1);

namespace OCA\Arcade\Listener;

use OCA\Arcade\AppInfo\Application;
use OCA\Arcade\CoreMap;
use OCA\Files\Event\LoadSidebar;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * Puts the Arcade tab into the sidebar of the Files app, where a ROM shows
 * what the app knows about it.
 *
 * @template-implements IEventListener<LoadSidebar>
 */
class LoadSidebarListener implements IEventListener {
	public function __construct(
		private IInitialState $initialState,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadSidebar) {
			return;
		}
		// The mimetypes the tab appears for. The Viewer listener provides
		// the same state; whichever runs second writes the same value.
		$this->initialState->provideInitialState('systems', CoreMap::SYSTEMS);
		Util::addStyle(Application::APP_ID, 'files');
		// Load after the Files app script so OCA.Files.Sidebar is there.
		Util::addScript(Application::APP_ID, 'arcade-files', 'files');
	}
}
