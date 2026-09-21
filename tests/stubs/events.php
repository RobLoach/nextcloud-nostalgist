<?php

declare(strict_types=1);

/**
 * The events of the Files and Viewer apps, which are not part of the public
 * API and so not shipped with the OCP package. Only their shape matters
 * here, for static analysis.
 */

namespace OCA\Files\Event {
	use OCP\EventDispatcher\Event;

	class LoadAdditionalScriptsEvent extends Event {
	}
}

namespace OCA\Viewer\Event {
	use OCP\EventDispatcher\Event;

	class LoadViewer extends Event {
	}
}
