import { translate as t } from '@nextcloud/l10n'
import { api, stateUrl } from '../api.js'

/**
 * Offer to continue from the most recent save state.
 *
 * @param {object} options options
 * @param {HTMLElement} options.container element to attach the prompt to
 * @param {string} options.romPath path identifying the game
 * @param {Function} options.load loads a state slot
 */
export async function offerResume({ container, romPath, load }) {
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

	const prompt = document.createElement('div')
	prompt.className = 'nostalgist-resume'
	const text = document.createElement('span')
	text.textContent = t('nostalgist', 'Continue from slot {slot} ({date})?', {
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
	resumeButton.textContent = t('nostalgist', 'Continue')
	resumeButton.addEventListener('click', () => {
		load(latest.slot)
		dismiss()
	})
	prompt.appendChild(resumeButton)

	const dismissButton = document.createElement('button')
	dismissButton.type = 'button'
	dismissButton.textContent = t('nostalgist', 'Dismiss')
	dismissButton.addEventListener('click', dismiss)
	prompt.appendChild(dismissButton)

	container.appendChild(prompt)
}
