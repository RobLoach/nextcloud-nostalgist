<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
$defaults = $_['defaults'];
$coreOptions = $_['coreOptions'];
$systemsByCore = $_['systemsByCore'];
$settings = ['core_options' => $_['storedCoreOptions']];
$systems = $_['systems'];
$thumbnailTypes = $_['thumbnailTypes'];
$storedTypes = $_['storedThumbnailTypes'];
?>

<div id="arcade-settings" data-scope="admin">
	<div class="section">
		<h2 class="inlineblock"><?php p($l->t('Folders')); ?></h2>
		<span class="msg" aria-live="polite"></span>
		<p class="settings-hint"><?php p($l->t('The folders users start with. Everybody can pick their own afterwards. Changes are saved as they are made.')); ?></p>

		<?php foreach ([
			'library_folder' => $l->t('Games library folder'),
			'thumbnails_folder' => $l->t('Thumbnails folder'),
			'saves_folder' => $l->t('Saves folder'),
			'screenshots_folder' => $l->t('Screenshots folder'),
			'system_folder' => $l->t('System folder, for BIOS files'),
		] as $key => $label): ?>
			<p>
				<label for="arcade-<?php p($key); ?>"><?php p($label); ?></label><br>
				<input type="text" id="arcade-<?php p($key); ?>" class="arcade-setting"
					data-setting="<?php p($key); ?>" value="<?php p($defaults[$key]); ?>">
				<button type="button" class="arcade-folder-picker"
					data-target="arcade-<?php p($key); ?>"><?php p($l->t('Browse …')); ?></button>
			</p>
		<?php endforeach; ?>
	</div>

	<?php /* Box art, checksums and the scan limits are a declarative
	        settings form now, rendered and saved by the server itself:
	        see \OCA\Arcade\Settings\DeclarativeAdmin. */ ?>

	<div class="section">
		<h2 class="inlineblock"><?php p($l->t('Core options')); ?></h2>
		<span class="msg" aria-live="polite"></span>
		<p class="settings-hint"><?php p($l->t('Options of the emulator cores themselves. Left on "Core default", the core decides.')); ?></p>
		<?php foreach ($coreOptions as $core => $options): ?>
			<details class="arcade-core-options">
				<summary>
					<?php p($core); ?>
					<em><?php p(implode(', ', $systemsByCore[$core] ?? [])); ?></em>
				</summary>
				<?php foreach ($options as $key => $option): ?>
					<p>
						<?php /* Not through $l->t(): these come from CoreOptions, so they are
						        not translatable anyway, and a "%" in them would be taken for
						        a format specifier. */ ?>
						<label for="arcade-option-<?php p($key); ?>"><?php p($option['label']); ?></label><br>
						<select id="arcade-option-<?php p($key); ?>" class="arcade-core-option"
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
				<?php foreach ($systems as $systemId => $system): ?>
					<?php if ($system['core'] !== $core) { continue; } ?>
					<p>
						<label for="arcade-thumbnail-<?php p($systemId); ?>">
							<?php p($l->t('Picture %s starts out shown with', [$system['short']])); ?>
						</label><br>
						<select id="arcade-thumbnail-<?php p($systemId); ?>" class="arcade-thumbnail-type"
							data-system="<?php p($systemId); ?>">
							<?php foreach ($thumbnailTypes as $type => $label): ?>
								<option value="<?php p($type); ?>"
									<?php if (($storedTypes[$systemId] ?? 'boxart') === $type) { p('selected'); } ?>>
									<?php p($label); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>
				<?php endforeach; ?>
				<p>
					<button type="button" class="arcade-core-reset"
						data-core="<?php p($core); ?>"><?php p($l->t('Reset this core to defaults')); ?></button>
				</p>
			</details>
		<?php endforeach; ?>
	</div>
</div>
