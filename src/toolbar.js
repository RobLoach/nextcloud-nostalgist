import { getCurrentUser, getRequestToken } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { davUrl } from './player.js'

const ICONS = {
	pause: 'M14,19H18V5H14M6,19H10V5H6V19Z',
	play: 'M8,5.14V19.14L19,12.14L8,5.14Z',
	restart: 'M12,4C14.1,4 16.1,4.8 17.6,6.3C20.7,9.4 20.7,14.5 17.6,17.6C15.8,19.5 13.3,20.2 10.9,19.9L11.4,17.9C13.1,18.1 14.9,17.5 16.2,16.2C18.5,13.9 18.5,10.1 16.2,7.7C15.1,6.6 13.5,6 12,6V10.6L7,5.6L12,0.6V4M6.3,17.6C3.7,15 3.3,11 5.1,7.9L6.6,9.4C5.5,11.6 5.9,14.4 7.8,16.2C8.3,16.7 8.9,17.1 9.6,17.4L9,19.4C8,19 7.1,18.4 6.3,17.6Z',
	save: 'M15,9H5V5H15M12,19A3,3 0 0,1 9,16A3,3 0 0,1 12,13A3,3 0 0,1 15,16A3,3 0 0,1 12,19M17,3H5C3.89,3 3,3.9 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V7L17,3Z',
	mute: 'M12,4L9.91,6.09L12,8.18M4.27,3L3,4.27L7.73,9H3V15H7L12,20V13.27L16.25,17.53C15.58,18.04 14.83,18.46 14,18.7V20.77C15.38,20.45 16.63,19.82 17.68,18.96L19.73,21L21,19.73L12,10.73M19,12C19,12.94 18.8,13.82 18.46,14.64L19.97,16.15C20.62,14.91 21,13.5 21,12C21,7.72 18,4.14 14,3.23V5.29C16.89,6.15 19,8.83 19,12M16.5,12C16.5,10.23 15.5,8.71 14,7.97V10.18L16.45,12.63C16.5,12.43 16.5,12.21 16.5,12Z',
	fastForward: 'M13,6V18L21.5,12M4,18L12.5,12L4,6V18Z',
	screenshot: 'M4,4H7L9,2H15L17,4H20A2,2 0 0,1 22,6V18A2,2 0 0,1 20,20H4A2,2 0 0,1 2,18V6A2,2 0 0,1 4,4M12,7A5,5 0 0,0 7,12A5,5 0 0,0 12,17A5,5 0 0,0 17,12A5,5 0 0,0 12,7M12,9A3,3 0 0,1 15,12A3,3 0 0,1 12,15A3,3 0 0,1 9,12A3,3 0 0,1 12,9Z',
	fullscreen: 'M5,5H10V7H7V10H5V5M14,5H19V10H17V7H14V5M17,14H19V19H14V17H17V14M10,17V19H5V14H7V17H10Z',
	close: 'M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z',
}

const STYLE_ID = 'nostalgist-toolbar-style'
const STYLE = `
.nostalgist-player-container { position: relative; }
.nostalgist-toolbar {
	position: absolute;
	bottom: 8px;
	left: 50%;
	transform: translateX(-50%);
	display: flex;
	gap: 4px;
	align-items: center;
	background-color: rgba(0, 0, 0, 0.65);
	border-radius: 8px;
	padding: 4px 8px;
	z-index: 20100;
	opacity: 0.4;
	transition: opacity 0.2s;
}
.nostalgist-toolbar:hover,
.nostalgist-toolbar:focus-within { opacity: 1; }
.nostalgist-toolbar button {
	background-color: transparent;
	border: none;
	min-height: 34px;
	min-width: 34px;
	padding: 6px;
	margin: 0;
	cursor: pointer;
	border-radius: 6px;
	display: flex;
	align-items: center;
	justify-content: center;
}
.nostalgist-toolbar button:hover { background-color: rgba(255, 255, 255, 0.2); }
.nostalgist-toolbar button.active { background-color: rgba(255, 255, 255, 0.35); }
.nostalgist-toolbar svg { width: 20px; height: 20px; fill: #fff; }
.nostalgist-toolbar-status { color: #fff; font-size: 12px; margin-inline-start: 6px; white-space: nowrap; }
.nostalgist-states {
	position: absolute;
	bottom: 56px;
	left: 50%;
	transform: translateX(-50%);
	background-color: rgba(0, 0, 0, 0.85);
	border-radius: 8px;
	padding: 12px;
	z-index: 20100;
	color: #fff;
	max-height: 70%;
	overflow-y: auto;
	min-width: 320px;
}
.nostalgist-states.hidden { display: none; }
.nostalgist-states h3 { color: #fff; margin: 0 0 8px 0; font-size: 14px; }
.nostalgist-states-slot {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 4px 0;
}
.nostalgist-states-thumbnail {
	width: 64px;
	height: 48px;
	object-fit: cover;
	border-radius: 4px;
	background-color: rgba(255, 255, 255, 0.1);
	flex-shrink: 0;
}
.nostalgist-states-label {
	flex: 1;
	font-size: 12px;
	line-height: 1.3;
}
.nostalgist-states-slot button {
	background-color: rgba(255, 255, 255, 0.15);
	border: none;
	border-radius: 6px;
	color: #fff;
	font-size: 12px;
	padding: 6px 10px;
	margin: 0;
	min-height: 0;
	cursor: pointer;
}
.nostalgist-states-slot button:hover:not(:disabled) { background-color: rgba(255, 255, 255, 0.3); }
.nostalgist-states-slot button:disabled { opacity: 0.4; cursor: default; }
`

/**
 * @param {string} path the MDI icon path
 * @return {string} an inline SVG
 */
function icon(path) {
	return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="${path}"/></svg>`
}

function ensureStyle() {
	if (document.getElementById(STYLE_ID) !== null) {
		return
	}
	const style = document.createElement('style')
	style.id = STYLE_ID
	style.textContent = STYLE
	document.head.appendChild(style)
}

/**
 * @param {string} route the app route, e.g. '/state'
 * @param {string} romPath path identifying the game
 * @param {number} [slot] the save state slot
 * @return {string} the endpoint URL
 */
function stateUrl(route, romPath, slot = 0) {
	return slot > 0
		? generateUrl(`/apps/nostalgist${route}?file={file}&slot={slot}`, { file: romPath, slot })
		: generateUrl(`/apps/nostalgist${route}?file={file}`, { file: romPath })
}

/**
 * @param {string} url endpoint to call
 * @param {object} [options] extra fetch options
 * @return {Promise<Response>} the response, always ok
 */
async function api(url, options = {}) {
	const response = await fetch(url, {
		...options,
		headers: {
			requesttoken: getRequestToken() ?? '',
			...(options.headers ?? {}),
		},
	})
	if (!response.ok) {
		throw new Error(`${response.status} ${response.statusText}`)
	}
	return response
}

/**
 * Build the save states panel.
 *
 * @param {object} options options
 * @param {import('nostalgist').Nostalgist} options.instance the running emulator
 * @param {string} options.romPath path identifying the game
 * @param {Function} options.flash shows a status message
 * @return {{element: HTMLElement, refresh: Function}} the panel
 */
function createStatesPanel({ instance, romPath, flash }) {
	const element = document.createElement('div')
	element.className = 'nostalgist-states hidden'

	const heading = document.createElement('h3')
	heading.textContent = t('nostalgist', 'Save states')
	element.appendChild(heading)

	const slotsContainer = document.createElement('div')
	element.appendChild(slotsContainer)

	const smallButton = (label, onClick, disabled = false) => {
		const buttonElement = document.createElement('button')
		buttonElement.type = 'button'
		buttonElement.textContent = label
		buttonElement.disabled = disabled
		buttonElement.addEventListener('click', (event) => {
			event.stopPropagation()
			onClick()
		})
		return buttonElement
	}

	const save = async (slot) => {
		try {
			const { state, thumbnail } = await instance.saveState()
			await api(stateUrl('/state', romPath, slot), {
				method: 'POST',
				headers: { 'Content-Type': 'application/octet-stream' },
				body: state,
			})
			if (thumbnail !== undefined) {
				await api(stateUrl('/state/thumbnail', romPath, slot), {
					method: 'POST',
					headers: { 'Content-Type': 'image/png' },
					body: thumbnail,
				}).catch(() => {})
			}
			flash(t('nostalgist', 'State saved to slot {slot}', { slot }))
			await refresh()
		} catch (error) {
			console.error('Could not save the state', error)
			flash(t('nostalgist', 'Could not save the state'))
		}
	}

	const load = async (slot) => {
		try {
			const response = await api(stateUrl('/state', romPath, slot))
			await instance.loadState(await response.blob())
			flash(t('nostalgist', 'State loaded from slot {slot}', { slot }))
		} catch (error) {
			console.error('Could not load the state', error)
			flash(t('nostalgist', 'Could not load the state'))
		}
	}

	const remove = async (slot) => {
		try {
			await api(stateUrl('/state', romPath, slot), { method: 'DELETE' })
			await refresh()
		} catch (error) {
			console.error('Could not delete the state', error)
			flash(t('nostalgist', 'Could not delete the state'))
		}
	}

	const refresh = async () => {
		let data
		try {
			const response = await api(stateUrl('/states', romPath))
			data = await response.json()
		} catch (error) {
			console.error('Could not list the states', error)
			return
		}
		const bySlot = new Map(data.states.map((state) => [state.slot, state]))
		slotsContainer.innerHTML = ''
		for (let slot = 1; slot <= data.slots; slot++) {
			const state = bySlot.get(slot)
			const row = document.createElement('div')
			row.className = 'nostalgist-states-slot'

			const thumbnail = document.createElement('img')
			thumbnail.className = 'nostalgist-states-thumbnail'
			thumbnail.alt = ''
			if (state?.hasThumbnail) {
				thumbnail.src = stateUrl('/state/thumbnail', romPath, slot) + `&mtime=${state.mtime}`
			}
			row.appendChild(thumbnail)

			const label = document.createElement('span')
			label.className = 'nostalgist-states-label'
			label.textContent = state === undefined
				? t('nostalgist', 'Slot {slot} — empty', { slot })
				: t('nostalgist', 'Slot {slot} — {date}', {
					slot,
					date: new Date(state.mtime * 1000).toLocaleString(),
				})
			row.appendChild(label)

			row.appendChild(smallButton(t('nostalgist', 'Save'), () => save(slot)))
			row.appendChild(smallButton(t('nostalgist', 'Load'), () => load(slot), state === undefined))
			if (state !== undefined) {
				row.appendChild(smallButton(t('nostalgist', 'Delete'), () => remove(slot)))
			}
			slotsContainer.appendChild(row)
		}
	}

	return { element, refresh }
}

/**
 * Attach a control bar for a running Nostalgist instance.
 *
 * @param {object} options options
 * @param {HTMLElement} options.container element to attach the toolbar to
 * @param {import('nostalgist').Nostalgist} options.instance the running emulator
 * @param {string} options.romPath path identifying the game, for save states
 * @param {string} options.romName file name of the ROM, for screenshots
 * @param {object} options.settings the user settings
 * @param {string} [options.closeUrl] when set, adds a close button leading there
 * @return {Function} detaches the toolbar again
 */
export function attachToolbar({ container, instance, romPath, romName, settings = {}, closeUrl = '' }) {
	ensureStyle()
	container.classList.add('nostalgist-player-container')

	const toolbar = document.createElement('div')
	toolbar.className = 'nostalgist-toolbar'

	const status = document.createElement('span')
	status.className = 'nostalgist-toolbar-status'
	let statusTimer = null
	const flash = (text) => {
		status.textContent = text
		clearTimeout(statusTimer)
		statusTimer = setTimeout(() => {
			status.textContent = ''
		}, 3000)
	}

	const button = (iconPath, label, onClick) => {
		const element = document.createElement('button')
		element.type = 'button'
		element.title = label
		element.setAttribute('aria-label', label)
		element.innerHTML = icon(iconPath)
		element.addEventListener('click', (event) => {
			event.stopPropagation()
			onClick(element)
		})
		toolbar.appendChild(element)
		return element
	}

	let paused = false
	button(ICONS.pause, t('nostalgist', 'Pause'), (element) => {
		paused = !paused
		if (paused) {
			instance.pause()
		} else {
			instance.resume()
		}
		element.innerHTML = icon(paused ? ICONS.play : ICONS.pause)
		element.title = paused ? t('nostalgist', 'Resume') : t('nostalgist', 'Pause')
		element.setAttribute('aria-label', element.title)
		element.classList.toggle('active', paused)
	})

	button(ICONS.restart, t('nostalgist', 'Restart'), () => {
		instance.restart()
		flash(t('nostalgist', 'Restarted'))
	})

	// Save states need a logged-in user; hide them on public share pages.
	let statesPanel = null
	if (getCurrentUser() !== null && romPath) {
		statesPanel = createStatesPanel({ instance, romPath, flash })
		button(ICONS.save, t('nostalgist', 'Save states'), (element) => {
			const visible = !statesPanel.element.classList.contains('hidden')
			statesPanel.element.classList.toggle('hidden', visible)
			element.classList.toggle('active', !visible)
			if (!visible) {
				statesPanel.refresh()
			}
		})
	}

	button(ICONS.mute, t('nostalgist', 'Mute'), (element) => {
		instance.sendCommand('MUTE')
		element.classList.toggle('active')
	})

	button(ICONS.fastForward, t('nostalgist', 'Fast-forward'), (element) => {
		instance.sendCommand('FAST_FORWARD')
		element.classList.toggle('active')
	})

	button(ICONS.screenshot, t('nostalgist', 'Screenshot'), async () => {
		try {
			const blob = await instance.screenshot()
			const stem = (romName || 'nostalgist').replace(/\.[^.]+$/, '')
			const folder = settings.screenshots_folder
			if (folder && getCurrentUser() !== null) {
				await saveScreenshot(folder, stem, blob)
				flash(t('nostalgist', 'Screenshot saved to {folder}', { folder }))
			} else {
				const url = URL.createObjectURL(blob)
				const link = document.createElement('a')
				link.href = url
				link.download = `${stem}.png`
				link.click()
				URL.revokeObjectURL(url)
			}
		} catch (error) {
			console.error('Could not take a screenshot', error)
			flash(t('nostalgist', 'Could not take a screenshot'))
		}
	})

	button(ICONS.fullscreen, t('nostalgist', 'Fullscreen'), () => {
		if (document.fullscreenElement !== null) {
			document.exitFullscreen()
		} else {
			container.requestFullscreen?.()
		}
	})

	if (closeUrl !== '') {
		button(ICONS.close, t('nostalgist', 'Close'), () => {
			try {
				instance.exit()
			} catch (error) {
				console.error('Nostalgist failed to exit', error)
			}
			window.location.href = closeUrl
		})
	}

	toolbar.appendChild(status)
	container.appendChild(toolbar)
	if (statesPanel !== null) {
		container.appendChild(statesPanel.element)
	}

	return () => {
		clearTimeout(statusTimer)
		statesPanel?.element.remove()
		toolbar.remove()
	}
}

/**
 * Upload a screenshot to the user's screenshots folder over WebDAV,
 * creating the folder if needed.
 *
 * @param {string} folder the screenshots folder
 * @param {string} stem the ROM file name without extension
 * @param {Blob} blob the screenshot
 */
async function saveScreenshot(folder, stem, blob) {
	const timestamp = new Date().toISOString().replace(/[:T]/g, '-').slice(0, 19)
	const url = davUrl(`${folder}/${stem} ${timestamp}.png`)
	const put = () => fetch(url, {
		method: 'PUT',
		headers: {
			'Content-Type': 'image/png',
			requesttoken: getRequestToken() ?? '',
		},
		body: blob,
		credentials: 'same-origin',
	})
	let response = await put()
	if (response.status === 404 || response.status === 409) {
		// The folder does not exist yet; create it and retry.
		await fetch(davUrl(folder), {
			method: 'MKCOL',
			headers: { requesttoken: getRequestToken() ?? '' },
			credentials: 'same-origin',
		})
		response = await put()
	}
	if (!response.ok) {
		throw new Error(`${response.status} ${response.statusText}`)
	}
}
