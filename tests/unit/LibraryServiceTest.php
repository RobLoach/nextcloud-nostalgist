<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\Service\LibraryService;
use OCA\Arcade\Service\StateService;
use OCA\Arcade\Service\ThumbnailService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;
use OCP\Files\Search\ISearchQuery;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use PHPUnit\Framework\TestCase;

class LibraryServiceTest extends TestCase {
	private LibraryService $service;
	private StateService $stateService;
	private ISystemTagManager $tagManager;
	private ISystemTagObjectMapper $tagObjectMapper;

	protected function setUp(): void {
		$this->stateService = $this->createStub(StateService::class);
		$this->tagManager = $this->createStub(ISystemTagManager::class);
		$this->tagObjectMapper = $this->createStub(ISystemTagObjectMapper::class);
		$this->service = new LibraryService(
			$this->createStub(ICacheFactory::class),
			new ThumbnailService(),
			$this->stateService,
			$this->createStub(IFilesMetadataManager::class),
			$this->tagManager,
			$this->tagObjectMapper,
		);
	}

	private function tag(string $id, string $name, bool $visible = true): ISystemTag {
		$tag = $this->createStub(ISystemTag::class);
		$tag->method('getId')->willReturn($id);
		$tag->method('getName')->willReturn($name);
		$tag->method('isUserVisible')->willReturn($visible);
		return $tag;
	}

	public function testEveryListOnThePageIsGivenItsFallbacks(): void {
		// The page, the recently played and the favorites are separate
		// lists holding the same games, and all of them are filled in.
		$this->stateService->method('thumbnailIndex')->willReturn([
			'/Games/Mario.nes' => ['slot' => 1, 'mtime' => 100],
		]);
		$game = ['id' => 1, 'path' => '/Games/Mario.nes', 'basename' => 'Mario.nes', 'system' => 'nes'];
		$page = [$game];
		$recent = [$game];
		$favorites = [];

		$this->service->addFallbackImages(
			'alice',
			$this->createStub(Folder::class),
			['screenshots_folder' => '', 'saves_folder' => ''],
			$page,
			$recent,
			$favorites,
		);

		$this->assertSame(['type' => 'state', 'slot' => 1], $page[0]['fallback'] ?? null);
		$this->assertSame(
			['type' => 'state', 'slot' => 1],
			$recent[0]['fallback'] ?? null,
			'the second list is filled in too, not just the first',
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function games(): array {
		return [
			['path' => '/Games/Zelda.sfc', 'basename' => 'Zelda.sfc', 'system' => 'snes', 'size' => 300, 'mtime' => 30],
			['path' => '/Games/mario.nes', 'basename' => 'mario.nes', 'system' => 'nes', 'size' => 100, 'mtime' => 10],
			['path' => '/Games/Sonic.md', 'basename' => 'Sonic.md', 'system' => 'genesis', 'size' => 200, 'mtime' => 20],
		];
	}

	/**
	 * @param list<array<string, mixed>> $games
	 * @return list<string>
	 */
	private function names(array $games): array {
		return array_column($games, 'basename');
	}

	public function testGamesAreSortedByNameWithoutRegardForCase(): void {
		$games = $this->games();
		$this->service->sortGames($games, 'name', 'asc');
		$this->assertSame(['mario.nes', 'Sonic.md', 'Zelda.sfc'], $this->names($games));

		$this->service->sortGames($games, 'name', 'desc');
		$this->assertSame(['Zelda.sfc', 'Sonic.md', 'mario.nes'], $this->names($games));
	}

	public function testGamesAreSortedBySizeAndDate(): void {
		$games = $this->games();
		$this->service->sortGames($games, 'size', 'asc');
		$this->assertSame(['mario.nes', 'Sonic.md', 'Zelda.sfc'], $this->names($games));

		$this->service->sortGames($games, 'mtime', 'desc');
		$this->assertSame(['Zelda.sfc', 'Sonic.md', 'mario.nes'], $this->names($games));
	}

	public function testGamesOfTheSameSystemKeepANameOrder(): void {
		$games = [
			['path' => '/b.nes', 'basename' => 'b.nes', 'system' => 'nes', 'size' => 1, 'mtime' => 1],
			['path' => '/a.nes', 'basename' => 'a.nes', 'system' => 'nes', 'size' => 2, 'mtime' => 2],
		];
		$this->service->sortGames($games, 'system', 'asc');
		$this->assertSame(['a.nes', 'b.nes'], $this->names($games));
	}

	public function testAnUnknownSortFallsBackToTheName(): void {
		$games = $this->games();
		$this->service->sortGames($games, 'whatever', 'asc');
		$this->assertSame(['mario.nes', 'Sonic.md', 'Zelda.sfc'], $this->names($games));
	}

	public function testGamesAreFilteredByName(): void {
		$this->assertSame(
			['mario.nes'],
			$this->names($this->service->filterGames($this->games(), 'MAR', '')),
			'the search ignores case',
		);
		$this->assertSame([], $this->service->filterGames($this->games(), 'nothing here', ''));
		$this->assertCount(3, $this->service->filterGames($this->games(), '  ', ''), 'blank searches keep everything');
	}

	public function testGamesAreFilteredBySystem(): void {
		$this->assertSame(['Zelda.sfc'], $this->names($this->service->filterGames($this->games(), '', 'snes')));
		$this->assertSame([], $this->service->filterGames($this->games(), '', 'gba'));
	}

	public function testFiltersApplyTogether(): void {
		$this->assertSame([], $this->service->filterGames($this->games(), 'mario', 'snes'));
		$this->assertSame(
			['mario.nes'],
			$this->names($this->service->filterGames($this->games(), 'mario', 'nes')),
		);
	}

	public function testGamesAreFilteredByTag(): void {
		$games = [
			['path' => '/a.nes', 'basename' => 'a.nes', 'system' => 'nes', 'tags' => ['Finished', 'Co-op']],
			['path' => '/b.nes', 'basename' => 'b.nes', 'system' => 'nes', 'tags' => ['Finished']],
			['path' => '/c.nes', 'basename' => 'c.nes', 'system' => 'nes'],
		];
		$this->assertSame(
			['a.nes'],
			$this->names($this->service->filterGames($games, '', '', 'Co-op')),
		);
		$this->assertCount(2, $this->service->filterGames($games, '', '', 'Finished'));
		$this->assertSame([], $this->service->filterGames($games, '', '', 'Backlog'));
		$this->assertCount(3, $this->service->filterGames($games, '', '', ''), 'no tag keeps everything');
	}

	public function testTagsAreAttachedByFileIdAndInvisibleOnesStayHidden(): void {
		$this->tagObjectMapper->method('getTagIdsForObjects')->willReturn([
			'1' => ['10', '11'],
			'2' => [],
		]);
		$this->tagManager->method('getTagsByIds')->willReturn([
			'10' => $this->tag('10', 'Finished'),
			'11' => $this->tag('11', 'Staff only', false),
		]);
		$games = [
			['id' => 1, 'path' => '/a.nes', 'basename' => 'a.nes', 'system' => 'nes'],
			['id' => 2, 'path' => '/b.nes', 'basename' => 'b.nes', 'system' => 'nes'],
		];

		$this->service->addTags($games);

		$this->assertSame(['Finished'], $games[0]['tags'], 'the invisible tag is left out');
		$this->assertSame([], $games[1]['tags']);
	}

	public function testFilteringKeepsAListWithoutGaps(): void {
		$filtered = $this->service->filterGames($this->games(), '', 'genesis');
		$this->assertSame([0], array_keys($filtered), 'the keys are renumbered for the JSON response');
	}

	private function buildService(ICacheFactory $cacheFactory): LibraryService {
		return new LibraryService(
			$cacheFactory,
			new ThumbnailService(),
			$this->stateService,
			$this->createStub(IFilesMetadataManager::class),
			$this->tagManager,
			$this->tagObjectMapper,
		);
	}

	private function cacheFactory(ICache $cache): ICacheFactory {
		$factory = $this->createStub(ICacheFactory::class);
		$factory->method('createDistributed')->willReturn($cache);
		return $factory;
	}

	private function file(int $id, string $path, int $size = 10, int $mtime = 5): File {
		$file = $this->createStub(File::class);
		$file->method('getId')->willReturn($id);
		$file->method('getPath')->willReturn($path);
		$file->method('getName')->willReturn(substr($path, (int)strrpos($path, '/') + 1));
		$file->method('getSize')->willReturn($size);
		$file->method('getMTime')->willReturn($mtime);
		return $file;
	}

	/**
	 * @param list<\OCP\Files\Node> $nodes what the one search comes back with
	 */
	private function libraryFolder(array $nodes, ?ISearchQuery &$query = null): Folder {
		$folder = $this->createStub(Folder::class);
		$folder->method('getEtag')->willReturn('etag-library');
		$folder->method('search')->willReturnCallback(function ($asked) use (&$query, $nodes): array {
			$query = $asked;
			return $nodes;
		});
		$folder->method('getRelativePath')->willReturnCallback(
			static fn (string $path): ?string => str_starts_with($path, '/alice/files/Games/')
				? substr($path, strlen('/alice/files/Games'))
				: null,
		);
		return $folder;
	}

	private function userFolder(): Folder {
		$folder = $this->createStub(Folder::class);
		$folder->method('getRelativePath')->willReturnCallback(
			static fn (string $path): ?string => str_starts_with($path, '/alice/files/')
				? substr($path, strlen('/alice/files'))
				: null,
		);
		return $folder;
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return list<array<string, mixed>>
	 */
	private function scan(LibraryService $service, Folder $library, array $settings = [], bool $refresh = false): array {
		return $service->getGames(
			'alice',
			$library,
			$this->userFolder(),
			'/Games',
			$settings + ['thumbnails_folder' => ''],
			$refresh,
		);
	}

	public function testOneSearchFindsTheGamesHoweverDeepTheyAre(): void {
		$fakeGame = $this->createStub(Folder::class);
		$fakeGame->method('getPath')->willReturn('/alice/files/Games/Fake.nes');
		$library = $this->libraryFolder([
			$this->file(1, '/alice/files/Games/mario.nes', 100, 10),
			$this->file(2, '/alice/files/Games/SNES/game.bin', 200, 20),
			$this->file(3, '/alice/files/Games/Stuff/random.zip', 300, 30),
			$this->file(4, '/alice/files/Games/readme.txt'),
			$fakeGame,
		]);

		$games = $this->scan($this->buildService($this->cacheFactory($this->createStub(ICache::class))), $library);

		$this->assertSame([
			// The search answers in no promised order; the scan is by path.
			['id' => 2, 'path' => '/Games/SNES/game.bin', 'basename' => 'game.bin', 'system' => 'snes', 'size' => 200, 'mtime' => 20],
			['id' => 3, 'path' => '/Games/Stuff/random.zip', 'basename' => 'random.zip', 'system' => 'zip', 'size' => 300, 'mtime' => 30],
			['id' => 1, 'path' => '/Games/mario.nes', 'basename' => 'mario.nes', 'system' => 'nes', 'size' => 100, 'mtime' => 10],
		], $games, 'a .bin takes its system from its folder, a folder named like a ROM and a stray text file are left out');
	}

	public function testTheSearchAsksForMimetypesAndFallsBackToNames(): void {
		$query = null;
		$library = $this->libraryFolder([], $query);

		$this->scan($this->buildService($this->cacheFactory($this->createStub(ICache::class))), $library);

		$this->assertInstanceOf(ISearchQuery::class, $query);
		$operator = $query->getSearchOperation();
		$this->assertInstanceOf(ISearchBinaryOperator::class, $operator);
		$this->assertSame(ISearchBinaryOperator::OPERATOR_OR, $operator->getType());
		$mimes = [];
		$names = [];
		foreach ($operator->getArguments() as $clause) {
			$this->assertInstanceOf(ISearchComparison::class, $clause);
			if ($clause->getField() === 'mimetype') {
				$this->assertSame(ISearchComparison::COMPARE_EQUAL, $clause->getType());
				$mimes[] = $clause->getValue();
			} else {
				$this->assertSame('name', $clause->getField());
				$this->assertSame(ISearchComparison::COMPARE_LIKE, $clause->getType());
				$names[] = $clause->getValue();
			}
		}
		$this->assertContains('application/x-nes-rom', $mimes);
		$this->assertContains('%.nes', $names, 'files from before the mimetypes were registered still match by name');
		$this->assertContains('%.zip', $names);
		$this->assertContains('%.bin', $names);
	}

	public function testTheScanStopsAtTheLimits(): void {
		$library = $this->libraryFolder([
			$this->file(1, '/alice/files/Games/a.nes'),
			$this->file(2, '/alice/files/Games/b.nes'),
			$this->file(3, '/alice/files/Games/Deep/Deeper/c.nes'),
		]);
		$service = $this->buildService($this->cacheFactory($this->createStub(ICache::class)));

		$games = $this->scan($service, $library, ['max_depth' => 1]);
		$this->assertSame(['a.nes', 'b.nes'], $this->names($games), 'a game below max_depth is left out');

		$games = $this->scan($service, $library, ['max_games' => 2]);
		$this->assertSame(['c.nes', 'a.nes'], $this->names($games), 'max_games keeps the first of the path order');
	}

	public function testTheCacheHoldsAGzippedEntryWithoutTheBasenames(): void {
		$stored = null;
		$cache = $this->createStub(ICache::class);
		$cache->method('get')->willReturn(null);
		$cache->method('set')->willReturnCallback(function (string $key, mixed $value) use (&$stored): bool {
			$stored = $value;
			return true;
		});
		$library = $this->libraryFolder([$this->file(1, '/alice/files/Games/mario.nes', 100, 10)]);

		$scanned = $this->scan($this->buildService($this->cacheFactory($cache)), $library);

		$this->assertIsString($stored, 'the cache holds a compressed string, not the array');
		$records = json_decode(gzuncompress($stored), true);
		$this->assertSame(
			[['id' => 1, 'path' => '/Games/mario.nes', 'system' => 'nes', 'size' => 100, 'mtime' => 10]],
			$records,
			'the basename is not worth its bytes: it is the last segment of the path',
		);
		$this->assertSame('mario.nes', $scanned[0]['basename'], 'the caller still gets the full shape');
	}

	public function testACachedEntryComesBackAsItWasScanned(): void {
		$stored = null;
		$cache = $this->createStub(ICache::class);
		$cache->method('set')->willReturnCallback(function (string $key, mixed $value) use (&$stored): bool {
			$stored = $value;
			return true;
		});
		$library = $this->libraryFolder([
			$this->file(1, '/alice/files/Games/mario.nes', 100, 10),
			$this->file(2, '/alice/files/Games/SNES/game.bin', 200, 20),
		]);
		$scanned = $this->scan($this->buildService($this->cacheFactory($cache)), $library);

		$hit = $this->createStub(ICache::class);
		$hit->method('get')->willReturn($stored);
		$quiet = $this->createMock(Folder::class);
		$quiet->method('getEtag')->willReturn('etag-library');
		$quiet->expects($this->never())->method('search');

		$this->assertSame(
			$scanned,
			$this->scan($this->buildService($this->cacheFactory($hit)), $quiet),
			'a cache hit is the scan again, without a search',
		);
	}

	public function testAnEntryTheCacheDidNotCompressStillCounts(): void {
		$cache = $this->createStub(ICache::class);
		$cache->method('get')->willReturn([
			['id' => 1, 'path' => '/Games/mario.nes', 'basename' => 'mario.nes', 'system' => 'nes', 'size' => 100, 'mtime' => 10],
		]);
		$library = $this->createMock(Folder::class);
		$library->method('getEtag')->willReturn('etag-library');
		$library->expects($this->never())->method('search');

		$games = $this->scan($this->buildService($this->cacheFactory($cache)), $library);
		$this->assertSame('mario.nes', $games[0]['basename']);
	}

	public function testAnUnreadableCacheEntryMeansAFreshScan(): void {
		$cache = $this->createStub(ICache::class);
		$cache->method('get')->willReturn('not gzip, not json');
		$library = $this->libraryFolder([$this->file(1, '/alice/files/Games/mario.nes', 100, 10)]);

		$games = $this->scan($this->buildService($this->cacheFactory($cache)), $library);
		$this->assertSame(['mario.nes'], $this->names($games), 'garbage in the cache falls back to scanning');
	}

	public function testFiveThousandGamesFitUnderAMemcachedMegabyte(): void {
		$nodes = [];
		for ($i = 0; $i < 5000; $i++) {
			$nodes[] = $this->file($i + 1, sprintf('/alice/files/Games/SNES/Some Long Game Title, The (USA) (Rev %04d).sfc', $i), 1024 * $i, 1700000000 + $i);
		}
		$stored = null;
		$cache = $this->createStub(ICache::class);
		$cache->method('set')->willReturnCallback(function (string $key, mixed $value) use (&$stored): bool {
			$stored = $value;
			return true;
		});
		$library = $this->libraryFolder($nodes);

		$games = $this->scan($this->buildService($this->cacheFactory($cache)), $library);

		$this->assertCount(5000, $games);
		$this->assertIsString($stored);
		$this->assertLessThan(
			512 * 1024,
			strlen($stored),
			'memcached drops entries over a megabyte without a word, so a full library must stay well clear of it',
		);
	}
}
