<?php

declare(strict_types=1);

namespace OCA\Arcade\Service;

use OCA\Arcade\AppInfo\Application;
use OCA\Arcade\CoreMap;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

/**
 * The BIOS files of the instance, for the systems that ask for one.
 *
 * A few consoles will not start without the file their own firmware lived
 * in, and that file is the one thing a player cannot make for themselves.
 * Keeping a copy per user would mean every user finding their own, so an
 * administrator can put one where everybody's player can reach it.
 *
 * Only the names the cores actually ask for are kept, so this cannot become
 * a place to put files in general.
 */
class BiosService {
	private const FOLDER = 'bios';

	public function __construct(
		private IAppDataFactory $appDataFactory,
	) {
	}

	/**
	 * Every file name any supported system may ask for.
	 *
	 * @return list<string>
	 */
	public static function names(): array {
		$names = [];
		foreach (CoreMap::SYSTEMS as $system) {
			foreach ($system['bios'] as $name) {
				$names[$name] = true;
			}
		}
		return array_keys($names);
	}

	public static function isKnown(string $name): bool {
		return in_array($name, self::names(), true);
	}

	/**
	 * The spelling a core asks for, matched without regard to case, or null
	 * when no core asks for a file of that name at all.
	 */
	public static function canonicalName(string $name): ?string {
		foreach (self::names() as $known) {
			if (strcasecmp($known, $name) === 0) {
				return $known;
			}
		}
		return null;
	}

	/**
	 * The file, or null when the instance has not been given it.
	 */
	public function read(string $name): ?string {
		if (!self::isKnown($name)) {
			return null;
		}
		try {
			$folder = $this->folder(false);
			return $folder !== null && $folder->fileExists($name)
				? $folder->getFile($name)->getContent()
				: null;
		} catch (NotFoundException) {
			return null;
		}
	}

	/**
	 * @return bool whether the name is one a core asks for
	 */
	public function write(string $name, string $data): bool {
		if (!self::isKnown($name)) {
			return false;
		}
		$folder = $this->folder(true);
		if ($folder === null) {
			return false;
		}
		try {
			$folder->getFile($name)->putContent($data);
		} catch (NotFoundException) {
			$folder->newFile($name, $data);
		}
		return true;
	}

	public function remove(string $name): bool {
		try {
			$folder = $this->folder(false);
			if ($folder === null || !$folder->fileExists($name)) {
				return false;
			}
			$folder->getFile($name)->delete();
			return true;
		} catch (NotFoundException) {
			return false;
		}
	}

	/**
	 * The names the instance has a file for.
	 *
	 * @return list<string>
	 */
	public function held(): array {
		$held = [];
		try {
			$folder = $this->folder(false);
			foreach ($folder?->getDirectoryListing() ?? [] as $file) {
				if (self::isKnown($file->getName())) {
					$held[] = $file->getName();
				}
			}
		} catch (NotFoundException) {
			return [];
		}
		return $held;
	}

	/**
	 * Everything in the store with its size, whether a core asks for it or
	 * not, so an administrator can see strays as well as what is wanted.
	 *
	 * @return array<string, int> name => size in bytes
	 */
	public function stored(): array {
		$stored = [];
		try {
			$folder = $this->folder(false);
			foreach ($folder?->getDirectoryListing() ?? [] as $file) {
				$stored[$file->getName()] = (int)$file->getSize();
			}
		} catch (NotFoundException) {
			return [];
		}
		return $stored;
	}

	private function folder(bool $create): ?ISimpleFolder {
		$appData = $this->appDataFactory->get(Application::APP_ID);
		try {
			return $appData->getFolder(self::FOLDER);
		} catch (NotFoundException) {
			return $create ? $appData->newFolder(self::FOLDER) : null;
		}
	}
}
