<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\CoreMap;
use OCA\Arcade\Preview\RomPreview;
use PHPUnit\Framework\TestCase;

/**
 * Which files Nextcloud will ask the app for a preview of.
 */
class RomPreviewTest extends TestCase {
	public function testEveryRomMimetypeIsCovered(): void {
		$regex = RomPreview::mimeTypeRegex();
		foreach (CoreMap::SYSTEMS as $id => $system) {
			$this->assertSame(1, preg_match($regex, $system['mime']), "$id is not offered a preview");
		}
	}

	public function testNothingElseIsClaimed(): void {
		$regex = RomPreview::mimeTypeRegex();
		foreach (['image/png', 'application/zip', 'text/plain', 'application/octet-stream', 'video/mp4'] as $mime) {
			$this->assertSame(0, preg_match($regex, $mime), "$mime is not the app's to preview");
		}
	}
}
