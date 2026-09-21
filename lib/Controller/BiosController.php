<?php

declare(strict_types=1);

namespace OCA\Arcade\Controller;

use OCA\Arcade\Service\BiosService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IRequest;

/**
 * Hands out the BIOS files the instance holds, for the players that need
 * one and whose own system folder has not got it.
 *
 * @psalm-suppress UnusedClass
 */
#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
class BiosController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private BiosService $biosService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/bios')]
	public function get(string $name = ''): DataDisplayResponse {
		if ($this->userId === null) {
			return new DataDisplayResponse('', Http::STATUS_UNAUTHORIZED);
		}
		$data = $this->biosService->read($name);
		if ($data === null) {
			return new DataDisplayResponse('', Http::STATUS_NOT_FOUND);
		}
		$response = new DataDisplayResponse($data, Http::STATUS_OK, [
			'Content-Type' => 'application/octet-stream',
		]);
		// The same bytes for everybody, and they do not change.
		$response->cacheFor(24 * 3600, false, true);
		return $response;
	}
}
