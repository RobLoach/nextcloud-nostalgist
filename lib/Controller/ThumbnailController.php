<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Controller;

use OCA\Nostalgist\Service\LibraryService;
use OCA\Nostalgist\Service\SettingsService;
use OCA\Nostalgist\Service\ThumbnailFetchService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IRequest;

/**
 * Fetching box art for the games that have none.
 *
 * @psalm-suppress UnusedClass
 */
#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
class ThumbnailController extends Controller {
	/** Games to look up in one request, so it answers in reasonable time. */
	private const BATCH = 20;

	public function __construct(
		string $appName,
		IRequest $request,
		private SettingsService $settingsService,
		private LibraryService $libraryService,
		private ThumbnailFetchService $fetchService,
		private IRootFolder $rootFolder,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/thumbnails/fetch')]
	public function fetch(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse([], Http::STATUS_UNAUTHORIZED);
		}
		$settings = $this->settingsService->getUserSettings($this->userId);
		if ($settings['thumbnails_folder'] === '') {
			return new JSONResponse(
				['message' => 'No thumbnails folder is set'],
				Http::STATUS_PRECONDITION_FAILED,
			);
		}

		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$library = $this->folderAt($userFolder, $settings['library_folder']);
		if ($library === null) {
			return new JSONResponse(['message' => 'No library folder'], Http::STATUS_PRECONDITION_FAILED);
		}
		$thumbnails = $this->folderAt($userFolder, $settings['thumbnails_folder'])
			?? $userFolder->newFolder(trim($settings['thumbnails_folder'], '/'));

		// A fresh scan, so games given an image a moment ago are skipped.
		$games = $this->libraryService->getGames(
			$this->userId,
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

		$result = $this->fetchService->fetch($this->userId, $missing, $thumbnails, self::BATCH);
		return new JSONResponse([
			'fetched' => $result['fetched'],
			'missing' => $result['missing'],
			'total' => count($missing),
		]);
	}

	private function folderAt(Folder $userFolder, string $path): ?Folder {
		try {
			$folder = $userFolder->get($path);
		} catch (NotFoundException) {
			return null;
		}
		return $folder instanceof Folder ? $folder : null;
	}
}
