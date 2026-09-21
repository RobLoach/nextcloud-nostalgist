<?php

declare(strict_types=1);

namespace OCA\Arcade\Service;

use OCA\Arcade\AppInfo\Application;
use OCA\Arcade\CoreMap;
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

	public function __construct(
		private IUserConfig $userConfig,
		private ITagManager $tagManager,
		private IRootFolder $rootFolder,
	) {
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function get(string $userId): array {
		$stats = $this->stats($userId);
		$recent = [];
		foreach ($this->read($userId, 'recent') as $entry) {
			$recent[] = [...$entry, ...($stats[$entry['id'] ?? 0] ?? [])];
		}
		return $recent;
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

		// A game played again moves back to the front instead of repeating.
		$recent = $this->without($this->read($userId, 'recent'), $path);
		array_unshift($recent, [
			'id' => $id,
			'path' => $path,
			'basename' => basename($path),
			'system' => $this->systemFor($path),
			...$counted,
		]);
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
	 * The system of a game, or "zip" for an archive that does not say which
	 * it holds.
	 */
	public function systemFor(string $path): string {
		$system = CoreMap::systemForPath($path);
		if ($system !== null) {
			return $system;
		}
		return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip' ? 'zip' : '';
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
	 * @param list<array<string, mixed>> $entries
	 * @return list<array<string, mixed>>
	 */
	private function without(array $entries, string $path): array {
		return array_values(array_filter(
			$entries,
			static fn (array $entry): bool => ($entry['path'] ?? '') !== $path,
		));
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
