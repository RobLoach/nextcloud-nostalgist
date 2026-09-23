<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\Controller\PageController;
use OCA\Arcade\Service\LibraryService;
use OCA\Arcade\Service\RecentService;
use OCA\Arcade\Service\SettingsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IInitialState;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class PageControllerTest extends TestCase {
	private const GAMES = [
		[
			'id' => 1,
			'path' => '/Games/Mario.nes',
			'basename' => 'Mario.nes',
			'system' => 'nes',
			'size' => 40976,
			'mtime' => 100,
			'tags' => ['platformer'],
		],
		[
			'id' => 2,
			'path' => '/Games/Sonic.md',
			'basename' => 'Sonic.md',
			'system' => 'segaMD',
			'size' => 524288,
			'mtime' => 200,
			'tags' => [],
		],
	];

	/**
	 * @param list<array<string, mixed>> $games what the scan finds
	 * @param array<int, bool> $favoriteIds file id => true
	 * @param list<int> $recentIds file ids in play order
	 * @param string $ifNoneMatch the If-None-Match header of the request
	 */
	private function controller(
		array $games = self::GAMES,
		array $favoriteIds = [],
		array $recentIds = [],
		string $ifNoneMatch = '',
	): PageController {
		$request = $this->createStub(IRequest::class);
		$request->method('getHeader')->willReturnCallback(
			static fn (string $name): string => $name === 'If-None-Match' ? $ifNoneMatch : '',
		);

		$settingsService = $this->createStub(SettingsService::class);
		$settingsService->method('getUserSettings')->willReturn([
			'library_folder' => '/Games',
			'max_games' => 5000,
		]);

		$libraryFolder = $this->createStub(Folder::class);
		$userFolder = $this->createStub(Folder::class);
		$userFolder->method('get')->willReturn($libraryFolder);
		$rootFolder = $this->createStub(IRootFolder::class);
		$rootFolder->method('getUserFolder')->willReturn($userFolder);

		$libraryService = $this->createStub(LibraryService::class);
		$libraryService->method('getGames')->willReturn($games);
		$libraryService->method('filterGames')->willReturnArgument(0);

		$recentService = $this->createStub(RecentService::class);
		$recentService->method('get')->willReturn($recentIds);
		$recentService->method('favoriteIds')->willReturn($favoriteIds);
		$recentService->method('stats')->willReturn([]);

		return new PageController(
			'arcade',
			$request,
			$this->createStub(IInitialState::class),
			$settingsService,
			$libraryService,
			$recentService,
			$rootFolder,
			$this->createStub(IJobList::class),
			'alice',
		);
	}

	public function testEtagIsSetAndStableForIdenticalInputs(): void {
		$first = $this->controller()->library();
		$second = $this->controller()->library();

		$this->assertSame(Http::STATUS_OK, $first->getStatus());
		$this->assertNotEmpty($first->getETag());
		$this->assertSame($first->getETag(), $second->getETag());
	}

	public function testEtagChangesWhenFavoritesChange(): void {
		$plain = $this->controller()->library();
		$starred = $this->controller(self::GAMES, [1 => true])->library();

		$this->assertNotSame($plain->getETag(), $starred->getETag());
	}

	public function testEtagChangesWhenRecentChange(): void {
		$plain = $this->controller()->library();
		$played = $this->controller(self::GAMES, [], [2])->library();

		$this->assertNotSame($plain->getETag(), $played->getETag());
	}

	public function testEtagChangesWhenTagsChange(): void {
		$games = self::GAMES;
		$games[1]['tags'] = ['co-op'];

		$plain = $this->controller()->library();
		$tagged = $this->controller($games)->library();

		$this->assertNotSame($plain->getETag(), $tagged->getETag());
	}

	public function testEtagChangesWithTheQuery(): void {
		$onePage = $this->controller()->library();
		$another = $this->controller()->library(limit: 120);

		$this->assertNotSame($onePage->getETag(), $another->getETag());
	}

	public function testMatchingIfNoneMatchGetsNotModified(): void {
		$etag = $this->controller()->library()->getETag();

		$response = $this->controller(ifNoneMatch: '"' . $etag . '"')->library();

		$this->assertSame(Http::STATUS_NOT_MODIFIED, $response->getStatus());
		$this->assertSame($etag, $response->getETag());
		$this->assertSame([], $response->getData());
	}

	public function testStaleIfNoneMatchGetsTheFullPayload(): void {
		$etag = $this->controller()->library()->getETag();

		$response = $this->controller(
			self::GAMES,
			[1 => true],
			ifNoneMatch: '"' . $etag . '"',
		)->library();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertNotSame($etag, $response->getETag());
		$this->assertNotEmpty($response->getData()['games']);
	}
}
