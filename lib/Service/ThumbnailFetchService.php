<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Service;

use OCA\Nostalgist\AppInfo\Application;
use OCA\Nostalgist\CoreMap;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;

/**
 * Downloads box art from the libretro thumbnail server into the thumbnails
 * folder of a user.
 *
 * The server keeps one file per No-Intro name, so a game is looked for
 * under a handful of spellings of its name: as it is called, with the
 * regions it is usually released under, and with the "+" that libretro
 * writes where a title says "and". Whatever is found is stored under the
 * name of the game itself, which is what the matching then looks for.
 */
class ThumbnailFetchService {
	private const SERVER = 'https://thumbnails.libretro.com';
	private const TYPE_FOLDER = 'Named_Boxarts';
	/** Regions to try for a game whose name carries none. */
	private const REGIONS = ['(USA)', '(Europe)', '(World)', '(Japan)'];
	/** A game that was looked for in vain is left alone for a while. */
	private const MISS_TTL = 7 * 24 * 3600;
	private const TIMEOUT = 15;

	public function __construct(
		private IClientService $clientService,
		private ICacheFactory $cacheFactory,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Look for the box art of games that have no image yet.
	 *
	 * @param list<array<string, mixed>> $games
	 * @return array{fetched: int, missing: int, tried: int}
	 */
	public function fetch(string $userId, array $games, Folder $thumbnails, int $limit): array {
		$cache = $this->cacheFactory->createDistributed(Application::APP_ID . '_fetch');
		$client = $this->clientService->newClient();

		$fetched = 0;
		$tried = 0;
		$missing = 0;
		foreach ($games as $game) {
			$platform = CoreMap::SYSTEMS[$game['system']]['platform'] ?? null;
			if ($platform === null) {
				// Zipped ROMs of an unknown system have nowhere to look.
				continue;
			}
			$key = $userId . '|' . $game['path'];
			if ($cache->get($key) !== null) {
				$missing++;
				continue;
			}
			if ($tried >= $limit) {
				$missing++;
				continue;
			}
			$tried++;

			$image = null;
			foreach ($this->candidates($game['basename']) as $candidate) {
				$image = $this->download($client, $platform, $candidate);
				if ($image !== null) {
					break;
				}
			}
			if ($image === null) {
				$cache->set($key, true, self::MISS_TTL);
				$missing++;
				continue;
			}
			$this->store($thumbnails, $platform, $game['basename'], $image);
			$fetched++;
		}
		return ['fetched' => $fetched, 'missing' => $missing, 'tried' => $tried];
	}

	/**
	 * The names a game may be filed under, most likely first.
	 *
	 * @return list<string>
	 */
	public function candidates(string $basename): array {
		$stem = pathinfo($basename, PATHINFO_FILENAME);
		// The characters libretro writes as an underscore.
		$stem = preg_replace('/[&*\/:`<>?\\\\|]/', '_', $stem) ?? $stem;

		$names = [$stem];
		// "Sonic and Knuckles" is filed as "Sonic + Knuckles", and the
		// other way around.
		foreach ([' and ' => ' + ', ' + ' => ' and '] as $from => $to) {
			if (str_contains($stem, $from)) {
				$names[] = str_replace($from, $to, $stem);
			}
		}

		// A name without its region, and then under the usual regions.
		foreach ($names as $name) {
			$bare = trim(preg_replace('/\s*[(\[][^)\]]*[)\]]/', '', $name) ?? $name);
			if ($bare === '' || $bare === $name) {
				continue;
			}
			$names[] = $bare;
		}
		foreach ($names as $name) {
			if (preg_match('/[(\[]/', $name) === 1) {
				continue;
			}
			foreach (self::REGIONS as $region) {
				$names[] = "$name $region";
			}
		}
		return array_values(array_unique(array_filter($names)));
	}

	private function download(\OCP\Http\Client\IClient $client, string $platform, string $name): ?string {
		$url = sprintf(
			'%s/%s/%s/%s.png',
			self::SERVER,
			rawurlencode($platform),
			self::TYPE_FOLDER,
			rawurlencode($name),
		);
		try {
			$response = $client->get($url, ['timeout' => self::TIMEOUT]);
			if ($response->getStatusCode() !== 200) {
				return null;
			}
			$body = $response->getBody();
			return is_string($body) && $body !== '' ? $body : null;
		} catch (\Throwable $e) {
			// A game that is not there answers with an error, which is the
			// common case rather than a problem.
			return null;
		}
	}

	/**
	 * Store the image the way a libretro thumbnail pack would, but under
	 * the name of the game as the user has it.
	 */
	private function store(Folder $thumbnails, string $platform, string $basename, string $image): void {
		try {
			$folder = $this->folder($this->folder($thumbnails, $platform), self::TYPE_FOLDER);
			$name = pathinfo($basename, PATHINFO_FILENAME) . '.png';
			$existing = $folder->nodeExists($name) ? $folder->get($name) : null;
			if ($existing instanceof File) {
				$existing->putContent($image);
			} elseif ($existing === null) {
				$folder->newFile($name, $image);
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Could not store the box art', ['exception' => $e]);
		}
	}

	private function folder(Folder $parent, string $name): Folder {
		try {
			$node = $parent->get($name);
			if ($node instanceof Folder) {
				return $node;
			}
		} catch (NotFoundException) {
			// Created below.
		}
		return $parent->newFolder($name);
	}
}
