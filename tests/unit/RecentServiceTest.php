<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\Service\RecentService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class RecentServiceTest extends TestCase {
	private const USER = 'alice';

	/** @var array<string, string> */
	private array $stored = [];

	private function service(): RecentService {
		$config = $this->createMock(IConfig::class);
		$config->method('setUserValue')->willReturnCallback(
			function (string $user, string $app, string $key, string $value): void {
				$this->stored[$key] = $value;
			},
		);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $user, string $app, string $key): string => $this->stored[$key] ?? '',
		);
		return new RecentService($config);
	}

	public function testNothingIsRememberedToStartWith(): void {
		$this->assertSame([], $this->service()->get(self::USER));
	}

	public function testBrokenStorageIsIgnored(): void {
		$this->stored['recent'] = 'not json';
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

	public function testPlayTimeAddsUpAcrossSessions(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Mario.nes');
		$service->addPlayTime(self::USER, '/Games/Mario.nes', 300);
		$service->addPlayTime(self::USER, '/Games/Mario.nes', 120);
		$this->assertSame(420, $service->get(self::USER)[0]['seconds']);
	}

	public function testPlayTimeSurvivesPlayingAgain(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Mario.nes');
		$service->addPlayTime(self::USER, '/Games/Mario.nes', 300);
		$service->record(self::USER, '/Games/Mario.nes');

		$entry = $service->get(self::USER)[0];
		$this->assertSame(300, $entry['seconds'], 'the time played is carried over');
		$this->assertSame(2, $entry['plays']);
	}

	public function testAForgottenTabDoesNotCountForHours(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Mario.nes');
		$service->addPlayTime(self::USER, '/Games/Mario.nes', 10 * 3600);
		$this->assertSame(4 * 3600, $service->get(self::USER)[0]['seconds']);
	}

	public function testNegativePlayTimeIsIgnored(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Mario.nes');
		$service->addPlayTime(self::USER, '/Games/Mario.nes', -60);
		$this->assertSame(0, $service->get(self::USER)[0]['seconds']);
	}

	public function testFavoritesAreToggledAndKeepWhatIsKnown(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Mario.nes');
		$service->addPlayTime(self::USER, '/Games/Mario.nes', 60);

		$this->assertTrue($service->toggleFavorite(self::USER, '/Games/Mario.nes'));
		$favorites = $service->getFavorites(self::USER);
		$this->assertCount(1, $favorites);
		$this->assertSame(60, $favorites[0]['seconds'], 'the time played comes along');

		$this->assertFalse($service->toggleFavorite(self::USER, '/Games/Mario.nes'));
		$this->assertSame([], $service->getFavorites(self::USER));
	}

	public function testAGameCanBeMadeAFavoriteBeforeItIsPlayed(): void {
		$service = $this->service();
		$this->assertTrue($service->toggleFavorite(self::USER, '/Games/SNES/Zelda.sfc'));
		$favorite = $service->getFavorites(self::USER)[0];
		$this->assertSame('Zelda.sfc', $favorite['basename']);
		$this->assertSame('snes', $favorite['system']);
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
