<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The parts of appinfo/info.xml that nothing else would notice were wrong.
 */
class AppInfoTest extends TestCase {
	private function info(): \SimpleXMLElement {
		$info = simplexml_load_file(dirname(__DIR__, 2) . '/appinfo/info.xml');
		$this->assertNotFalse($info, 'info.xml does not parse');
		return $info;
	}

	public function testTheAppIsLoadedWhileFilesAreWritten(): void {
		// remote.php -- every upload, from the web and from the desktop
		// client alike -- loads only apps of a few types. Without this one
		// the app is not booted there, so CoreMap's mimetypes are never
		// registered with the detector, and an uploaded ROM is filed as
		// application/octet-stream. The Viewer then has no player for it.
		$this->assertTrue(
			isset($this->info()->types->filesystem),
			'without the filesystem type, uploaded ROMs get the wrong mimetype',
		);
	}

	public function testEveryClassTheInfoNamesExists(): void {
		$named = $this->info()->xpath(
			'//step | //command | //admin | //admin-section | //personal | //personal-section',
		);
		$this->assertNotEmpty($named, 'the info names no classes at all, which cannot be right');
		foreach ($named as $class) {
			$name = trim((string)$class);
			$this->assertTrue(class_exists($name), "$name is named in info.xml but does not exist");
		}
	}

	public function testTheVersionMatchesThePackage(): void {
		$package = json_decode((string)file_get_contents(dirname(__DIR__, 2) . '/package.json'), true);
		$this->assertSame($package['version'] ?? null, (string)$this->info()->version);
	}
}
