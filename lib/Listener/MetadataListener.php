<?php

declare(strict_types=1);

namespace OCA\Arcade\Listener;

use OCA\Arcade\CoreMap;
use OCA\Arcade\RomHeader;
use OCA\Arcade\Service\SettingsService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\File;
use OCP\FilesMetadata\AMetadataEvent;
use OCP\FilesMetadata\Event\MetadataBackgroundEvent;
use OCP\FilesMetadata\Event\MetadataLiveEvent;
use Psr\Log\LoggerInterface;

/**
 * What is true of a ROM itself, filed with the file rather than with the
 * user: which system it is for, what the cartridge calls itself, and what
 * it hashes to.
 *
 * Nextcloud keeps this by file id, so it survives a rename and a move, and
 * the indexed keys can be searched. Reading the file is left to the
 * background pass, so scanning a folder of games stays quick.
 *
 * @template-implements IEventListener<AMetadataEvent>
 */
class MetadataListener implements IEventListener {
	/** What is stored, by the name it is stored under. */
	public const SYSTEM = 'arcade-system';
	public const TITLE = 'arcade-title';
	public const REGION = 'arcade-region';
	public const CHECKSUM = 'arcade-md5';

	/**
	 * Hashing a file that big would cost more than the name it might win.
	 */
	private const MAX_HASH_SIZE = 64 * 1024 * 1024;

	public function __construct(
		private SettingsService $settingsService,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof AMetadataEvent) {
			return;
		}
		$node = $event->getNode();
		if (!$node instanceof File) {
			return;
		}
		$system = CoreMap::systemForPath($node->getPath());
		if ($system === null) {
			return;
		}

		// Both are had without reading a byte of the file: the system from
		// the name, the checksum from what the client sent with the upload.
		$metadata = $event->getMetadata();
		$metadata->setString(self::SYSTEM, $system, true);
		$given = $this->givenChecksum($node);
		if ($given !== '') {
			$metadata->setString(self::CHECKSUM, $given, true);
		}

		if ($event instanceof MetadataLiveEvent) {
			// Opening the file is only worth a background job when there is
			// something in it to read.
			if (RomHeader::handles($system) || ($given === '' && $this->hashingWanted())) {
				$event->requestBackgroundJob();
			}
			return;
		}
		if ($event instanceof MetadataBackgroundEvent) {
			$this->readTheRom($event, $node, $system, $given);
		}
	}

	private function readTheRom(
		MetadataBackgroundEvent $event,
		File $node,
		string $system,
		string $given,
	): void {
		$metadata = $event->getMetadata();
		$handle = false;
		try {
			$handle = $node->fopen('rb');
			if ($handle === false) {
				return;
			}
			// The file is read once: the front of it is the header, and the
			// rest of the same stream is what is hashed. Systems whose
			// cartridges carry no name the app reads skip the first part.
			$front = RomHeader::handles($system) ? (string)fread($handle, RomHeader::BYTES) : '';
			$header = RomHeader::read($front, $system);
			if ($header['title'] !== '') {
				$metadata->setString(self::TITLE, $header['title'], true);
			}
			if ($header['region'] !== '') {
				$metadata->setString(self::REGION, $header['region'], true);
			}

			if ($given === '' && $this->hashingWanted() && $node->getSize() <= self::MAX_HASH_SIZE) {
				$metadata->setString(self::CHECKSUM, $this->hash($handle, $front), true);
			}
		} catch (\Throwable $e) {
			$this->logger->debug('Could not read the header of a ROM', ['exception' => $e]);
		} finally {
			if ($handle !== false) {
				fclose($handle);
			}
		}
	}

	/**
	 * The checksum the client worked out on the way up, if it sent one.
	 * Desktop clients do; browsers do not.
	 */
	private function givenChecksum(File $node): string {
		foreach (explode(' ', $node->getChecksum()) as $checksum) {
			if (str_starts_with(strtoupper($checksum), 'MD5:')) {
				return strtolower(substr($checksum, 4));
			}
		}
		return '';
	}

	/**
	 * Working a checksum out means reading the whole ROM, so an
	 * administrator has to ask for it.
	 */
	private function hashingWanted(): bool {
		return (bool)$this->settingsService->getDefaults()['hash_roms'];
	}

	/**
	 * @param resource $handle the file, already read up to $front
	 */
	private function hash($handle, string $front): string {
		$context = hash_init('md5');
		hash_update($context, $front);
		hash_update_stream($context, $handle);
		return hash_final($context);
	}
}
