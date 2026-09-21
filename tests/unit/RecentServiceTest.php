<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\Service\RecentService;
use OCP\Config\IUserConfig;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ITagManager;
use OCP\ITags;
use PHPUnit\Framework\TestCase;

class RecentServiceTest extends TestCase {
	private const USER = 'alice';

	/** What is kept under the app, by key. */
	private array $stored = [];
	/** The file ids the user has starred in Files, as keys. */
	private array $starred = [];
	/** The files the user has, by path. */
	private array $files = [
		'/Games/Mario.nes' => 101,
		'/Games/Zelda.sfc' => 102,
		'/Games/NES/Mario.nes' => 103,
		'/Games/SNES/Zelda.sfc' => 104,
		'/Games/Nintendo - Super Nintendo Entertainment System/NHL 96.zip' => 105,
		'/Games/Unsorted/Mystery.zip' => 106,
	];

	private function service(): RecentService {
		// Anything else the tests play is a file of its own.
		for ($i = 1; $i <= 20; $i++) {
			$this->files["/Games/Game $i.nes"] ??= 200 + $i;
		}

		$config = $this->createStub(IUserConfig::class);
		$config->method('setValueString')->willReturnCallback(
			function (string $user, string $app, string $key, string $value): bool {
				$this->stored[$key] = $value;
				return true;
			},
		);
		$config->method('getValueString')->willReturnCallback(
			fn (string $user, string $app, string $key): string => $this->stored[$key] ?? '',
		);
		$config->method('deleteUserConfig')->willReturnCallback(
			function (string $user, string $app, string $key): void {
				unset($this->stored[$key]);
			},
		);

		$tags = $this->createStub(ITags::class);
		$tags->method('getFavorites')->willReturnCallback(fn (): array => array_keys($this->starred));
		$tags->method('addToFavorites')->willReturnCallback(
			function ($id): bool {
				$this->starred[(int)$id] = true;
				return true;
			},
		);
		$tags->method('removeFromFavorites')->willReturnCallback(
			function ($id): bool {
				unset($this->starred[(int)$id]);
				return true;
			},
		);
		$tagManager = $this->createStub(ITagManager::class);
		$tagManager->method('load')->willReturn($tags);

		$folder = $this->createStub(Folder::class);
		$folder->method('get')->willReturnCallback(
			function (string $path): File {
				if (!isset($this->files[$path])) {
					throw new NotFoundException($path);
				}
				$file = $this->createStub(File::class);
				$file->method('getId')->willReturn($this->files[$path]);
				return $file;
			},
		);
		$rootFolder = $this->createStub(IRootFolder::class);
		$rootFolder->method('getUserFolder')->willReturn($folder);

		return new RecentService($config, $tagManager, $rootFolder);
	}

	public function testNothingIsRememberedToStartWith(): void {
		$this->assertSame([], $this->service()->get(self::USER));
	}

	public function testBrokenStorageIsIgnored(): void {
		$this->stored['recent'] = 'not json';
		$this->assertSame([], $this->service()->get(self::USER));
	}

	public function testAGameThatIsGoneIsNotRemembered(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Nothing There.nes');
		$this->assertSame([], $service->get(self::USER), 'there is no id to remember it by');
	}

	public function testGamesAreRememberedNewestFirst(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/NES/Mario.nes');
		$service->record(self::USER, '/Games/SNES/Zelda.sfc');

		// By file id: what a game is called is the library's to say, and
		// changes when it is renamed.
		$this->assertSame([104, 103], $service->get(self::USER));
	}

	public function testPlayingAgainMovesAGameBackToTheFront(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Mario.nes');
		$service->record(self::USER, '/Games/Zelda.sfc');
		$service->record(self::USER, '/Games/Mario.nes');

		$this->assertSame([101, 102], $service->get(self::USER), 'a game is not remembered twice');
	}

	public function testOnlyTheLastTwelveGamesAreKept(): void {
		$service = $this->service();
		for ($i = 1; $i <= 20; $i++) {
			$service->record(self::USER, "/Games/Game $i.nes");
		}
		$recent = $service->get(self::USER);
		$this->assertCount(12, $recent);
		$this->assertSame(220, $recent[0], 'the twentieth game, played last');
		$this->assertSame(209, $recent[11]);
	}

	public function testPlayTimeAddsUpAcrossSessions(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Mario.nes');
		$service->addPlayTime(self::USER, '/Games/Mario.nes', 300);
		$service->addPlayTime(self::USER, '/Games/Mario.nes', 120);
		$this->assertSame(420, $service->stats(self::USER)[101]['seconds']);
	}

	public function testPlayTimeSurvivesPlayingAgain(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Mario.nes');
		$service->addPlayTime(self::USER, '/Games/Mario.nes', 300);
		$service->record(self::USER, '/Games/Mario.nes');

		$counted = $service->stats(self::USER)[101];
		$this->assertSame(300, $counted['seconds'], 'the time played is carried over');
		$this->assertSame(2, $counted['plays']);
	}

	public function testWhatAGameWasPlayedForOutlivesTheRecentList(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Mario.nes');
		$service->addPlayTime(self::USER, '/Games/Mario.nes', 300);
		// Pushed off the end of the list of twelve.
		for ($i = 1; $i <= 20; $i++) {
			$service->record(self::USER, "/Games/Game $i.nes");
		}

		$this->assertNotContains(101, $service->get(self::USER), 'it is out of the recent list');
		$this->assertSame(
			300,
			$service->stats(self::USER)[101]['seconds'] ?? null,
			'but what it was played for is still counted, under the id of the file',
		);
	}

	public function testAForgottenTabDoesNotCountForHours(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Mario.nes');
		$service->addPlayTime(self::USER, '/Games/Mario.nes', 10 * 3600);
		$this->assertSame(4 * 3600, $service->stats(self::USER)[101]['seconds']);
	}

	public function testNegativePlayTimeIsIgnored(): void {
		$service = $this->service();
		$service->record(self::USER, '/Games/Mario.nes');
		$service->addPlayTime(self::USER, '/Games/Mario.nes', -60);
		$this->assertSame(0, $service->stats(self::USER)[101]['seconds']);
	}

	public function testAFavoriteIsTheStarOfTheFilesApp(): void {
		$service = $this->service();

		$this->assertTrue($service->toggleFavorite(self::USER, '/Games/Mario.nes'));
		$this->assertSame([101], array_keys($this->starred), 'starred by file id, where Files keeps it');
		$this->assertSame([101 => true], $service->favoriteIds(self::USER));

		$this->assertFalse($service->toggleFavorite(self::USER, '/Games/Mario.nes'));
		$this->assertSame([], $this->starred);
	}

	public function testAGameStarredInFilesIsAFavoriteHereToo(): void {
		$this->starred[102] = true;
		$this->assertSame([102 => true], $this->service()->favoriteIds(self::USER));
	}

	public function testAGameThatIsGoneCannotBeStarred(): void {
		$service = $this->service();
		$this->assertFalse($service->toggleFavorite(self::USER, '/Games/Nothing There.nes'));
		$this->assertSame([], $this->starred);
	}

	public function testTheFavoritesOfOlderVersionsAreHandedToFiles(): void {
		$this->stored['favorites'] = json_encode([
			['path' => '/Games/Mario.nes', 'seconds' => 90, 'plays' => 3, 'time' => 1000],
			['path' => '/Games/Gone.nes', 'seconds' => 10, 'plays' => 1, 'time' => 900],
		]);

		$service = $this->service();
		$this->assertSame([101 => true], $service->favoriteIds(self::USER), 'the game that is still there');
		$this->assertSame(90, $service->stats(self::USER)[101]['seconds'] ?? null);
		$this->assertArrayNotHasKey('favorites', $this->stored, 'and the old list is gone');
	}

}
