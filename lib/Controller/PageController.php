<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Controller;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\CoreMap;
use OCA\Nostalgist\Service\LibraryService;
use OCA\Nostalgist\Service\RecentService;
use OCA\Nostalgist\Service\SettingsService;
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
				'favorites' => [],
				'games' => [],
			]);
		}

		$games = $this->libraryService->getGames($this->userId, $folder, $userFolder, $folderPath, $settings, $refresh);
		$libraryTotal = count($games);
		$recent = $this->getRecent($userFolder, $games);
		$favorites = $this->getFavorites($userFolder, $games);
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
		$this->libraryService->addFallbackImages($this->userId, $page, $userFolder, $settings);
		$this->libraryService->addFallbackImages($this->userId, $recent, $userFolder, $settings);
		$this->libraryService->addFallbackImages($this->userId, $favorites, $userFolder, $settings);

		return new JSONResponse([
			'folder' => $folderPath,
			'exists' => true,
			'total' => count($games),
			'libraryTotal' => $libraryTotal,
			'offset' => $offset,
			'limit' => $limit,
			'truncated' => $libraryTotal >= LibraryService::MAX_GAMES,
			'systems' => $systems,
			'recent' => $recent,
			'favorites' => $favorites,
			'games' => $page,
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
		return $this->present($this->recentService->get((string)$this->userId), $userFolder, $games);
	}

	/**
	 * @param list<array<string, mixed>> $games
	 * @return list<array<string, mixed>>
	 */
	private function getFavorites(Folder $userFolder, array $games): array {
		$favorites = $this->present($this->recentService->getFavorites((string)$this->userId), $userFolder, $games);
		// The most played first, which is what a favorite is about.
		usort($favorites, static fn (array $a, array $b): int => ($b['seconds'] ?? 0) <=> ($a['seconds'] ?? 0));
		return $favorites;
	}

	/**
	 * Fill in what the library knows about remembered games, and drop the
	 * ones that are gone.
	 *
	 * @param list<array<string, mixed>> $entries
	 * @param list<array<string, mixed>> $games
	 * @return list<array<string, mixed>>
	 */
	private function present(array $entries, Folder $userFolder, array $games): array {
		$byPath = array_column($games, null, 'path');
		$present = [];
		foreach ($entries as $entry) {
			$path = $entry['path'] ?? '';
			if ($path === '' || !$userFolder->nodeExists($path)) {
				continue;
			}
			$present[] = isset($byPath[$path]) ? [...$byPath[$path], ...$entry] : $entry;
		}
		return $present;
	}
}
