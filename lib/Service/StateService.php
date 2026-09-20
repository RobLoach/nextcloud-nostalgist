<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Service;

use OCA\Nostalgist\AppInfo\Application;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

/**
 * Stores emulator save states in the app data folder, keyed by user and
 * ROM path, so every Nextcloud user has their own save states per game.
 * Each game has a fixed number of slots, with an optional screenshot
 * thumbnail per slot.
 */
class StateService {
	public const SLOTS = 6;

	public function __construct(
		private IAppDataFactory $appDataFactory,
	) {
	}

	public function save(string $userId, string $romPath, int $slot, string $data): void {
		$this->write($this->stateName($userId, $romPath, $slot), $data);
	}

	public function saveThumbnail(string $userId, string $romPath, int $slot, string $data): void {
		$this->write($this->thumbnailName($userId, $romPath, $slot), $data);
	}

	public function load(string $userId, string $romPath, int $slot): ?string {
		return $this->read($this->stateName($userId, $romPath, $slot));
	}

	public function loadThumbnail(string $userId, string $romPath, int $slot): ?string {
		return $this->read($this->thumbnailName($userId, $romPath, $slot));
	}

	public function delete(string $userId, string $romPath, int $slot): bool {
		$folder = $this->getStatesFolder();
		try {
			$folder->getFile($this->thumbnailName($userId, $romPath, $slot))->delete();
		} catch (NotFoundException) {
			// No thumbnail to delete.
		}
		try {
			$folder->getFile($this->stateName($userId, $romPath, $slot))->delete();
			return true;
		} catch (NotFoundException) {
			return false;
		}
	}

	/**
	 * @return list<array{slot: int, size: int, mtime: int, hasThumbnail: bool}>
	 */
	public function list(string $userId, string $romPath): array {
		$folder = $this->getStatesFolder();
		$states = [];
		for ($slot = 1; $slot <= self::SLOTS; $slot++) {
			try {
				$file = $folder->getFile($this->stateName($userId, $romPath, $slot));
			} catch (NotFoundException) {
				continue;
			}
			$states[] = [
				'slot' => $slot,
				'size' => $file->getSize(),
				'mtime' => $file->getMTime(),
				'hasThumbnail' => $folder->fileExists($this->thumbnailName($userId, $romPath, $slot)),
			];
		}
		return $states;
	}

	private function write(string $name, string $data): void {
		$folder = $this->getStatesFolder();
		try {
			$folder->getFile($name)->putContent($data);
		} catch (NotFoundException) {
			$folder->newFile($name, $data);
		}
	}

	private function read(string $name): ?string {
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
