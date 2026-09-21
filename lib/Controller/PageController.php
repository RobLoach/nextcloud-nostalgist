<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Controller;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\CoreMap;
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
	private const LIBRARY_MAX_GAMES = 500;
	private const LIBRARY_MAX_DEPTH = 4;

	public function __construct(
		string $appName,
		IRequest $request,
		private IInitialState $initialState,
		private SettingsService $settingsService,
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
	 * List the ROMs found in the user's games library folder.
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/library')]
	public function library(): JSONResponse {
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
				'games' => [],
			]);
		}

		$games = [];
		$extensionMap = CoreMap::extensionSystemMap();
		// Zipped ROMs are extracted in the browser when launched.
		$extensionMap['zip'] = 'zip';
		$this->findRoms($folder, $userFolder, $extensionMap, $games, 0, []);
		usort($games, static fn (array $a, array $b): int => strcasecmp($a['basename'], $b['basename']));
		$this->addThumbnails($games, $userFolder, $settings['thumbnails_folder'], $folderPath);

		return new JSONResponse([
			'folder' => $folderPath,
			'exists' => true,
			'games' => $games,
		]);
	}

	/**
	 * Attach the file id of a matching thumbnail image to each game. A game
	 * called Mario.nes matches Mario.png, Mario.jpg, etc. in the thumbnails
	 * folder — first in the same subfolder the game is in relative to the
	 * library (Games/NES/Mario.nes matches Thumbs/NES/Mario.png), then in
	 * the thumbnails folder root.
	 *
	 * @param list<array{path: string, basename: string, system: string}> $games
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
		foreach ($games as &$game) {
			$stem = pathinfo($game['basename'], PATHINFO_FILENAME);
			$subfolder = trim(dirname(substr($game['path'], strlen($libraryPath))), '/.');
			$candidates = [];
			foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $extension) {
				if ($subfolder !== '') {
					$candidates[] = "$subfolder/$stem.$extension";
				}
				$candidates[] = "$stem.$extension";
			}
			foreach ($candidates as $candidate) {
				if ($thumbnails->nodeExists($candidate)) {
					$game['thumbnail'] = $thumbnails->get($candidate)->getId();
					break;
				}
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
			];
		}
	}
}
