<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Service;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\CoreMap;
use OCP\IConfig;

/**
 * What a user played, when, and for how long, wherever it was started from.
 */
class RecentService {
	private const MAX_ENTRIES = 12;
	/** A session longer than this was most likely a forgotten tab. */
	private const MAX_SESSION = 4 * 3600;

	public function __construct(
		private IConfig $config,
	) {
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function get(string $userId): array {
		return $this->read($userId, 'recent');
	}

	/**
	 * The games marked as favorites, most recently played first.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function getFavorites(string $userId): array {
		return $this->read($userId, 'favorites');
	}

	public function record(string $userId, string $path): void {
		$recent = $this->get($userId);
		$existing = $this->find($recent, $path);
		// A game played again moves back to the front instead of repeating.
		$recent = $this->without($recent, $path);
		array_unshift($recent, [
			'path' => $path,
			'basename' => basename($path),
			'system' => $this->systemFor($path),
			'time' => time(),
			'seconds' => $existing['seconds'] ?? 0,
			'plays' => ($existing['plays'] ?? 0) + 1,
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
		$seconds = min($seconds, self::MAX_SESSION);
		foreach (['recent', 'favorites'] as $list) {
			$entries = $this->read($userId, $list);
			$changed = false;
			foreach ($entries as &$entry) {
				if (($entry['path'] ?? '') === $path) {
					$entry['seconds'] = ($entry['seconds'] ?? 0) + $seconds;
					$changed = true;
				}
			}
			unset($entry);
			if ($changed) {
				$this->write($userId, $list, $entries);
			}
		}
	}

	/**
	 * @return bool whether the game is a favorite afterwards
	 */
	public function toggleFavorite(string $userId, string $path): bool {
		$favorites = $this->getFavorites($userId);
		if ($this->find($favorites, $path) !== null) {
			$this->write($userId, 'favorites', $this->without($favorites, $path));
			return false;
		}
		$known = $this->find($this->get($userId), $path);
		array_unshift($favorites, $known ?? [
			'path' => $path,
			'basename' => basename($path),
			'system' => $this->systemFor($path),
			'time' => time(),
			'seconds' => 0,
			'plays' => 0,
		]);
		$this->write($userId, 'favorites', $favorites);
		return true;
	}

	/**
	 * @param list<array<string, mixed>> $entries
	 * @return array<string, mixed>|null
	 */
	private function find(array $entries, string $path): ?array {
		foreach ($entries as $entry) {
			if (($entry['path'] ?? '') === $path) {
				return $entry;
			}
		}
		return null;
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
		$stored = $this->config->getUserValue($userId, Application::APP_ID, $key, '');
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
		$this->config->setUserValue($userId, Application::APP_ID, $key, json_encode($entries));
	}

	/**
	 * The system of a game, or "zip" for an archive that does not say which
	 * it holds.
	 */
	private function systemFor(string $path): string {
		$system = CoreMap::systemForPath($path);
		if ($system !== null) {
			return $system;
		}
		return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip' ? 'zip' : '';
	}
}
