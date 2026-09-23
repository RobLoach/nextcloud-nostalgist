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

/**
 * The event of the Files app, likewise not part of the public API. Fired
 * when the sidebar is loaded, which is when a tab of it can be offered.
 */
namespace OCA\Files\Event {
	use OCP\EventDispatcher\Event;

	class LoadSidebar extends Event {
	}
}

/**
 * OCP\Image extends this one, which is internal to the server and so not
 * shipped with the OCP package. Only its existence matters here.
 */
namespace OC {
	class Image {
		public function __construct() {
		}
	}
}
