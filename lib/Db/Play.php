<?php

declare(strict_types=1);

namespace OCA\Arcade\Db;

use OCP\AppFramework\Db\Entity;

/**
 * What one user played one game for: how often, how long, and when last.
 *
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method int getPlays()
 * @method void setPlays(int $plays)
 * @method int getSeconds()
 * @method void setSeconds(int $seconds)
 * @method int getLastPlayed()
 * @method void setLastPlayed(int $lastPlayed)
 */
class Play extends Entity {
	protected string $userId = '';
	protected int $fileId = 0;
	protected int $plays = 0;
	protected int $seconds = 0;
	protected int $lastPlayed = 0;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('fileId', 'integer');
		$this->addType('plays', 'integer');
		$this->addType('seconds', 'integer');
		$this->addType('lastPlayed', 'integer');
	}
}
