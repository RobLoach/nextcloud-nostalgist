<?php

declare(strict_types=1);

namespace OCA\Arcade\BackgroundJob;

use OCA\Arcade\Listener\MetadataListener;
use OCA\Arcade\Service\LibraryService;
use OCA\Arcade\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\FilesMetadata\IFilesMetadataManager;
use Psr\Log\LoggerInterface;

/**
 * Reads what a ROM says about itself for the games that have never been
 * asked.
 *
 * Nextcloud only reads a file's metadata when the file is written, so a
 * library that was already there when the app arrived is never looked at.
 * Rescanning the library folder asks for this job, which walks the games a
 * batch at a time and hands each one to Nextcloud as though it had just
 * been written: the cheap part is done at once, and the reading of the file
 * itself is queued behind it, one job per game.
 *
 * @psalm-suppress UnusedClass
 */
class RefreshMetadata extends QueuedJob {
	/**
	 * Games handed over in one run. Each one queues a job of its own that
	 * opens the file, so a run stays small and this one comes back for the
	 * rest.
	 */
	public const BATCH = 50;

	public function __construct(
		ITimeFactory $time,
		private SettingsService $settingsService,
		private LibraryService $libraryService,
		private IFilesMetadataManager $metadataManager,
		private IRootFolder $rootFolder,
		private IJobList $jobList,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	/**
	 * @param mixed $argument
	 */
	protected function run($argument): void {
		$userId = is_array($argument) ? (string)($argument['userId'] ?? '') : '';
		if ($userId === '') {
			return;
		}
		try {
			$this->refreshFor($userId);
		} catch (\Throwable $e) {
			$this->logger->error('Could not read what the ROMs say about themselves', ['exception' => $e]);
		}
	}

	private function refreshFor(string $userId): void {
		$missing = $this->missing($userId);
		if ($missing === []) {
			return;
		}

		$userFolder = $this->rootFolder->getUserFolder($userId);
		foreach (array_slice($missing, 0, self::BATCH) as $id) {
			$node = $userFolder->getFirstNodeById($id);
			if ($node === null) {
				continue;
			}
			// Live, so that only the naming of the system is done here and
			// the reading of the file is left to a job of its own.
			$this->metadataManager->refreshMetadata($node, IFilesMetadataManager::PROCESS_LIVE);
		}

		if (count($missing) > self::BATCH) {
			$this->jobList->add(self::class, ['userId' => $userId]);
		}
	}

	/**
	 * The ids of the games nothing is known about yet.
	 *
	 * @return list<int>
	 */
	private function missing(string $userId): array {
		$settings = $this->settingsService->getUserSettings($userId);
		$userFolder = $this->rootFolder->getUserFolder($userId);
		try {
			$folder = $userFolder->get($settings['library_folder']);
		} catch (NotFoundException) {
			return [];
		}
		if (!$folder instanceof Folder) {
			return [];
		}

		$games = $this->libraryService->getGames(
			$userId,
			$folder,
			$userFolder,
			$settings['library_folder'],
			$settings,
			false,
		);
		$ids = array_values(array_filter(array_column($games, 'id')));
		if ($ids === []) {
			return [];
		}

		$known = [];
		foreach ($this->metadataManager->getMetadataForFiles($ids) as $id => $metadata) {
			// The system is set the moment a game is looked at, so a game
			// without it has never been looked at.
			if ($metadata->getString(MetadataListener::SYSTEM) !== '') {
				$known[(int)$id] = true;
			}
		}
		return array_values(array_filter($ids, static fn (int $id): bool => !isset($known[$id])));
	}
}
