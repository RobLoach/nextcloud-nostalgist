<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\Controller\BiosController;
use OCA\Arcade\CoreMap;
use OCA\Arcade\Service\BiosService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * A BiosController whose request body a test can write, php://input being
 * out of reach from here.
 */
class TestableBiosController extends BiosController {
	public string $body = '';

	protected function readBody(int $limit): string|false {
		return substr($this->body, 0, $limit);
	}
}

/**
 * The admin endpoints an administrator manages the BIOS store with.
 */
class BiosControllerTest extends TestCase {
	private BiosService&MockObject $biosService;

	private function controller(string $body = ''): TestableBiosController {
		$this->biosService = $this->createMock(BiosService::class);
		$controller = new TestableBiosController(
			'arcade',
			$this->createStub(IRequest::class),
			$this->biosService,
			'admin',
		);
		$controller->body = $body;
		return $controller;
	}

	public function testStatusNamesEverySystemThatWantsABios(): void {
		$controller = $this->controller();
		$this->biosService->method('stored')->willReturn([
			'gb_bios.bin' => 100,
			'stray.bin' => 5,
		]);

		$data = $controller->status()->getData();

		$expected = array_filter(CoreMap::SYSTEMS, fn (array $system): bool => $system['bios'] !== []);
		$this->assertCount(count($expected), $data['systems']);
		foreach ($data['systems'] as $entry) {
			$id = $entry['system']['id'];
			$this->assertSame(CoreMap::SYSTEMS[$id]['label'], $entry['system']['name']);
			$this->assertSame(CoreMap::SYSTEMS[$id]['bios'], array_column($entry['files'], 'name'));
			foreach ($entry['files'] as $file) {
				if ($file['name'] === 'gb_bios.bin') {
					$this->assertTrue($file['present']);
					$this->assertSame(100, $file['size']);
				} else {
					$this->assertFalse($file['present']);
					$this->assertSame(0, $file['size']);
				}
			}
		}
		$this->assertSame([['name' => 'stray.bin', 'size' => 5]], $data['extra']);
	}

	public function testUploadRefusesANameNoCoreAsksFor(): void {
		$controller = $this->controller('firmware');
		$this->biosService->expects($this->never())->method('write');

		$response = $controller->upload('malware.php');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testUploadStoresUnderTheCanonicalName(): void {
		$controller = $this->controller('the firmware');
		$this->biosService->expects($this->once())->method('write')
			->with('gb_bios.bin', 'the firmware')
			->willReturn(true);

		$response = $controller->upload('GB_BIOS.BIN');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(
			['name' => 'gb_bios.bin', 'size' => strlen('the firmware')],
			$response->getData(),
		);
	}

	public function testUploadRefusesAFileTooBigToBeABios(): void {
		$controller = $this->controller(str_repeat('x', 16 * 1024 * 1024 + 1));
		$this->biosService->expects($this->never())->method('write');

		$response = $controller->upload('gb_bios.bin');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testUploadRefusesAnEmptyBody(): void {
		$controller = $this->controller('');
		$this->biosService->expects($this->never())->method('write');

		$response = $controller->upload('gb_bios.bin');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testRemoveTakesAFileOut(): void {
		$controller = $this->controller();
		$this->biosService->expects($this->once())->method('remove')
			->with('gb_bios.bin')
			->willReturn(true);

		$response = $controller->remove('GB_BIOS.bin');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testRemoveRefusesANameNoCoreAsksFor(): void {
		$controller = $this->controller();
		$this->biosService->expects($this->never())->method('remove');

		$response = $controller->remove('../config.php');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testRemoveSaysWhenThereWasNothingToRemove(): void {
		$controller = $this->controller();
		$this->biosService->method('remove')->willReturn(false);

		$response = $controller->remove('gb_bios.bin');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}
}
