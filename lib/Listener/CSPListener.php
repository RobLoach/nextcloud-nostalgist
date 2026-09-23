<?php

declare(strict_types=1);

namespace OCA\Arcade\Listener;

use OCA\Arcade\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

/**
 * Nostalgist.js compiles the RetroArch cores to WebAssembly and runs them
 * from blob: URLs, which the default policy blocks. The player also runs
 * inside the Files app (through the Viewer), so the allowances are added
 * on every page a user with the app could see -- a path check would miss
 * the Viewer.
 *
 * Only the needed directives are added, through an empty policy: adding a
 * full ContentSecurityPolicy here would merge all of its defaults into the
 * policy of every page of the instance.
 *
 * @template-implements IEventListener<AddContentSecurityPolicyEvent>
 */
class CSPListener implements IEventListener {
	public function __construct(
		private IUserSession $userSession,
		private IAppManager $appManager,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof AddContentSecurityPolicyEvent) {
			return;
		}
		// The app offers no player on unauthenticated pages, so the login
		// page and public share pages keep the default policy. A future
		// public-share player (the Viewer does fire on share links, and the
		// viewer component already takes a `source` URL for them) would have
		// to revisit this guard, or those games will not start.
		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}
		// "Enable app for specific groups" is a promise the policy keeps too.
		if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return;
		}
		$csp = new EmptyContentSecurityPolicy();
		$csp->allowEvalWasm(true);
		$csp->addAllowedScriptDomain('blob:');
		// Without worker-src the cores fall through to default-src, which
		// blocks them. Nextcloud 34 dropped child-src, the fallback older
		// browsers used, so worker-src is all there is to set.
		$csp->addAllowedWorkerSrcDomain('blob:');
		$csp->addAllowedFrameDomain('blob:');
		// The emulator fetches its core, ROM and save data as blobs.
		$csp->addAllowedConnectDomain('blob:');
		$csp->addAllowedConnectDomain('data:');
		$csp->addAllowedImageDomain('blob:');
		$csp->addAllowedImageDomain('data:');
		$csp->addAllowedMediaDomain('blob:');
		$csp->addAllowedMediaDomain('data:');
		$event->addPolicy($csp);
	}
}
