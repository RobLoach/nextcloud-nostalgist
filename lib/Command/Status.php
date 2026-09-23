<?php

declare(strict_types=1);

namespace OCA\Arcade\Command;

use OCA\Arcade\Db\GameMapper;
use OCA\Arcade\Db\PlayMapper;
use OCA\Arcade\Service\StateService;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Says what the app holds, and for whom.
 *
 * The states live in the app data, where no quota counts them and no file
 * listing shows them, so this is the one place an administrator sees what
 * each user's saves add up to. The play records live in a table of their
 * own and are just as invisible; they are reported alongside.
 *
 * @psalm-suppress UnusedClass
 */
class Status extends Command {
	public function __construct(
		private StateService $stateService,
		private IUserManager $userManager,
		private GameMapper $gameMapper,
		private PlayMapper $playMapper,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('arcade:status')
			->setDescription('Report the save state storage and play activity of every user')
			->addOption('json', null, InputOption::VALUE_NONE, 'Print the report as JSON instead');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		// The folders are named after a hash of their user, so the users are
		// hashed and matched against the folders. Only a user who has signed
		// in at least once can have anything here, and that is what "seen"
		// means, so walking the seen users -- answered from the local
		// database -- misses nobody, where callForAllUsers would also ask
		// every backend (an LDAP one at length) about users who cannot have
		// a folder.
		$names = [];
		$this->userManager->callForSeenUsers(function (IUser $user) use (&$names): void {
			$names[$this->stateService->folderKeyOf($user->getUID())] = $user->getUID();
		});

		$games = $this->gameMapper->countsByUser();
		$plays = $this->playMapper->totalsByUser();

		/** @var array<string, array{files: int, bytes: int}> $folders */
		$folders = [];
		$orphans = [];
		foreach ($this->stateService->userFolders() as $key => $folder) {
			[$files, $bytes] = $this->measure($folder);
			$userId = $names[(string)$key] ?? null;
			if ($userId === null) {
				$orphans[] = ['folder' => (string)$key, 'files' => $files, 'bytes' => $bytes];
				continue;
			}
			$folders[$userId] = ['files' => $files, 'bytes' => $bytes];
		}
		// A user can have plays or registered games without a folder, when
		// their saves go to a folder of their own files instead.
		foreach ([...array_keys($games), ...array_keys($plays)] as $userId) {
			$folders[(string)$userId] ??= ['files' => 0, 'bytes' => 0];
		}
		ksort($folders);

		$users = [];
		$totals = ['files' => 0, 'bytes' => 0, 'games' => 0, 'plays' => 0, 'seconds' => 0];
		foreach ($folders as $userId => $folder) {
			$userId = (string)$userId;
			$user = [
				'user' => $userId,
				'files' => $folder['files'],
				'bytes' => $folder['bytes'],
				'games' => $games[$userId] ?? 0,
				'plays' => $plays[$userId]['plays'] ?? 0,
				'seconds' => $plays[$userId]['seconds'] ?? 0,
			];
			$users[] = $user;
			$totals['files'] += $user['files'];
			$totals['bytes'] += $user['bytes'];
			$totals['games'] += $user['games'];
			$totals['plays'] += $user['plays'];
			$totals['seconds'] += $user['seconds'];
		}

		$status = [
			'users' => $users,
			'totals' => $totals,
			'legacyFiles' => $this->stateService->countLegacyFiles(),
			'orphanedFolders' => $orphans,
		];

		if ((bool)$input->getOption('json')) {
			$json = json_encode($status, JSON_PRETTY_PRINT);
			$output->writeln($json === false ? '{}' : $json);
			return 0;
		}
		$this->report($status, $output);
		return 0;
	}

	/**
	 * @param array{
	 *     users: list<array{user: string, files: int, bytes: int, games: int, plays: int, seconds: int}>,
	 *     totals: array{files: int, bytes: int, games: int, plays: int, seconds: int},
	 *     legacyFiles: int,
	 *     orphanedFolders: list<array{folder: string, files: int, bytes: int}>,
	 * } $status
	 */
	private function report(array $status, OutputInterface $output): void {
		if ($status['users'] === []) {
			$output->writeln('Nothing is stored: nobody has saved or played anything yet.');
		} else {
			$output->writeln(sprintf(
				'  %-24s %7s %10s %7s %7s %10s',
				'User', 'Files', 'Size', 'Games', 'Plays', 'Playtime',
			));
			foreach ($status['users'] as $user) {
				$output->writeln(sprintf(
					'  %-24s %7d %10s %7d %7d %10s',
					$user['user'],
					$user['files'],
					$this->sizeOf($user['bytes']),
					$user['games'],
					$user['plays'],
					$this->playtimeOf($user['seconds']),
				));
			}
			$totals = $status['totals'];
			$output->writeln(sprintf(
				'  %-24s %7d %10s %7d %7d %10s',
				'Total',
				$totals['files'],
				$this->sizeOf($totals['bytes']),
				$totals['games'],
				$totals['plays'],
				$this->playtimeOf($totals['seconds']),
			));
		}

		$legacy = $status['legacyFiles'];
		if ($legacy > 0) {
			$output->writeln(
				"<comment>$legacy files written before the states were kept per user hold "
				. 'no record of whose they are, so they are only counted.</comment>',
			);
		}
		foreach ($status['orphanedFolders'] as $orphan) {
			$output->writeln(sprintf(
				'<comment>A folder of a user that no longer exists holds %d files (%s): %s. '
				. 'arcade:cleanup removes it.</comment>',
				$orphan['files'],
				$this->sizeOf($orphan['bytes']),
				$orphan['folder'],
			));
		}
	}

	/**
	 * What a user's folder holds, from one listing: how many files, and
	 * their sizes added up. The sizes come from the file cache; nothing is
	 * read.
	 *
	 * @return array{0: int, 1: int} files, bytes
	 */
	private function measure(ISimpleFolder $folder): array {
		$files = 0;
		$bytes = 0;
		foreach ($folder->getDirectoryListing() as $node) {
			if ($node instanceof ISimpleFile) {
				$files++;
				$bytes += (int)$node->getSize();
			}
		}
		return [$files, $bytes];
	}

	private function sizeOf(int $bytes): string {
		$units = ['KB', 'MB', 'GB', 'TB'];
		if ($bytes < 1024) {
			return $bytes . ' B';
		}
		$value = (float)$bytes;
		$unit = '';
		foreach ($units as $unit) {
			$value /= 1024.0;
			if ($value < 1024.0) {
				break;
			}
		}
		return sprintf('%.1f %s', $value, $unit);
	}

	private function playtimeOf(int $seconds): string {
		if ($seconds < 60) {
			return $seconds . 's';
		}
		$minutes = intdiv($seconds, 60);
		$hours = intdiv($minutes, 60);
		$minutes %= 60;
		return $hours === 0 ? sprintf('%dm', $minutes) : sprintf('%dh %02dm', $hours, $minutes);
	}
}
