<?php

declare(strict_types=1);

namespace OCA\Arcade\Listener;

use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

/**
 * Nostalgist.js compiles the RetroArch cores to WebAssembly and runs them
 * from blob: URLs, which the default policy blocks. The player also runs
 * inside the Files app (through the Viewer), so the allowances are added
 * globally.
 *
 * Only the needed directives are added, through an empty policy: adding a
 * full ContentSecurityPolicy here would merge all of its defaults into the
 * policy of every page of the instance.
 *
 * @template-implements IEventListener<AddContentSecurityPolicyEvent>
 */
class CSPListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof AddContentSecurityPolicyEvent) {
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
