import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { davUrl, launchRom } from './player.js'
import { coreForSystem, systemForFile } from './systems.js'

const file = loadState('nostalgist', 'file', '')
const settings = loadState('nostalgist', 'settings', {})

const canvas = document.querySelector('.nostalgist canvas')
const message = document.querySelector('.nostalgist .nostalgist-message')

/**
 * @param {string} text message to show instead of the player
 */
function showMessage(text) {
	canvas.classList.add('hidden')
	message.classList.remove('hidden')
	message.textContent = text
}

async function main() {
	if (!file) {
		showMessage(t('nostalgist', 'Open a ROM in the Files app to start playing.'))
		return
	}
	const basename = file.split('/').pop()
	const system = systemForFile(basename)
	if (system === null) {
		showMessage(t('nostalgist', 'Unsupported ROM type: {file}', { file: basename }))
		return
	}
	document.title = `${basename} - Nostalgist`
	try {
		await launchRom({
			element: canvas,
			romUrl: davUrl(file),
			romName: basename,
			core: coreForSystem(system.id, settings),
			settings,
		})
	} catch (error) {
		console.error('Nostalgist failed to start', error)
		showMessage(t('nostalgist', 'Could not start the emulator: {error}', { error: error.message }))
	}
}

main()
