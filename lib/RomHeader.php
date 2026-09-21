<?php

declare(strict_types=1);

namespace OCA\Arcade;

/**
 * The name a game calls itself.
 *
 * Cartridges carry a header with the title the console shows, the region it
 * was sold in, and a few bytes that say which machine it is for. That is
 * worth having for a file named "rom1.gb": the title is what box art is
 * filed under, and the file name may be anything at all.
 *
 * Everything here works on a prefix of the file, and nothing here trusts
 * what it reads: a header that does not check out is no header.
 */
class RomHeader {
	/** Enough for the headers of every system below, SNES HiROM included. */
	public const BYTES = 0x10200;

	/** The systems whose cartridges carry a name the app can read. */
	private const READABLE = ['gb', 'gbc', 'gba', 'snes', 'genesis', 'sega32x'];

	/**
	 * Whether reading the front of this system's files is worth the read.
	 */
	public static function handles(string $system): bool {
		return in_array($system, self::READABLE, true);
	}

	/**
	 * @return array{title: string, region: string} empty strings when the
	 *                                              file does not say
	 */
	public static function read(string $data, string $system): array {
		$found = match ($system) {
			'gb', 'gbc' => self::gameBoy($data),
			'gba' => self::gameBoyAdvance($data),
			'snes' => self::superNintendo($data),
			'genesis', 'sega32x' => self::megaDrive($data),
			default => null,
		};
		return $found ?? ['title' => '', 'region' => ''];
	}

	/**
	 * The title sits right after the logo every cartridge has to carry, so
	 * the logo is what says the header is really there.
	 *
	 * @return array{title: string, region: string}|null
	 */
	private static function gameBoy(string $data): ?array {
		if (substr($data, 0x104, 4) !== "\xCE\xED\x66\x66") {
			return null;
		}
		return ['title' => self::text(substr($data, 0x134, 15)), 'region' => ''];
	}

	/**
	 * @return array{title: string, region: string}|null
	 */
	private static function gameBoyAdvance(string $data): ?array {
		if (substr($data, 0x04, 4) !== "\x24\xFF\xAE\x51") {
			return null;
		}
		return ['title' => self::text(substr($data, 0xA0, 12)), 'region' => ''];
	}

	/**
	 * The header is at one of two places, depending on how the cartridge
	 * maps its memory, and either may be pushed along by the 512 bytes a
	 * copier put in front. The checksum and its complement tell which one
	 * is the real header.
	 *
	 * @return array{title: string, region: string}|null
	 */
	private static function superNintendo(string $data): ?array {
		foreach ([0x7FC0, 0xFFC0, 0x7FC0 + 0x200, 0xFFC0 + 0x200] as $offset) {
			$header = substr($data, $offset, 32);
			if (strlen($header) < 32) {
				continue;
			}
			$complement = unpack('v', substr($header, 0x1C, 2));
			$checksum = unpack('v', substr($header, 0x1E, 2));
			if ($complement === false || $checksum === false) {
				continue;
			}
			if ((($complement[1] ^ $checksum[1]) & 0xFFFF) !== 0xFFFF) {
				continue;
			}
			return [
				'title' => self::text(substr($header, 0, 21)),
				'region' => self::snesRegion(ord($header[0x19])),
			];
		}
		return null;
	}

	/**
	 * Mega Drive cartridges carry two names, the one at home and the one
	 * abroad. The one abroad is the one box art is usually filed under.
	 *
	 * @return array{title: string, region: string}|null
	 */
	private static function megaDrive(string $data): ?array {
		if (!str_starts_with(substr($data, 0x100, 4), 'SEGA')) {
			return null;
		}
		$international = self::text(substr($data, 0x150, 48));
		$domestic = self::text(substr($data, 0x120, 48));
		return [
			'title' => $international !== '' ? $international : $domestic,
			'region' => self::megaDriveRegion(self::text(substr($data, 0x1F0, 3))),
		];
	}

	/**
	 * Mega Drive cartridges name their regions by letter, as many as they
	 * were sold in. Said the way a picture of one is filed: all three is
	 * the world.
	 */
	private static function megaDriveRegion(string $letters): string {
		$letters = strtoupper($letters);
		$regions = ['J' => 'Japan', 'U' => 'USA', 'E' => 'Europe'];
		$found = array_values(array_filter(
			$regions,
			static fn (string $letter): bool => str_contains($letters, $letter),
			ARRAY_FILTER_USE_KEY,
		));
		if (count($found) === count($regions)) {
			return 'World';
		}
		return $found[0] ?? '';
	}

	/**
	 * The countries a Super Nintendo cartridge was sold in, as the handful
	 * of codes that matter for a name.
	 */
	private static function snesRegion(int $code): string {
		return match ($code) {
			0x00 => 'Japan',
			0x01 => 'USA',
			0x02 => 'Europe',
			0x03 => 'Sweden',
			0x06 => 'France',
			0x07 => 'Netherlands',
			0x08 => 'Spain',
			0x09 => 'Germany',
			0x0A => 'Italy',
			0x0B => 'China',
			0x0D => 'Korea',
			0x0F => 'Canada',
			0x10 => 'Brazil',
			0x11 => 'Australia',
			default => '',
		};
	}

	/**
	 * Header text is padded with spaces or with nothing at all, and is only
	 * ever plain ASCII. Anything else means this is not a header.
	 */
	private static function text(string $raw): string {
		$text = '';
		foreach (str_split($raw) as $byte) {
			$code = ord($byte);
			if ($code === 0x00) {
				break;
			}
			if ($code < 0x20 || $code > 0x7E) {
				return '';
			}
			$text .= $byte;
		}
		return trim($text);
	}
}
