<?php

declare(strict_types=1);

namespace OCA\Arcade\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * What was played and which games have saves used to live in JSON blobs: the
 * play stats in the user config, the games registry in a games.json of the
 * app data. Both are registries by nature, so they become tables, where two
 * sessions cannot overwrite each other and nothing has to be capped.
 *
 * The rows themselves are brought over lazily, the first time a user's
 * records are touched, since only the services know which user is which.
 *
 * @psalm-suppress UnusedClass
 */
class Version0380Date20260922120000 extends SimpleMigrationStep {
	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$changed = false;

		if (!$schema->hasTable('arcade_plays')) {
			$table = $schema->createTable('arcade_plays');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('plays', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('seconds', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('last_played', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id', 'file_id'], 'arcade_plays_user_file');
			$table->addIndex(['user_id', 'last_played'], 'arcade_plays_user_when');
			$changed = true;
		}

		if (!$schema->hasTable('arcade_games')) {
			$table = $schema->createTable('arcade_games');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			// Zero for the games that never had one, which are keyed by the
			// hash of their path instead.
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			// What the states of the game are filed under in the app data:
			// the file id as a string, or the sha256 of the path. This is
			// the name of a game, so it is what a user may have only one of.
			$table->addColumn('game_key', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('last_path', Types::STRING, [
				'notnull' => true,
				'length' => 4000,
				'default' => '',
			]);
			$table->addColumn('checksum', Types::STRING, [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id', 'game_key'], 'arcade_games_user_key');
			$table->addIndex(['user_id', 'file_id'], 'arcade_games_user_file');
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
