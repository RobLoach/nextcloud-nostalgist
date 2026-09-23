<?php

declare(strict_types=1);

/**
 * The Doctrine constants OCP\DB\QueryBuilder\IQueryBuilder builds its own
 * constants from. Doctrine ships with the server, not with the OCP package,
 * so the tests bring the handful of values along themselves, as Doctrine
 * DBAL 3 declares them.
 */

namespace Doctrine\DBAL {
	if (!class_exists(ParameterType::class)) {
		class ParameterType {
			public const NULL = 0;
			public const INTEGER = 1;
			public const STRING = 2;
			public const LARGE_OBJECT = 3;
			public const BOOLEAN = 5;
			public const BINARY = 16;
			public const ASCII = 17;
		}
	}

	if (!class_exists(ArrayParameterType::class)) {
		class ArrayParameterType {
			public const INTEGER = 101;
			public const STRING = 102;
			public const BINARY = 116;
			public const ASCII = 117;
		}
	}
}

namespace Doctrine\DBAL\Types {
	if (!class_exists(Types::class)) {
		class Types {
			public const BOOLEAN = 'boolean';
			public const DATE_MUTABLE = 'date';
			public const DATE_IMMUTABLE = 'date_immutable';
			public const DATETIME_MUTABLE = 'datetime';
			public const DATETIME_IMMUTABLE = 'datetime_immutable';
			public const DATETIMETZ_MUTABLE = 'datetimetz';
			public const DATETIMETZ_IMMUTABLE = 'datetimetz_immutable';
			public const TIME_MUTABLE = 'time';
			public const TIME_IMMUTABLE = 'time_immutable';
		}
	}
}
