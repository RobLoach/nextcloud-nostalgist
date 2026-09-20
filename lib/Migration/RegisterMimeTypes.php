<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Migration;

use OCA\Nostalgist\CoreMap;
use OCP\Files\IMimeTypeLoader;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Updates the file cache so ROMs uploaded before the app was enabled get
 * their proper mimetype instead of application/octet-stream.
 */
class RegisterMimeTypes implements IRepairStep {
	public function __construct(
		private IMimeTypeLoader $mimeTypeLoader,
	) {
	}

	public function getName(): string {
		return 'Register Nostalgist ROM mimetypes';
	}

	public function run(IOutput $output): void {
		$updated = 0;
		foreach (CoreMap::extensionMimeMap() as $extension => $mime) {
			$mimeId = $this->mimeTypeLoader->getId($mime);
			$updated += $this->mimeTypeLoader->updateFilecache($extension, $mimeId);
		}
		$output->info("Updated the mimetype of $updated files");
	}
}
