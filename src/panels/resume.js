import { translate as t } from '@nextcloud/l10n'
import { api, stateUrl } from '../api.js'

/**
 * Pick up a game where it was left: either by offering to, or by doing it
 * as soon as the game starts.
 *
 * @param {object} options options
 * @param {HTMLElement} options.container element to attach the prompt to
 * @param {string} options.romPath path identifying the game
 * @param {Function} options.load loads a state slot
 * @param {boolean} [options.automatic] load without asking
 */
export async function offerResume({ container, romPath, load, automatic = false }) {
	let latest = null
	try {
		const response = await api(stateUrl('/states', romPath))
		const data = await response.json()
		latest = data.states.reduce((a, b) => (a === null || b.mtime > a.mtime ? b : a), null)
	} catch (error) {
		console.error('Could not list the states', error)
	}
	if (latest === null) {
		return
	}
	if (automatic) {
		load(latest.slot)
		return
	}

	const prompt = document.createElement('div')
	prompt.className = 'arcade-resume'
	const text = document.createElement('span')
	text.textContent = t('arcade', 'Continue from slot {slot} ({date})?', {
		slot: latest.slot,
		date: new Date(latest.mtime * 1000).toLocaleString(),
	})
	prompt.appendChild(text)

	const dismiss = () => {
		clearTimeout(timer)
		prompt.remove()
	}
	const timer = setTimeout(dismiss, 15000)

	const resumeButton = document.createElement('button')
	resumeButton.type = 'button'
	resumeButton.className = 'primary-action'
	resumeButton.textContent = t('arcade', 'Continue')
	resumeButton.addEventListener('click', () => {
		load(latest.slot)
		dismiss()
	})
	prompt.appendChild(resumeButton)

	const dismissButton = document.createElement('button')
	dismissButton.type = 'button'
	dismissButton.textContent = t('arcade', 'Dismiss')
	dismissButton.addEventListener('click', dismiss)
	prompt.appendChild(dismissButton)

	container.appendChild(prompt)
}
