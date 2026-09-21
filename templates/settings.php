<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
$settings = $_['settings'];
$coreOptions = $_['coreOptions'];
$systemsByCore = $_['systemsByCore'];
?>

<div id="nostalgist-settings" class="section">
	<h2><?php p($l->t('Nostalgist')); ?></h2>
	<p class="settings-hint"><?php p($l->t('Configure how the Nostalgist retro game player behaves.')); ?></p>

	<h3><?php p($l->t('Player')); ?></h3>
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

	<h3><?php p($l->t('Folders')); ?></h3>
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
	</p>
	<p>
		<label for="nostalgist-saves-folder"><?php p($l->t('Saves folder')); ?></label><br>
		<em><?php p($l->t('Save states and their screenshots are stored in this folder, one subfolder per game. Leave empty to store them internally.')); ?></em><br>
		<input type="text" id="nostalgist-saves-folder" class="nostalgist-setting"
			data-setting="saves_folder"
			value="<?php p($settings['saves_folder']); ?>">
		<button type="button" class="nostalgist-folder-picker"
			data-target="nostalgist-saves-folder"><?php p($l->t('Browse …')); ?></button>
	</p>
	<p>
		<label for="nostalgist-screenshots-folder"><?php p($l->t('Screenshots folder')); ?></label><br>
		<em><?php p($l->t('Screenshots taken in the player are saved to this folder. Leave empty to download them instead.')); ?></em><br>
		<input type="text" id="nostalgist-screenshots-folder" class="nostalgist-setting"
			data-setting="screenshots_folder"
			value="<?php p($settings['screenshots_folder']); ?>">
		<button type="button" class="nostalgist-folder-picker"
			data-target="nostalgist-screenshots-folder"><?php p($l->t('Browse …')); ?></button>
	</p>

	<h3><?php p($l->t('Core options')); ?></h3>
	<p class="settings-hint"><?php p($l->t('Options of the emulator cores themselves. Left on "Core default", the core decides.')); ?></p>
	<?php foreach ($coreOptions as $core => $options): ?>
		<details class="nostalgist-core-options">
			<summary>
				<?php p($core); ?>
				<em><?php p(implode(', ', $systemsByCore[$core] ?? [])); ?></em>
			</summary>
			<?php foreach ($options as $key => $option): ?>
				<p>
					<?php /* Not through $l->t(): these come from CoreOptions, so they are
					        not translatable anyway, and a "%" in them would be taken for
					        a format specifier. */ ?>
					<label for="nostalgist-option-<?php p($key); ?>"><?php p($option['label']); ?></label><br>
					<select id="nostalgist-option-<?php p($key); ?>" class="nostalgist-core-option"
						data-core="<?php p($core); ?>" data-option="<?php p($key); ?>">
						<option value=""><?php p($l->t('Core default')); ?></option>
						<?php foreach ($option['values'] as $value => $label): ?>
							<option value="<?php p($value); ?>"
								<?php if (($settings['core_options'][$core][$key] ?? '') === (string)$value) { p('selected'); } ?>>
								<?php p($label); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endforeach; ?>
			<p>
				<button type="button" class="nostalgist-core-reset"
					data-core="<?php p($core); ?>"><?php p($l->t('Reset this core to defaults')); ?></button>
			</p>
		</details>
	<?php endforeach; ?>

	<p>
		<button id="nostalgist-save" class="primary"><?php p($l->t('Save')); ?></button>
		<span id="nostalgist-save-status" aria-live="polite"></span>
	</p>
</div>
