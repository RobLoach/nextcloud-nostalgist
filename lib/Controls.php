<?php

declare(strict_types=1);

namespace OCA\Nostalgist;

/**
 * What the keyboard does: which key works which button of the controller,
 * and which key reaches for the player itself.
 *
 * Keys are kept as the codes a browser reports, such as "KeyX" or
 * "ArrowUp", since that is what both ends can agree on. The player turns
 * the controller ones into the names RetroArch knows when it starts a game.
 */
class Controls {
	/**
	 * The buttons of the controller, in the order they are shown, with the
	 * key RetroArch would use for them.
	 */
	public const BUTTONS = [
		'up' => ['label' => 'Up', 'default' => 'ArrowUp'],
		'down' => ['label' => 'Down', 'default' => 'ArrowDown'],
		'left' => ['label' => 'Left', 'default' => 'ArrowLeft'],
		'right' => ['label' => 'Right', 'default' => 'ArrowRight'],
		'a' => ['label' => 'A', 'default' => 'KeyX'],
		'b' => ['label' => 'B', 'default' => 'KeyZ'],
		'x' => ['label' => 'X', 'default' => 'KeyS'],
		'y' => ['label' => 'Y', 'default' => 'KeyA'],
		'l' => ['label' => 'L', 'default' => 'KeyQ'],
		'r' => ['label' => 'R', 'default' => 'KeyW'],
		'select' => ['label' => 'Select', 'default' => 'ShiftRight'],
		'start' => ['label' => 'Start', 'default' => 'Enter'],
	];

	/** What the player itself listens for. */
	public const HOTKEYS = [
		'pause' => ['label' => 'Pause and resume', 'default' => 'Space'],
		'fastForward' => ['label' => 'Fast-forward', 'default' => 'KeyT'],
		'fullscreen' => ['label' => 'Fullscreen', 'default' => 'KeyF'],
		'saveStates' => ['label' => 'Save states', 'default' => 'KeyO'],
		'screenshot' => ['label' => 'Screenshot', 'default' => 'KeyP'],
	];

	/**
	 * @return array<string, string> button => key code
	 */
	public static function defaultButtons(): array {
		return array_map(static fn (array $button): string => $button['default'], self::BUTTONS);
	}

	/**
	 * @return array<string, string> hotkey => key code
	 */
	public static function defaultHotkeys(): array {
		return array_map(static fn (array $hotkey): string => $hotkey['default'], self::HOTKEYS);
	}

	/**
	 * Keep the bindings that name something bindable. A key code is a short
	 * word from the browser, such as "KeyX", "F10" or "ArrowUp".
	 *
	 * @param array<string, mixed> $bindings
	 * @param array<string, array{label: string, default: string}> $known
	 * @return array<string, string>
	 */
	public static function sanitize(array $bindings, array $known): array {
		$sanitized = [];
		foreach ($bindings as $name => $code) {
			if (!isset($known[$name]) || !is_string($code)) {
				continue;
			}
			if (preg_match('/^[A-Za-z][A-Za-z0-9]{0,19}$/', $code) === 1) {
				$sanitized[$name] = $code;
			}
		}
		return $sanitized;
	}
}
