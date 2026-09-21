<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
$settings = $_['settings'];
$buttons = $_['buttons'];
$hotkeys = $_['hotkeys'];
?>

<div id="nostalgist-settings" class="section">
	<h2><?php p($l->t('Nostalgist')); ?></h2>
	<p class="settings-hint"><?php p($l->t('Configure how the Nostalgist retro game player behaves.')); ?></p>

	<details class="nostalgist-section" open>
		<summary><?php p($l->t('Player')); ?></summary>
	<p>
		<input type="checkbox" id="nostalgist-smooth" class="checkbox nostalgist-setting"
			data-setting="video_smooth" <?php if ($settings['video_smooth']) { p('checked'); } ?>>
		<label for="nostalgist-smooth"><?php p($l->t('Smooth video filtering (bilinear)')); ?></label>
	</p>
	<p>
		<input type="checkbox" id="nostalgist-global-events" class="checkbox nostalgist-setting"
			data-setting="respond_to_global_events" <?php if ($settings['respond_to_global_events']) { p('checked'); } ?>>
		<label for="nostalgist-global-events"><?php p($l->t('Capture gamepad and keyboard input for the whole page while playing')); ?></label>
	</p>
	<p>
		<input type="checkbox" id="nostalgist-scale-integer" class="checkbox nostalgist-setting"
			data-setting="scale_integer" <?php if ($settings['scale_integer']) { p('checked'); } ?>>
		<label for="nostalgist-scale-integer"><?php p($l->t('Pixel-perfect scaling (whole pixels, with borders)')); ?></label>
	</p>
	<p>
		<input type="checkbox" id="nostalgist-pause-hidden" class="checkbox nostalgist-setting"
			data-setting="pause_when_hidden" <?php if ($settings['pause_when_hidden']) { p('checked'); } ?>>
		<label for="nostalgist-pause-hidden"><?php p($l->t('Pause the game while the tab is in the background')); ?></label>
	</p>
	<p>
		<input type="checkbox" id="nostalgist-autosave" class="checkbox nostalgist-setting"
			data-setting="autosave_on_close" <?php if ($settings['autosave_on_close']) { p('checked'); } ?>>
		<label for="nostalgist-autosave"><?php p($l->t('Save the game automatically when closing the player')); ?></label>
	</p>
	<p>
		<input type="checkbox" id="nostalgist-autoload" class="checkbox nostalgist-setting"
			data-setting="autoload_on_start" <?php if ($settings['autoload_on_start']) { p('checked'); } ?>>
		<label for="nostalgist-autoload"><?php p($l->t('Continue from the latest save when a game starts, without asking')); ?></label>
	</p>
	<p>
		<label for="nostalgist-autosave-interval"><?php p($l->t('Save the game to the Auto slot every')); ?></label><br>
		<select id="nostalgist-autosave-interval" class="nostalgist-setting" data-setting="autosave_interval">
			<?php foreach ([
				0 => $l->t('Never'),
				30 => $l->t('30 seconds'),
				60 => $l->t('Minute'),
				120 => $l->t('2 minutes'),
				300 => $l->t('5 minutes'),
				600 => $l->t('10 minutes'),
			] as $seconds => $label): ?>
				<option value="<?php p($seconds); ?>"
					<?php if ((int)$settings['autosave_interval'] === $seconds) { p('selected'); } ?>>
					<?php p($label); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="nostalgist-fastforward"><?php p($l->t('Fast-forward speed')); ?></label><br>
		<input type="range" id="nostalgist-fastforward" class="nostalgist-setting nostalgist-range"
			data-setting="fastforward_ratio" data-unit="×" min="1" max="5" step="0.5"
			value="<?php p($settings['fastforward_ratio']); ?>">
		<output for="nostalgist-fastforward"><?php p($settings['fastforward_ratio']); ?>×</output>
	</p>
	<p>
		<label for="nostalgist-volume"><?php p($l->t('Volume, in decibels, 0 is as recorded')); ?></label><br>
		<input type="range" id="nostalgist-volume" class="nostalgist-setting nostalgist-range"
			data-setting="audio_volume" data-unit=" dB" min="-20" max="10" step="1"
			value="<?php p($settings['audio_volume']); ?>">
		<output for="nostalgist-volume"><?php p($settings['audio_volume']); ?> dB</output>
	</p>
	<p>
		<label for="nostalgist-audio-latency"><?php p($l->t('Audio latency, raise it if the sound crackles')); ?></label><br>
		<input type="range" id="nostalgist-audio-latency" class="nostalgist-setting nostalgist-range"
			data-setting="audio_latency" data-unit=" ms" min="16" max="256" step="16"
			value="<?php p($settings['audio_latency']); ?>">
		<output for="nostalgist-audio-latency"><?php p($settings['audio_latency']); ?> ms</output>
	</p>

	</details>

	<details class="nostalgist-section">
		<summary><?php p($l->t('Controls')); ?></summary>
	<p class="settings-hint"><?php p($l->t('Click a key to set it, then press the one to use. A key that works a button of the controller is left to the game.')); ?></p>

	<h4><?php p($l->t('Controller')); ?></h4>
	<div class="nostalgist-keys">
		<?php foreach ($buttons as $name => $button): ?>
			<div class="nostalgist-key">
				<span><?php p($l->t($button['label'])); ?></span>
				<button type="button" class="nostalgist-key-binding"
					data-kind="buttons" data-binding="<?php p($name); ?>"
					data-default="<?php p($button['default']); ?>"
					data-code="<?php p($settings['buttons'][$name] ?? $button['default']); ?>"></button>
			</div>
		<?php endforeach; ?>
	</div>

	<h4><?php p($l->t('Player')); ?></h4>
	<div class="nostalgist-keys">
		<?php foreach ($hotkeys as $name => $hotkey): ?>
			<div class="nostalgist-key">
				<span><?php p($l->t($hotkey['label'])); ?></span>
				<button type="button" class="nostalgist-key-binding"
					data-kind="hotkeys" data-binding="<?php p($name); ?>"
					data-default="<?php p($hotkey['default']); ?>"
					data-code="<?php p($settings['hotkeys'][$name] ?? $hotkey['default']); ?>"></button>
			</div>
		<?php endforeach; ?>
	</div>
	<p>
		<button type="button" id="nostalgist-keys-reset"><?php p($l->t('Put the keys back as they were')); ?></button>
	</p>

	</details>

	<details class="nostalgist-section">
		<summary><?php p($l->t('Folders')); ?></summary>
	<p>
		<label for="nostalgist-library-folder"><?php p($l->t('Games library folder')); ?></label><br>
		<em><?php p($l->t('Games in this folder are listed on the Nostalgist page.')); ?></em><br>
		<input type="text" id="nostalgist-library-folder" class="nostalgist-setting"
			data-setting="library_folder" placeholder="/Games"
			value="<?php p($settings['library_folder']); ?>">
		<button type="button" class="nostalgist-folder-picker"
			data-target="nostalgist-library-folder"><?php p($l->t('Browse …')); ?></button>
	</p>
	<p>
		<label for="nostalgist-thumbnails-folder"><?php p($l->t('Thumbnails folder')); ?></label><br>
		<em><?php p($l->t('Images in this folder are used as game thumbnails, matched by file name: Mario.png is the thumbnail of Mario.nes. Leave empty to disable.')); ?></em><br>
		<input type="text" id="nostalgist-thumbnails-folder" class="nostalgist-setting"
			data-setting="thumbnails_folder"
			value="<?php p($settings['thumbnails_folder']); ?>">
		<button type="button" class="nostalgist-folder-picker"
			data-target="nostalgist-thumbnails-folder"><?php p($l->t('Browse …')); ?></button>
		<button type="button" id="nostalgist-fetch-thumbnails"><?php p($l->t('Look for missing box art')); ?></button>
		<span id="nostalgist-fetch-status" aria-live="polite"></span>
	</p>
	<p>
		<label for="nostalgist-saves-folder"><?php p($l->t('Saves folder')); ?></label><br>
		<em><?php p($l->t('Save states and battery saves are stored here, under the system and the game. Without a folder, a game cannot be saved at all and the player says so.')); ?></em><br>
		<input type="text" id="nostalgist-saves-folder" class="nostalgist-setting"
			data-setting="saves_folder"
			value="<?php p($settings['saves_folder']); ?>">
		<button type="button" class="nostalgist-folder-picker"
			data-target="nostalgist-saves-folder"><?php p($l->t('Browse …')); ?></button>
	</p>
	<p>
		<label for="nostalgist-system-folder"><?php p($l->t('System folder')); ?></label><br>
		<em><?php p($l->t('BIOS files are read from this folder, by the name the core expects, such as colecovision.rom. Leave empty if no game needs one.')); ?></em><br>
		<input type="text" id="nostalgist-system-folder" class="nostalgist-setting"
			data-setting="system_folder"
			value="<?php p($settings['system_folder']); ?>">
		<button type="button" class="nostalgist-folder-picker"
			data-target="nostalgist-system-folder"><?php p($l->t('Browse …')); ?></button>
	</p>
	<p>
		<label for="nostalgist-screenshots-folder"><?php p($l->t('Screenshots folder')); ?></label><br>
		<em><?php p($l->t('Screenshots taken in the player are saved here, under the system. Leave empty to download them instead.')); ?></em><br>
		<input type="text" id="nostalgist-screenshots-folder" class="nostalgist-setting"
			data-setting="screenshots_folder"
			value="<?php p($settings['screenshots_folder']); ?>">
		<button type="button" class="nostalgist-folder-picker"
			data-target="nostalgist-screenshots-folder"><?php p($l->t('Browse …')); ?></button>
	</p>

	</details>

	<p>
		<button id="nostalgist-save" class="primary"><?php p($l->t('Save')); ?></button>
		<span id="nostalgist-save-status" aria-live="polite"></span>
	</p>
</div>
