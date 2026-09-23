<?php

declare(strict_types=1);

namespace OCA\Arcade\Listener;

use OCA\Arcade\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
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
		private IRequest $request,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof AddContentSecurityPolicyEvent) {
			return;
		}
		$user = $this->userSession->getUser();
		if ($user === null) {
			// A game shared by link plays for anonymous visitors too -- the
			// Viewer fires on share pages and the player takes the share's
			// own URL as its source -- so those pages keep the allowances.
			// Everything else without a user, the login page above all,
			// keeps the default policy.
			if (!$this->isPublicSharePage()) {
				return;
			}
		} elseif (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			// "Enable app for specific groups" is a promise the policy keeps too.
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

	/**
	 * Whether this request renders a public share link, where the Viewer
	 * -- and with it the player -- can open without anybody logged in.
	 */
	private function isPublicSharePage(): bool {
		$path = $this->request->getPathInfo();
		return is_string($path) && str_starts_with($path, '/s/');
	}
}
