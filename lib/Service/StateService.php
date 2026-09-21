<?php

declare(strict_types=1);

namespace OCA\Arcade\Service;

use OCA\Arcade\AppInfo\Application;
use OCA\Arcade\CoreMap;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

/**
 * Stores emulator save states, keyed by user and ROM path, so every
 * Nextcloud user has their own save states per game.
 *
 * States live in the app data folder by default, in a folder per user so
 * that everything of a user can be dropped at once. When the user configures
 * a saves folder, they are stored there instead, as regular files like
 * "Saves/Mario/Slot 1.state" with "Slot 1.png" next to them — per user by
 * nature, since the folder is in the user's own files.
 */
class StateService {
	/** The slots a game can be saved into. */
	public const SLOTS = 3;
	/** The slot written when a game is closed, kept apart from the numbered ones. */
	public const AUTO_SLOT = 0;
	/** Lists the games a user has states for, next to the states. */
	private const GAMES_FILE = 'games.json';
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
			$this->remember($userId, $romPath);
			return;
		}
		$key = $this->key($userId, $romPath);
		$this->writeAppData($userId, $this->fileName($key, $slot, 'state'), $data, $romPath, $key);
	}

	public function saveThumbnail(string $userId, string $romPath, int $slot, string $data): void {
		$folder = $this->getGameFolder($userId, $romPath, true);
		if ($folder !== null) {
			$this->writeNode($folder, $this->slotName($slot) . '.png', $data);
			return;
		}
		$this->writeAppData($userId, $this->fileName($this->key($userId, $romPath), $slot, 'png'), $data);
	}

	public function load(string $userId, string $romPath, int $slot): ?string {
		$folder = $this->getGameFolder($userId, $romPath, false);
		if ($folder !== null) {
			return $this->readNode($folder, $this->slotName($slot) . '.state');
		}
		return $this->readAppData(
			$userId,
			$this->fileName($this->key($userId, $romPath), $slot, 'state'),
			$this->fileName($this->pathKey($romPath), $slot, 'state'),
			$this->legacyFileName($userId, $romPath, $slot, 'state'),
		);
	}

	public function loadThumbnail(string $userId, string $romPath, int $slot): ?string {
		$folder = $this->getGameFolder($userId, $romPath, false);
		if ($folder !== null) {
			return $this->readNode($folder, $this->slotName($slot) . '.png');
		}
		return $this->readAppData(
			$userId,
			$this->fileName($this->key($userId, $romPath), $slot, 'png'),
			$this->fileName($this->pathKey($romPath), $slot, 'png'),
			$this->legacyFileName($userId, $romPath, $slot, 'png'),
		);
	}

	/**
	 * SRAM is the in-game battery save (e.g. an RPG's own save file),
	 * stored once per user and game, next to the save states.
	 */
	public function saveSram(string $userId, string $romPath, string $data): void {
		$folder = $this->getGameFolder($userId, $romPath, true);
		if ($folder !== null) {
			$this->writeNode($folder, $this->sramNodeName($romPath), $data);
			$this->remember($userId, $romPath);
			return;
		}
		$key = $this->key($userId, $romPath);
		$this->writeAppData($userId, $this->sramFileName($key), $data, $romPath, $key);
	}

	public function loadSram(string $userId, string $romPath): ?string {
		$folder = $this->getGameFolder($userId, $romPath, false);
		if ($folder !== null) {
			// The battery save is named after the game. A game that was
			// renamed has one under its old name, and there is only ever
			// one in the folder.
			return $this->readNode($folder, $this->sramNodeName($romPath))
				?? $this->readAnySram($folder);
		}
		if ($this->savesFolderPath($userId) !== '') {
			return null;
		}
		return $this->readAppData(
			$userId,
			$this->sramFileName($this->key($userId, $romPath)),
			$this->sramFileName($this->pathKey($romPath)),
			$this->legacyKey($userId, $romPath) . '.srm',
		);
	}

	public function delete(string $userId, string $romPath, int $slot): bool {
		$folder = $this->getGameFolder($userId, $romPath, false);
		if ($folder !== null) {
			$this->deleteNode($folder, $this->slotName($slot) . '.png');
			return $this->deleteNode($folder, $this->slotName($slot) . '.state');
		}
		$key = $this->key($userId, $romPath);
		$this->deleteAppData(
			$userId,
			$this->fileName($key, $slot, 'png'),
			$this->fileName($this->pathKey($romPath), $slot, 'png'),
			$this->legacyFileName($userId, $romPath, $slot, 'png'),
		);
		return $this->deleteAppData(
			$userId,
			$this->fileName($key, $slot, 'state'),
			$this->fileName($this->pathKey($romPath), $slot, 'state'),
			$this->legacyFileName($userId, $romPath, $slot, 'state'),
		);
	}

	/**
	 * Drop everything kept for one game of one user, wherever it lives, for
	 * when the game itself is deleted.
	 */
	public function deleteAllForGame(string $userId, string $romPath): void {
		$key = $this->key($userId, $romPath);
		$pathKey = $this->pathKey($romPath);
		foreach ($this->slots() as $slot) {
			$this->deleteAppData(
				$userId,
				$this->fileName($key, $slot, 'state'),
				$this->fileName($pathKey, $slot, 'state'),
				$this->legacyFileName($userId, $romPath, $slot, 'state'),
			);
			$this->deleteAppData(
				$userId,
				$this->fileName($key, $slot, 'png'),
				$this->fileName($pathKey, $slot, 'png'),
				$this->legacyFileName($userId, $romPath, $slot, 'png'),
			);
		}
		$this->deleteAppData(
			$userId,
			$this->sramFileName($key),
			$this->sramFileName($pathKey),
			$this->legacyKey($userId, $romPath) . '.srm',
		);
		$this->forgetGame($userId, $romPath);
		// And the folder of the game in the user's own saves folder.
		$this->getGameFolder($userId, $romPath, false)?->delete();
	}

	/**
	 * Drop everything kept for a user, for when the user is deleted. Their
	 * own files, and so any saves folder, are removed by Nextcloud itself.
	 */
	public function deleteAllForUser(string $userId): void {
		try {
			$this->statesRoot()->getFolder($this->userKey($userId))->delete();
		} catch (NotFoundException) {
			// Nothing was ever stored for this user.
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

		$userFolder = $this->userStates($userId, false);
		$legacy = $this->statesRoot();
		$keys = [$this->key($userId, $romPath), $this->pathKey($romPath)];
		foreach ($this->slots() as $slot) {
			$key = null;
			foreach ($keys as $candidate) {
				if ($userFolder !== null && $userFolder->fileExists($this->fileName($candidate, $slot, 'state'))) {
					$key = $candidate;
					break;
				}
			}
			$legacyName = $this->legacyFileName($userId, $romPath, $slot, 'state');
			if ($userFolder !== null && $key !== null) {
				$file = $userFolder->getFile($this->fileName($key, $slot, 'state'));
				$hasThumbnail = $userFolder->fileExists($this->fileName($key, $slot, 'png'));
			} elseif ($legacy->fileExists($legacyName)) {
				$file = $legacy->getFile($legacyName);
				$hasThumbnail = $legacy->fileExists($this->legacyFileName($userId, $romPath, $slot, 'png'));
			} else {
				continue;
			}
			$states[] = [
				'slot' => $slot,
				'size' => $file->getSize(),
				'mtime' => $file->getMTime(),
				'hasThumbnail' => $hasThumbnail,
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
		$keys = [];
		foreach ($romPaths as $path) {
			$keys[$path] = [$this->key($userId, $path), $this->pathKey($path)];
		}
		// Two listings, so looking a game up afterwards costs nothing.
		$mtimes = [];
		$userFolder = $this->userStates($userId, false);
		foreach ($userFolder?->getDirectoryListing() ?? [] as $file) {
			$mtimes[$file->getName()] = $file->getMTime();
		}
		$legacyMtimes = [];
		foreach ($this->statesRoot()->getDirectoryListing() as $file) {
			$legacyMtimes[$file->getName()] = $file->getMTime();
		}

		$found = [];
		foreach ($romPaths as $path) {
			foreach ($this->slots() as $slot) {
				$mtime = $mtimes[$this->fileName($keys[$path][0], $slot, 'png')]
					?? $mtimes[$this->fileName($keys[$path][1], $slot, 'png')]
					?? $legacyMtimes[$this->legacyFileName($userId, $path, $slot, 'png')]
					?? null;
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

	/**
	 * Where the saves of a game live: under the system it belongs to, so
	 * that two games of the same name do not share a folder.
	 */
	private function gameFolderPath(string $savesPath, string $romPath): string {
		$stem = pathinfo(basename($romPath), PATHINFO_FILENAME);
		$system = CoreMap::shortNameForPath($romPath);
		$folder = trim($savesPath, '/');
		return $system === '' ? "$folder/$stem" : "$folder/$system/$stem";
	}

	/** Where they lived before the system was part of the path. */
	private function legacyGameFolderPath(string $savesPath, string $romPath): string {
		return trim($savesPath, '/') . '/' . pathinfo(basename($romPath), PATHINFO_FILENAME);
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
		$path = $this->gameFolderPath($savesPath, $romPath);

		foreach ([$path, $this->legacyGameFolderPath($savesPath, $romPath)] as $candidate) {
			try {
				$node = $userFolder->get($candidate);
				if ($node instanceof Folder) {
					return $node;
				}
			} catch (NotFoundException) {
				// Looked for where it would be now, then where it used to be.
			}
		}
		$followed = $this->followGame($userId, $userFolder, $savesPath, $romPath, $path);
		if ($followed !== null) {
			return $followed;
		}
		return $create ? $this->makeFolder($userFolder, $path) : null;
	}

	/**
	 * A game that was renamed or moved does not lose its saves: the app data
	 * remembers the path each file id last had, so the folder is found under
	 * the old name and brought along to the new one.
	 */
	private function followGame(
		string $userId,
		Folder $userFolder,
		string $savesPath,
		string $romPath,
		string $path,
	): ?Folder {
		$id = $this->fileId($userId, $romPath);
		$was = $id === null ? null : ($this->gamesOf($userId)[(string)$id] ?? null);
		if ($was === null || $was === $romPath) {
			return null;
		}
		foreach ([$this->gameFolderPath($savesPath, $was), $this->legacyGameFolderPath($savesPath, $was)] as $old) {
			try {
				$node = $userFolder->get($old);
			} catch (NotFoundException) {
				continue;
			}
			if (!$node instanceof Folder) {
				continue;
			}
			try {
				if (!$userFolder->nodeExists($path) && $this->makeFolder($userFolder, dirname($path)) !== null) {
					$node->move($userFolder->getPath() . '/' . $path);
				}
			} catch (\Throwable) {
				// Keeping the folder where it is beats losing the saves.
			}
			return $node;
		}
		return null;
	}

	/** Creates the folders of a path one level at a time. */
	private function makeFolder(Folder $userFolder, string $path): ?Folder {
		$folder = $userFolder;
		foreach (explode('/', trim($path, '/')) as $segment) {
			if ($segment === '' || $segment === '.') {
				continue;
			}
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

	/** Notes where a game is now, so it can be followed if it moves. */
	private function remember(string $userId, string $romPath): void {
		$folder = $this->userStates($userId, true);
		if ($folder !== null) {
			$this->rememberGame($folder, $romPath, $this->key($userId, $romPath));
		}
	}

	private function readAnySram(Folder $folder): ?string {
		foreach ($folder->getDirectoryListing() as $node) {
			if ($node instanceof File && str_ends_with(strtolower($node->getName()), '.srm')) {
				return $node->getContent();
			}
		}
		return null;
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

	private function writeAppData(
		string $userId,
		string $name,
		string $data,
		string $romPath = '',
		string $key = '',
	): void {
		$folder = $this->userStates($userId, true);
		if ($folder === null) {
			return;
		}
		try {
			$folder->getFile($name)->putContent($data);
		} catch (NotFoundException) {
			$folder->newFile($name, $data);
		}
		if ($romPath !== '') {
			$this->rememberGame($folder, $romPath, $key === '' ? $this->key($userId, $romPath) : $key);
		}
	}

	/**
	 * The file names hide which game they belong to, so the games a user has
	 * states for are listed alongside them. That is what tells apart a state
	 * whose game is gone from one whose game is merely not being played.
	 */
	private function rememberGame(ISimpleFolder $folder, string $romPath, string $key): void {
		$games = $this->readGames($folder);
		if (($games[$key] ?? null) === $romPath) {
			return;
		}
		$games[$key] = $romPath;
		$this->writeGames($folder, $games);
	}

	private function forgetGame(string $userId, string $romPath): void {
		$folder = $this->userStates($userId, false);
		if ($folder === null) {
			return;
		}
		$games = $this->readGames($folder);
		unset($games[$this->key($userId, $romPath)], $games[$this->pathKey($romPath)]);
		$this->writeGames($folder, $games);
	}

	/**
	 * The games a user has states for, by their path.
	 *
	 * @return array<string, string>
	 */
	public function gamesOf(string $userId): array {
		$folder = $this->userStates($userId, false);
		return $folder === null ? [] : $this->readGames($folder);
	}

	/**
	 * @return array<string, string>
	 */
	private function readGames(ISimpleFolder $folder): array {
		try {
			if (!$folder->fileExists(self::GAMES_FILE)) {
				return [];
			}
			$games = json_decode($folder->getFile(self::GAMES_FILE)->getContent(), true);
		} catch (NotFoundException) {
			return [];
		}
		return is_array($games) ? $games : [];
	}

	/**
	 * @param array<string, string> $games
	 */
	private function writeGames(ISimpleFolder $folder, array $games): void {
		$content = json_encode($games);
		try {
			$folder->getFile(self::GAMES_FILE)->putContent($content);
		} catch (NotFoundException) {
			$folder->newFile(self::GAMES_FILE, $content);
		}
	}

	/**
	 * The folders of the app data holding states, by the key of their user.
	 *
	 * @return array<string, ISimpleFolder>
	 */
	public function userFolders(): array {
		$folders = [];
		foreach ($this->statesRoot()->getDirectoryListing() as $node) {
			if ($node instanceof ISimpleFolder) {
				$folders[$node->getName()] = $node;
			}
		}
		return $folders;
	}

	public function folderKeyOf(string $userId): string {
		return $this->userKey($userId);
	}

	/**
	 * How many files written before the states were kept per user are left.
	 * They cannot be told apart, so they are only ever counted.
	 */
	public function countLegacyFiles(): int {
		$count = 0;
		foreach ($this->statesRoot()->getDirectoryListing() as $node) {
			if (!$node instanceof ISimpleFolder) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Reads from the folder of the user, and failing that from the flat
	 * names used before the states were kept per user.
	 */
	/**
	 * The name it has now, then the names it had in older versions: first
	 * under the hash of its path, then, before states were kept per user,
	 * flat in the states folder.
	 */
	private function readAppData(string $userId, string $name, string ...$older): ?string {
		try {
			$folder = $this->userStates($userId, false);
			foreach ([$name, ...$older] as $candidate) {
				if ($folder !== null && $folder->fileExists($candidate)) {
					return $folder->getFile($candidate)->getContent();
				}
			}
			$legacy = $this->statesRoot();
			$flat = $older === [] ? $name : array_pop($older);
			if ($legacy->fileExists($flat)) {
				return $legacy->getFile($flat)->getContent();
			}
		} catch (NotFoundException) {
			return null;
		}
		return null;
	}

	private function deleteAppData(string $userId, string $name, string ...$older): bool {
		$deleted = false;
		try {
			$folder = $this->userStates($userId, false);
			foreach ([$name, ...$older] as $candidate) {
				if ($folder !== null && $folder->fileExists($candidate)) {
					$folder->getFile($candidate)->delete();
					$deleted = true;
				}
			}
			$legacy = $this->statesRoot();
			$flat = $older === [] ? $name : array_pop($older);
			if ($legacy->fileExists($flat)) {
				$legacy->getFile($flat)->delete();
				$deleted = true;
			}
		} catch (NotFoundException) {
			return $deleted;
		}
		return $deleted;
	}

	private function statesRoot(): ISimpleFolder {
		$appData = $this->appDataFactory->get(Application::APP_ID);
		try {
			return $appData->getFolder('states');
		} catch (NotFoundException) {
			return $appData->newFolder('states');
		}
	}

	private function userStates(string $userId, bool $create): ?ISimpleFolder {
		$root = $this->statesRoot();
		try {
			return $root->getFolder($this->userKey($userId));
		} catch (NotFoundException) {
			return $create ? $root->newFolder($this->userKey($userId)) : null;
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

	/** How a slot is named in the user's saves folder. */
	private function slotName(int $slot): string {
		return $slot === self::AUTO_SLOT ? 'Auto' : "Slot $slot";
	}

	private function sramNodeName(string $romPath): string {
		return pathinfo(basename($romPath), PATHINFO_FILENAME) . '.srm';
	}

	private function fileName(string $key, int $slot, string $extension): string {
		return $key . '-' . $slot . '.' . $extension;
	}

	private function sramFileName(string $key): string {
		return $key . '.srm';
	}

	/**
	 * What a game is filed under in the app data: the id Nextcloud gave the
	 * file, so renaming or moving a ROM does not lose its saves. Games that
	 * are no longer there keep the name they had, which is how what they
	 * left behind is still found and removed.
	 */
	private function key(string $userId, string $romPath): string {
		$id = $this->fileId($userId, $romPath);
		return $id === null ? $this->pathKey($romPath) : (string)$id;
	}

	/** What a game was filed under before the file id was used. */
	private function pathKey(string $romPath): string {
		return hash('sha256', $romPath);
	}

	private function fileId(string $userId, string $romPath): ?int {
		try {
			return $this->rootFolder->getUserFolder($userId)->get($romPath)->getId();
		} catch (\Throwable) {
			return null;
		}
	}

	private function legacyFileName(string $userId, string $romPath, int $slot, string $extension): string {
		return $this->legacyKey($userId, $romPath) . '-' . $slot . '.' . $extension;
	}

	/** The names used before the states were kept in a folder per user. */
	private function legacyKey(string $userId, string $romPath): string {
		return hash('sha256', $userId . '|' . $romPath);
	}

	private function userKey(string $userId): string {
		return hash('sha256', $userId);
	}
}
