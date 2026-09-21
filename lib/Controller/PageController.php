<?php

declare(strict_types=1);

namespace OCA\Arcade\Controller;

use OCA\Arcade\AppInfo\Application;
use OCA\Arcade\BackgroundJob\RefreshMetadata;
use OCA\Arcade\CoreMap;
use OCA\Arcade\Service\LibraryService;
use OCA\Arcade\Service\RecentService;
use OCA\Arcade\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {
	private const MAX_PAGE_SIZE = 500;

	public function __construct(
		string $appName,
		IRequest $request,
		private IInitialState $initialState,
		private SettingsService $settingsService,
		private LibraryService $libraryService,
		private RecentService $recentService,
		private IRootFolder $rootFolder,
		private IJobList $jobList,
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
		// The words that say nothing about a system, so the browser can
		// read a folder name the way the server does without keeping a
		// copy of the lists.
		$this->initialState->provideInitialState('folderWords', [
			'noise' => CoreMap::NOISE,
			'vendors' => CoreMap::VENDORS,
		]);
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
				'favorites' => [],
				'games' => [],
			]);
		}

		$games = $this->libraryService->getGames($this->userId, $folder, $userFolder, $folderPath, $settings, $refresh);
		$libraryTotal = count($games);
		if ($refresh) {
			// Rescanning is the moment to ask what the games that were
			// already here say about themselves. Nextcloud reads that only
			// when a file is written, so nothing else ever asks.
			$this->queueMetadata();
		}
		$recent = $this->getRecent($games);
		$favorites = $this->getFavorites($games);
		// The systems of the whole library, so the filter keeps offering
		// them while a filter is active.
		$systems = array_values(array_unique(array_column($games, 'system')));
		sort($systems);

		$games = $this->libraryService->filterGames($games, $search, $system);
		$this->libraryService->sortGames($games, $sort, $order);

		$limit = max(1, min(self::MAX_PAGE_SIZE, $limit));
		$offset = max(0, min($offset, max(0, count($games) - 1)));

		// Only for what is about to be shown, and outside the cached scan:
		// screenshots and save states change as games are played.
		$page = array_slice($games, $offset, $limit);
		$this->libraryService->addFallbackImages($this->userId, $userFolder, $settings, $page, $recent, $favorites);

		return new JSONResponse([
			'folder' => $folderPath,
			'exists' => true,
			'total' => count($games),
			'libraryTotal' => $libraryTotal,
			'offset' => $offset,
			'limit' => $limit,
			'truncated' => $libraryTotal >= (int)($settings['max_games'] ?? LibraryService::MAX_GAMES),
			'systems' => $systems,
			'recent' => $recent,
			'favorites' => $favorites,
			'games' => $page,
		]);
	}

	/**
	 * Ask for the ROMs to be read, unless that is already waiting to
	 * happen. The job works out for itself which games are missing it.
	 */
	private function queueMetadata(): void {
		$argument = ['userId' => (string)$this->userId];
		if (!$this->jobList->has(RefreshMetadata::class, $argument)) {
			$this->jobList->add(RefreshMetadata::class, $argument);
		}
	}

	/**
	 * The games played last, in the order they were played, drawn from the
	 * library so they carry everything the library knows. A game that is
	 * gone, or that lives outside the library folder, is left out.
	 *
	 * @param list<array<string, mixed>> $games
	 * @return list<array<string, mixed>>
	 */
	private function getRecent(array $games): array {
		$byId = [];
		foreach ($games as $game) {
			$byId[$game['id'] ?? 0] = $game;
		}
		$stats = $this->recentService->stats((string)$this->userId);

		$recent = [];
		foreach ($this->recentService->get((string)$this->userId) as $id) {
			if (isset($byId[$id])) {
				$recent[] = [...$byId[$id], ...($stats[$id] ?? [])];
			}
		}
		return $recent;
	}

	/**
	 * The games of the library the user has starred, in the Files app or
	 * here -- it is the same star. Matched by file id, so a game keeps it
	 * when renamed or moved, and no lookup of its own is needed.
	 *
	 * @param list<array<string, mixed>> $games
	 * @return list<array<string, mixed>>
	 */
	private function getFavorites(array $games): array {
		$ids = $this->recentService->favoriteIds((string)$this->userId);
		if ($ids === []) {
			return [];
		}
		$stats = $this->recentService->stats((string)$this->userId);
		$favorites = [];
		foreach ($games as $game) {
			if (isset($ids[$game['id'] ?? 0])) {
				$favorites[] = [...$game, ...($stats[$game['id'] ?? 0] ?? [])];
			}
		}
		// The most played first, which is what a favorite is about.
		usort($favorites, static fn (array $a, array $b): int => ($b['seconds'] ?? 0) <=> ($a['seconds'] ?? 0));
		return $favorites;
	}
}
