import { getRequestToken } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

const container = document.getElementById('nostalgist-settings')

async function save() {
	const status = document.getElementById('nostalgist-save-status')
	const settings = { cores: {} }
	container.querySelectorAll('.nostalgist-setting').forEach((element) => {
		settings[element.dataset.setting] = element.type === 'checkbox'
			? element.checked
			: element.value
	})
	container.querySelectorAll('.nostalgist-core').forEach((element) => {
		settings.cores[element.dataset.system] = element.value
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

if (container !== null) {
	document.getElementById('nostalgist-save').addEventListener('click', save)
}
