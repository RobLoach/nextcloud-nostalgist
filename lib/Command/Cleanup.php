<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Command;

use OCA\Nostalgist\Service\StateService;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Removes save states whose game or user is gone.
 *
 * The listeners take care of that as it happens, but a folder full of games
 * deleted in one go, or anything removed while the app was disabled, leaves
 * states behind that nothing points at any more.
 *
 * @psalm-suppress UnusedClass
 */
class Cleanup extends Command {
	public function __construct(
		private StateService $stateService,
		private IUserManager $userManager,
		private IRootFolder $rootFolder,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('nostalgist:cleanup')
			->setDescription('Remove save states of games and users that no longer exist')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report what would be removed');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$dryRun = (bool)$input->getOption('dry-run');
		if ($dryRun) {
			$output->writeln('<comment>Dry run, nothing is removed.</comment>');
		}

		// The folders are named after the user they belong to, so the users
		// there are tell which folders are still spoken for.
		$keys = [];
		$this->userManager->callForAllUsers(function ($user) use (&$keys): void {
			$keys[$this->stateService->folderKeyOf($user->getUID())] = $user->getUID();
		});

		$users = 0;
		$games = 0;
		foreach ($this->stateService->userFolders() as $key => $folder) {
			$userId = $keys[$key] ?? null;
			if ($userId === null) {
				$output->writeln("Removing the states of a user that no longer exists ($key)");
				if (!$dryRun) {
					$folder->delete();
				}
				$users++;
				continue;
			}
			$games += $this->cleanUser($userId, $dryRun, $output);
		}

		$output->writeln(
			"Removed the states of <info>$users</info> users and <info>$games</info> games",
		);
		$legacy = $this->stateService->countLegacyFiles();
		if ($legacy > 0) {
			$output->writeln(
				"<comment>$legacy files written before the states were kept per user are left. "
				. 'They hold no record of whose they are, and are removed with their game as it is played.</comment>',
			);
		}
		return 0;
	}

	private function cleanUser(string $userId, bool $dryRun, OutputInterface $output): int {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
		} catch (NotFoundException|\Throwable) {
			return 0;
		}
		$removed = 0;
		foreach ($this->stateService->gamesOf($userId) as $path) {
			if ($userFolder->nodeExists($path)) {
				continue;
			}
			$output->writeln("Removing the states of $userId for $path");
			if (!$dryRun) {
				$this->stateService->deleteAllForGame($userId, $path);
			}
			$removed++;
		}
		return $removed;
	}
}
