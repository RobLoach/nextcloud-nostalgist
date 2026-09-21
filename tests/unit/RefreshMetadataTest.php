<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\BackgroundJob\RefreshMetadata;
use OCA\Arcade\Listener\MetadataListener;
use OCA\Arcade\Service\LibraryService;
use OCA\Arcade\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\FilesMetadata\Model\IFilesMetadata;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Asking what the games already in a library say about themselves, for the
 * ones nothing has ever asked.
 */
class RefreshMetadataTest extends TestCase {
	private const USER = 'alice';

	/** The games in the library, by id. */
	private array $games = [];
	/** The ids something is already known about. */
	private array $known = [];
	/** The ids that were handed to Nextcloud to be read. */
	private array $refreshed = [];
	/** The jobs queued while running. */
	private array $queued = [];
	/** Ids whose file is no longer there. */
	private array $gone = [];

	private function job(): RefreshMetadata {
		$settingsService = $this->createStub(SettingsService::class);
		$settingsService->method('getUserSettings')->willReturn(['library_folder' => '/Games']);

		$libraryService = $this->createStub(LibraryService::class);
		$libraryService->method('getGames')->willReturnCallback(fn (): array => array_values($this->games));

		$userFolder = $this->createStub(Folder::class);
		$userFolder->method('get')->willReturn($this->createStub(Folder::class));
		$userFolder->method('getFirstNodeById')->willReturnCallback(
			fn (int $id): ?Node => isset($this->gone[$id]) ? null : $this->createStub(Node::class),
		);
		$rootFolder = $this->createStub(IRootFolder::class);
		$rootFolder->method('getUserFolder')->willReturn($userFolder);

		$metadataManager = $this->createStub(IFilesMetadataManager::class);
		$metadataManager->method('getMetadataForFiles')->willReturnCallback(
			function (array $ids): array {
				$found = [];
				foreach ($ids as $id) {
					$metadata = $this->createStub(IFilesMetadata::class);
					$metadata->method('getString')->willReturn(isset($this->known[$id]) ? 'nes' : '');
					$found[$id] = $metadata;
				}
				return $found;
			},
		);
		$metadataManager->method('refreshMetadata')->willReturnCallback(
			function (Node $node, int $process = 1, string $namedEvent = ''): IFilesMetadata {
				$this->refreshed[] = $process;
				return $this->createStub(IFilesMetadata::class);
			},
		);

		$jobList = $this->createStub(IJobList::class);
		$jobList->method('add')->willReturnCallback(
			function (mixed $job, mixed $argument = null): void {
				$this->queued[] = is_string($job) ? $job : $job::class;
			},
		);

		return new RefreshMetadata(
			$this->createStub(ITimeFactory::class),
			$settingsService,
			$libraryService,
			$metadataManager,
			$rootFolder,
			$jobList,
			$this->createStub(LoggerInterface::class),
		);
	}

	private function runJob(mixed $argument = ['userId' => self::USER]): void {
		$job = $this->job();
		$method = new \ReflectionMethod($job, 'run');
		$method->setAccessible(true);
		$method->invoke($job, $argument);
	}

	private function library(int $count): void {
		for ($id = 1; $id <= $count; $id++) {
			$this->games[$id] = ['id' => $id, 'path' => "/Games/Game $id.nes", 'basename' => "Game $id.nes"];
		}
	}

	public function testOnlyTheGamesNothingIsKnownAboutAreAskedAbout(): void {
		$this->library(4);
		$this->known = [2 => true, 4 => true];

		$this->runJob();

		$this->assertCount(2, $this->refreshed, 'the two that were never looked at');
	}

	public function testTheFileItselfIsLeftToAJobOfItsOwn(): void {
		$this->library(1);
		$this->runJob();

		$this->assertSame(
			[IFilesMetadataManager::PROCESS_LIVE],
			$this->refreshed,
			'live, so that only the cheap part happens here',
		);
	}

	public function testALibraryThatIsAllKnownIsLeftAlone(): void {
		$this->library(3);
		$this->known = [1 => true, 2 => true, 3 => true];

		$this->runJob();

		$this->assertSame([], $this->refreshed);
		$this->assertSame([], $this->queued, 'and nothing is asked for again');
	}

	public function testOnlyOneBatchIsDoneAtATime(): void {
		$this->library(RefreshMetadata::BATCH + 10);

		$this->runJob();

		$this->assertCount(RefreshMetadata::BATCH, $this->refreshed);
		$this->assertSame([RefreshMetadata::class], $this->queued, 'and it comes back for the rest');
	}

	public function testAJobThatFinishesTheLibraryDoesNotComeBack(): void {
		$this->library(RefreshMetadata::BATCH);

		$this->runJob();

		$this->assertCount(RefreshMetadata::BATCH, $this->refreshed);
		$this->assertSame([], $this->queued);
	}

	public function testAGameThatIsNoLongerThereIsSkipped(): void {
		$this->library(2);
		$this->gone = [1 => true];

		$this->runJob();

		$this->assertCount(1, $this->refreshed);
	}

	public function testAJobWithoutAUserDoesNothing(): void {
		$this->library(2);
		$this->runJob([]);
		$this->runJob('not an array');

		$this->assertSame([], $this->refreshed);
	}
}
