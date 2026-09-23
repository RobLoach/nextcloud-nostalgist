<?php

declare(strict_types=1);

namespace OCA\Arcade\Search;

use OCP\Files\Search\ISearchComparison;

/**
 * A single field-against-value comparison, for searching the file cache.
 *
 * Nextcloud only ships the search interfaces publicly -- the classes that
 * implement them live in the private OC namespace -- so the app carries
 * these few lines itself rather than lean on what it is not promised.
 */
class SearchComparison implements ISearchComparison {
	/** @var array<string, mixed> */
	private array $hints = [];

	public function __construct(
		private string $type,
		private string $field,
		private string|int|bool|\DateTime|array $value,
		private string $extra = '',
	) {
	}

	public function getType(): string {
		return $this->type;
	}

	public function getField(): string {
		return $this->field;
	}

	public function getValue(): string|int|bool|\DateTime|array {
		return $this->value;
	}

	public function getExtra(): string {
		return $this->extra;
	}

	public function getQueryHint(string $name, $default) {
		return $this->hints[$name] ?? $default;
	}

	public function setQueryHint(string $name, $value): void {
		$this->hints[$name] = $value;
	}
}
