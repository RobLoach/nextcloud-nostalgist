import { getRequestToken } from '@nextcloud/auth'
import { FilePickerType, getFilePickerBuilder } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { keyLabel, retroarchKey } from './keys.js'
import '@nextcloud/dialogs/style.css'

const container = document.getElementById('nostalgist-settings')

/**
 * @param {HTMLInputElement} input the folder input to fill
 */
async function pickFolder(input) {
	const picker = getFilePickerBuilder(t('nostalgist', 'Choose a folder'))
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
	const status = document.getElementById('nostalgist-save-status')
	const settings = {}
	container.querySelectorAll('.nostalgist-setting').forEach((element) => {
		settings[element.dataset.setting] = element.type === 'checkbox'
			? element.checked
			: element.value
	})
	for (const kind of ['buttons', 'hotkeys']) {
		const bindings = {}
		container.querySelectorAll(`.nostalgist-key-binding[data-kind="${kind}"]`)
			.forEach((element) => {
				bindings[element.dataset.binding] = element.dataset.code
			})
		if (Object.keys(bindings).length > 0) {
			settings[kind] = bindings
		}
	}
	settings.core_options = {}
	container.querySelectorAll('.nostalgist-core-option').forEach((element) => {
		if (element.value !== '') {
			settings.core_options[element.dataset.core] ??= {}
			settings.core_options[element.dataset.core][element.dataset.option] = element.value
		}
	})

	status.textContent = t('nostalgist', 'Saving …')
	try {
		const url = container.dataset.scope === 'admin'
			? '/apps/nostalgist/settings/admin'
			: '/apps/nostalgist/settings'
		const response = await fetch(generateUrl(url), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				requesttoken: getRequestToken() ?? '',
			},
			body: JSON.stringify(settings),
		})
		if (!response.ok) {
			throw new Error(`${response.status} ${response.statusText}`)
		}
		status.textContent = t('nostalgist', 'Saved')
	} catch (error) {
		console.error('Could not save Nostalgist settings', error)
		status.textContent = t('nostalgist', 'Could not save the settings')
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
	container.querySelectorAll(`.nostalgist-core-option[data-core="${CSS.escape(core)}"]`)
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
	element.textContent = t('nostalgist', 'Press a key …')

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
			element.title = t('nostalgist', 'The emulator has no name for that key, try another one')
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
 * Ask for the box art of the games that have none. The looking itself runs
 * as a background job, so this only starts it and reports what it says.
 */
async function fetchThumbnails() {
	const button = document.getElementById('nostalgist-fetch-thumbnails')
	const status = document.getElementById('nostalgist-fetch-status')
	button.disabled = true
	status.textContent = t('nostalgist', 'Starting …')
	try {
		// Saving first, so a folder just typed in is the one used.
		await save()
		const response = await fetch(generateUrl('/apps/nostalgist/thumbnails/fetch'), {
			method: 'POST',
			headers: { requesttoken: getRequestToken() ?? '' },
		})
		if (!response.ok) {
			throw new Error(`${response.status} ${response.statusText}`)
		}
		status.textContent = t('nostalgist', 'Looking for box art in the background. It carries on without this page.')
	} catch (error) {
		console.error('Could not look for box art', error)
		status.textContent = t('nostalgist', 'Could not start looking. A thumbnails folder has to be set first.')
	}
	button.disabled = false
}

/**
 * Show what the background job last had to say for itself.
 */
async function showFetchStatus() {
	const status = document.getElementById('nostalgist-fetch-status')
	if (status === null) {
		return
	}
	try {
		const response = await fetch(generateUrl('/apps/nostalgist/thumbnails/fetch'), {
			headers: { requesttoken: getRequestToken() ?? '' },
		})
		const result = await response.json()
		if (result.message) {
			status.textContent = result.queued
				? t('nostalgist', '{message}, still going', result)
				: result.message
		}
	} catch (error) {
		// Only the last word on an old run was at stake.
	}
}

if (container !== null) {
	document.getElementById('nostalgist-save').addEventListener('click', save)
	document.getElementById('nostalgist-fetch-thumbnails')?.addEventListener('click', fetchThumbnails)
	container.querySelectorAll('.nostalgist-key-binding').forEach((element) => {
		showBinding(element)
		element.addEventListener('click', () => captureKey(element))
	})
	document.getElementById('nostalgist-keys-reset')?.addEventListener('click', () => {
		container.querySelectorAll('.nostalgist-key-binding').forEach((element) => {
			element.dataset.code = element.dataset.default ?? element.dataset.code
		})
		save()
	})
	showFetchStatus()
	container.querySelectorAll('.nostalgist-range').forEach((range) => {
		range.addEventListener('input', () => showRangeValue(range))
	})
	container.querySelectorAll('.nostalgist-core-reset').forEach((button) => {
		button.addEventListener('click', () => resetCore(button.dataset.core))
	})
	container.querySelectorAll('.nostalgist-folder-picker').forEach((button) => {
		button.addEventListener('click', () => {
			pickFolder(document.getElementById(button.dataset.target))
		})
	})
}
