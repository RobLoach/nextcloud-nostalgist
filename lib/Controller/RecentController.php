<?php

declare(strict_types=1);

namespace OCA\Arcade\Controller;

use OCA\Arcade\Service\RecentService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IRequest;

/**
 * What was played, and what is a favorite.
 *
 * @psalm-suppress UnusedClass
 */
#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
class RecentController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private RecentService $recentService,
		private IRootFolder $rootFolder,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/recent')]
	public function record(string $file = '', int $seconds = 0): JSONResponse {
		if (!$this->isGame($file)) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		if ($seconds > 0) {
			// Reported when a game is left, to add to its play time.
			$this->recentService->addPlayTime($this->userId, $file, $seconds);
		} else {
			$this->recentService->record($this->userId, $file);
		}
		return new JSONResponse([]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/favorite')]
	public function favorite(string $file = ''): JSONResponse {
		if (!$this->isGame($file)) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse([
			'favorite' => $this->recentService->toggleFavorite($this->userId, $file),
		]);
	}

	/**
	 * Only files the user actually has are worth remembering.
	 *
	 * @psalm-assert-if-true string $this->userId
	 */
	private function isGame(string $file): bool {
		return $this->userId !== null
			&& $file !== ''
			&& $this->rootFolder->getUserFolder($this->userId)->nodeExists($file);
	}
}
