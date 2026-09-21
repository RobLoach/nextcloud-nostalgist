<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
$settings = $_['settings'];
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
		<label for="nostalgist-fastforward"><?php p($l->t('Fast-forward ratio (0 for unlimited)')); ?></label><br>
		<input type="number" id="nostalgist-fastforward" class="nostalgist-setting"
			data-setting="fastforward_ratio" min="0" max="50" step="0.5"
			value="<?php p($settings['fastforward_ratio']); ?>">
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

	<p>
		<button id="nostalgist-save" class="primary"><?php p($l->t('Save')); ?></button>
		<span id="nostalgist-save-status" aria-live="polite"></span>
	</p>
</div>
