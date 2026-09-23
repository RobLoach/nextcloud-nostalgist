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
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;

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
		private ISystemTagManager $tagManager,
		private ISystemTagObjectMapper $tagObjectMapper,
	) {
	}

	/**
	 * Attach the system tags of each ROM, as the Files app has them. Two
	 * queries for the whole library, however many games carry tags.
	 *
	 * Tags come and go without the folder changing, so they are looked up
	 * on every request rather than kept in the cached scan.
	 *
	 * @param list<array<string, mixed>> $games
	 */
	public function addTags(array &$games): void {
		$fileIds = array_map(
			static fn (int $id): string => (string)$id,
			array_values(array_filter(array_column($games, 'id'))),
		);
		if ($fileIds === []) {
			return;
		}
		try {
			$tagIdsByFile = $this->tagObjectMapper->getTagIdsForObjects($fileIds, 'files');
			$tagIds = [];
			foreach ($tagIdsByFile as $ids) {
				foreach ($ids as $tagId) {
					$tagIds[(string)$tagId] = true;
				}
			}
			$names = [];
			if ($tagIds !== []) {
				foreach ($this->tagManager->getTagsByIds(array_map('strval', array_keys($tagIds))) as $tag) {
					// Tags an administrator keeps out of sight in the Files
					// app stay out of sight here too.
					if ($tag->isUserVisible()) {
						$names[$tag->getId()] = $tag->getName();
					}
				}
			}
		} catch (\Throwable) {
			// Tags are a filter, not the library: the games still list
			// without them.
			return;
		}
		foreach ($games as &$game) {
			$tags = [];
			foreach ($tagIdsByFile[(string)($game['id'] ?? '')] ?? [] as $tagId) {
				if (isset($names[$tagId])) {
					$tags[] = $names[$tagId];
				}
			}
			sort($tags, SORT_NATURAL | SORT_FLAG_CASE);
			$game['tags'] = $tags;
		}
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
	public function filterGames(array $games, string $search, string $system, string $tag = ''): array {
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
		if ($tag !== '') {
			$games = array_filter(
				$games,
				static fn (array $game): bool => in_array($tag, $game['tags'] ?? [], true),
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
		// Zipped ROMs are extracted in the browser when launched, and a
		// .bin says nothing about whose game it is.
		$extensionMap = CoreMap::libraryExtensions();
		$limits = [
			'games' => (int)($settings['max_games'] ?? self::MAX_GAMES),
			'depth' => (int)($settings['max_depth'] ?? self::MAX_DEPTH),
		];
		$this->findRoms($folder, $userFolder, $extensionMap, $games, 0, [], $limits);
		$this->addWhatWasRead($games);
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
		foreach ($games as &$game) {
			$found = $this->thumbnailService->forGameNamed(
				$index,
				$game['system'],
				$this->thumbnailService->subfolderOf($game['path'], $libraryPath),
				$game['basename'],
				$game['title'] ?? '',
			);
			if ($found !== []) {
				$game['thumbnails'] = $found;
			}
		}
	}

	/**
	 * Attach what was read of each ROM: the name the cartridge gives
	 * itself, the region it was sold in, and -- for a .bin, whose name says
	 * nothing -- which machine it turned out to be for.
	 *
	 * @param list<array<string, mixed>> $games
	 */
	private function addWhatWasRead(array &$games): void {
		$known = $this->knownOf($games);
		foreach ($games as &$game) {
			$read = $known[$game['id']] ?? null;
			if ($read === null) {
				continue;
			}
			foreach (['title', 'region'] as $key) {
				if ($read[$key] !== '') {
					$game[$key] = $read[$key];
				}
			}
			// A name that says nothing leaves the folder guessing, and what
			// was read of the file itself settles it either way.
			$ambiguous = CoreMap::isAmbiguous(pathinfo($game['basename'], PATHINFO_EXTENSION));
			if ($read['system'] !== '' && (($game['system'] ?? '') === '' || $ambiguous)) {
				$game['system'] = $read['system'];
			}
		}
	}

	/**
	 * What each cartridge says about itself, for the games that have been
	 * read. One query for the whole library.
	 *
	 * @param list<array<string, mixed>> $games
	 * @return array<int, array{title: string, region: string, system: string}>
	 */
	private function knownOf(array $games): array {
		$ids = array_values(array_filter(array_column($games, 'id')));
		if ($ids === []) {
			return [];
		}
		$known = [];
		try {
			foreach ($this->metadataManager->getMetadataForFiles($ids) as $id => $metadata) {
				$known[(int)$id] = [
					'title' => $metadata->getString(MetadataListener::TITLE),
					'region' => $metadata->getString(MetadataListener::REGION),
					'system' => $metadata->getString(MetadataListener::SYSTEM),
				];
			}
		} catch (\Throwable) {
			// Nothing has been read of the ROMs yet, which is no reason to
			// go without the thumbnails that do match.
		}
		return $known;
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
			if ($system === '') {
				// Neither a zip nor a .bin reveals its system; the folder it
				// is stored in often does, e.g. "Games/SNES/NHL 96.zip".
				foreach (array_reverse($parents) as $parent) {
					$fromFolder = CoreMap::systemForFolderName($parent);
					if ($fromFolder !== null) {
						$system = $fromFolder;
						break;
					}
				}
			}
			// A zip that nothing names is still a zip; a .bin that nothing
			// names waits for its first bytes to be read.
			if ($system === '' && $extension === 'zip') {
				$system = 'zip';
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
