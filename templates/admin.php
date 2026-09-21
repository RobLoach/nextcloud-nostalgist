<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
$defaults = $_['defaults'];
?>

<div id="nostalgist-settings" class="section" data-scope="admin">
	<h2><?php p($l->t('Nostalgist')); ?></h2>
	<p class="settings-hint"><?php p($l->t('The folders users start with. Everybody can pick their own afterwards.')); ?></p>

	<?php foreach ([
		'library_folder' => $l->t('Games library folder'),
		'thumbnails_folder' => $l->t('Thumbnails folder'),
		'saves_folder' => $l->t('Saves folder'),
		'screenshots_folder' => $l->t('Screenshots folder'),
	] as $key => $label): ?>
		<p>
			<label for="nostalgist-<?php p($key); ?>"><?php p($label); ?></label><br>
			<input type="text" id="nostalgist-<?php p($key); ?>" class="nostalgist-setting"
				data-setting="<?php p($key); ?>" value="<?php p($defaults[$key]); ?>">
			<button type="button" class="nostalgist-folder-picker"
				data-target="nostalgist-<?php p($key); ?>"><?php p($l->t('Browse …')); ?></button>
		</p>
	<?php endforeach; ?>

	<p>
		<button id="nostalgist-save" class="primary"><?php p($l->t('Save')); ?></button>
		<span id="nostalgist-save-status" aria-live="polite"></span>
	</p>
</div>
