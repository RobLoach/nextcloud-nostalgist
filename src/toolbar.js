import { getCurrentUser, getRequestToken } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

const ICONS = {
	pause: 'M14,19H18V5H14M6,19H10V5H6V19Z',
	play: 'M8,5.14V19.14L19,12.14L8,5.14Z',
	restart: 'M12,4C14.1,4 16.1,4.8 17.6,6.3C20.7,9.4 20.7,14.5 17.6,17.6C15.8,19.5 13.3,20.2 10.9,19.9L11.4,17.9C13.1,18.1 14.9,17.5 16.2,16.2C18.5,13.9 18.5,10.1 16.2,7.7C15.1,6.6 13.5,6 12,6V10.6L7,5.6L12,0.6V4M6.3,17.6C3.7,15 3.3,11 5.1,7.9L6.6,9.4C5.5,11.6 5.9,14.4 7.8,16.2C8.3,16.7 8.9,17.1 9.6,17.4L9,19.4C8,19 7.1,18.4 6.3,17.6Z',
	save: 'M15,9H5V5H15M12,19A3,3 0 0,1 9,16A3,3 0 0,1 12,13A3,3 0 0,1 15,16A3,3 0 0,1 12,19M17,3H5C3.89,3 3,3.9 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V7L17,3Z',
	load: 'M13,3A9,9 0 0,0 4,12H1L4.89,15.89L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3M12,8V13L16.28,15.54L17,14.33L13.5,12.25V8H12Z',
	mute: 'M12,4L9.91,6.09L12,8.18M4.27,3L3,4.27L7.73,9H3V15H7L12,20V13.27L16.25,17.53C15.58,18.04 14.83,18.46 14,18.7V20.77C15.38,20.45 16.63,19.82 17.68,18.96L19.73,21L21,19.73L12,10.73M19,12C19,12.94 18.8,13.82 18.46,14.64L19.97,16.15C20.62,14.91 21,13.5 21,12C21,7.72 18,4.14 14,3.23V5.29C16.89,6.15 19,8.83 19,12M16.5,12C16.5,10.23 15.5,8.71 14,7.97V10.18L16.45,12.63C16.5,12.43 16.5,12.21 16.5,12Z',
	fastForward: 'M13,6V18L21.5,12M4,18L12.5,12L4,6V18Z',
	screenshot: 'M4,4H7L9,2H15L17,4H20A2,2 0 0,1 22,6V18A2,2 0 0,1 20,20H4A2,2 0 0,1 2,18V6A2,2 0 0,1 4,4M12,7A5,5 0 0,0 7,12A5,5 0 0,0 12,17A5,5 0 0,0 17,12A5,5 0 0,0 12,7M12,9A3,3 0 0,1 15,12A3,3 0 0,1 12,15A3,3 0 0,1 9,12A3,3 0 0,1 12,9Z',
	fullscreen: 'M5,5H10V7H7V10H5V5M14,5H19V10H17V7H14V5M17,14H19V19H14V17H17V14M10,17V19H5V14H7V17H10Z',
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
 * @param {string} romPath path identifying the game
 * @return {string} the save state endpoint URL
 */
function stateUrl(romPath) {
	return generateUrl('/apps/nostalgist/state?file={file}', { file: romPath })
}

/**
 * Attach a control bar for a running Nostalgist instance.
 *
 * @param {object} options options
 * @param {HTMLElement} options.container element to attach the toolbar to
 * @param {import('nostalgist').Nostalgist} options.instance the running emulator
 * @param {string} options.romPath path identifying the game, for save states
 * @param {string} options.romName file name of the ROM, for screenshots
 * @return {Function} detaches the toolbar again
 */
export function attachToolbar({ container, instance, romPath, romName }) {
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
	if (getCurrentUser() !== null && romPath) {
		button(ICONS.save, t('nostalgist', 'Save state'), async () => {
			try {
				const { state } = await instance.saveState()
				const response = await fetch(stateUrl(romPath), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/octet-stream',
						requesttoken: getRequestToken() ?? '',
					},
					body: state,
				})
				if (!response.ok) {
					throw new Error(`${response.status} ${response.statusText}`)
				}
				flash(t('nostalgist', 'State saved'))
			} catch (error) {
				console.error('Could not save the state', error)
				flash(t('nostalgist', 'Could not save the state'))
			}
		})

		button(ICONS.load, t('nostalgist', 'Load state'), async () => {
			try {
				const response = await fetch(stateUrl(romPath), {
					headers: { requesttoken: getRequestToken() ?? '' },
				})
				if (response.status === 404) {
					flash(t('nostalgist', 'No saved state yet'))
					return
				}
				if (!response.ok) {
					throw new Error(`${response.status} ${response.statusText}`)
				}
				await instance.loadState(await response.blob())
				flash(t('nostalgist', 'State loaded'))
			} catch (error) {
				console.error('Could not load the state', error)
				flash(t('nostalgist', 'Could not load the state'))
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
			const url = URL.createObjectURL(blob)
			const link = document.createElement('a')
			link.href = url
			link.download = `${romName || 'nostalgist'}.png`
			link.click()
			URL.revokeObjectURL(url)
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

	toolbar.appendChild(status)
	container.appendChild(toolbar)

	return () => {
		clearTimeout(statusTimer)
		toolbar.remove()
	}
}
