<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\Listener\MetadataListener;
use OCA\Arcade\Service\SettingsService;
use OCP\Files\File;
use OCP\FilesMetadata\Event\MetadataBackgroundEvent;
use OCP\FilesMetadata\Model\IFilesMetadata;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * What gets filed against a ROM when the background pass reads it, and in
 * particular that one read of the file yields both of its checksums.
 */
class MetadataListenerTest extends TestCase {
	/**
	 * The classic check vector: hashing the ASCII bytes "123456789" must
	 * give CRC32 cbf43926 (the crc32b polynomial No-Intro uses) and MD5
	 * 25f9e794323b453885f5181f1b624d0b.
	 */
	private const VECTOR = '123456789';
	private const VECTOR_CRC32 = 'cbf43926';
	private const VECTOR_MD5 = '25f9e794323b453885f5181f1b624d0b';

	/** What was filed, by key: ['value' => ..., 'indexed' => ...]. */
	private array $written = [];

	private function listener(bool $hashRoms): MetadataListener {
		$settingsService = $this->createStub(SettingsService::class);
		$settingsService->method('getDefaults')->willReturn(['hash_roms' => $hashRoms]);
		return new MetadataListener($settingsService, $this->createStub(LoggerInterface::class));
	}

	private function rom(string $content, string $checksum = ''): File {
		$handle = fopen('php://memory', 'r+b');
		fwrite($handle, $content);
		rewind($handle);
		$node = $this->createStub(File::class);
		$node->method('getPath')->willReturn('/alice/files/Games/Mario.nes');
		$node->method('getSize')->willReturn(strlen($content));
		$node->method('getChecksum')->willReturn($checksum);
		$node->method('fopen')->willReturn($handle);
		return $node;
	}

	private function metadata(): IFilesMetadata {
		$metadata = $this->createStub(IFilesMetadata::class);
		$metadata->method('setString')->willReturnCallback(
			function (string $key, string $value, bool $index = false) use ($metadata): IFilesMetadata {
				$this->written[$key] = ['value' => $value, 'indexed' => $index];
				return $metadata;
			},
		);
		return $metadata;
	}

	private function read(File $node, bool $hashRoms = true): void {
		$this->listener($hashRoms)->handle(new MetadataBackgroundEvent($node, $this->metadata()));
	}

	public function testOneReadOfTheFileYieldsBothChecksums(): void {
		$this->read($this->rom(self::VECTOR));

		$this->assertSame(self::VECTOR_MD5, $this->written[MetadataListener::CHECKSUM]['value']);
		$this->assertTrue($this->written[MetadataListener::CHECKSUM]['indexed']);
		$this->assertSame(self::VECTOR_CRC32, $this->written[MetadataListener::CRC32]['value']);
		$this->assertFalse($this->written[MetadataListener::CRC32]['indexed'], 'nobody searches by CRC32');
	}

	public function testNothingIsHashedUnlessTheAdministratorAsked(): void {
		$this->read($this->rom(self::VECTOR), hashRoms: false);

		$this->assertArrayNotHasKey(MetadataListener::CHECKSUM, $this->written);
		$this->assertArrayNotHasKey(MetadataListener::CRC32, $this->written);
	}

	public function testAChecksumSentWithTheUploadLeavesTheCrc32Absent(): void {
		// The client worked the MD5 out on the way up, so the file is never
		// hashed here -- and a CRC32 alone is not worth a second full read.
		$this->read($this->rom(self::VECTOR, 'MD5:' . self::VECTOR_MD5));

		$this->assertSame(self::VECTOR_MD5, $this->written[MetadataListener::CHECKSUM]['value']);
		$this->assertArrayNotHasKey(MetadataListener::CRC32, $this->written);
	}
}
