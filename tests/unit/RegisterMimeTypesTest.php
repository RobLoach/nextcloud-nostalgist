<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\Migration\RegisterMimeTypes;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IMimeTypeLoader;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class RegisterMimeTypesTest extends TestCase {
	/**
	 * @param array<string, list<string>> $mappings what the server already maps
	 */
	private function repair(array $mappings, IMimeTypeLoader $loader): void {
		$detector = $this->createStub(IMimeTypeDetector::class);
		$detector->method('getAllMappings')->willReturn($mappings);
		(new RegisterMimeTypes($loader, $detector))->run($this->createStub(IOutput::class));
	}

	public function testAnExtensionTheServerKnowsIsGivenBackItsType(): void {
		// ".md" is Markdown to Nextcloud before it is a Mega Drive dump;
		// an earlier version of this step took it over, so the files are
		// pointed back at the server's type rather than at ours.
		$loader = $this->createMock(IMimeTypeLoader::class);
		$loader->method('getId')->willReturnCallback(
			static fn (string $mime): int => $mime === 'text/markdown' ? 7 : 1000,
		);
		$calls = [];
		$loader->method('updateFilecache')->willReturnCallback(
			function (string $extension, int $mimeId) use (&$calls): int {
				$calls[$extension] = $mimeId;
				return 1;
			},
		);

		$this->repair(['md' => ['text/markdown', 'text/plain']], $loader);

		$this->assertSame(7, $calls['md'], 'markdown files go back to being markdown');
		$this->assertArrayHasKey('sfc', $calls, 'unclaimed extensions still get the ROM mimetype');
		$this->assertNotSame(7, $calls['sfc']);
	}

	public function testAnExtensionNobodyClaimsBecomesARom(): void {
		$loader = $this->createMock(IMimeTypeLoader::class);
		$loader->method('getId')->willReturn(42);
		$extensions = [];
		$loader->method('updateFilecache')->willReturnCallback(
			function (string $extension) use (&$extensions): int {
				$extensions[] = $extension;
				return 0;
			},
		);

		$this->repair([], $loader);

		$this->assertContains('md', $extensions, 'with no server claim, .md is ours to type');
		$this->assertContains('gb', $extensions);
	}
}
