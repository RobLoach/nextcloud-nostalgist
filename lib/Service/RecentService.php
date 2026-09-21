<?php

declare(strict_types=1);

namespace OCA\Arcade\Service;

use OCA\Arcade\AppInfo\Application;
use OCP\Config\IUserConfig;
use OCP\Files\IRootFolder;
use OCP\ITagManager;
use OCP\ITags;

/**
 * What a user played, when, and for how long, wherever it was started from.
 *
 * A favorite is the star of the Files app, kept where Files keeps it: by the
 * id of the file, so a game that is renamed or moved stays a favorite, and a
 * game starred in one place is starred in the other. What a game was played
 * for is ours, and is kept by that same file id, so it survives a rename
 * just as well.
 */
class RecentService {
	private const MAX_ENTRIES = 12;
	/** A session longer than this was most likely a forgotten tab. */
	private const MAX_SESSION = 4 * 3600;
	/** How many games keep a record of being played. */
	private const MAX_STATS = 200;

	/** The users whose old list of favorites has been looked at already. */
	private array $migrated = [];

	public function __construct(
		private IUserConfig $userConfig,
		private ITagManager $tagManager,
		private IRootFolder $rootFolder,
	) {
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	/**
	 * The games played last, newest first, as ids: what a game is called
	 * and where it lives are the library's to say, and change when it is
	 * renamed or moved.
	 *
	 * @return list<int>
	 */
	public function get(string $userId): array {
		$ids = [];
		foreach ($this->read($userId, 'recent') as $entry) {
			$id = (int)($entry['id'] ?? 0);
			if ($id !== 0) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * The ids of the files the user has starred, as keys.
	 *
	 * One query for all of them, so a listing can be marked without asking
	 * about each game in turn.
	 *
	 * @return array<int, true>
	 */
	public function favoriteIds(string $userId): array {
		$this->migrateLegacyFavorites($userId);
		$favorites = $this->tags($userId)?->getFavorites();
		if (!is_array($favorites)) {
			return [];
		}
		$ids = [];
		foreach ($favorites as $id) {
			$ids[(int)$id] = true;
		}
		return $ids;
	}

	/**
	 * What each game was played for, by the id of its file.
	 *
	 * @return array<int, array<string, int>>
	 */
	public function stats(string $userId): array {
		$stored = $this->userConfig->getValueString($userId, Application::APP_ID, 'stats', '');
		$stats = $stored === '' ? [] : json_decode($stored, true);
		return is_array($stats) ? $stats : [];
	}

	public function record(string $userId, string $path): void {
		$id = $this->fileId($userId, $path);
		$stats = $this->stats($userId);
		$counted = [
			'seconds' => (int)($stats[$id]['seconds'] ?? 0),
			'plays' => (int)($stats[$id]['plays'] ?? 0) + 1,
			'time' => time(),
		];
		if ($id !== null) {
			$stats[$id] = $counted;
			$this->writeStats($userId, $stats);
		}

		if ($id === null) {
			return;
		}
		// A game played again moves back to the front instead of repeating.
		$recent = array_values(array_filter(
			$this->read($userId, 'recent'),
			static fn (array $entry): bool => (int)($entry['id'] ?? 0) !== $id,
		));
		array_unshift($recent, ['id' => $id]);
		$this->write($userId, 'recent', array_slice($recent, 0, self::MAX_ENTRIES));
	}

	/**
	 * Add the length of a session to what a game was played for.
	 */
	public function addPlayTime(string $userId, string $path, int $seconds): void {
		if ($seconds <= 0) {
			return;
		}
		$id = $this->fileId($userId, $path);
		if ($id === null) {
			return;
		}
		$stats = $this->stats($userId);
		// A game whose start was never recorded still counts.
		$stats[$id] = [
			'seconds' => (int)($stats[$id]['seconds'] ?? 0) + min($seconds, self::MAX_SESSION),
			'plays' => (int)($stats[$id]['plays'] ?? 0),
			'time' => (int)($stats[$id]['time'] ?? time()),
		];
		$this->writeStats($userId, $stats);
	}

	/**
	 * @return bool whether the game is a favorite afterwards
	 */
	public function toggleFavorite(string $userId, string $path): bool {
		$id = $this->fileId($userId, $path);
		$tags = $this->tags($userId);
		if ($id === null || $tags === null) {
			return false;
		}
		if (isset($this->favoriteIds($userId)[$id])) {
			$tags->removeFromFavorites($id);
			return false;
		}
		$tags->addToFavorites($id);
		return true;
	}

	/**
	 * The tags of the Files app, which is where a favorite lives. There are
	 * none to load without a user, on a public share for instance.
	 */
	private function tags(string $userId): ?ITags {
		return $this->tagManager->load('files', [], false, $userId);
	}

	private function fileId(string $userId, string $path): ?int {
		try {
			return $this->rootFolder->getUserFolder($userId)->get($path)->getId();
		} catch (\Throwable) {
			return null;
		}
	}

	/**
	 * Favorites used to be a list of ours, kept by path. They are the stars
	 * of the Files app now, so the ones that were set are handed over, and
	 * what those games were played for is kept.
	 */
	private function migrateLegacyFavorites(string $userId): void {
		if (isset($this->migrated[$userId])) {
			return;
		}
		$this->migrated[$userId] = true;
		$legacy = $this->read($userId, 'favorites');
		if ($legacy === []) {
			return;
		}
		$tags = $this->tags($userId);
		if ($tags === null) {
			return;
		}
		$stats = $this->stats($userId);
		foreach ($legacy as $entry) {
			$path = (string)($entry['path'] ?? '');
			if ($path === '') {
				continue;
			}
			$id = $this->fileId($userId, $path);
			if ($id === null) {
				continue;
			}
			$tags->addToFavorites($id);
			$stats[$id] ??= [
				'seconds' => (int)($entry['seconds'] ?? 0),
				'plays' => (int)($entry['plays'] ?? 0),
				'time' => (int)($entry['time'] ?? 0),
			];
		}
		$this->writeStats($userId, $stats);
		$this->userConfig->deleteUserConfig($userId, Application::APP_ID, 'favorites');
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function read(string $userId, string $key): array {
		$stored = $this->userConfig->getValueString($userId, Application::APP_ID, $key, '');
		if ($stored === '') {
			return [];
		}
		$entries = json_decode($stored, true);
		return is_array($entries) ? array_values($entries) : [];
	}

	/**
	 * @param list<array<string, mixed>> $entries
	 */
	private function write(string $userId, string $key, array $entries): void {
		$this->userConfig->setValueString($userId, Application::APP_ID, $key, json_encode($entries));
	}

	/**
	 * @param array<int, array<string, int>> $stats
	 */
	private function writeStats(string $userId, array $stats): void {
		if (count($stats) > self::MAX_STATS) {
			// The games played longest ago make way first.
			uasort($stats, static fn (array $a, array $b): int => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));
			$stats = array_slice($stats, 0, self::MAX_STATS, true);
		}
		$this->userConfig->setValueString($userId, Application::APP_ID, 'stats', json_encode($stats));
	}
}
