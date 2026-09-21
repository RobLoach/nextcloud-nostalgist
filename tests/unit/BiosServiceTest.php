<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\CoreMap;
use OCA\Arcade\Service\BiosService;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use PHPUnit\Framework\TestCase;

/**
 * The BIOS files an instance offers to every player.
 */
class BiosServiceTest extends TestCase {
	/** What the app data holds, by name. */
	private array $files = [];

	private function service(): BiosService {
		$folder = $this->createStub(ISimpleFolder::class);
		$folder->method('fileExists')->willReturnCallback(fn (string $name): bool => isset($this->files[$name]));
		$folder->method('getFile')->willReturnCallback(
			function (string $name): ISimpleFile {
				if (!isset($this->files[$name])) {
					throw new NotFoundException($name);
				}
				$file = $this->createStub(ISimpleFile::class);
				$file->method('getName')->willReturn($name);
				$file->method('getContent')->willReturnCallback(fn (): string => $this->files[$name]);
				$file->method('putContent')->willReturnCallback(
					function ($content) use ($name): void {
						$this->files[$name] = (string)$content;
					},
				);
				$file->method('delete')->willReturnCallback(
					function () use ($name): void {
						unset($this->files[$name]);
					},
				);
				return $file;
			},
		);
		$folder->method('newFile')->willReturnCallback(
			function (string $name, $content = null): ISimpleFile {
				$this->files[$name] = (string)$content;
				return $this->createStub(ISimpleFile::class);
			},
		);
		$folder->method('getDirectoryListing')->willReturnCallback(
			function (): array {
				$listing = [];
				foreach (array_keys($this->files) as $name) {
					$file = $this->createStub(ISimpleFile::class);
					$file->method('getName')->willReturn($name);
					$listing[] = $file;
				}
				return $listing;
			},
		);

		$appData = $this->createStub(IAppData::class);
		$appData->method('getFolder')->willReturn($folder);
		$appData->method('newFolder')->willReturn($folder);
		$factory = $this->createStub(IAppDataFactory::class);
		$factory->method('get')->willReturn($appData);

		return new BiosService($factory);
	}

	public function testEveryNameACoreAsksForIsAllowed(): void {
		foreach (CoreMap::SYSTEMS as $id => $system) {
			foreach ($system['bios'] as $name) {
				$this->assertTrue(BiosService::isKnown($name), "$name is asked for by $id");
			}
		}
	}

	public function testNothingElseCanBePutThere(): void {
		$service = $this->service();
		$this->assertFalse($service->write('../../secret.txt', 'no'));
		$this->assertFalse($service->write('anything.bin', 'no'));
		$this->assertSame([], $this->files, 'nothing was written');
	}

	public function testAFileTheInstanceHoldsIsHandedOut(): void {
		$service = $this->service();
		$this->assertTrue($service->write('gb_bios.bin', 'the firmware'));
		$this->assertSame('the firmware', $service->read('gb_bios.bin'));
		$this->assertSame(['gb_bios.bin'], $service->held());
	}

	public function testAFileTheInstanceHasNotGotIsNothingToWorryAbout(): void {
		$this->assertNull($this->service()->read('gba_bios.bin'));
	}

	public function testAFileCanBeTakenBack(): void {
		$service = $this->service();
		$service->write('gb_bios.bin', 'the firmware');
		$this->assertTrue($service->remove('gb_bios.bin'));
		$this->assertNull($service->read('gb_bios.bin'));
		$this->assertFalse($service->remove('gb_bios.bin'), 'and only once');
	}
}
