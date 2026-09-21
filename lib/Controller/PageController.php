<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Controller;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\CoreMap;
use OCA\Nostalgist\Service\RecentService;
use OCA\Nostalgist\Service\SettingsService;
use OCA\Nostalgist\Service\ThumbnailService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ICacheFactory;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {
	private const LIBRARY_MAX_GAMES = 5000;
	private const LIBRARY_MAX_DEPTH = 6;
	private const LIBRARY_MAX_PAGE_SIZE = 500;
	private const LIBRARY_CACHE_TTL = 24 * 3600;
	/** Bumped when the shape of a cached entry changes. */
	private const LIBRARY_CACHE_VERSION = 2;

	public function __construct(
		string $appName,
		IRequest $request,
		private IInitialState $initialState,
		private SettingsService $settingsService,
		private IRootFolder $rootFolder,
		private ICacheFactory $cacheFactory,
		private ThumbnailService $thumbnailService,
		private RecentService $recentService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(string $file = ''): TemplateResponse {
		$this->initialState->provideInitialState('file', $file);
		$this->initialState->provideInitialState('systems', CoreMap::SYSTEMS);
		$this->initialState->provideInitialState(
			'settings',
			$this->userId === null
				? $this->settingsService->getDefaults()
				: $this->settingsService->getUserSettings($this->userId),
		);

		// The emulator's Content Security Policy needs are added globally by
		// the CSPListener, so the default policy applies here.
		return new TemplateResponse(
			Application::APP_ID,
			'index',
		);
	}

	/**
	 * List the ROMs found in the user's games library folder, one page at a
	 * time. The full scan is cached, so paging through a large library only
	 * walks the folders once.
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/library')]
	public function library(
		int $offset = 0,
		int $limit = 60,
		string $sort = 'name',
		string $order = 'asc',
		string $search = '',
		string $system = '',
		bool $refresh = false,
	): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse([], Http::STATUS_UNAUTHORIZED);
		}
		$settings = $this->settingsService->getUserSettings($this->userId);
		$folderPath = $settings['library_folder'];
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		try {
			$folder = $userFolder->get($folderPath);
		} catch (NotFoundException) {
			$folder = null;
		}
		if (!$folder instanceof Folder) {
			return new JSONResponse([
				'folder' => $folderPath,
				'exists' => false,
				'total' => 0,
				'libraryTotal' => 0,
				'offset' => 0,
				'limit' => $limit,
				'systems' => [],
				'recent' => [],
				'games' => [],
			]);
		}

		$games = $this->getGames($folder, $userFolder, $folderPath, $settings, $refresh);
		$libraryTotal = count($games);
		$recent = $this->getRecent($userFolder, $games);
		// The systems of the whole library, so the filter keeps offering
		// them while a filter is active.
		$systems = array_values(array_unique(array_column($games, 'system')));
		sort($systems);

		$games = $this->filterGames($games, $search, $system);
		$this->sortGames($games, $sort, $order);

		$limit = max(1, min(self::LIBRARY_MAX_PAGE_SIZE, $limit));
		$offset = max(0, min($offset, max(0, count($games) - 1)));

		return new JSONResponse([
			'folder' => $folderPath,
			'exists' => true,
			'total' => count($games),
			'libraryTotal' => $libraryTotal,
			'offset' => $offset,
			'limit' => $limit,
			'truncated' => $libraryTotal >= self::LIBRARY_MAX_GAMES,
			'systems' => $systems,
			'recent' => $recent,
			'games' => array_slice($games, $offset, $limit),
		]);
	}

	/**
	 * The games played last, with the thumbnails of the library when they
	 * are part of it, and without the ones that are gone.
	 *
	 * @param list<array<string, mixed>> $games
	 * @return list<array<string, mixed>>
	 */
	private function getRecent(Folder $userFolder, array $games): array {
		$byPath = array_column($games, null, 'path');
		$recent = [];
		foreach ($this->recentService->get((string)$this->userId) as $entry) {
			$path = $entry['path'] ?? '';
			if ($path === '' || !$userFolder->nodeExists($path)) {
				continue;
			}
			$recent[] = isset($byPath[$path])
				? [...$byPath[$path], 'time' => $entry['time'] ?? 0]
				: $entry;
		}
		return $recent;
	}

	/**
	 * @param list<array<string, mixed>> $games
	 * @return list<array<string, mixed>>
	 */
	private function filterGames(array $games, string $search, string $system): array {
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
	private function getGames(Folder $folder, Folder $userFolder, string $folderPath, array $settings, bool $refresh): array {
		$cache = $this->cacheFactory->createDistributed(Application::APP_ID . '_library');
		// Nextcloud propagates etags up the tree, so the library folder's
		// etag changes whenever anything inside it does.
		$key = implode('|', [
			self::LIBRARY_CACHE_VERSION,
			$this->userId,
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

		$cache->set($key, $games, self::LIBRARY_CACHE_TTL);
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
	private function sortGames(array &$games, string $sort, string $order): void {
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
		if ($depth > self::LIBRARY_MAX_DEPTH || count($games) >= self::LIBRARY_MAX_GAMES) {
			return;
		}
		foreach ($folder->getDirectoryListing() as $node) {
			if (count($games) >= self::LIBRARY_MAX_GAMES) {
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
