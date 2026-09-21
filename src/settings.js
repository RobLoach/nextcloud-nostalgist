import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { api } from './api.js'
import { keyLabel, retroarchKey } from './keys.js'

const container = document.getElementById('arcade-settings')

/**
 * @param {HTMLInputElement} input the folder input to fill
 */
async function pickFolder(input) {
	// The file picker weighs more than the rest of this page put together,
	// so it is fetched when somebody actually goes looking for a folder.
	const [{ FilePickerType, getFilePickerBuilder }] = await Promise.all([
		import(/* webpackChunkName: 'picker' */ '@nextcloud/dialogs'),
		import(/* webpackChunkName: 'picker' */ '@nextcloud/dialogs/style.css'),
	])
	const picker = getFilePickerBuilder(t('arcade', 'Choose a folder'))
		.setMultiSelect(false)
		.setMimeTypeFilter(['httpd/unix-directory'])
		.allowDirectories(true)
		.setType(FilePickerType.Choose)
		.startAt(input.value || '/')
		.build()
	try {
		const path = await picker.pick()
		if (typeof path === 'string') {
			input.value = path === '' ? '/' : path
		}
	} catch (error) {
		// The picker was cancelled.
	}
}

async function save() {
	const status = document.getElementById('arcade-save-status')
	const settings = {}
	container.querySelectorAll('.arcade-setting').forEach((element) => {
		settings[element.dataset.setting] = element.type === 'checkbox'
			? element.checked
			: element.value
	})
	for (const kind of ['buttons', 'hotkeys']) {
		const bindings = {}
		container.querySelectorAll(`.arcade-key-binding[data-kind="${kind}"]`)
			.forEach((element) => {
				bindings[element.dataset.binding] = element.dataset.code
			})
		if (Object.keys(bindings).length > 0) {
			settings[kind] = bindings
		}
	}
	settings.thumbnail_types = {}
	container.querySelectorAll('.arcade-thumbnail-type').forEach((element) => {
		settings.thumbnail_types[element.dataset.system] = element.value
	})
	settings.core_options = {}
	container.querySelectorAll('.arcade-core-option').forEach((element) => {
		if (element.value !== '') {
			settings.core_options[element.dataset.core] ??= {}
			settings.core_options[element.dataset.core][element.dataset.option] = element.value
		}
	})

	status.textContent = t('arcade', 'Saving …')
	try {
		const url = container.dataset.scope === 'admin'
			? '/apps/arcade/settings/admin'
			: '/apps/arcade/settings'
		await api(generateUrl(url), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(settings),
		})
		status.textContent = t('arcade', 'Saved')
	} catch (error) {
		console.error('Could not save Arcade settings', error)
		status.textContent = t('arcade', 'Could not save the settings')
	}
	setTimeout(() => {
		status.textContent = ''
	}, 3000)
}

/**
 * Put every option of a core back to "Core default".
 *
 * @param {string} core the core to reset
 */
function resetCore(core) {
	container.querySelectorAll(`.arcade-core-option[data-core="${CSS.escape(core)}"]`)
		.forEach((element) => {
			element.value = ''
		})
	save()
}

/**
 * Keep the value next to a slider in step with it.
 *
 * @param {HTMLInputElement} range the slider
 */
function showRangeValue(range) {
	const output = container.querySelector(`output[for="${CSS.escape(range.id)}"]`)
	if (output !== null) {
		output.textContent = range.value + (range.dataset.unit ?? '')
	}
}

/**
 * Wait for a key, and give it to a binding.
 *
 * @param {HTMLElement} element the button of the binding
 */
function captureKey(element) {
	const previous = element.dataset.code
	element.classList.add('capturing')
	element.textContent = t('arcade', 'Press a key …')

	const done = (code) => {
		document.removeEventListener('keydown', onKey, true)
		element.classList.remove('capturing')
		if (code !== null) {
			element.dataset.code = code
		}
		showBinding(element)
	}

	const onKey = (event) => {
		event.preventDefault()
		event.stopPropagation()
		if (event.code === 'Escape') {
			done(previous)
			return
		}
		// The controller is bound through RetroArch, which has to have a
		// name for the key; the player itself can take any of them.
		if (element.dataset.kind === 'buttons' && retroarchKey(event.code) === null) {
			done(previous)
			element.title = t('arcade', 'The emulator has no name for that key, try another one')
			return
		}
		element.title = ''
		done(event.code)
	}
	document.addEventListener('keydown', onKey, true)
}

/**
 * @param {HTMLElement} element the button of a binding
 */
function showBinding(element) {
	element.textContent = keyLabel(element.dataset.code)
}

/**
 * Say so where a key of the player is also a key of the controller: the
 * game gets it, and the player is left waiting for a key that never comes.
 */
function showShadowedHotkeys() {
	const taken = new Set(
		[...container.querySelectorAll('.arcade-key-binding[data-kind="buttons"]')]
			.map((element) => element.dataset.code),
	)
	container.querySelectorAll('.arcade-key-binding[data-kind="hotkeys"]').forEach((element) => {
		const shadowed = taken.has(element.dataset.code)
		element.classList.toggle('shadowed', shadowed)
		element.title = shadowed
			? t('arcade', 'This key works a button of the controller, so the game gets it instead')
			: ''
	})
}

/**
 * Ask for the box art of the games that have none. The looking itself runs
 * as a background job, so this only starts it and reports what it says.
 */
async function fetchThumbnails() {
	const button = document.getElementById('arcade-fetch-thumbnails')
	const status = document.getElementById('arcade-fetch-status')
	button.disabled = true
	status.textContent = t('arcade', 'Starting …')
	try {
		// Saving first, so a folder just typed in is the one used.
		await save()
		await api(generateUrl('/apps/arcade/thumbnails/fetch'), { method: 'POST' })
		status.textContent = t('arcade', 'Looking for box art in the background. It carries on without this page.')
	} catch (error) {
		console.error('Could not look for box art', error)
		status.textContent = t('arcade', 'Could not start looking. A thumbnails folder has to be set first.')
	}
	button.disabled = false
}

/**
 * Show what the background job last had to say for itself.
 */
async function showFetchStatus() {
	const status = document.getElementById('arcade-fetch-status')
	if (status === null) {
		return
	}
	try {
		const result = await (await api(generateUrl('/apps/arcade/thumbnails/fetch'))).json()
		if (result.message) {
			status.textContent = result.queued
				? t('arcade', '{message}, still going', result)
				: result.message
		}
	} catch (error) {
		// Only the last word on an old run was at stake.
	}
}

if (container !== null) {
	document.getElementById('arcade-save').addEventListener('click', save)
	document.getElementById('arcade-fetch-thumbnails')?.addEventListener('click', fetchThumbnails)
	container.querySelectorAll('.arcade-key-binding').forEach((element) => {
		showBinding(element)
		element.addEventListener('click', () => captureKey(element))
	})
	showShadowedHotkeys()
	document.getElementById('arcade-keys-reset')?.addEventListener('click', () => {
		container.querySelectorAll('.arcade-key-binding').forEach((element) => {
			element.dataset.code = element.dataset.default ?? element.dataset.code
		})
		save()
	})
	showFetchStatus()
	container.querySelectorAll('.arcade-range').forEach((range) => {
		range.addEventListener('input', () => showRangeValue(range))
	})
	container.querySelectorAll('.arcade-core-reset').forEach((button) => {
		button.addEventListener('click', () => resetCore(button.dataset.core))
	})
	container.querySelectorAll('.arcade-folder-picker').forEach((button) => {
		button.addEventListener('click', () => {
			pickFolder(document.getElementById(button.dataset.target))
		})
	})
}
