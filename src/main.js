import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { renderLibrary } from './library.js'
import { davUrl, launchRom, recordRecent, startSramSync } from './player.js'
import { isPlayable, systemForFolderPath } from './systems.js'
import { attachToolbar } from './toolbar.js'

const file = loadState('nostalgist', 'file', '')
const settings = loadState('nostalgist', 'settings', {})

const canvas = document.querySelector('.nostalgist canvas')
const message = document.querySelector('.nostalgist .nostalgist-message')
const library = document.querySelector('.nostalgist .nostalgist-library')

/**
 * @param {string} text message to show instead of the player
 */
function showMessage(text) {
	canvas.classList.add('hidden')
	library.classList.add('hidden')
	message.classList.remove('hidden')
	message.textContent = text
}

/**
 * List the games found in the user's library folder.
 */
async function showLibrary() {
	canvas.classList.add('hidden')
	library.classList.remove('hidden')
	await renderLibrary(library, showMessage)
}

async function main() {
	if (!file) {
		await showLibrary()
		return
	}
	const basename = file.split('/').pop()
	if (!isPlayable(basename)) {
		showMessage(t('nostalgist', 'Unsupported ROM type: {file}', { file: basename }))
		return
	}
	document.title = `${basename.replace(/\.[^.]+$/, '')} - Nostalgist`
	try {
		const instance = await launchRom({
			element: canvas,
			romUrl: davUrl(file),
			romName: basename,
			settings,
			systemHint: systemForFolderPath(file),
			romPath: file,
		})
		startSramSync(instance, file)
		const stopPlayTime = recordRecent(file)
		attachToolbar({
			container: document.querySelector('.nostalgist'),
			instance,
			romPath: file,
			romName: basename,
			settings,
			closeUrl: generateUrl('/apps/nostalgist/'),
			onClose: stopPlayTime,
		})
	} catch (error) {
		console.error('Nostalgist failed to start', error)
		showMessage(t('nostalgist', 'Could not start the emulator: {error}', { error: error.message }))
	}
}

main()
