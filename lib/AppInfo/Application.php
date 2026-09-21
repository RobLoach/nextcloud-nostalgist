<?php

declare(strict_types=1);

namespace OCA\Nostalgist\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Nostalgist\CoreMap;
use OCA\Nostalgist\Listener\CSPListener;
use OCA\Nostalgist\Listener\LoadFilesScriptsListener;
use OCA\Nostalgist\Listener\LoadViewerListener;
use OCA\Viewer\Event\LoadViewer;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\IMimeTypeDetector;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'nostalgist';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadFilesScriptsListener::class);
		$context->registerEventListener(AddContentSecurityPolicyEvent::class, CSPListener::class);
		if (class_exists(LoadViewer::class)) {
			$context->registerEventListener(LoadViewer::class, LoadViewerListener::class);
		}
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
