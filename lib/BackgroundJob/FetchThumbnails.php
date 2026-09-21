<?php

declare(strict_types=1);

namespace OCA\Nostalgist\BackgroundJob;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\Service\LibraryService;
use OCA\Nostalgist\Service\SettingsService;
use OCA\Nostalgist\Service\ThumbnailFetchService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

/**
 * Looks for the box art of a user's games, away from the browser.
 *
 * A library can hold thousands of games and every one of them is a request
 * to the thumbnail server, so the work is done a batch at a time: the job
 * queues itself again while there is more to look for, which also keeps a
 * single run short.
 *
 * @psalm-suppress UnusedClass
 */
class FetchThumbnails extends QueuedJob {
	/**
	 * Games to look up in one run. Every one of them is a few requests to
	 * a server on the other side of the internet, so a run stays small and
	 * the job queues itself again for the rest.
	 */
	public const BATCH = 10;

	public function __construct(
		ITimeFactory $time,
		private SettingsService $settingsService,
		private LibraryService $libraryService,
		private ThumbnailFetchService $fetchService,
		private IRootFolder $rootFolder,
		private IJobList $jobList,
		private IConfig $config,
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
			$this->fetchFor($userId);
		} catch (\Throwable $e) {
			$this->logger->error('Could not look for box art', ['exception' => $e]);
			$this->report($userId, 'Something went wrong while looking for box art');
		}
	}

	private function fetchFor(string $userId): void {
		$settings = $this->settingsService->getUserSettings($userId);
		if ($settings['thumbnails_folder'] === '') {
			$this->report($userId, 'No thumbnails folder is set');
			return;
		}

		$userFolder = $this->rootFolder->getUserFolder($userId);
		$library = $this->folderAt($userFolder, $settings['library_folder']);
		if ($library === null) {
			$this->report($userId, 'The games library folder does not exist');
			return;
		}
		$thumbnails = $this->folderAt($userFolder, $settings['thumbnails_folder'])
			?? $userFolder->newFolder(trim($settings['thumbnails_folder'], '/'));

		// A fresh scan, so games given an image in an earlier run are left
		// out of this one.
		$games = $this->libraryService->getGames(
			$userId,
			$library,
			$userFolder,
			$settings['library_folder'],
			$settings,
			true,
		);
		$missing = array_values(array_filter(
			$games,
			static fn (array $game): bool => empty($game['thumbnails']),
		));
		if ($missing === []) {
			$this->report($userId, 'Every game has a picture');
			return;
		}

		$result = $this->fetchService->fetch($userId, $missing, $thumbnails, self::BATCH);
		if ($result['tried'] >= self::BATCH) {
			// There is more to look for; carry on in the next run.
			$this->report($userId, sprintf(
				'Looking for box art, %d still to go',
				max(0, count($missing) - $result['fetched']),
			));
			$this->jobList->add(self::class, ['userId' => $userId]);
			return;
		}
		$this->report($userId, sprintf(
			'Found box art for %d games, %d were nowhere to be found',
			$result['fetched'],
			$result['missing'],
		));
	}

	private function folderAt(Folder $userFolder, string $path): ?Folder {
		try {
			$folder = $userFolder->get($path);
		} catch (NotFoundException) {
			return null;
		}
		return $folder instanceof Folder ? $folder : null;
	}

	/**
	 * Leave word for the settings page, which has no other way of knowing
	 * how a job that runs on its own is getting on.
	 */
	private function report(string $userId, string $message): void {
		$this->config->setUserValue(
			$userId,
			Application::APP_ID,
			'fetch_status',
			json_encode(['message' => $message, 'time' => time()]),
		);
	}
}
