<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\Listener\CSPListener;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The CSP allowances the emulator needs are added for anyone who can use
 * the app, and for no one else.
 */
class CSPListenerTest extends TestCase {
	private IUserSession&MockObject $userSession;
	private IAppManager&MockObject $appManager;
	private IRequest&MockObject $request;
	private CSPListener $listener;

	protected function setUp(): void {
		$this->userSession = $this->createMock(IUserSession::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->request = $this->createMock(IRequest::class);
		$this->request->method('getPathInfo')->willReturn('/apps/files');
		$this->listener = new CSPListener($this->userSession, $this->appManager, $this->request);
	}

	/** The event's constructor wants OC internals, so it is mocked whole. */
	private function event(): AddContentSecurityPolicyEvent&MockObject {
		return $this->createMock(AddContentSecurityPolicyEvent::class);
	}

	private function user(): IUser {
		$user = $this->createStub(IUser::class);
		$user->method('getUID')->willReturn('alice');
		return $user;
	}

	public function testAUserWithTheAppGetsThePolicy(): void {
		$user = $this->user();
		$this->userSession->method('getUser')->willReturn($user);
		$this->appManager->method('isEnabledForUser')
			->with('arcade', $user)
			->willReturn(true);

		$event = $this->event();
		$event->expects($this->once())
			->method('addPolicy')
			->with($this->isInstanceOf(EmptyContentSecurityPolicy::class));

		$this->listener->handle($event);
	}

	public function testNobodyLoggedInGetsNoPolicy(): void {
		// The login page and every other page without a user keep the
		// default policy.
		$this->userSession->method('getUser')->willReturn(null);
		$this->appManager->expects($this->never())->method('isEnabledForUser');

		$event = $this->event();
		$event->expects($this->never())->method('addPolicy');

		$this->listener->handle($event);
	}

	public function testASharePagePlaysForAnonymousVisitors(): void {
		// A game shared by link opens in the Viewer without a login, so the
		// share page keeps the allowances the emulator needs.
		$this->userSession->method('getUser')->willReturn(null);
		$request = $this->createMock(IRequest::class);
		$request->method('getPathInfo')->willReturn('/s/AbCdEfGh');
		$listener = new CSPListener($this->userSession, $this->appManager, $request);

		$event = $this->event();
		$event->expects($this->once())
			->method('addPolicy')
			->with($this->isInstanceOf(EmptyContentSecurityPolicy::class));

		$listener->handle($event);
	}

	public function testAUserWithoutTheAppGetsNoPolicy(): void {
		// "Enable app for specific groups" leaves everyone else untouched.
		$user = $this->user();
		$this->userSession->method('getUser')->willReturn($user);
		$this->appManager->method('isEnabledForUser')
			->with('arcade', $user)
			->willReturn(false);

		$event = $this->event();
		$event->expects($this->never())->method('addPolicy');

		$this->listener->handle($event);
	}

	public function testAnythingElseIsNotItsBusiness(): void {
		$this->userSession->expects($this->never())->method('getUser');

		$this->listener->handle(new Event());
		$this->addToAssertionCount(1);
	}
}
