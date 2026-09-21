import { getRequestToken } from '@nextcloud/auth'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { systemLabel } from './systems.js'

const VIEWS = ['grid', 'list', 'table']
const PAGE_SIZES = [24, 60, 120, 240]
const VIEW_KEY = 'nostalgist-library-view'
const PAGE_SIZE_KEY = 'nostalgist-library-page-size'

const ICONS = {
	grid: 'M3,11H11V3H3M3,21H11V13H3M13,21H21V13H13M13,3V11H21V3',
	list: 'M3,4H21V8H3V4M3,10H21V14H3V10M3,16H21V20H3V16Z',
	table: 'M5,4H19A2,2 0 0,1 21,6V18A2,2 0 0,1 19,20H5A2,2 0 0,1 3,18V6A2,2 0 0,1 5,4M5,8V12H11V8H5M13,8V12H19V8H13M5,14V18H11V14H5M13,14V18H19V14H13Z',
	gamepad: 'M7.97,16L5,19C4.67,19.3 4.23,19.5 3.75,19.5A1.75,1.75 0 0,1 2,17.75V17.5L3,10.12C3.21,7.81 5.14,6 7.5,6H16.5C18.86,6 20.79,7.81 21,10.12L22,17.5V17.75A1.75,1.75 0 0,1 20.25,19.5C19.77,19.5 19.33,19.3 19,19L16.03,16H7.97M7,8V10H5V11H7V13H8V11H10V10H8V8H7M16.5,8A0.75,0.75 0 0,0 15.75,8.75A0.75,0.75 0 0,0 16.5,9.5A0.75,0.75 0 0,0 17.25,8.75A0.75,0.75 0 0,0 16.5,8M14.75,9.75A0.75,0.75 0 0,0 14,10.5A0.75,0.75 0 0,0 14.75,11.25A0.75,0.75 0 0,0 15.5,10.5A0.75,0.75 0 0,0 14.75,9.75M18.25,9.75A0.75,0.75 0 0,0 17.5,10.5A0.75,0.75 0 0,0 18.25,11.25A0.75,0.75 0 0,0 19,10.5A0.75,0.75 0 0,0 18.25,9.75M16.5,11.5A0.75,0.75 0 0,0 15.75,12.25A0.75,0.75 0 0,0 16.5,13A0.75,0.75 0 0,0 17.25,12.25A0.75,0.75 0 0,0 16.5,11.5Z',
	refresh: 'M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z',
}

const state = {
	view: localStorage.getItem(VIEW_KEY) ?? 'grid',
	pageSize: Number(localStorage.getItem(PAGE_SIZE_KEY)) || 60,
	sort: 'name',
	order: 'asc',
	offset: 0,
}

/**
 * @param {string} path the MDI icon path
 * @return {string} an inline SVG
 */
function icon(path) {
	return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="${path}"/></svg>`
}

/**
 * @param {number} bytes a file size
 * @return {string} the size in human readable units
 */
function formatSize(bytes) {
	const units = ['B', 'KB', 'MB', 'GB']
	let size = bytes
	let unit = 0
	while (size >= 1024 && unit < units.length - 1) {
		size /= 1024
		unit++
	}
	return `${size < 10 && unit > 0 ? size.toFixed(1) : Math.round(size)} ${units[unit]}`
}

/**
 * @param {object} game the game
 * @return {string} the game name, without its file extension
 */
function gameName(game) {
	return game.basename.replace(/\.[^.]+$/, '')
}

/**
 * @param {object} game the game
 * @return {string} the name of the game's system
 */
function gameSystem(game) {
	return game.system === 'zip' ? t('nostalgist', 'ZIP archive') : systemLabel(game.system)
}

/**
 * @param {object} game the game
 * @return {string} the URL that plays the game
 */
function gameUrl(game) {
	return generateUrl('/apps/nostalgist/?file={file}', { file: game.path })
}

/**
 * @param {object} game the game
 * @param {number} size the requested thumbnail size
 * @return {HTMLElement} the thumbnail image, or a placeholder
 */
function thumbnailFor(game, size) {
	if (game.thumbnail) {
		const image = document.createElement('img')
		image.className = 'nostalgist-library-thumbnail'
		image.src = generateUrl('/core/preview?fileId={fileId}&x={size}&y={size}&a=1', {
			fileId: game.thumbnail,
			size,
		})
		image.alt = ''
		image.loading = 'lazy'
		return image
	}
	const placeholder = document.createElement('div')
	placeholder.className = 'nostalgist-library-thumbnail nostalgist-library-placeholder'
	placeholder.innerHTML = icon(ICONS.gamepad)
	return placeholder
}

/**
 * @param {object[]} games the games of the current page
 * @return {HTMLElement} the grid of game cards
 */
function renderGrid(games) {
	const grid = document.createElement('div')
	grid.className = 'nostalgist-library-grid'
	for (const game of games) {
		const card = document.createElement('a')
		card.className = 'nostalgist-library-game'
		card.href = gameUrl(game)
		card.appendChild(thumbnailFor(game, 256))

		const name = document.createElement('span')
		name.className = 'nostalgist-library-game-name'
		name.textContent = gameName(game)
		name.title = game.basename
		card.appendChild(name)

		const system = document.createElement('span')
		system.className = 'nostalgist-library-game-system'
		system.textContent = gameSystem(game)
		card.appendChild(system)

		grid.appendChild(card)
	}
	return grid
}

/**
 * @param {object[]} games the games of the current page
 * @return {HTMLElement} the list of games
 */
function renderList(games) {
	const list = document.createElement('div')
	list.className = 'nostalgist-library-rows'
	for (const game of games) {
		const row = document.createElement('a')
		row.className = 'nostalgist-library-row'
		row.href = gameUrl(game)
		row.appendChild(thumbnailFor(game, 64))

		const name = document.createElement('span')
		name.className = 'nostalgist-library-row-name'
		name.textContent = gameName(game)
		name.title = game.basename
		row.appendChild(name)

		const system = document.createElement('span')
		system.className = 'nostalgist-library-row-system'
		system.textContent = gameSystem(game)
		row.appendChild(system)

		list.appendChild(row)
	}
	return list
}

/**
 * @param {object[]} games the games of the current page
 * @param {Function} reload reloads the library with new parameters
 * @return {HTMLElement} the table of games
 */
function renderTable(games, reload) {
	const table = document.createElement('table')
	table.className = 'nostalgist-library-table'

	const head = document.createElement('thead')
	const headRow = document.createElement('tr')
	const columns = [
		{ key: 'name', label: t('nostalgist', 'Name') },
		{ key: 'system', label: t('nostalgist', 'System') },
		{ key: 'size', label: t('nostalgist', 'Size') },
		{ key: 'mtime', label: t('nostalgist', 'Modified') },
	]
	for (const column of columns) {
		const cell = document.createElement('th')
		const button = document.createElement('button')
		button.type = 'button'
		button.textContent = state.sort === column.key
			? `${column.label} ${state.order === 'asc' ? '▲' : '▼'}`
			: column.label
		button.addEventListener('click', () => {
			state.order = state.sort === column.key && state.order === 'asc' ? 'desc' : 'asc'
			state.sort = column.key
			state.offset = 0
			reload()
		})
		cell.appendChild(button)
		headRow.appendChild(cell)
	}
	head.appendChild(headRow)
	table.appendChild(head)

	const body = document.createElement('tbody')
	for (const game of games) {
		const row = document.createElement('tr')

		const nameCell = document.createElement('td')
		const link = document.createElement('a')
		link.href = gameUrl(game)
		link.textContent = gameName(game)
		link.title = game.basename
		nameCell.appendChild(link)
		row.appendChild(nameCell)

		const systemCell = document.createElement('td')
		systemCell.textContent = gameSystem(game)
		row.appendChild(systemCell)

		const sizeCell = document.createElement('td')
		sizeCell.textContent = formatSize(game.size ?? 0)
		row.appendChild(sizeCell)

		const modifiedCell = document.createElement('td')
		modifiedCell.textContent = game.mtime
			? new Date(game.mtime * 1000).toLocaleDateString()
			: ''
		row.appendChild(modifiedCell)

		body.appendChild(row)
	}
	table.appendChild(body)
	return table
}

/**
 * @param {object} data the library response
 * @param {Function} reload reloads the library with new parameters
 * @return {HTMLElement} the pagination controls
 */
function renderPagination(data, reload) {
	const pagination = document.createElement('div')
	pagination.className = 'nostalgist-library-pagination'

	const first = data.total === 0 ? 0 : data.offset + 1
	const last = Math.min(data.offset + data.limit, data.total)

	const pageButton = (label, targetOffset, disabled) => {
		const button = document.createElement('button')
		button.type = 'button'
		button.textContent = label
		button.disabled = disabled
		button.addEventListener('click', () => {
			state.offset = targetOffset
			reload()
		})
		return button
	}

	pagination.appendChild(pageButton(
		t('nostalgist', 'Previous'),
		Math.max(0, data.offset - data.limit),
		data.offset === 0,
	))

	const count = document.createElement('span')
	count.className = 'nostalgist-library-count'
	count.textContent = t('nostalgist', '{first}–{last} of {total}', {
		first,
		last,
		total: data.total,
	})
	pagination.appendChild(count)

	pagination.appendChild(pageButton(
		t('nostalgist', 'Next'),
		data.offset + data.limit,
		last >= data.total,
	))

	const pageSize = document.createElement('select')
	pageSize.className = 'nostalgist-library-page-size'
	pageSize.setAttribute('aria-label', t('nostalgist', 'Games per page'))
	for (const size of PAGE_SIZES) {
		const option = document.createElement('option')
		option.value = String(size)
		option.textContent = t('nostalgist', '{count} per page', { count: size })
		option.selected = size === state.pageSize
		pageSize.appendChild(option)
	}
	pageSize.addEventListener('change', () => {
		state.pageSize = Number(pageSize.value)
		state.offset = 0
		localStorage.setItem(PAGE_SIZE_KEY, String(state.pageSize))
		reload()
	})
	pagination.appendChild(pageSize)

	return pagination
}

/**
 * @param {Function} reload reloads the library with new parameters
 * @return {HTMLElement} the header, with the view switcher
 */
function renderHeader(reload) {
	const header = document.createElement('div')
	header.className = 'nostalgist-library-header'

	const heading = document.createElement('h2')
	heading.textContent = t('nostalgist', 'Games library')
	header.appendChild(heading)

	const controls = document.createElement('div')
	controls.className = 'nostalgist-library-controls'

	const labels = {
		grid: t('nostalgist', 'Grid view'),
		list: t('nostalgist', 'List view'),
		table: t('nostalgist', 'Table view'),
	}
	for (const view of VIEWS) {
		const button = document.createElement('button')
		button.type = 'button'
		button.className = view === state.view ? 'active' : ''
		button.title = labels[view]
		button.setAttribute('aria-label', labels[view])
		button.innerHTML = icon(ICONS[view])
		button.addEventListener('click', () => {
			state.view = view
			localStorage.setItem(VIEW_KEY, view)
			reload()
		})
		controls.appendChild(button)
	}

	const refresh = document.createElement('button')
	refresh.type = 'button'
	refresh.title = t('nostalgist', 'Rescan the library folder')
	refresh.setAttribute('aria-label', refresh.title)
	refresh.innerHTML = icon(ICONS.refresh)
	refresh.addEventListener('click', () => reload(true))
	controls.appendChild(refresh)

	header.appendChild(controls)
	return header
}

/**
 * Render the games library, replacing the contents of the container.
 *
 * @param {HTMLElement} container the element to render into
 * @param {Function} onError called with a message when the library fails to load
 */
export async function renderLibrary(container, onError) {
	const load = async (refresh = false) => {
		let data
		try {
			const response = await fetch(generateUrl(
				'/apps/nostalgist/library?offset={offset}&limit={limit}&sort={sort}&order={order}&refresh={refresh}',
				{
					offset: state.offset,
					limit: state.pageSize,
					sort: state.sort,
					order: state.order,
					refresh: refresh ? 1 : 0,
				},
			), { headers: { requesttoken: getRequestToken() ?? '' } })
			if (!response.ok) {
				throw new Error(`${response.status} ${response.statusText}`)
			}
			data = await response.json()
		} catch (error) {
			console.error('Could not load the games library', error)
			onError(t('nostalgist', 'Could not load the games library.'))
			return
		}
		render(data)
	}

	const render = (data) => {
		container.innerHTML = ''
		container.appendChild(renderHeader(load))

		if (!data.exists || data.total === 0) {
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
			container.appendChild(hint)
			return
		}

		const views = {
			grid: () => renderGrid(data.games),
			list: () => renderList(data.games),
			table: () => renderTable(data.games, () => load()),
		}
		container.appendChild((views[state.view] ?? views.grid)())

		if (data.total > data.limit) {
			container.appendChild(renderPagination(data, () => load()))
		}
		if (data.truncated) {
			const truncated = document.createElement('p')
			truncated.className = 'nostalgist-library-hint'
			truncated.textContent = t('nostalgist', 'Only the first {count} games are listed.', { count: data.total })
			container.appendChild(truncated)
		}
	}

	await load()
}
