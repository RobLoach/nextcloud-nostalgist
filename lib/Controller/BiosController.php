<?php

declare(strict_types=1);

namespace OCA\Arcade\Controller;

use OCA\Arcade\CoreMap;
use OCA\Arcade\Service\BiosService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Hands out the BIOS files the instance holds, for the players that need
 * one and whose own system folder has not got it, and lets an administrator
 * manage that store from the settings page instead of the occ command.
 *
 * @psalm-suppress UnusedClass
 */
#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
class BiosController extends Controller {
	// BIOS files are small; the largest asked for is well under a megabyte.
	private const MAX_BIOS_SIZE = 16 * 1024 * 1024;

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

	/**
	 * What every system that wants a BIOS has and has not got, for the
	 * administrator. Admin-only, so no NoAdminRequired here.
	 */
	#[FrontpageRoute(verb: 'GET', url: '/bios/status')]
	public function status(): JSONResponse {
		$stored = $this->biosService->stored();
		$systems = [];
		$claimed = [];
		foreach (CoreMap::SYSTEMS as $id => $system) {
			if ($system['bios'] === []) {
				continue;
			}
			$files = [];
			foreach ($system['bios'] as $name) {
				$claimed[$name] = true;
				$files[] = [
					'name' => $name,
					'present' => array_key_exists($name, $stored),
					'size' => $stored[$name] ?? 0,
				];
			}
			$systems[] = [
				'system' => ['id' => $id, 'name' => $system['label']],
				'files' => $files,
			];
		}
		$extra = [];
		foreach ($stored as $name => $size) {
			if (!isset($claimed[$name])) {
				$extra[] = ['name' => $name, 'size' => $size];
			}
		}
		return new JSONResponse(['systems' => $systems, 'extra' => $extra]);
	}

	/**
	 * Takes one BIOS file, sent as the raw request body, and only under a
	 * name some core actually asks for. Admin-only.
	 */
	#[FrontpageRoute(verb: 'POST', url: '/bios')]
	public function upload(string $name = ''): JSONResponse {
		$canonical = BiosService::canonicalName($name);
		if ($canonical === null) {
			return new JSONResponse(
				['error' => 'No core asks for a file of that name'],
				Http::STATUS_BAD_REQUEST,
			);
		}
		$data = $this->readBody(self::MAX_BIOS_SIZE + 1);
		if (!is_string($data) || $data === '' || strlen($data) > self::MAX_BIOS_SIZE) {
			return new JSONResponse(
				['error' => 'The file is empty or larger than a BIOS could be'],
				Http::STATUS_BAD_REQUEST,
			);
		}
		if (!$this->biosService->write($canonical, $data)) {
			return new JSONResponse(
				['error' => 'The file could not be stored'],
				Http::STATUS_INTERNAL_SERVER_ERROR,
			);
		}
		return new JSONResponse(['name' => $canonical, 'size' => strlen($data)]);
	}

	/**
	 * Takes a BIOS file back out of the store, by name. Admin-only.
	 */
	#[FrontpageRoute(verb: 'DELETE', url: '/bios')]
	public function remove(string $name = ''): JSONResponse {
		$canonical = BiosService::canonicalName($name);
		if ($canonical === null) {
			return new JSONResponse(
				['error' => 'No core asks for a file of that name'],
				Http::STATUS_BAD_REQUEST,
			);
		}
		if (!$this->biosService->remove($canonical)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
		return new JSONResponse([]);
	}

	/**
	 * The raw request body, up to $limit bytes. Overridable so tests can
	 * stand in for php://input, which cannot be written to from a test.
	 */
	protected function readBody(int $limit): string|false {
		return file_get_contents('php://input', length: $limit);
	}
}
