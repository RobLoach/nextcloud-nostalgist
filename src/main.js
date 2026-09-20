import { getRequestToken } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { davUrl, launchRom } from './player.js'
import { isPlayable, systemForFolderPath, systemLabel } from './systems.js'
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

	if (!data.exists || data.games.length === 0) {
		const hint = document.createElement('p')
		hint.className = 'nostalgist-library-hint'
		hint.textContent = data.exists
			? t(
				'nostalgist',
				'No games found in {folder}. Upload some ROMs there, or pick another folder in the Nostalgist personal settings.',
				{ folder: data.folder },
			)
			: t(
				'nostalgist',
				'The games library folder {folder} does not exist. Create it, or pick another folder in the Nostalgist personal settings.',
				{ folder: data.folder },
			)
		library.appendChild(hint)
		return
	}

	const grid = document.createElement('div')
	grid.className = 'nostalgist-library-grid'
	for (const game of data.games) {
		const card = document.createElement('a')
		card.className = 'nostalgist-library-game'
		card.href = generateUrl('/apps/nostalgist/?file={file}', { file: game.path })

		if (game.thumbnail) {
			const thumbnail = document.createElement('img')
			thumbnail.className = 'nostalgist-library-game-thumbnail'
			thumbnail.src = generateUrl('/core/preview?fileId={fileId}&x=256&y=192&a=1', { fileId: game.thumbnail })
			thumbnail.alt = ''
			thumbnail.loading = 'lazy'
			card.appendChild(thumbnail)
		} else {
			const placeholder = document.createElement('div')
			placeholder.className = 'nostalgist-library-game-thumbnail nostalgist-library-game-placeholder'
			placeholder.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.97,16L5,19C4.67,19.3 4.23,19.5 3.75,19.5A1.75,1.75 0 0,1 2,17.75V17.5L3,10.12C3.21,7.81 5.14,6 7.5,6H16.5C18.86,6 20.79,7.81 21,10.12L22,17.5V17.75A1.75,1.75 0 0,1 20.25,19.5C19.77,19.5 19.33,19.3 19,19L16.03,16H7.97M7,8V10H5V11H7V13H8V11H10V10H8V8H7M16.5,8A0.75,0.75 0 0,0 15.75,8.75A0.75,0.75 0 0,0 16.5,9.5A0.75,0.75 0 0,0 17.25,8.75A0.75,0.75 0 0,0 16.5,8M14.75,9.75A0.75,0.75 0 0,0 14,10.5A0.75,0.75 0 0,0 14.75,11.25A0.75,0.75 0 0,0 15.5,10.5A0.75,0.75 0 0,0 14.75,9.75M18.25,9.75A0.75,0.75 0 0,0 17.5,10.5A0.75,0.75 0 0,0 18.25,11.25A0.75,0.75 0 0,0 19,10.5A0.75,0.75 0 0,0 18.25,9.75M16.5,11.5A0.75,0.75 0 0,0 15.75,12.25A0.75,0.75 0 0,0 16.5,13A0.75,0.75 0 0,0 17.25,12.25A0.75,0.75 0 0,0 16.5,11.5Z"/></svg>'
			card.appendChild(placeholder)
		}

		const name = document.createElement('span')
		name.className = 'nostalgist-library-game-name'
		name.textContent = game.basename.replace(/\.[^.]+$/, '')
		name.title = game.basename
		card.appendChild(name)

		const system = document.createElement('span')
		system.className = 'nostalgist-library-game-system'
		system.textContent = game.system === 'zip'
			? t('nostalgist', 'ZIP archive')
			: systemLabel(game.system)
		card.appendChild(system)

		grid.appendChild(card)
	}
	library.appendChild(grid)
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
		})
		attachToolbar({
			container: document.querySelector('.nostalgist'),
			instance,
			romPath: file,
			romName: basename,
			settings,
			closeUrl: generateUrl('/apps/nostalgist/'),
		})
	} catch (error) {
		console.error('Nostalgist failed to start', error)
		showMessage(t('nostalgist', 'Could not start the emulator: {error}', { error: error.message }))
	}
}

main()
