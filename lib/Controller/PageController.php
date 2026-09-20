<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Controller;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\CoreMap;
use OCA\Nostalgist\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IInitialState $initialState,
		private SettingsService $settingsService,
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
}
