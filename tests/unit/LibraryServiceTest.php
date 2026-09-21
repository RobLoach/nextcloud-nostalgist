<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\Service\LibraryService;
use OCA\Arcade\Service\StateService;
use OCA\Arcade\Service\ThumbnailService;
use OCP\Files\Folder;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\ICacheFactory;
use PHPUnit\Framework\TestCase;

class LibraryServiceTest extends TestCase {
	private LibraryService $service;
	private StateService $stateService;

	protected function setUp(): void {
		$this->stateService = $this->createStub(StateService::class);
		$this->service = new LibraryService(
			$this->createStub(ICacheFactory::class),
			new ThumbnailService(),
			$this->stateService,
			$this->createStub(IFilesMetadataManager::class),
		);
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

	public function testFilteringKeepsAListWithoutGaps(): void {
		$filtered = $this->service->filterGames($this->games(), '', 'genesis');
		$this->assertSame([0], array_keys($filtered), 'the keys are renumbered for the JSON response');
	}
}
