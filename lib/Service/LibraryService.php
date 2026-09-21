<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Service;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\CoreMap;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\ICacheFactory;

/**
 * Finds the games of a library folder, and puts them in the order and on
 * the page that was asked for.
 *
 * Scanning walks the whole folder, so the result is cached against the etag
 * of the folder: Nextcloud moves that along whenever anything inside
 * changes, which makes the cache correct without a lifetime to guess at.
 */
class LibraryService {
	public const MAX_GAMES = 5000;
	public const MAX_DEPTH = 6;
	private const CACHE_TTL = 24 * 3600;
	/** Bumped when the shape of a cached entry changes. */
	private const CACHE_VERSION = 4;

	public function __construct(
		private ICacheFactory $cacheFactory,
		private ThumbnailService $thumbnailService,
		private StateService $stateService,
	) {
	}

	/**
	 * Games without a thumbnail fall back to a picture of themselves: the
	 * most recent of the screenshots taken of them and the screenshots of
	 * their save states.
	 *
	 * @param list<array<string, mixed>> $games
	 * @param array<string, mixed> $settings
	 */
	public function addFallbackImages(string $userId, array &$games, Folder $userFolder, array $settings): void {
		$missing = array_filter($games, static fn (array $game): bool => empty($game['thumbnails']));
		if ($missing === []) {
			return;
		}

		$screenshots = [];
		if ($settings['screenshots_folder'] !== '') {
			try {
				$folder = $userFolder->get($settings['screenshots_folder']);
				if ($folder instanceof Folder) {
					$screenshots = $this->thumbnailService->indexScreenshots($folder);
				}
			} catch (NotFoundException) {
				// No screenshots folder, no screenshots.
			}
		}
		$states = $this->stateService->thumbnailIndex($userId, array_column($missing, 'path'));

		foreach ($games as &$game) {
			if (!empty($game['thumbnails'])) {
				continue;
			}
			$screenshot = null;
			foreach ($this->thumbnailService->screenshotKeys($game['basename']) as $key) {
				if (isset($screenshots[$key])) {
					$screenshot = $screenshots[$key];
					break;
				}
			}
			$state = $states[$game['path']] ?? null;

			if ($screenshot !== null && ($state === null || $screenshot['mtime'] >= $state['mtime'])) {
				$game['fallback'] = ['type' => 'screenshot', 'fileId' => $screenshot['id']];
			} elseif ($state !== null) {
				$game['fallback'] = ['type' => 'state', 'slot' => $state['slot']];
			}
		}
	}

	/**
	 * @param list<array<string, mixed>> $games
	 * @return list<array<string, mixed>>
	 */
	public function filterGames(array $games, string $search, string $system): array {
		$search = trim($search);
		if ($search !== '') {
			$games = array_filter(
				$games,
				static fn (array $game): bool => mb_stripos($game['basename'], $search) !== false,
			);
		}
		if ($system !== '') {
			$games = array_filter(
				$games,
				static fn (array $game): bool => $game['system'] === $system,
			);
		}
		return array_values($games);
	}

	/**
	 * The scanned games, from the cache when the library and thumbnails
	 * folders have not changed since.
	 *
	 * @param array<string, mixed> $settings
	 * @return list<array<string, mixed>>
	 */
	public function getGames(string $userId, Folder $folder, Folder $userFolder, string $folderPath, array $settings, bool $refresh): array {
		$cache = $this->cacheFactory->createDistributed(Application::APP_ID . '_library');
		// Nextcloud propagates etags up the tree, so the library folder's
		// etag changes whenever anything inside it does.
		$key = implode('|', [
			self::CACHE_VERSION,
			$userId,
			$folderPath,
			$folder->getEtag(),
			$settings['thumbnails_folder'],
			$this->folderEtag($userFolder, $settings['thumbnails_folder']),
		]);
		if (!$refresh) {
			$cached = $cache->get($key);
			if (is_array($cached)) {
				return $cached;
			}
		}

		$games = [];
		$extensionMap = CoreMap::extensionSystemMap();
		// Zipped ROMs are extracted in the browser when launched.
		$extensionMap['zip'] = 'zip';
		$this->findRoms($folder, $userFolder, $extensionMap, $games, 0, []);
		$this->addThumbnails($games, $userFolder, $settings['thumbnails_folder'], $folderPath);

		// Only what the list draws is worth keeping: a big library would
		// otherwise weigh on the memory cache of small instances.
		$cache->set($key, $games, self::CACHE_TTL);
		return $games;
	}

	private function folderEtag(Folder $userFolder, string $path): string {
		if ($path === '') {
			return '';
		}
		try {
			return $userFolder->get($path)->getEtag();
		} catch (NotFoundException) {
			return '';
		}
	}

	/**
	 * @param list<array<string, mixed>> $games
	 */
	public function sortGames(array &$games, string $sort, string $order): void {
		$direction = $order === 'desc' ? -1 : 1;
		usort($games, static function (array $a, array $b) use ($sort, $direction): int {
			$result = match ($sort) {
				'system' => strcasecmp($a['system'], $b['system']),
				'size' => $a['size'] <=> $b['size'],
				'mtime' => $a['mtime'] <=> $b['mtime'],
				default => 0,
			};
			// Fall back to the name, so the order is always stable.
			if ($result === 0) {
				$result = strcasecmp($a['basename'], $b['basename']);
				return $sort === 'name' ? $result * $direction : $result;
			}
			return $result * $direction;
		});
	}

	/**
	 * Attach the matching images to each game, by type.
	 *
	 * @param list<array<string, mixed>> $games
	 */
	private function addThumbnails(array &$games, Folder $userFolder, string $thumbnailsPath, string $libraryPath): void {
		if ($thumbnailsPath === '') {
			return;
		}
		try {
			$thumbnails = $userFolder->get($thumbnailsPath);
		} catch (NotFoundException) {
			return;
		}
		if (!$thumbnails instanceof Folder) {
			return;
		}
		$index = $this->thumbnailService->buildIndex($thumbnails);
		foreach ($games as &$game) {
			$subfolder = trim(dirname(substr($game['path'], strlen($libraryPath))), '/.');
			$found = $this->thumbnailService->forGame($index, $game['system'], $subfolder, $game['basename']);
			if ($found !== []) {
				$game['thumbnails'] = $found;
			}
		}
	}

	/**
	 * @param array<string, string> $extensionMap extension => system id
	 * @param list<array{path: string, basename: string, system: string}> $games
	 * @param list<string> $parents folder names between the library root and here
	 */
	private function findRoms(Folder $folder, Folder $userFolder, array $extensionMap, array &$games, int $depth, array $parents): void {
		if ($depth > self::MAX_DEPTH || count($games) >= self::MAX_GAMES) {
			return;
		}
		foreach ($folder->getDirectoryListing() as $node) {
			if (count($games) >= self::MAX_GAMES) {
				return;
			}
			if ($node instanceof Folder) {
				$this->findRoms($node, $userFolder, $extensionMap, $games, $depth + 1, [...$parents, $node->getName()]);
				continue;
			}
			$extension = strtolower(pathinfo($node->getName(), PATHINFO_EXTENSION));
			if (!isset($extensionMap[$extension])) {
				continue;
			}
			$system = $extensionMap[$extension];
			if ($system === 'zip') {
				// A zip does not reveal its system; the folder it is stored
				// in often does, e.g. "Games/SNES/NHL 96.zip".
				foreach (array_reverse($parents) as $parent) {
					$fromFolder = CoreMap::systemForFolderName($parent);
					if ($fromFolder !== null) {
						$system = $fromFolder;
						break;
					}
				}
			}
			$games[] = [
				'path' => $userFolder->getRelativePath($node->getPath()),
				'basename' => $node->getName(),
				'system' => $system,
				'size' => $node->getSize(),
				'mtime' => $node->getMTime(),
			];
		}
	}
}
