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
	public const SLOTS = 6;

	public function __construct(
		private IAppDataFactory $appDataFactory,
		private IRootFolder $rootFolder,
		private SettingsService $settingsService,
	) {
	}

	public function save(string $userId, string $romPath, int $slot, string $data): void {
		$folder = $this->getGameFolder($userId, $romPath, true);
		if ($folder !== null) {
			$this->writeNode($folder, "Slot $slot.state", $data);
			return;
		}
		$this->writeAppData($this->stateName($userId, $romPath, $slot), $data);
	}

	public function saveThumbnail(string $userId, string $romPath, int $slot, string $data): void {
		$folder = $this->getGameFolder($userId, $romPath, true);
		if ($folder !== null) {
			$this->writeNode($folder, "Slot $slot.png", $data);
			return;
		}
		$this->writeAppData($this->thumbnailName($userId, $romPath, $slot), $data);
	}

	public function load(string $userId, string $romPath, int $slot): ?string {
		$folder = $this->getGameFolder($userId, $romPath, false);
		if ($folder !== null) {
			return $this->readNode($folder, "Slot $slot.state");
		}
		return $this->readAppData($this->stateName($userId, $romPath, $slot));
	}

	public function loadThumbnail(string $userId, string $romPath, int $slot): ?string {
		$folder = $this->getGameFolder($userId, $romPath, false);
		if ($folder !== null) {
			return $this->readNode($folder, "Slot $slot.png");
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
			$this->deleteNode($folder, "Slot $slot.png");
			return $this->deleteNode($folder, "Slot $slot.state");
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
			for ($slot = 1; $slot <= self::SLOTS; $slot++) {
				if ($folder === null || !$folder->nodeExists("Slot $slot.state")) {
					continue;
				}
				$file = $folder->get("Slot $slot.state");
				$states[] = [
					'slot' => $slot,
					'size' => (int)$file->getSize(),
					'mtime' => $file->getMTime(),
					'hasThumbnail' => $folder->nodeExists("Slot $slot.png"),
				];
			}
			return $states;
		}

		$appDataFolder = $this->getStatesFolder();
		for ($slot = 1; $slot <= self::SLOTS; $slot++) {
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
