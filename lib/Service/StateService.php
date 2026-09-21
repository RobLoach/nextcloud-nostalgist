<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Service;

use OCA\Nostalgist\AppInfo\Application;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

/**
 * Stores emulator save states, keyed by user and ROM path, so every
 * Nextcloud user has their own save states per game. Each game has a fixed
 * number of slots, with a screenshot thumbnail per slot.
 *
 * States live in the app data folder by default. When the user configures
 * a saves folder, they are stored there instead, as regular files like
 * "Saves/Mario/Slot 1.state" with "Slot 1.png" next to them — per user by
 * nature, since the folder is in the user's own files.
 */
class StateService {
	/** The slots a game can be saved into. */
	public const SLOTS = 3;
	/** The slot written when a game is closed, kept apart from the numbered ones. */
	public const AUTO_SLOT = 0;
	/**
	 * Earlier versions offered more slots. They are still listed, so what
	 * they hold can be loaded and removed, but nothing is written to them.
	 */
	public const HIGHEST_SLOT = 6;

	public function __construct(
		private IAppDataFactory $appDataFactory,
		private IRootFolder $rootFolder,
		private SettingsService $settingsService,
	) {
	}

	public function save(string $userId, string $romPath, int $slot, string $data): void {
		$folder = $this->getGameFolder($userId, $romPath, true);
		if ($folder !== null) {
			$this->writeNode($folder, $this->slotName($slot) . '.state', $data);
			return;
		}
		$this->writeAppData($this->stateName($userId, $romPath, $slot), $data);
	}

	public function saveThumbnail(string $userId, string $romPath, int $slot, string $data): void {
		$folder = $this->getGameFolder($userId, $romPath, true);
		if ($folder !== null) {
			$this->writeNode($folder, $this->slotName($slot) . '.png', $data);
			return;
		}
		$this->writeAppData($this->thumbnailName($userId, $romPath, $slot), $data);
	}

	public function load(string $userId, string $romPath, int $slot): ?string {
		$folder = $this->getGameFolder($userId, $romPath, false);
		if ($folder !== null) {
			return $this->readNode($folder, $this->slotName($slot) . '.state');
		}
		return $this->readAppData($this->stateName($userId, $romPath, $slot));
	}

	public function loadThumbnail(string $userId, string $romPath, int $slot): ?string {
		$folder = $this->getGameFolder($userId, $romPath, false);
		if ($folder !== null) {
			return $this->readNode($folder, $this->slotName($slot) . '.png');
		}
		return $this->readAppData($this->thumbnailName($userId, $romPath, $slot));
	}

	/**
	 * SRAM is the in-game battery save (e.g. an RPG's own save file),
	 * stored once per user and game, next to the save states.
	 */
	public function saveSram(string $userId, string $romPath, string $data): void {
		$folder = $this->getGameFolder($userId, $romPath, true);
		if ($folder !== null) {
			$this->writeNode($folder, $this->sramNodeName($romPath), $data);
			return;
		}
		$this->writeAppData($this->key($userId, $romPath) . '.srm', $data);
	}

	public function loadSram(string $userId, string $romPath): ?string {
		$folder = $this->getGameFolder($userId, $romPath, false);
		if ($folder !== null) {
			return $this->readNode($folder, $this->sramNodeName($romPath));
		}
		if ($this->savesFolderPath($userId) !== '') {
			return null;
		}
		return $this->readAppData($this->key($userId, $romPath) . '.srm');
	}

	private function sramNodeName(string $romPath): string {
		return pathinfo(basename($romPath), PATHINFO_FILENAME) . '.srm';
	}

	public function delete(string $userId, string $romPath, int $slot): bool {
		$folder = $this->getGameFolder($userId, $romPath, false);
		if ($folder !== null) {
			$this->deleteNode($folder, $this->slotName($slot) . '.png');
			return $this->deleteNode($folder, $this->slotName($slot) . '.state');
		}
		$appDataFolder = $this->getStatesFolder();
		try {
			$appDataFolder->getFile($this->thumbnailName($userId, $romPath, $slot))->delete();
		} catch (NotFoundException) {
			// No thumbnail to delete.
		}
		try {
			$appDataFolder->getFile($this->stateName($userId, $romPath, $slot))->delete();
			return true;
		} catch (NotFoundException) {
			return false;
		}
	}

	/**
	 * @return list<array{slot: int, size: int, mtime: int, hasThumbnail: bool}>
	 */
	public function list(string $userId, string $romPath): array {
		$states = [];
		$folder = $this->getGameFolder($userId, $romPath, false);
		if ($folder !== null || $this->savesFolderPath($userId) !== '') {
			foreach ($this->slots() as $slot) {
				$name = $this->slotName($slot) . '.state';
				if ($folder === null || !$folder->nodeExists($name)) {
					continue;
				}
				$file = $folder->get($name);
				$states[] = [
					'slot' => $slot,
					'size' => (int)$file->getSize(),
					'mtime' => $file->getMTime(),
					'hasThumbnail' => $folder->nodeExists($this->slotName($slot) . '.png'),
				];
			}
			return $states;
		}

		$appDataFolder = $this->getStatesFolder();
		foreach ($this->slots() as $slot) {
			try {
				$file = $appDataFolder->getFile($this->stateName($userId, $romPath, $slot));
			} catch (NotFoundException) {
				continue;
			}
			$states[] = [
				'slot' => $slot,
				'size' => $file->getSize(),
				'mtime' => $file->getMTime(),
				'hasThumbnail' => $appDataFolder->fileExists($this->thumbnailName($userId, $romPath, $slot)),
			];
		}
		return $states;
	}

	/**
	 * The most recent save state screenshot of each of the given games, to
	 * stand in for a missing thumbnail.
	 *
	 * @param list<string> $romPaths
	 * @return array<string, array{slot: int, mtime: int}> rom path => slot
	 */
	public function thumbnailIndex(string $userId, array $romPaths): array {
		if ($romPaths === []) {
			return [];
		}
		return $this->savesFolderPath($userId) === ''
			? $this->appDataThumbnailIndex($userId, $romPaths)
			: $this->savesFolderThumbnailIndex($userId, $romPaths);
	}

	/**
	 * @param list<string> $romPaths
	 * @return array<string, array{slot: int, mtime: int}>
	 */
	private function appDataThumbnailIndex(string $userId, array $romPaths): array {
		// One listing, so looking a game up afterwards costs nothing.
		$mtimes = [];
		foreach ($this->getStatesFolder()->getDirectoryListing() as $file) {
			$mtimes[$file->getName()] = $file->getMTime();
		}
		$found = [];
		foreach ($romPaths as $path) {
			$key = $this->key($userId, $path);
			foreach ($this->slots() as $slot) {
				$mtime = $mtimes["$key-$slot.png"] ?? null;
				if ($mtime !== null && ($found[$path]['mtime'] ?? -1) < $mtime) {
					$found[$path] = ['slot' => $slot, 'mtime' => $mtime];
				}
			}
		}
		return $found;
	}

	/**
	 * @param list<string> $romPaths
	 * @return array<string, array{slot: int, mtime: int}>
	 */
	private function savesFolderThumbnailIndex(string $userId, array $romPaths): array {
		$savesPath = $this->savesFolderPath($userId);
		try {
			$saves = $this->rootFolder->getUserFolder($userId)->get($savesPath);
		} catch (NotFoundException) {
			return [];
		}
		if (!$saves instanceof Folder) {
			return [];
		}
		// The saves folder holds one folder per game, so listing it once is
		// enough to know which games have anything at all.
		$gameFolders = [];
		foreach ($saves->getDirectoryListing() as $node) {
			if ($node instanceof Folder) {
				$gameFolders[mb_strtolower($node->getName())] = $node;
			}
		}

		$found = [];
		foreach ($romPaths as $path) {
			$stem = mb_strtolower(pathinfo(basename($path), PATHINFO_FILENAME));
			$folder = $gameFolders[$stem] ?? null;
			if ($folder === null) {
				continue;
			}
			foreach ($folder->getDirectoryListing() as $node) {
				$name = $node->getName();
				if ($name === $this->slotName(self::AUTO_SLOT) . '.png') {
					$slot = self::AUTO_SLOT;
				} elseif (preg_match('/^Slot (\d+)\.png$/', $name, $matches) === 1) {
					$slot = (int)$matches[1];
				} else {
					continue;
				}
				$mtime = $node->getMTime();
				if (($found[$path]['mtime'] ?? -1) < $mtime) {
					$found[$path] = ['slot' => $slot, 'mtime' => $mtime];
				}
			}
		}
		return $found;
	}

	private function savesFolderPath(string $userId): string {
		return $this->settingsService->getUserSettings($userId)['saves_folder'];
	}

	/**
	 * The folder holding a game's states in the user's files, or null when
	 * the saves folder is not configured (or, unless $create is set, when
	 * the folders do not exist yet).
	 */
	private function getGameFolder(string $userId, string $romPath, bool $create): ?Folder {
		$savesPath = $this->savesFolderPath($userId);
		if ($savesPath === '') {
			return null;
		}
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$stem = pathinfo(basename($romPath), PATHINFO_FILENAME);
		$path = trim($savesPath, '/') . '/' . $stem;

		try {
			$node = $userFolder->get($path);
			return $node instanceof Folder ? $node : null;
		} catch (NotFoundException) {
			if (!$create) {
				return null;
			}
		}

		// Create the folders one level at a time.
		$folder = $userFolder;
		foreach (explode('/', $path) as $segment) {
			try {
				$node = $folder->get($segment);
				if (!$node instanceof Folder) {
					return null;
				}
				$folder = $node;
			} catch (NotFoundException) {
				$folder = $folder->newFolder($segment);
			}
		}
		return $folder;
	}

	private function writeNode(Folder $folder, string $name, string $data): void {
		try {
			$node = $folder->get($name);
			if ($node instanceof File) {
				$node->putContent($data);
			}
		} catch (NotFoundException) {
			$folder->newFile($name, $data);
		}
	}

	private function readNode(Folder $folder, string $name): ?string {
		try {
			$node = $folder->get($name);
			return $node instanceof File ? $node->getContent() : null;
		} catch (NotFoundException) {
			return null;
		}
	}

	private function deleteNode(Folder $folder, string $name): bool {
		try {
			$folder->get($name)->delete();
			return true;
		} catch (NotFoundException) {
			return false;
		}
	}

	private function writeAppData(string $name, string $data): void {
		$folder = $this->getStatesFolder();
		try {
			$folder->getFile($name)->putContent($data);
		} catch (NotFoundException) {
			$folder->newFile($name, $data);
		}
	}

	private function readAppData(string $name): ?string {
		try {
			return $this->getStatesFolder()->getFile($name)->getContent();
		} catch (NotFoundException) {
			return null;
		}
	}

	private function getStatesFolder(): ISimpleFolder {
		$appData = $this->appDataFactory->get(Application::APP_ID);
		try {
			return $appData->getFolder('states');
		} catch (NotFoundException) {
			return $appData->newFolder('states');
		}
	}

	/**
	 * The slots a game can have, the automatic one first.
	 *
	 * @return list<int>
	 */
	private function slots(): array {
		return [self::AUTO_SLOT, ...range(1, self::HIGHEST_SLOT)];
	}

	/**
	 * How a slot is named in the user's saves folder.
	 */
	private function slotName(int $slot): string {
		return $slot === self::AUTO_SLOT ? 'Auto' : "Slot $slot";
	}

	private function stateName(string $userId, string $romPath, int $slot): string {
		return $this->key($userId, $romPath) . '-' . $slot . '.state';
	}

	private function thumbnailName(string $userId, string $romPath, int $slot): string {
		return $this->key($userId, $romPath) . '-' . $slot . '.png';
	}

	private function key(string $userId, string $romPath): string {
		return hash('sha256', $userId . '|' . $romPath);
	}
}
