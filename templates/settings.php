<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
$settings = $_['settings'];
$systems = $_['systems'];
?>

<div id="nostalgist-settings" class="section">
	<h2><?php p($l->t('Nostalgist')); ?></h2>
	<p class="settings-hint"><?php p($l->t('Configure how the Nostalgist retro game player behaves.')); ?></p>

	<h3><?php p($l->t('Player')); ?></h3>
	<p>
		<input type="checkbox" id="nostalgist-rewind" class="checkbox nostalgist-setting"
			data-setting="rewind_enable" <?php if ($settings['rewind_enable']) { p('checked'); } ?>>
		<label for="nostalgist-rewind"><?php p($l->t('Enable rewinding')); ?></label>
	</p>
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

	<h3><?php p($l->t('Emulator cores')); ?></h3>
	<p class="settings-hint"><?php p($l->t('Choose which libretro core is used for each system.')); ?></p>
	<?php foreach ($systems as $id => $system): ?>
		<p>
			<label for="nostalgist-core-<?php p($id); ?>"><?php p($system['label']); ?></label><br>
			<select id="nostalgist-core-<?php p($id); ?>" class="nostalgist-core"
				data-system="<?php p($id); ?>" <?php if (count($system['cores']) === 1) { p('disabled'); } ?>>
				<?php foreach ($system['cores'] as $core): ?>
					<option value="<?php p($core); ?>" <?php if (($settings['cores'][$id] ?? '') === $core) { p('selected'); } ?>>
						<?php p($core); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
	<?php endforeach; ?>

	<p>
		<button id="nostalgist-save" class="primary"><?php p($l->t('Save')); ?></button>
		<span id="nostalgist-save-status" aria-live="polite"></span>
	</p>
</div>
