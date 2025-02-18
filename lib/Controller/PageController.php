<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Controller;

use OCA\Nostalgist\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {
	#[NoCSRFRequired]
	#[NoAdminRequired]
	//#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		return new TemplateResponse(
			Application::APP_ID,
			'index',
		);
	}
}
