<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Controller;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\BackgroundJob\FetchThumbnails;
use OCA\Nostalgist\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IRequest;

/**
 * Asking for the box art of the games that have none, and hearing how it
 * went. The looking itself happens in a background job.
 *
 * @psalm-suppress UnusedClass
 */
#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
class ThumbnailController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private SettingsService $settingsService,
		private IJobList $jobList,
		private IConfig $config,
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
		if ($this->settingsService->getUserSettings($this->userId)['thumbnails_folder'] === '') {
			return new JSONResponse(
				['message' => 'Set a thumbnails folder first'],
				Http::STATUS_PRECONDITION_FAILED,
			);
		}

		$argument = ['userId' => $this->userId];
		if (!$this->jobList->has(FetchThumbnails::class, $argument)) {
			$this->jobList->add(FetchThumbnails::class, $argument);
		}
		$this->setStatus('Looking for box art in the background');
		return new JSONResponse($this->status());
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/thumbnails/fetch')]
	public function status(): JSONResponse|array {
		if ($this->userId === null) {
			return new JSONResponse([], Http::STATUS_UNAUTHORIZED);
		}
		$stored = $this->config->getUserValue($this->userId, Application::APP_ID, 'fetch_status', '');
		$status = $stored === '' ? null : json_decode($stored, true);
		return [
			'message' => is_array($status) ? ($status['message'] ?? '') : '',
			'time' => is_array($status) ? ($status['time'] ?? 0) : 0,
			'queued' => $this->jobList->has(FetchThumbnails::class, ['userId' => $this->userId]),
		];
	}

	private function setStatus(string $message): void {
		$this->config->setUserValue(
			(string)$this->userId,
			Application::APP_ID,
			'fetch_status',
			json_encode(['message' => $message, 'time' => time()]),
		);
	}
}
