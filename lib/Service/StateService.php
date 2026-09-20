<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Service;

use OCA\Nostalgist\AppInfo\Application;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

/**
 * Stores emulator save states in the app data folder, keyed by user and
 * ROM path, so every Nextcloud user has their own save state per game.
 */
class StateService {
	public function __construct(
		private IAppDataFactory $appDataFactory,
	) {
	}

	public function save(string $userId, string $romPath, string $data): void {
		$folder = $this->getStatesFolder();
		$name = $this->fileName($userId, $romPath);
		try {
			$folder->getFile($name)->putContent($data);
		} catch (NotFoundException) {
			$folder->newFile($name, $data);
		}
	}

	public function load(string $userId, string $romPath): ?string {
		try {
			return $this->getStatesFolder()
				->getFile($this->fileName($userId, $romPath))
				->getContent();
		} catch (NotFoundException) {
			return null;
		}
	}

	public function delete(string $userId, string $romPath): bool {
		try {
			$this->getStatesFolder()
				->getFile($this->fileName($userId, $romPath))
				->delete();
			return true;
		} catch (NotFoundException) {
			return false;
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

	private function fileName(string $userId, string $romPath): string {
		return hash('sha256', $userId . '|' . $romPath) . '.state';
	}
}
