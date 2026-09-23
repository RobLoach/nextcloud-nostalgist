<?php

declare(strict_types=1);

namespace OCA\Arcade\Search;

use OCP\Files\Search\ISearchOperator;
use OCP\Files\Search\ISearchOrder;
use OCP\Files\Search\ISearchQuery;
use OCP\IUser;

/**
 * A query against the file cache, handed to Folder::search().
 */
class SearchQuery implements ISearchQuery {
	/**
	 * @param list<ISearchOrder> $order
	 */
	public function __construct(
		private ISearchOperator $operation,
		private int $limit = 0,
		private int $offset = 0,
		private array $order = [],
		private ?IUser $user = null,
		private bool $limitToHome = false,
	) {
	}

	public function getSearchOperation(): ISearchOperator {
		return $this->operation;
	}

	public function getLimit(): int {
		return $this->limit;
	}

	public function getOffset(): int {
		return $this->offset;
	}

	/**
	 * @return list<ISearchOrder>
	 */
	public function getOrder(): array {
		return $this->order;
	}

	public function getUser(): ?IUser {
		return $this->user;
	}

	public function limitToHome(): bool {
		return $this->limitToHome;
	}

	/**
	 * Empty keeps the default columns, which is everything a FileInfo holds.
	 *
	 * @return list<string>
	 */
	public function getSelectFields(): array {
		return [];
	}
}
