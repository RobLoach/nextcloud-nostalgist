<?php

declare(strict_types=1);

namespace OCA\Arcade\Search;

use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchOperator;

/**
 * Joins comparisons with AND, OR or NOT, for searching the file cache.
 */
class SearchBinaryOperator implements ISearchBinaryOperator {
	/** @var array<string, mixed> */
	private array $hints = [];

	/**
	 * @param list<ISearchOperator> $arguments
	 */
	public function __construct(
		private string $type,
		private array $arguments,
	) {
	}

	public function getType(): string {
		return $this->type;
	}

	/**
	 * @return list<ISearchOperator>
	 */
	public function getArguments(): array {
		return $this->arguments;
	}

	public function getQueryHint(string $name, $default) {
		return $this->hints[$name] ?? $default;
	}

	public function setQueryHint(string $name, $value): void {
		$this->hints[$name] = $value;
	}
}
