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
	 * An exact file name match wins. Failing that, a loose match ignores
	 * region and revision tags, articles and punctuation, so that
	 * "Batman Returns.zip" finds "Batman Returns (USA).png".
	 *
	 * @return array<string, int> type => file id
	 */
	public function forGame(array $index, string $systemId, string $subfolder, string $basename): array {
		$exact = $this->stemKey($basename);
		$loose = $this->looseKey($basename);

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
				if (isset($thumbnails[$type])) {
					continue;
				}
				if (isset($images['exact'][$exact])) {
					$thumbnails[$type] = $images['exact'][$exact];
				} elseif ($loose !== '' && isset($images['loose'][$loose])) {
					$thumbnails[$type] = $images['loose'][$loose]['id'];
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
					$this->addImage($index['paths'][$path]['plain'], $name, $node->getId());
				}
				continue;
			}

			$type = self::TYPE_FOLDERS[mb_strtolower($name)] ?? null;
			if ($type !== null) {
				$index['paths'][$path][$type] = $this->indexImages($node);
				continue;
			}
			unset($type);

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
	 * @return array{exact: array<string, int>, loose: array<string, array{id: int, rank: int, length: int}>}
	 */
	private function indexImages(Folder $folder): array {
		$images = ['exact' => [], 'loose' => []];
		foreach ($folder->getDirectoryListing() as $node) {
			if (!$node instanceof Folder && $this->isImage($node->getName())) {
				$this->addImage($images, $node->getName(), $node->getId());
			}
		}
		return $images;
	}

	/**
	 * @param array{exact: array<string, int>, loose: array<string, array{id: int, rank: int, length: int}>} $images
	 */
	private function addImage(?array &$images, string $name, int $id): void {
		$images ??= ['exact' => [], 'loose' => []];
		$images['exact'][$this->stemKey($name)] = $id;

		$loose = $this->looseKey($name);
		if ($loose === '') {
			return;
		}
		// Several files can share a loose key, such as the USA and the
		// Europe release. Keep the most widely useful one, so the choice
		// does not depend on the order the folder is read in.
		$candidate = [
			'id' => $id,
			'rank' => $this->regionRank($name),
			'length' => mb_strlen($name),
		];
		$current = $images['loose'][$loose] ?? null;
		if ($current === null
			|| $candidate['rank'] < $current['rank']
			|| ($candidate['rank'] === $current['rank'] && $candidate['length'] < $current['length'])) {
			$images['loose'][$loose] = $candidate;
		}
	}

	/**
	 * How preferable the release a file name refers to is, lower is better.
	 */
	private function regionRank(string $name): int {
		$lower = mb_strtolower($name);
		foreach (['(world)' => 0, '(usa' => 1, '(u)' => 1, '(europe' => 2, '(e)' => 2, '(japan' => 3, '(j)' => 3] as $needle => $rank) {
			if (str_contains($lower, $needle)) {
				return $rank;
			}
		}
		return 4;
	}

	/**
	 * A forgiving match key: no extension, no "(USA)" or "[!]" tags, no
	 * leading or trailing article, no punctuation, so "The Legend of
	 * Zelda.nes" and "Legend of Zelda, The (USA) (Rev 1).png" both become
	 * "legend of zelda".
	 *
	 * Titles join their parts with "+", "&" or "and" interchangeably, and
	 * libretro-thumbnails turns "&" into an underscore, so all of them are
	 * dropped: "Super Mario All-Stars and Super Mario World (Europe).zip"
	 * matches "Super Mario All-Stars + Super Mario World.png".
	 */
	private function looseKey(string $name): string {
		$value = $this->transliterate(mb_strtolower(pathinfo($name, PATHINFO_FILENAME)));
		$value = preg_replace('/[(\[][^)\]]*[)\]]/u', ' ', $value) ?? $value;
		$value = preg_replace('/,\s*(the|a|an)\s*$/u', '', trim($value)) ?? $value;
		$value = preg_replace('/^(the|a|an)\s+/u', '', $value) ?? $value;
		$value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
		$words = array_filter(
			explode(' ', $value),
			static fn (string $word): bool => $word !== '' && $word !== 'and',
		);
		return implode(' ', $words);
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

	/**
	 * Accents are written both ways in collections, so "Pokémon" and
	 * "Pokemon" have to end up as the same key.
	 */
	private function transliterate(string $value): string {
		if (!preg_match('/[^\x00-\x7F]/', $value)) {
			return $value;
		}
		if (function_exists('transliterator_transliterate')) {
			$transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);
			if (is_string($transliterated)) {
				return $transliterated;
			}
		}
		$transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		return is_string($transliterated) ? mb_strtolower($transliterated) : $value;
	}
}
