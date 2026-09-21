<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Service;

use OCA\Nostalgist\CoreMap;
use OCP\Files\Folder;
use OCP\Files\Node;

/**
 * Finds the images that go with the games of a library.
 *
 * Both a flat folder of images named after the games and the
 * libretro-thumbnails layout are supported:
 *
 *   Thumbs/Mario.png
 *   Thumbs/NES/Mario.png
 *   Thumbs/Nintendo - Nintendo Entertainment System/Named_Boxarts/Mario.png
 *   Thumbs/Nintendo - Nintendo Entertainment System/Named_Titles/Mario.png
 *
 * The whole folder is indexed in one pass, so matching a game is a lookup
 * instead of a query per candidate file.
 */
class ThumbnailService {
	private const MAX_DEPTH = 3;
	private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

	/** libretro-thumbnails folder name => thumbnail type */
	private const TYPE_FOLDERS = [
		'named_boxarts' => 'boxart',
		'named_titles' => 'title',
		'named_snaps' => 'snap',
		'named_logos' => 'logo',
	];

	/**
	 * Index every image of a thumbnails folder, by folder path and type.
	 *
	 * @return array{paths: array<string, array<string, array<string, int>>>, systems: array<string, string>}
	 */
	public function buildIndex(Folder $folder): array {
		$index = ['paths' => [], 'systems' => []];
		$this->indexFolder($folder, '', $index, 0);
		return $index;
	}

	/**
	 * The images of a game, by type: boxart, title, snap, logo or plain.
	 *
	 * @param array{paths: array<string, array<string, array<string, int>>>, systems: array<string, string>} $index
	 * @return array<string, int> type => file id
	 */
	public function forGame(array $index, string $systemId, string $subfolder, string $basename): array {
		$stem = $this->stemKey($basename);

		// The platform folder of the system first, then the folder the game
		// itself is in, then the root of the thumbnails folder.
		$candidates = [];
		if (isset($index['systems'][$systemId])) {
			$candidates[] = $index['systems'][$systemId];
		}
		$path = $this->normalize(trim($subfolder, '/'));
		while ($path !== '') {
			$candidates[] = $path;
			$path = substr($path, 0, (int)strrpos($path, '/'));
		}
		$candidates[] = '';

		$thumbnails = [];
		foreach ($candidates as $candidate) {
			foreach ($index['paths'][$candidate] ?? [] as $type => $images) {
				if (!isset($thumbnails[$type]) && isset($images[$stem])) {
					$thumbnails[$type] = $images[$stem];
				}
			}
		}
		return $thumbnails;
	}

	/**
	 * @param array{paths: array<string, array<string, array<string, int>>>, systems: array<string, string>} $index
	 */
	private function indexFolder(Folder $folder, string $path, array &$index, int $depth): void {
		if ($depth > self::MAX_DEPTH) {
			return;
		}
		foreach ($folder->getDirectoryListing() as $node) {
			$name = $node->getName();
			if (!$node instanceof Folder) {
				if ($this->isImage($name)) {
					$index['paths'][$path]['plain'][$this->stemKey($name)] = $node->getId();
				}
				continue;
			}

			$type = self::TYPE_FOLDERS[mb_strtolower($name)] ?? null;
			if ($type !== null) {
				$index['paths'][$path][$type] = $this->indexImages($node);
				continue;
			}

			$childPath = $path === '' ? $this->normalize($name) : $path . '/' . $this->normalize($name);
			// "Nintendo - Super Nintendo Entertainment System" and "SNES"
			// both point at the same system.
			$system = CoreMap::systemForFolderName($name);
			if ($system !== null && !isset($index['systems'][$system])) {
				$index['systems'][$system] = $childPath;
			}
			$this->indexFolder($node, $childPath, $index, $depth + 1);
		}
	}

	/**
	 * @return array<string, int> file name stem => file id
	 */
	private function indexImages(Folder $folder): array {
		$images = [];
		foreach ($folder->getDirectoryListing() as $node) {
			if (!$node instanceof Folder && $this->isImage($node->getName())) {
				$images[$this->stemKey($node->getName())] = $node->getId();
			}
		}
		return $images;
	}

	private function isImage(string $name): bool {
		return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
	}

	/**
	 * The key a file name is matched by: without its extension, lowercase,
	 * and with the characters libretro-thumbnails replaces with an
	 * underscore already replaced, so "Mario Bros: 3.nes" matches
	 * "Mario Bros_ 3.png".
	 */
	private function stemKey(string $name): string {
		$stem = pathinfo($name, PATHINFO_FILENAME);
		return $this->normalize(preg_replace('/[&*\/:`<>?\\\\|]/', '_', $stem) ?? $stem);
	}

	private function normalize(string $value): string {
		return mb_strtolower(trim($value));
	}
}
