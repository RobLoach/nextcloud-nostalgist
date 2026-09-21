<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Listener;

use OCA\Nostalgist\CoreMap;
use OCA\Nostalgist\Service\StateService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Folder;
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
		$extension = strtolower(pathinfo($node->getName(), PATHINFO_EXTENSION));
		if ($extension !== 'zip' && !isset(CoreMap::extensionSystemMap()[$extension])) {
			return;
		}
		$owner = $node->getOwner();
		if ($owner === null) {
			return;
		}
		$userId = $owner->getUID();
		// The states of a game are keyed by its path in the user folder,
		// which is what the path of the node holds after its prefix.
		$prefix = '/' . $userId . '/files';
		$path = $node->getPath();
		if (!str_starts_with($path, $prefix)) {
			return;
		}
		$this->stateService->deleteAllForGame($userId, substr($path, strlen($prefix)));
	}
}
