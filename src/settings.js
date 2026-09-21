import { getRequestToken } from '@nextcloud/auth'
import { FilePickerType, getFilePickerBuilder } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
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
	settings.core_options = {}
	container.querySelectorAll('.nostalgist-core-option').forEach((element) => {
		if (element.value !== '') {
			settings.core_options[element.dataset.core] ??= {}
			settings.core_options[element.dataset.core][element.dataset.option] = element.value
		}
	})

	status.textContent = t('nostalgist', 'Saving …')
	try {
		const response = await fetch(generateUrl('/apps/nostalgist/settings'), {
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

if (container !== null) {
	document.getElementById('nostalgist-save').addEventListener('click', save)
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
