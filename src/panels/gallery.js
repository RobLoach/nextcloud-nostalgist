import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { api } from '../api.js'
import { ICONS, icon } from '../icons.js'

/**
 * Build the panel listing the screenshots taken of a game.
 *
 * @param {object} options options
 * @param {string} options.romPath path identifying the game
 * @param {Function} options.flash shows a status message
 * @return {{element: HTMLElement, refresh: Function}} the panel
 */
export function createGalleryPanel({ romPath, flash }) {
	const element = document.createElement('div')
	element.className = 'nostalgist-gallery hidden'

	const heading = document.createElement('h3')
	heading.textContent = t('nostalgist', 'Screenshots')
	element.appendChild(heading)

	const grid = document.createElement('div')
	grid.className = 'nostalgist-gallery-grid'
	element.appendChild(grid)

	const empty = document.createElement('p')
	empty.className = 'nostalgist-gallery-empty hidden'
	element.appendChild(empty)

	const remove = async (fileId) => {
		try {
			await api(generateUrl('/apps/nostalgist/screenshots?fileId={fileId}', { fileId }), {
				method: 'DELETE',
			})
			await refresh()
		} catch (error) {
			console.error('Could not delete the screenshot', error)
			flash(t('nostalgist', 'Could not delete the screenshot'))
		}
	}

	const refresh = async () => {
		let data
		try {
			const response = await api(generateUrl(
				'/apps/nostalgist/screenshots?file={file}',
				{ file: romPath },
			))
			data = await response.json()
		} catch (error) {
			console.error('Could not list the screenshots', error)
			return
		}

		grid.innerHTML = ''
		if (data.folder === '') {
			empty.textContent = t('nostalgist', 'Set a screenshots folder in the Nostalgist settings to keep your screenshots.')
		} else if (data.screenshots.length === 0) {
			empty.textContent = t('nostalgist', 'No screenshots of this game yet.')
		} else {
			empty.textContent = ''
		}
		empty.classList.toggle('hidden', empty.textContent === '')

		for (const screenshot of data.screenshots) {
			const item = document.createElement('figure')
			item.className = 'nostalgist-gallery-item'

			const link = document.createElement('a')
			link.href = generateUrl('/f/{fileId}', { fileId: screenshot.fileId })
			link.target = '_blank'
			link.rel = 'noreferrer noopener'
			link.title = screenshot.basename

			const image = document.createElement('img')
			image.src = generateUrl('/core/preview?fileId={fileId}&x=256&y=192&a=1', {
				fileId: screenshot.fileId,
			})
			image.alt = screenshot.basename
			image.loading = 'lazy'
			link.appendChild(image)
			item.appendChild(link)

			const caption = document.createElement('figcaption')
			caption.textContent = new Date(screenshot.mtime * 1000).toLocaleString()
			item.appendChild(caption)

			const deleteButton = document.createElement('button')
			deleteButton.type = 'button'
			deleteButton.title = t('nostalgist', 'Delete')
			deleteButton.setAttribute('aria-label', t('nostalgist', 'Delete'))
			deleteButton.innerHTML = icon(ICONS.trash)
			deleteButton.addEventListener('click', (event) => {
				event.stopPropagation()
				remove(screenshot.fileId)
			})
			item.appendChild(deleteButton)

			grid.appendChild(item)
		}
	}

	return { element, refresh }
}
