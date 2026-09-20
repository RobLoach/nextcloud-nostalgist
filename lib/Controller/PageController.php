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
use OCP\AppFramework\Http\ContentSecurityPolicy;
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

		$response = new TemplateResponse(
			Application::APP_ID,
			'index',
		);

		// Nostalgist.js compiles RetroArch cores to WebAssembly from blob: URLs,
		// which the default policy blocks.
		$csp = new ContentSecurityPolicy();
		$csp->allowEvalWasm(true);
		$csp->addAllowedScriptDomain('blob:');
		$csp->addAllowedWorkerSrcDomain('blob:');
		$response->setContentSecurityPolicy($csp);

		return $response;
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
		$this->findRoms($folder, $userFolder, CoreMap::extensionSystemMap(), $games, 0);
		usort($games, static fn (array $a, array $b): int => strcasecmp($a['basename'], $b['basename']));

		return new JSONResponse([
			'folder' => $folderPath,
			'exists' => true,
			'games' => $games,
		]);
	}

	/**
	 * @param array<string, string> $extensionMap extension => system id
	 * @param list<array{path: string, basename: string, system: string}> $games
	 */
	private function findRoms(Folder $folder, Folder $userFolder, array $extensionMap, array &$games, int $depth): void {
		if ($depth > self::LIBRARY_MAX_DEPTH || count($games) >= self::LIBRARY_MAX_GAMES) {
			return;
		}
		foreach ($folder->getDirectoryListing() as $node) {
			if (count($games) >= self::LIBRARY_MAX_GAMES) {
				return;
			}
			if ($node instanceof Folder) {
				$this->findRoms($node, $userFolder, $extensionMap, $games, $depth + 1);
				continue;
			}
			$extension = strtolower(pathinfo($node->getName(), PATHINFO_EXTENSION));
			if (isset($extensionMap[$extension])) {
				$games[] = [
					'path' => $userFolder->getRelativePath($node->getPath()),
					'basename' => $node->getName(),
					'system' => $extensionMap[$extension],
				];
			}
		}
	}
}
