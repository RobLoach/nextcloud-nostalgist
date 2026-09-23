<?php

declare(strict_types=1);

namespace OCA\Arcade\Tests\Unit;

use OCA\Arcade\Command\Status;
use OCA\Arcade\Db\GameMapper;
use OCA\Arcade\Db\PlayMapper;
use OCA\Arcade\Service\StateService;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * What `occ arcade:status` reports: the storage nothing else shows, put to
 * the users it belongs to.
 */
class StatusTest extends TestCase {
	/** The users the instance has seen, by uid. */
	private array $seen = ['alice', 'bob'];
	/** The app data folders of states, by name, as name => file sizes. */
	private array $folders = [];
	/** @var array<string, int> games with saves, by user */
	private array $games = [];
	/** @var array<string, array{plays: int, seconds: int}> */
	private array $plays = [];
	private int $legacy = 0;
	/** How often each folder was listed, by name. */
	private array $listed = [];

	private function tester(): CommandTester {
		$stateService = $this->createStub(StateService::class);
		$stateService->method('folderKeyOf')->willReturnCallback(
			static fn (string $userId): string => hash('sha256', $userId),
		);
		$stateService->method('userFolders')->willReturnCallback(
			function (): array {
				$folders = [];
				foreach (array_keys($this->folders) as $name) {
					$folders[$name] = $this->folder($name);
				}
				return $folders;
			},
		);
		$stateService->method('countLegacyFiles')->willReturnCallback(fn (): int => $this->legacy);

		$userManager = $this->createStub(IUserManager::class);
		$userManager->method('callForSeenUsers')->willReturnCallback(
			function (\Closure $callback): void {
				foreach ($this->seen as $uid) {
					$user = $this->createStub(IUser::class);
					$user->method('getUID')->willReturn($uid);
					$callback($user);
				}
			},
		);

		$gameMapper = $this->createStub(GameMapper::class);
		$gameMapper->method('countsByUser')->willReturnCallback(fn (): array => $this->games);

		$playMapper = $this->createStub(PlayMapper::class);
		$playMapper->method('totalsByUser')->willReturnCallback(fn (): array => $this->plays);

		return new CommandTester(new Status($stateService, $userManager, $gameMapper, $playMapper));
	}

	private function folder(string $name): ISimpleFolder {
		$folder = $this->createStub(ISimpleFolder::class);
		$folder->method('getName')->willReturn($name);
		$folder->method('getDirectoryListing')->willReturnCallback(
			function () use ($name): array {
				$this->listed[$name] = ($this->listed[$name] ?? 0) + 1;
				$files = [];
				foreach ($this->folders[$name] as $i => $size) {
					$file = $this->createStub(ISimpleFile::class);
					$file->method('getName')->willReturn("file-$i");
					$file->method('getSize')->willReturn($size);
					$files[] = $file;
				}
				return $files;
			},
		);
		return $folder;
	}

	private function aliceAndBobAndAStranger(): void {
		$this->folders[hash('sha256', 'alice')] = [100, 200, 44];
		$this->folders[hash('sha256', 'bob')] = [2048];
		// A folder whose hash matches nobody the instance has seen.
		$this->folders[hash('sha256', 'who was this')] = [512, 512];
		$this->games = ['alice' => 2, 'bob' => 1];
		$this->plays = [
			'alice' => ['plays' => 14, 'seconds' => 7530],
			'bob' => ['plays' => 2, 'seconds' => 59],
		];
	}

	public function testEveryFolderIsPutToItsUser(): void {
		$this->aliceAndBobAndAStranger();
		$tester = $this->tester();
		$this->assertSame(0, $tester->execute([]));
		$display = $tester->getDisplay();

		$this->assertMatchesRegularExpression('/alice\s+3\s+344 B\s+2\s+14\s+2h 05m/', $display);
		$this->assertMatchesRegularExpression('/bob\s+1\s+2\.0 KB\s+1\s+2\s+59s/', $display);
		$this->assertMatchesRegularExpression(
			'/Total\s+4\s+2\.3 KB\s+3\s+16\s+2h 06m/',
			$display,
			'the totals count the users; the orphan is reported apart',
		);
	}

	public function testAFolderOfNobodyIsReportedNotTouched(): void {
		$this->aliceAndBobAndAStranger();
		$tester = $this->tester();
		$tester->execute([]);
		$display = $tester->getDisplay();

		$this->assertStringContainsString('no longer exists', $display);
		$this->assertStringContainsString(hash('sha256', 'who was this'), $display);
		$this->assertStringContainsString('2 files (1.0 KB)', $display);
		$this->assertStringContainsString('arcade:cleanup', $display, 'removal is pointed at, not done');
		$this->assertStringNotContainsString(
			hash('sha256', 'who was this'),
			preg_replace('/^.*no longer exists.*$/m', '', $display) ?? '',
			'the stranger is no row of the table',
		);
	}

	public function testEachFolderIsListedOnce(): void {
		$this->aliceAndBobAndAStranger();
		$this->tester()->execute([]);
		$this->assertSame([1, 1, 1], array_values($this->listed), 'one listing answers for a folder');
	}

	public function testPlaysWithoutAFolderStillShow(): void {
		// Saves kept in the user's own files leave no app data folder, and
		// play records need no saves at all.
		$this->plays = ['carol' => ['plays' => 3, 'seconds' => 90]];
		$tester = $this->tester();
		$tester->execute([]);
		$this->assertMatchesRegularExpression('/carol\s+0\s+0 B\s+0\s+3\s+1m/', $tester->getDisplay());
	}

	public function testLegacyFilesAreCounted(): void {
		$this->legacy = 4;
		$tester = $this->tester();
		$tester->execute([]);
		$display = $tester->getDisplay();
		$this->assertStringContainsString('4 files written before', $display);
		$this->assertStringContainsString('Nothing is stored', $display, 'an empty table says so instead');
	}

	public function testJsonSaysTheSameThingsByMachine(): void {
		$this->aliceAndBobAndAStranger();
		$this->legacy = 1;
		$tester = $this->tester();
		$this->assertSame(0, $tester->execute(['--json' => true]));

		$status = json_decode($tester->getDisplay(), true);
		$this->assertIsArray($status);
		$this->assertSame(
			['users', 'totals', 'legacyFiles', 'orphanedFolders'],
			array_keys($status),
		);
		$this->assertSame(
			['user' => 'alice', 'files' => 3, 'bytes' => 344, 'games' => 2, 'plays' => 14, 'seconds' => 7530],
			$status['users'][0],
		);
		$this->assertSame(
			['user' => 'bob', 'files' => 1, 'bytes' => 2048, 'games' => 1, 'plays' => 2, 'seconds' => 59],
			$status['users'][1],
		);
		$this->assertSame(
			['files' => 4, 'bytes' => 2392, 'games' => 3, 'plays' => 16, 'seconds' => 7589],
			$status['totals'],
		);
		$this->assertSame(1, $status['legacyFiles']);
		$this->assertSame(
			[['folder' => hash('sha256', 'who was this'), 'files' => 2, 'bytes' => 1024]],
			$status['orphanedFolders'],
		);
	}
}
