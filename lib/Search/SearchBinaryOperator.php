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

	/**
	 * The optimizer also rewrites the arguments of an operator in place,
	 * so the setter of the server's own class is offered too.
	 *
	 * @param list<ISearchOperator> $arguments
	 */
	public function setArguments(array $arguments): void {
		$this->arguments = $arguments;
	}

	/**
	 * The server's query optimizer tells clauses apart by their string
	 * form, casting without asking, so the form matches the server's own.
	 * An operator of somebody else's class without a string form is named
	 * rather than crashed on.
	 */
	public function __toString(): string {
		$parts = array_map(
			static fn (ISearchOperator $argument): string => $argument instanceof \Stringable
				? (string)$argument
				: get_class($argument),
			$this->arguments,
		);
		if ($this->type === ISearchBinaryOperator::OPERATOR_NOT) {
			return '(not ' . ($parts[0] ?? '') . ')';
		}
		return '(' . implode(' ' . $this->type . ' ', $parts) . ')';
	}
}
