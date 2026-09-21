<?php

declare(strict_types=1);

namespace OCA\Arcade\AppInfo;

use OCA\Arcade\CoreMap;
use OCA\Arcade\Listener\CleanupListener;
use OCA\Arcade\Listener\CSPListener;
use OCA\Arcade\Listener\LoadViewerListener;
use OCA\Arcade\Preview\RomPreview;
use OCA\Viewer\Event\LoadViewer;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'arcade';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(AddContentSecurityPolicyEvent::class, CSPListener::class);
		// Save states have no owner of their own, so they are removed with
		// the game or the user they belong to.
		$context->registerEventListener(NodeDeletedEvent::class, CleanupListener::class);
		$context->registerEventListener(UserDeletedEvent::class, CleanupListener::class);
		if (class_exists(LoadViewer::class)) {
			$context->registerEventListener(LoadViewer::class, LoadViewerListener::class);
		}
		// Box art becomes the preview of a ROM, in the Files app and
		// anywhere else Nextcloud shows one.
		$context->registerPreviewProvider(RomPreview::class, RomPreview::mimeTypeRegex());
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (IMimeTypeDetector $detector): void {
			// registerType() lives on the implementation, not on the public
			// interface, so make sure it is there. Without it ROMs are still
			// recognized by their file extension, only the mimetype-based
			// integrations are lost.
			if (!method_exists($detector, 'registerType')) {
				return;
			}
			// Load the default mappings first, as registering a type before
			// they are loaded would prevent them from being loaded at all.
			$detector->getAllMappings();
			foreach (CoreMap::extensionMimeMap() as $extension => $mime) {
				$detector->registerType($extension, $mime);
			}
		});
	}
}
