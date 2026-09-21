<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Controller;

use OCA\Nostalgist\Service\RecentService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
class RecentController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private RecentService $recentService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/recent')]
	public function record(string $file = ''): JSONResponse {
		if ($this->userId === null || $file === '') {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		$this->recentService->record($this->userId, $file);
		return new JSONResponse([]);
	}
}
