<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Listener;

use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

/**
 * Nostalgist.js compiles RetroArch cores to WebAssembly from blob: URLs,
 * which the default policy blocks. The player also runs inside the Files
 * app (through the Viewer), so the allowances are added globally.
 *
 * @template-implements IEventListener<AddContentSecurityPolicyEvent>
 */
class CSPListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof AddContentSecurityPolicyEvent) {
			return;
		}
		$csp = new ContentSecurityPolicy();
		$csp->allowEvalWasm(true);
		$csp->addAllowedScriptDomain('blob:');
		$csp->addAllowedWorkerSrcDomain('blob:');
		$event->addPolicy($csp);
	}
}
