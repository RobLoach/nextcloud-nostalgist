<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Tests\Unit;

use OCA\Nostalgist\Listener\CleanupListener;
use OCA\Nostalgist\Service\StateService;
use OCP\EventDispatcher\Event;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CleanupListenerTest extends TestCase {
	private StateService&MockObject $stateService;
	private CleanupListener $listener;

	protected function setUp(): void {
		$this->stateService = $this->createMock(StateService::class);
		$this->listener = new CleanupListener(
			$this->stateService,
			$this->createStub(LoggerInterface::class),
		);
	}

	private function fileEvent(string $path, string $owner = 'alice'): NodeDeletedEvent {
		$user = $this->createStub(IUser::class);
		$user->method('getUID')->willReturn($owner);

		$file = $this->createStub(File::class);
		$file->method('getName')->willReturn(basename($path));
		$file->method('getPath')->willReturn($path);
		$file->method('getOwner')->willReturn($user);

		return new NodeDeletedEvent($file);
	}

	public function testDeletingAGameTakesItsSavesWithIt(): void {
		$this->stateService->expects($this->once())
			->method('deleteAllForGame')
			->with('alice', '/Games/NES/Mario.nes');

		$this->listener->handle($this->fileEvent('/alice/files/Games/NES/Mario.nes'));
	}

	public function testAZippedGameCountsToo(): void {
		$this->stateService->expects($this->once())
			->method('deleteAllForGame')
			->with('alice', '/Games/NHL 96.zip');

		$this->listener->handle($this->fileEvent('/alice/files/Games/NHL 96.zip'));
	}

	public function testAnythingThatIsNotAGameIsLeftAlone(): void {
		$this->stateService->expects($this->never())->method('deleteAllForGame');

		$this->listener->handle($this->fileEvent('/alice/files/Documents/Notes.txt'));
		$this->listener->handle($this->fileEvent('/alice/files/Games/Cover.png'));
	}

	public function testAFolderSaysNothingAboutWhatWasInIt(): void {
		$user = $this->createStub(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$folder = $this->createStub(Folder::class);
		$folder->method('getName')->willReturn('NES');
		$folder->method('getPath')->willReturn('/alice/files/Games/NES');
		$folder->method('getOwner')->willReturn($user);

		$this->stateService->expects($this->never())->method('deleteAllForGame');
		$this->listener->handle(new NodeDeletedEvent($folder));
	}

	public function testAFileOutsideTheFilesOfItsOwnerIsLeftAlone(): void {
		$this->stateService->expects($this->never())->method('deleteAllForGame');
		$this->listener->handle($this->fileEvent('/somewhere/else/Mario.nes'));
	}

	public function testDeletingAUserTakesEverythingOfTheirs(): void {
		$user = $this->createStub(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$this->stateService->expects($this->once())
			->method('deleteAllForUser')
			->with('alice');

		$this->listener->handle(new UserDeletedEvent($user));
	}

	public function testAnythingElseIsNotItsBusiness(): void {
		$this->stateService->expects($this->never())->method('deleteAllForGame');
		$this->stateService->expects($this->never())->method('deleteAllForUser');

		$this->listener->handle(new Event());
	}

	public function testCleaningUpNeverGetsInTheWayOfTheDeletion(): void {
		$this->stateService->method('deleteAllForGame')
			->willThrowException(new \RuntimeException('the storage said no'));

		// No exception leaves the listener, or Nextcloud would fail the
		// deletion over something that is only housekeeping.
		$this->listener->handle($this->fileEvent('/alice/files/Games/Mario.nes'));
		$this->addToAssertionCount(1);
	}
}
