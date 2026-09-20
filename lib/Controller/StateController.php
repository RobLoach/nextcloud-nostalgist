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
 * Save states, unique per user and game, with a fixed number of slots.
 *
 * @psalm-suppress UnusedClass
 */
#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
class StateController extends Controller {
	// RetroArch save states are a few MB; leave plenty of headroom.
	private const MAX_STATE_SIZE = 64 * 1024 * 1024;
	private const MAX_THUMBNAIL_SIZE = 4 * 1024 * 1024;

	public function __construct(
		string $appName,
		IRequest $request,
		private StateService $stateService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/states')]
	public function list(string $file = ''): JSONResponse {
		if ($this->userId === null || $file === '') {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		return new JSONResponse([
			'slots' => StateService::SLOTS,
			'states' => $this->stateService->list($this->userId, $file),
		]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/state')]
	public function get(string $file = '', int $slot = 1): Response {
		if (!$this->isValidRequest($file, $slot)) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		$state = $this->stateService->load($this->userId, $file, $slot);
		if ($state === null) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
		return new DataDownloadResponse($state, basename($file) . '.state', 'application/octet-stream');
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/state')]
	public function save(string $file = '', int $slot = 1): JSONResponse {
		if (!$this->isValidRequest($file, $slot)) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		$state = $this->readBody(self::MAX_STATE_SIZE);
		if ($state === null) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		$this->stateService->save($this->userId, $file, $slot, $state);
		return new JSONResponse(['size' => strlen($state)]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/state/thumbnail')]
	public function getThumbnail(string $file = '', int $slot = 1): Response {
		if (!$this->isValidRequest($file, $slot)) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		$thumbnail = $this->stateService->loadThumbnail($this->userId, $file, $slot);
		if ($thumbnail === null) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
		return new DataDownloadResponse($thumbnail, 'thumbnail.png', 'image/png');
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/state/thumbnail')]
	public function saveThumbnail(string $file = '', int $slot = 1): JSONResponse {
		if (!$this->isValidRequest($file, $slot)) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		$thumbnail = $this->readBody(self::MAX_THUMBNAIL_SIZE);
		if ($thumbnail === null) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		$this->stateService->saveThumbnail($this->userId, $file, $slot, $thumbnail);
		return new JSONResponse([]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/state')]
	public function delete(string $file = '', int $slot = 1): JSONResponse {
		if (!$this->isValidRequest($file, $slot)) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}
		if (!$this->stateService->delete($this->userId, $file, $slot)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
		return new JSONResponse([]);
	}

	/**
	 * @psalm-assert-if-true string $this->userId
	 */
	private function isValidRequest(string $file, int $slot): bool {
		return $this->userId !== null
			&& $file !== ''
			&& $slot >= 1
			&& $slot <= StateService::SLOTS;
	}

	private function readBody(int $maxSize): ?string {
		$body = file_get_contents('php://input', length: $maxSize + 1);
		if ($body === false || $body === '' || strlen($body) > $maxSize) {
			return null;
		}
		return $body;
	}
}
