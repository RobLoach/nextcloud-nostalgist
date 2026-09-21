<?php

declare(strict_types=1);

/**
 * The event of the Viewer app, which is not part of the public API and so
 * not shipped with the OCP package. Only its shape matters here, for static
 * analysis.
 */

namespace OCA\Viewer\Event {
	use OCP\EventDispatcher\Event;

	class LoadViewer extends Event {
	}
}
