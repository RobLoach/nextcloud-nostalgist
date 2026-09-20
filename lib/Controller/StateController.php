<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Controller;

use OCA\Nostalgist\Service\StateService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

/**
 * Save states, one per user and game.
 *
 * @psalm-suppress UnusedClass
 */
#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
class StateController extends Controller {
	// RetroArch save states are a few MB; leave plenty of headroom.
	private const MAX_STATE_SIZE = 64 * 1024 * 1024;

	public function __construct(
		string $appName,
		IRequest $request,
		private StateService $stateService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/state')]
	public function get(string $file = ''): Response {
		if ($this->userId === null || $file === '') {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		$state = $this->stateService->load($this->userId, $file);
		if ($state === null) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
		return new DataDownloadResponse($state, basename($file) . '.state', 'application/octet-stream');
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/state')]
	public function save(string $file = ''): JSONResponse {
		if ($this->userId === null || $file === '') {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		$state = file_get_contents('php://input', length: self::MAX_STATE_SIZE + 1);
		if ($state === false || $state === '' || strlen($state) > self::MAX_STATE_SIZE) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		$this->stateService->save($this->userId, $file, $state);
		return new JSONResponse(['size' => strlen($state)]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/state')]
	public function delete(string $file = ''): JSONResponse {
		if ($this->userId === null || $file === '') {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		if (!$this->stateService->delete($this->userId, $file)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
		return new JSONResponse([]);
	}
}
