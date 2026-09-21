<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\BackgroundJob\FetchThumbnails;
use OCA\Arcade\Service\LibraryService;
use OCA\Arcade\Service\SettingsService;
use OCA\Arcade\Service\ThumbnailFetchService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Config\IUserConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The job that goes looking for box art: what it hands to the looking, how
 * it reports, and when it asks to be run again.
 */
class FetchThumbnailsTest extends TestCase {
	private const USER = 'alice';

	private ThumbnailFetchService&MockObject $fetchService;
	private IJobList&MockObject $jobList;
	private string $status = '';
	/** @var array<string, mixed> */
	private array $settings = [];
	/** @var list<array<string, mixed>> */
	private array $games = [];
	private bool $libraryExists = true;

	protected function setUp(): void {
		$this->settings = [
			'library_folder' => '/Games',
			'thumbnails_folder' => '/Thumbs',
		];
		$this->games = [];
		$this->libraryExists = true;
		$this->status = '';
		$this->fetchService = $this->createMock(ThumbnailFetchService::class);
		$this->jobList = $this->createMock(IJobList::class);
	}

	private function job(): FetchThumbnails {
		$settingsService = $this->createStub(SettingsService::class);
		$settingsService->method('getUserSettings')->willReturnCallback(fn (): array => $this->settings);

		$libraryService = $this->createStub(LibraryService::class);
		$libraryService->method('getGames')->willReturnCallback(fn (): array => $this->games);

		$folder = $this->createStub(Folder::class);
		$userFolder = $this->createStub(Folder::class);
		$userFolder->method('get')->willReturnCallback(
			function (string $path) use ($folder): Folder {
				if (!$this->libraryExists) {
					throw new NotFoundException($path);
				}
				return $folder;
			},
		);
		$userFolder->method('newFolder')->willReturn($folder);
		$rootFolder = $this->createStub(IRootFolder::class);
		$rootFolder->method('getUserFolder')->willReturn($userFolder);

		$config = $this->createStub(IUserConfig::class);
		$config->method('setValueString')->willReturnCallback(
			function (string $user, string $app, string $key, string $value): bool {
				$this->status = $value;
				return true;
			},
		);

		return new FetchThumbnails(
			$this->createStub(ITimeFactory::class),
			$settingsService,
			$libraryService,
			$this->fetchService,
			$rootFolder,
			$this->jobList,
			$config,
			$this->createStub(LoggerInterface::class),
		);
	}

	private function runJob(): void {
		$job = $this->job();
		$method = new \ReflectionMethod($job, 'run');
		$method->setAccessible(true);
		$method->invoke($job, ['userId' => self::USER]);
	}

	/**
	 * @param int $count how many games have no picture
	 * @return list<array<string, mixed>>
	 */
	private function gamesWithout(int $count): array {
		$games = [];
		for ($i = 1; $i <= $count; $i++) {
			$games[] = ['path' => "/Games/Game $i.nes", 'basename' => "Game $i.nes", 'system' => 'nes'];
		}
		return $games;
	}

	public function testOnlyTheGamesWithoutAPictureAreLookedFor(): void {
		$this->games = [
			['path' => '/Games/Has one.nes', 'basename' => 'Has one.nes', 'system' => 'nes', 'thumbnails' => ['boxart' => 1]],
			['path' => '/Games/Has none.nes', 'basename' => 'Has none.nes', 'system' => 'nes'],
		];

		$this->fetchService->expects($this->once())
			->method('fetch')
			->with(
				self::USER,
				$this->callback(static fn (array $games): bool => count($games) === 1
					&& $games[0]['basename'] === 'Has none.nes'),
			)
			->willReturn(['fetched' => 1, 'missing' => 0, 'tried' => 1]);

		$this->runJob();
	}

	public function testARunStaysSmall(): void {
		$this->games = $this->gamesWithout(100);
		$this->fetchService->method('fetch')
			->with($this->anything(), $this->anything(), $this->anything(), FetchThumbnails::BATCH)
			->willReturn(['fetched' => 0, 'missing' => 100, 'tried' => FetchThumbnails::BATCH]);
		$this->jobList->expects($this->once())->method('add');

		$this->runJob();
		$this->assertLessThanOrEqual(10, FetchThumbnails::BATCH, 'a run is a handful of games, not a library');
	}

	public function testTheJobCarriesOnWhileThereIsMoreToLookFor(): void {
		$this->games = $this->gamesWithout(50);
		$this->fetchService->method('fetch')
			->willReturn(['fetched' => 2, 'missing' => 48, 'tried' => FetchThumbnails::BATCH]);

		$this->jobList->expects($this->once())
			->method('add')
			->with(FetchThumbnails::class, ['userId' => self::USER]);

		$this->runJob();
		$this->assertStringContainsString('still to go', $this->status);
	}

	public function testItStopsOnceItHasBeenThroughEverything(): void {
		$this->games = $this->gamesWithout(3);
		$this->fetchService->method('fetch')
			->willReturn(['fetched' => 2, 'missing' => 1, 'tried' => 3]);

		$this->jobList->expects($this->never())->method('add');

		$this->runJob();
		$this->assertStringContainsString('Found box art for 2 games', $this->status);
	}

	public function testNothingToDoIsSaidRatherThanDone(): void {
		$this->games = [
			['path' => '/Games/Mario.nes', 'basename' => 'Mario.nes', 'system' => 'nes', 'thumbnails' => ['boxart' => 1]],
		];
		$this->fetchService->expects($this->never())->method('fetch');

		$this->runJob();
		$this->assertStringContainsString('Every game has a picture', $this->status);
	}

	public function testWithoutAThumbnailsFolderThereIsNowhereToPutAnything(): void {
		$this->settings['thumbnails_folder'] = '';
		$this->fetchService->expects($this->never())->method('fetch');

		$this->runJob();
		$this->assertStringContainsString('No thumbnails folder', $this->status);
	}

	public function testWithoutALibraryThereIsNothingToLookFor(): void {
		$this->libraryExists = false;
		$this->fetchService->expects($this->never())->method('fetch');

		$this->runJob();
		$this->assertStringContainsString('library folder does not exist', $this->status);
	}

	public function testTroubleIsReportedRatherThanThrown(): void {
		$this->games = $this->gamesWithout(1);
		$this->fetchService->method('fetch')
			->willThrowException(new \RuntimeException('the server said no'));

		$this->runJob();
		$this->assertStringContainsString('Something went wrong', $this->status);
	}
}
