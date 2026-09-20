import { getRequestToken } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { davUrl, launchRom } from './player.js'
import { coreForSystem, systemForFile, systemLabel } from './systems.js'
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
	message.classList.remove('hidden')
	message.textContent = text
}

/**
 * List the games found in the user's library folder.
 */
async function showLibrary() {
	canvas.classList.add('hidden')
	library.classList.remove('hidden')

	let data
	try {
		const response = await fetch(generateUrl('/apps/nostalgist/library'), {
			headers: { requesttoken: getRequestToken() ?? '' },
		})
		if (!response.ok) {
			throw new Error(`${response.status} ${response.statusText}`)
		}
		data = await response.json()
	} catch (error) {
		console.error('Could not load the games library', error)
		showMessage(t('nostalgist', 'Could not load the games library.'))
		return
	}

	const heading = document.createElement('h2')
	heading.textContent = t('nostalgist', 'Games library')
	library.appendChild(heading)

	const hint = document.createElement('p')
	hint.className = 'nostalgist-library-hint'
	library.appendChild(hint)

	if (!data.exists) {
		hint.textContent = t(
			'nostalgist',
			'The games library folder {folder} does not exist. Create it, or pick another folder in the Nostalgist personal settings.',
			{ folder: data.folder },
		)
		return
	}
	if (data.games.length === 0) {
		hint.textContent = t(
			'nostalgist',
			'No games found in {folder}. Upload some ROMs there, or pick another folder in the Nostalgist personal settings.',
			{ folder: data.folder },
		)
		return
	}
	hint.textContent = t('nostalgist', 'Games found in {folder}:', { folder: data.folder })

	const list = document.createElement('ul')
	list.className = 'nostalgist-library-list'
	for (const game of data.games) {
		const item = document.createElement('li')
		const link = document.createElement('a')
		link.href = generateUrl('/apps/nostalgist/?file={file}', { file: game.path })
		link.textContent = game.basename
		item.appendChild(link)
		const system = document.createElement('span')
		system.className = 'nostalgist-library-system'
		system.textContent = systemLabel(game.system)
		item.appendChild(system)
		list.appendChild(item)
	}
	library.appendChild(list)
}

async function main() {
	if (!file) {
		await showLibrary()
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
		const instance = await launchRom({
			element: canvas,
			romUrl: davUrl(file),
			romName: basename,
			core: coreForSystem(system.id, settings),
			settings,
		})
		attachToolbar({
			container: document.querySelector('.nostalgist'),
			instance,
			romPath: file,
			romName: basename,
		})
	} catch (error) {
		console.error('Nostalgist failed to start', error)
		showMessage(t('nostalgist', 'Could not start the emulator: {error}', { error: error.message }))
	}
}

main()
