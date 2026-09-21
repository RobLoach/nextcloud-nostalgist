<?php

declare(strict_types=1);

namespace OCA\Arcade\Controller;

use OCA\Arcade\Service\SettingsService;
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
class SettingsController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private SettingsService $settingsService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/settings')]
	public function get(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse([], Http::STATUS_UNAUTHORIZED);
		}
		return new JSONResponse($this->settingsService->getUserSettings($this->userId));
	}

	#[FrontpageRoute(verb: 'POST', url: '/settings/admin')]
	public function saveAdmin(): JSONResponse {
		return new JSONResponse(
			$this->settingsService->setInstanceDefaults($this->request->getParams()),
		);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/settings')]
	public function save(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse([], Http::STATUS_UNAUTHORIZED);
		}
		$settings = $this->request->getParams();
		return new JSONResponse($this->settingsService->setUserSettings($this->userId, $settings));
	}
}
