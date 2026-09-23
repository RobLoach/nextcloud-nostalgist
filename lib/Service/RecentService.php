<?php

declare(strict_types=1);

namespace OCA\Arcade\Service;

use OCA\Arcade\AppInfo\Application;
use OCA\Arcade\Db\PlayMapper;
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
 * for is ours, and is kept in a table by that same file id, so it survives a
 * rename just as well -- and two sessions ending together both count, which
 * a JSON blob read and written whole could not promise.
 */
class RecentService {
	private const MAX_ENTRIES = 12;
	/** A session longer than this was most likely a forgotten tab. */
	private const MAX_SESSION = 4 * 3600;

	/** The users whose old list of favorites has been looked at already. */
	private array $migrated = [];
	/** The users whose old JSON blobs have been brought into the table. */
	private array $imported = [];

	public function __construct(
		private IUserConfig $userConfig,
		private ITagManager $tagManager,
		private IRootFolder $rootFolder,
		private PlayMapper $playMapper,
	) {
	}

	/**
	 * The games played last, newest first, as ids: what a game is called
	 * and where it lives are the library's to say, and change when it is
	 * renamed or moved.
	 *
	 * @return list<int>
	 */
	public function get(string $userId): array {
		$this->importLegacyPlays($userId);
		return $this->playMapper->recentFileIds($userId, self::MAX_ENTRIES);
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
		$this->importLegacyPlays($userId);
		return $this->playMapper->statsOf($userId);
	}

	public function record(string $userId, string $path): void {
		$id = $this->fileId($userId, $path);
		if ($id === null) {
			return;
		}
		$this->importLegacyPlays($userId);
		$this->playMapper->recordPlay($userId, $id, time());
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
		$this->importLegacyPlays($userId);
		$this->playMapper->addSeconds($userId, $id, min($seconds, self::MAX_SESSION), time());
	}

	/**
	 * Drop everything counted for a user, for when the user is deleted.
	 */
	public function deleteAllForUser(string $userId): void {
		$this->playMapper->deleteAllForUser($userId);
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
	 * The plays used to be two JSON blobs of the user config: the counts
	 * under 'stats', the order under 'recent'. The first touch of a user's
	 * records brings them into the table, and the blobs go.
	 */
	private function importLegacyPlays(string $userId): void {
		if (isset($this->imported[$userId])) {
			return;
		}
		$this->imported[$userId] = true;

		$storedStats = $this->userConfig->getValueString($userId, Application::APP_ID, 'stats', '');
		$storedRecent = $this->userConfig->getValueString($userId, Application::APP_ID, 'recent', '');
		if ($storedStats === '' && $storedRecent === '') {
			return;
		}

		$stats = json_decode($storedStats, true);
		$stats = is_array($stats) ? $stats : [];
		foreach ($stats as $id => $counted) {
			$id = (int)$id;
			if ($id === 0 || !is_array($counted)) {
				continue;
			}
			$this->playMapper->importPlay(
				$userId,
				$id,
				(int)($counted['plays'] ?? 0),
				(int)($counted['seconds'] ?? 0),
				(int)($counted['time'] ?? 0),
			);
		}

		// A game on the recent list was played even if its counts were
		// trimmed away; its place in the order is kept by spacing the
		// moments just below now.
		$recent = json_decode($storedRecent, true);
		$now = time();
		foreach (is_array($recent) ? array_values($recent) : [] as $index => $entry) {
			$id = (int)(is_array($entry) ? ($entry['id'] ?? 0) : 0);
			if ($id === 0 || isset($stats[$id]) || isset($stats[(string)$id])) {
				continue;
			}
			$this->playMapper->importPlay($userId, $id, 1, 0, $now - $index);
		}

		$this->userConfig->deleteUserConfig($userId, Application::APP_ID, 'stats');
		$this->userConfig->deleteUserConfig($userId, Application::APP_ID, 'recent');
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
		$this->importLegacyPlays($userId);
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
			$this->playMapper->importPlay(
				$userId,
				$id,
				(int)($entry['plays'] ?? 0),
				(int)($entry['seconds'] ?? 0),
				(int)($entry['time'] ?? 0),
			);
		}
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
}
