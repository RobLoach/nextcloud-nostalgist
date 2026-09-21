<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Tests\Unit;

use OCA\Nostalgist\Service\RecentService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class RecentServiceTest extends TestCase {
	private const USER = 'alice';

	private string $stored = '';

	private function service(): RecentService {
		$config = $this->createMock(IConfig::class);
		$config->method('setUserValue')->willReturnCallback(
			function (string $user, string $app, string $key, string $value): void {
				$this->stored = $value;
			},
		);
		$config->method('getUserValue')->willReturnCallback(fn (): string => $this->stored);
		return new RecentService($config);
	}

	public function testNothingIsRememberedToStartWith(): void {
		$this->assertSame([], $this->service()->get(self::USER));
	}

	public function testBrokenStorageIsIgnored(): void {
		$this->stored = 'not json';
		$this->assertSame([], $this->service()->get(self::USER));
	}

	public function testGamesAreRememberedNewestFirst(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/NES/Mario.nes');
		$service->record(self::USER, '/Games/SNES/Zelda.sfc');

		$recent = $service->get(self::USER);
		$this->assertCount(2, $recent);
		$this->assertSame('Zelda.sfc', $recent[0]['basename']);
		$this->assertSame('/Games/NES/Mario.nes', $recent[1]['path']);
	}

	public function testPlayingAgainMovesAGameBackToTheFront(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Mario.nes');
		$service->record(self::USER, '/Games/Zelda.sfc');
		$service->record(self::USER, '/Games/Mario.nes');

		$recent = $service->get(self::USER);
		$this->assertCount(2, $recent, 'a game is not remembered twice');
		$this->assertSame('/Games/Mario.nes', $recent[0]['path']);
	}

	public function testOnlyTheLastTwelveGamesAreKept(): void {
		$service = $this->service();
		for ($i = 1; $i <= 20; $i++) {
			$service->record(self::USER, "/Games/Game $i.nes");
		}
		$recent = $service->get(self::USER);
		$this->assertCount(12, $recent);
		$this->assertSame('/Games/Game 20.nes', $recent[0]['path']);
		$this->assertSame('/Games/Game 9.nes', $recent[11]['path']);
	}

	public function testTheSystemIsFoundFromTheExtensionOrTheFolder(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Mario.nes');
		$this->assertSame('nes', $service->get(self::USER)[0]['system']);

		$service->record(self::USER, '/Games/Nintendo - Super Nintendo Entertainment System/NHL 96.zip');
		$this->assertSame('snes', $service->get(self::USER)[0]['system'], 'a zip takes the system of its folder');

		$service->record(self::USER, '/Games/Unsorted/Mystery.zip');
		$this->assertSame('zip', $service->get(self::USER)[0]['system'], 'and stays a zip when nothing says otherwise');
	}
}
