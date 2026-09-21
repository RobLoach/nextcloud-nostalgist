<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Service;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\CoreMap;
use OCP\IConfig;

/**
 * The games a user played last, wherever they were started from.
 */
class RecentService {
	private const MAX_ENTRIES = 12;

	public function __construct(
		private IConfig $config,
	) {
	}

	/**
	 * @return list<array{path: string, basename: string, system: string, time: int}>
	 */
	public function get(string $userId): array {
		$stored = $this->config->getUserValue($userId, Application::APP_ID, 'recent', '');
		if ($stored === '') {
			return [];
		}
		$recent = json_decode($stored, true);
		return is_array($recent) ? array_values($recent) : [];
	}

	public function record(string $userId, string $path): void {
		$basename = basename($path);
		$recent = $this->get($userId);
		// A game played again moves back to the front instead of repeating.
		$recent = array_values(array_filter(
			$recent,
			static fn (array $entry): bool => ($entry['path'] ?? '') !== $path,
		));
		array_unshift($recent, [
			'path' => $path,
			'basename' => $basename,
			'system' => $this->systemFor($path),
			'time' => time(),
		]);
		$this->config->setUserValue(
			$userId,
			Application::APP_ID,
			'recent',
			json_encode(array_slice($recent, 0, self::MAX_ENTRIES)),
		);
	}

	/**
	 * The system of a game, from its extension, or from the folders it is
	 * stored in when the extension does not tell, as for zipped ROMs.
	 */
	private function systemFor(string $path): string {
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
		$system = CoreMap::extensionSystemMap()[$extension] ?? null;
		if ($system !== null) {
			return $system;
		}
		$folders = array_slice(explode('/', trim($path, '/')), 0, -1);
		foreach (array_reverse($folders) as $folder) {
			$fromFolder = CoreMap::systemForFolderName($folder);
			if ($fromFolder !== null) {
				return $fromFolder;
			}
		}
		return $extension === 'zip' ? 'zip' : '';
	}
}
