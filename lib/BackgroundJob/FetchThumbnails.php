<?php

declare(strict_types=1);

namespace OCA\Arcade\BackgroundJob;

use OCA\Arcade\AppInfo\Application;
use OCA\Arcade\Service\LibraryService;
use OCA\Arcade\Service\SettingsService;
use OCA\Arcade\Service\ThumbnailFetchService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\Config\IUserConfig;
use OCP\Files\Folder;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IPreview;
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

	/**
	 * The sizes the library asks /core/preview for: 256 for the cards of
	 * the grid, 64 for the rows of the list (see thumbnailFor() in
	 * src/library.js). The library passes a=1, which the preview endpoint
	 * turns into crop=false, so the same is asked for here.
	 */
	public const PREVIEW_SIZES = [256, 64];

	public function __construct(
		ITimeFactory $time,
		private SettingsService $settingsService,
		private LibraryService $libraryService,
		private ThumbnailFetchService $fetchService,
		private IRootFolder $rootFolder,
		private IJobList $jobList,
		private IUserConfig $userConfig,
		private IPreview $preview,
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
		// An administrator can turn the looking up off for the instance,
		// and a job queued before that must not go anyway.
		if (!$this->fetchService->isAllowed()) {
			$this->report($userId, 'Looking up box art is turned off for this instance');
			return;
		}
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
		$this->warm($result['written'] ?? []);
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

	/**
	 * Generate and cache the previews the library will ask for, so the
	 * first paint does not send a request per game to a cold preview
	 * generator.
	 *
	 * Done right here, one file after the other: a run writes at most
	 * BATCH images, and a preview of a small box art PNG is cheap, so a
	 * handful of them is no reason for sub-jobs. Only the files written
	 * this run are warmed, never the whole folder.
	 *
	 * A warm-up is a nicety: whatever it cannot do, the preview endpoint
	 * will do later, so trouble is logged at debug and the job goes on.
	 *
	 * @param list<File> $files
	 */
	private function warm(array $files): void {
		foreach ($files as $file) {
			foreach (self::PREVIEW_SIZES as $size) {
				try {
					$this->preview->getPreview($file, $size, $size, false);
				} catch (\Throwable $e) {
					$this->logger->debug('Could not warm a box art preview', ['exception' => $e]);
				}
			}
		}
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
		$this->userConfig->setValueString(
			$userId,
			Application::APP_ID,
			'fetch_status',
			json_encode(['message' => $message, 'time' => time()]),
		);
	}
}
