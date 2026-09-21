import { getCurrentUser, getRequestToken } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { AUTO_SLOT } from './api.js'
import { ICONS, icon } from './icons.js'
import { createGalleryPanel } from './panels/gallery.js'
import { offerResume } from './panels/resume.js'
import { createStatesPanel } from './panels/states.js'
import { davUrl } from './player.js'
import { attachTouchControls, isTouchDevice } from './touch.js'

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
export function attachToolbar({ container, instance, romPath, romName, settings = {}, closeUrl = '', onClose = null }) {
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
	const pauseButton = button(ICONS.pause, t('nostalgist', 'Pause'), (element) => {
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

	// A game left in a background tab keeps the processor busy for nothing.
	let pausedByTab = false
	const onVisibilityChange = () => {
		if (settings.pause_when_hidden === false) {
			return
		}
		if (document.hidden && !paused) {
			instance.pause()
			pausedByTab = true
		} else if (!document.hidden && pausedByTab) {
			instance.resume()
			pausedByTab = false
		}
	}
	document.addEventListener('visibilitychange', onVisibilityChange)

	button(ICONS.restart, t('nostalgist', 'Restart'), () => {
		instance.restart()
		flash(t('nostalgist', 'Restarted'))
	})

	// Save states need a logged-in user; hide them on public share pages.
	let autosaveTimer = null
	let statesPanel = null
	let statesButton = null
	const hideStates = () => {
		statesPanel?.element.classList.add('hidden')
		statesButton?.classList.remove('active')
	}
	if (getCurrentUser() !== null && romPath) {
		statesPanel = createStatesPanel({
			instance,
			romPath,
			flash,
			// Saving or loading a slot is the end of the interaction, so get
			// the panel out of the way and back to the game.
			onDone: () => {
				statesPanel.element.classList.add('hidden')
				statesButton?.classList.remove('active')
			},
		})
		statesButton = button(ICONS.save, t('nostalgist', 'Save states'), (element) => {
			const visible = !statesPanel.element.classList.contains('hidden')
			statesPanel.element.classList.toggle('hidden', visible)
			element.classList.toggle('active', !visible)
			if (!visible) {
				// Both panels cover the game, so only one shows at a time.
				galleryPanel?.element.classList.add('hidden')
				galleryButton?.classList.remove('active')
				statesPanel.refresh()
			}
		})
		offerResume({
			container,
			romPath,
			load: statesPanel.load,
			automatic: settings.autoload_on_start === true,
		})

		// And keep saving it while it is played, when asked to.
		const interval = Number(settings.autosave_interval ?? 0)
		if (interval > 0) {
			autosaveTimer = setInterval(() => {
				if (!paused && !document.hidden) {
					statesPanel.save(AUTO_SLOT)
				}
			}, interval * 1000)
		}
	}

	// The screenshots of this game, which also live in the user's files.
	let galleryPanel = null
	let galleryButton = null
	if (getCurrentUser() !== null && romPath) {
		galleryPanel = createGalleryPanel({ romPath, flash })
		galleryButton = button(ICONS.gallery, t('nostalgist', 'Screenshots'), (element) => {
			const visible = !galleryPanel.element.classList.contains('hidden')
			galleryPanel.element.classList.toggle('hidden', visible)
			element.classList.toggle('active', !visible)
			if (!visible) {
				hideStates()
				galleryPanel.refresh()
			}
		})
	}

	// Virtual gamepad for touch play.
	let touchControls = null
	if (isTouchDevice()) {
		touchControls = attachTouchControls({ container, instance })
		button(ICONS.gamepad, t('nostalgist', 'Touch controls'), (element) => {
			const hidden = touchControls.element.classList.toggle('hidden')
			element.classList.toggle('active', !hidden)
		}).classList.add('active')
	}

	button(ICONS.mute, t('nostalgist', 'Mute'), (element) => {
		instance.sendCommand('MUTE')
		element.classList.toggle('active')
	})

	button(ICONS.fastForward, t('nostalgist', 'Fast-forward'), (element) => {
		instance.sendCommand('FAST_FORWARD')
		element.classList.toggle('active')
	})

	// RetroArch's built-in menu, with core options, control remapping, etc.
	button(ICONS.menu, t('nostalgist', 'RetroArch menu'), (element) => {
		instance.sendCommand('MENU_TOGGLE')
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
				galleryPanel?.refresh()
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

	const fullscreenButton = button(ICONS.fullscreen, t('nostalgist', 'Fullscreen'), () => {
		if (document.fullscreenElement !== null) {
			document.exitFullscreen()
		} else {
			container.requestFullscreen?.()
		}
	})

	if (closeUrl !== '') {
		button(ICONS.close, t('nostalgist', 'Close'), async (element) => {
			element.disabled = true
			// Leave the game where it was, so it can be picked up again.
			if (settings.autosave_on_close !== false && statesPanel !== null) {
				flash(t('nostalgist', 'Saving the game …'))
				await statesPanel.save(AUTO_SLOT)
			}
			onClose?.()
			try {
				instance.exit()
			} catch (error) {
				console.error('Nostalgist failed to exit', error)
			}
			window.location.href = closeUrl
		})
	}

	// A handful of keys for what the buttons do, for playing without
	// reaching for the mouse. Keys the game itself uses are left alone.
	const shortcuts = {
		Space: () => pauseButton.click(),
		KeyF: () => fullscreenButton.click(),
		KeyS: () => statesButton?.click(),
		Escape: () => {
			hideStates()
			galleryPanel?.element.classList.add('hidden')
			galleryButton?.classList.remove('active')
		},
	}
	const onKeyDown = (event) => {
		if (event.ctrlKey || event.altKey || event.metaKey) {
			return
		}
		const target = event.target
		if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
			return
		}
		const shortcut = shortcuts[event.code]
		if (shortcut !== undefined) {
			event.preventDefault()
			event.stopPropagation()
			shortcut()
		}
	}
	// Ahead of the emulator, which listens on the window for its own keys.
	document.addEventListener('keydown', onKeyDown, true)

	toolbar.appendChild(status)
	container.appendChild(toolbar)
	if (statesPanel !== null) {
		container.appendChild(statesPanel.element)
	}
	if (galleryPanel !== null) {
		container.appendChild(galleryPanel.element)
	}

	return () => {
		clearTimeout(statusTimer)
		clearInterval(autosaveTimer)
		document.removeEventListener('visibilitychange', onVisibilityChange)
		document.removeEventListener('keydown', onKeyDown, true)
		touchControls?.detach()
		statesPanel?.element.remove()
		galleryPanel?.element.remove()
		container.querySelector('.nostalgist-resume')?.remove()
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
