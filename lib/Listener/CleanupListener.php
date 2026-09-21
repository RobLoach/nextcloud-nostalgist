<?php

declare(strict_types=1);

namespace OCA\Arcade\Listener;

use OCA\Arcade\CoreMap;
use OCA\Arcade\Service\StateService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Folder;
use OCP\App\IAppManager;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * Save states outlive the games and the users they belong to otherwise,
 * with no way left of reaching them.
 *
 * @template-implements IEventListener<NodeDeletedEvent|UserDeletedEvent>
 */
class CleanupListener implements IEventListener {
	public function __construct(
		private StateService $stateService,
		private IAppManager $appManager,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		try {
			if ($event instanceof UserDeletedEvent) {
				$this->stateService->deleteAllForUser($event->getUser()->getUID());
			} elseif ($event instanceof NodeDeletedEvent) {
				$this->handleDeletedNode($event);
			}
		} catch (\Throwable $e) {
			// Cleaning up must never get in the way of the deletion itself.
			$this->logger->warning('Could not clean up the save states', ['exception' => $e]);
		}
	}

	private function handleDeletedNode(NodeDeletedEvent $event): void {
		$node = $event->getNode();
		if ($node instanceof Folder) {
			// A folder does not say what was in it; the games it held keep
			// their states until they are deleted one by one.
			return;
		}
		$owner = $node->getOwner();
		if ($owner === null) {
			return;
		}
		$userId = $owner->getUID();
		$path = $node->getPath();

		// The trash puts the hour of the deletion after the name, so what
		// it holds is "Mario.nes.d1700000000".
		$name = preg_replace('/\.d\d+$/', '', $node->getName()) ?? $node->getName();
		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if ($extension !== 'zip' && !isset(CoreMap::extensionSystemMap()[$extension])) {
			return;
		}

		// A game emptied out of the trash is gone for good. Its path there
		// says nothing of where it used to be, but its file id is the one
		// it always had, and that is what its states are filed under.
		if (str_starts_with($path, '/' . $userId . '/files_trashbin/')) {
			$this->stateService->deleteAllForFileId($userId, $node->getId());
			return;
		}

		// The states of a game are keyed by its path in the user folder,
		// which is what the path of the node holds after its prefix.
		$prefix = '/' . $userId . '/files/';
		if (!str_starts_with($path, $prefix)) {
			return;
		}

		// A deleted game that went to the trash can come back, with the
		// same file id and the same name, and a player who restores it
		// would not expect to have lost their saves. They go when the
		// trash lets go of it, or with `occ arcade:cleanup`.
		if ($this->appManager->isEnabledForUser('files_trashbin', $owner)) {
			return;
		}
		$this->stateService->deleteAllForGame($userId, substr($path, strlen($prefix) - 1));
	}
}
