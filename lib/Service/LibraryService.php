<?php

declare(strict_types=1);

namespace OCA\Arcade\Service;

use OCA\Arcade\AppInfo\Application;
use OCA\Arcade\CoreMap;
use OCA\Arcade\Listener\MetadataListener;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\FilesMetadata\IFilesMetadataManager;
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
	/**
	 * What a library is walked to, unless an administrator says otherwise.
	 * SettingsService offers these as the defaults of the instance, so the
	 * limits arrive with everything else a scan is given.
	 */
	public const MAX_GAMES = 5000;
	public const MAX_DEPTH = 6;
	public const CACHE_TTL = 24 * 3600;
	/** Bumped when the shape of a cached entry changes. */
	private const CACHE_VERSION = 5;

	/**
	 * The screenshots of a user, by the game they were taken of.
	 *
	 * @var array<string, array<string, array{id: int, mtime: int}>>
	 */
	private array $screenshots = [];

	public function __construct(
		private ICacheFactory $cacheFactory,
		private ThumbnailService $thumbnailService,
		private StateService $stateService,
		private IFilesMetadataManager $metadataManager,
	) {
	}

	/**
	 * Games without a thumbnail fall back to a picture of themselves: the
	 * most recent of the screenshots taken of them and the screenshots of
	 * their save states.
	 *
	 * @param array<string, mixed> $settings
	 * @param list<array<string, mixed>> ...$lists every list the page shows,
	 *                                             so a game is worked out once
	 */
	public function addFallbackImages(
		string $userId,
		Folder $userFolder,
		array $settings,
		array &...$lists,
	): void {
		$missing = [];
		foreach ($lists as $games) {
			foreach ($games as $game) {
				if (empty($game['thumbnails'])) {
					$missing[$game['path']] = $game;
				}
			}
		}
		if ($missing === []) {
			return;
		}

		// The listing asks three times over -- for the page, the recently
		// played and the favorites -- and the folder cannot change in
		// between, so it is walked once.
		$screenshots = $this->screenshots[$userId] ??= $this->indexScreenshots($userFolder, $settings);
		$states = $this->stateService->thumbnailIndex($userId, array_keys($missing));

		// Worked out once per game, however many of the lists it is in.
		$fallbacks = [];
		foreach ($missing as $path => $game) {
			$screenshot = null;
			foreach ($this->thumbnailService->screenshotKeys($game['basename']) as $key) {
				if (isset($screenshots[$key])) {
					$screenshot = $screenshots[$key];
					break;
				}
			}
			$state = $states[$path] ?? null;

			if ($screenshot !== null && ($state === null || $screenshot['mtime'] >= $state['mtime'])) {
				$fallbacks[$path] = ['type' => 'screenshot', 'fileId' => $screenshot['id']];
			} elseif ($state !== null) {
				$fallbacks[$path] = ['type' => 'state', 'slot' => $state['slot']];
			}
		}

		foreach ($lists as &$games) {
			foreach ($games as &$game) {
				if (empty($game['thumbnails']) && isset($fallbacks[$game['path']])) {
					$game['fallback'] = $fallbacks[$game['path']];
				}
			}
			unset($game);
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
			(string)($settings['max_games'] ?? self::MAX_GAMES),
			(string)($settings['max_depth'] ?? self::MAX_DEPTH),
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
		$limits = [
			'games' => (int)($settings['max_games'] ?? self::MAX_GAMES),
			'depth' => (int)($settings['max_depth'] ?? self::MAX_DEPTH),
		];
		$this->findRoms($folder, $userFolder, $extensionMap, $games, 0, [], $limits);
		$this->addThumbnails($games, $userFolder, $settings['thumbnails_folder'], $folderPath);

		// Only what the list draws is worth keeping: a big library would
		// otherwise weigh on the memory cache of small instances.
		$cache->set($key, $games, (int)($settings['cache_ttl'] ?? self::CACHE_TTL));
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
	 * @param array<string, mixed> $settings
	 * @return array<string, array{id: int, mtime: int}>
	 */
	private function indexScreenshots(Folder $userFolder, array $settings): array {
		if ($settings['screenshots_folder'] === '') {
			return [];
		}
		try {
			$folder = $userFolder->get($settings['screenshots_folder']);
		} catch (NotFoundException) {
			// No screenshots folder, no screenshots.
			return [];
		}
		return $folder instanceof Folder ? $this->thumbnailService->indexScreenshots($folder) : [];
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
		$titles = $this->titles($games);
		foreach ($games as &$game) {
			$title = $titles[$game['id']] ?? '';
			if ($title !== '') {
				$game['title'] = $title;
			}
			$found = $this->thumbnailService->forGameNamed(
				$index,
				$game['system'],
				$this->thumbnailService->subfolderOf($game['path'], $libraryPath),
				$game['basename'],
				$title,
			);
			if ($found !== []) {
				$game['thumbnails'] = $found;
			}
		}
	}

	/**
	 * The name each cartridge gives itself, for the games that have one
	 * read. One query for the whole library.
	 *
	 * @param list<array<string, mixed>> $games
	 * @return array<int, string>
	 */
	private function titles(array $games): array {
		$ids = array_values(array_filter(array_column($games, 'id')));
		if ($ids === []) {
			return [];
		}
		$titles = [];
		try {
			foreach ($this->metadataManager->getMetadataForFiles($ids) as $id => $metadata) {
				$title = $metadata->getString(MetadataListener::TITLE);
				if ($title !== '') {
					$titles[(int)$id] = $title;
				}
			}
		} catch (\Throwable) {
			// Nothing has been read of the ROMs yet, which is no reason to
			// go without the thumbnails that do match.
		}
		return $titles;
	}

	/**
	 * @param array<string, string> $extensionMap extension => system id
	 * @param list<array{path: string, basename: string, system: string}> $games
	 * @param list<string> $parents folder names between the library root and here
	 * @param array{games: int, depth: int} $limits how far this scan goes
	 */
	private function findRoms(
		Folder $folder,
		Folder $userFolder,
		array $extensionMap,
		array &$games,
		int $depth,
		array $parents,
		array $limits,
	): void {
		if ($depth > $limits['depth'] || count($games) >= $limits['games']) {
			return;
		}
		foreach ($folder->getDirectoryListing() as $node) {
			if (count($games) >= $limits['games']) {
				return;
			}
			if ($node instanceof Folder) {
				$this->findRoms(
					$node,
					$userFolder,
					$extensionMap,
					$games,
					$depth + 1,
					[...$parents, $node->getName()],
					$limits,
				);
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
				'id' => $node->getId(),
				'path' => $userFolder->getRelativePath($node->getPath()),
				'basename' => $node->getName(),
				'system' => $system,
				'size' => $node->getSize(),
				'mtime' => $node->getMTime(),
			];
		}
	}
}
