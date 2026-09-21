<?php

declare(strict_types=1);

namespace OCA\Arcade\Preview;

use OCA\Arcade\AppInfo\Application;
use OCA\Arcade\CoreMap;
use OCA\Arcade\Service\SettingsService;
use OCA\Arcade\Service\ThumbnailService;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ICacheFactory;
use OCP\IImage;
use OCP\Image;
use OCP\Preview\IProviderV2;

/**
 * Gives ROMs the box art of the thumbnails folder as their Nextcloud
 * preview, so a folder of games looks like a shelf of games in the Files
 * app, and anywhere else a preview is shown.
 *
 * Nothing is generated: the picture is one the user already has, only
 * scaled. A game with no box art has no preview, and falls back to the
 * icon of its mimetype.
 */
class RomPreview implements IProviderV2 {
	/** The index of a thumbnails folder is reused across previews. */
	private const INDEX_TTL = 300;

	public function __construct(
		private IRootFolder $rootFolder,
		private SettingsService $settingsService,
		private ThumbnailService $thumbnailService,
		private ICacheFactory $cacheFactory,
	) {
	}

	/**
	 * Every ROM mimetype the app knows, as one expression.
	 */
	public static function mimeTypeRegex(): string {
		$mimes = array_map(
			static fn (string $mime): string => preg_quote($mime, '/'),
			array_values(array_unique(array_column(CoreMap::SYSTEMS, 'mime'))),
		);
		return '/^(' . implode('|', $mimes) . ')$/';
	}

	public function getMimeType(): string {
		return self::mimeTypeRegex();
	}

	public function isAvailable(FileInfo $file): bool {
		return $file->getSize() > 0;
	}

	public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage {
		$owner = $file->getOwner()?->getUID();
		if ($owner === null) {
			return null;
		}
		$content = $this->boxArt($owner, $file);
		if ($content === null) {
			return null;
		}

		$image = $this->image();
		$image->loadFromData($content);
		if (!$image->valid()) {
			return null;
		}
		$image->scaleDownToFit($maxX, $maxY);
		return $image;
	}

	/**
	 * OCP\Image is the only way an app can make one, and it takes its
	 * methods from the interface.
	 */
	private function image(): IImage {
		return new Image();
	}

	/**
	 * The picture of the game, if the user has one filed for it.
	 */
	private function boxArt(string $userId, File $file): ?string {
		$settings = $this->settingsService->getUserSettings($userId);
		if ($settings['thumbnails_folder'] === '') {
			return null;
		}
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			$path = $userFolder->getRelativePath($file->getPath());
			if ($path === null) {
				return null;
			}
			$index = $this->index($userId, $userFolder, $settings['thumbnails_folder']);
			if ($index === []) {
				return null;
			}

			$library = '/' . trim($settings['library_folder'], '/');
			$subfolder = str_starts_with($path, $library . '/')
				? trim(dirname(substr($path, strlen($library))), '/.')
				: trim(dirname($path), '/.');
			$found = $this->thumbnailService->forGame(
				$index,
				CoreMap::systemForPath($path) ?? '',
				$subfolder,
				basename($path),
			);
			if ($found === []) {
				return null;
			}

			$node = $userFolder->getFirstNodeById((int)reset($found));
			return $node instanceof File ? $node->getContent() : null;
		} catch (NotFoundException|\Throwable) {
			return null;
		}
	}

	/**
	 * Walking the thumbnails folder for every preview would be wasteful, so
	 * what was found is kept for a few minutes.
	 *
	 * @return array<string, mixed>
	 */
	private function index(string $userId, Folder $userFolder, string $thumbnailsPath): array {
		$cache = $this->cacheFactory->createDistributed(Application::APP_ID . '_preview');
		$key = $userId . '|' . $thumbnailsPath;
		$cached = $cache->get($key);
		if (is_array($cached)) {
			return $cached;
		}

		$folder = $userFolder->get($thumbnailsPath);
		$index = $folder instanceof Folder ? $this->thumbnailService->buildIndex($folder) : [];
		$cache->set($key, $index, self::INDEX_TTL);
		return $index;
	}
}
