import { unzipSync } from 'fflate'
import { Nostalgist } from 'nostalgist'
import { getCurrentUser, getRequestToken } from '@nextcloud/auth'
import { defaultRemoteURL, defaultRootPath } from '@nextcloud/files/dav'
import { translate as t } from '@nextcloud/l10n'
import { generateFilePath, generateUrl } from '@nextcloud/router'
import { coreForSystem, systemForFile } from './systems.js'

const SRAM_SYNC_INTERVAL = 60 * 1000

/**
 * @param {string} path path of the file, relative to the user folder or,
 *                      on public share pages, the share root
 * @return {string} the WebDAV URL of the file
 */
export function davUrl(path) {
	// defaultRemoteURL and defaultRootPath resolve to the public share
	// endpoint and share token on public pages, and to the regular files
	// endpoint and user id otherwise.
	const encoded = path.replace(/^\/+/, '').split('/').map(encodeURIComponent).join('/')
	return `${defaultRemoteURL}${defaultRootPath}/${encoded}`
}

/**
 * Turn the fetched file into a playable ROM, extracting zip archives and
 * detecting the system from the (inner) file name.
 *
 * @param {Blob} blob the fetched file
 * @param {string} romName the file name
 * @param {?object} systemHint system detected from the folder the game is in
 * @return {Promise<{rom: File, system: object}>} the ROM and its system
 */
async function resolveRom(blob, romName, systemHint) {
	if (!romName.toLowerCase().endsWith('.zip')) {
		return { rom: new File([blob], romName), system: systemForFile(romName) }
	}
	const entries = Object.entries(unzipSync(new Uint8Array(await blob.arrayBuffer())))
		.filter(([name]) => !name.endsWith('/'))
	for (const [name, data] of entries) {
		const system = systemForFile(name)
		if (system !== null) {
			return { rom: new File([data], name.split('/').pop()), system }
		}
	}
	// No known extension inside; if the folder names the system, run the
	// largest entry with it.
	if (systemHint !== null && entries.length > 0) {
		const [name, data] = entries.reduce((a, b) => (a[1].length >= b[1].length ? a : b))
		return { rom: new File([data], name.split('/').pop()), system: systemHint }
	}
	throw new Error(t('nostalgist', 'No supported ROM found in the archive'))
}

/**
 * Fetch a ROM and launch it with Nostalgist.js.
 *
 * @param {object} options launch options
 * @param {HTMLCanvasElement} options.element the canvas to render into
 * @param {string} options.romUrl URL to fetch the ROM from
 * @param {string} options.romName file name of the ROM
 * @param {object} options.settings the user settings
 * @param {?object} [options.systemHint] system detected from the game's folder
 * @param {string} [options.romPath] path identifying the game, enables SRAM restore
 * @return {Promise<Nostalgist>} the running Nostalgist instance
 */
export async function launchRom({ element, romUrl, romName, settings = {}, systemHint = null, romPath = '' }) {
	// Fetch the ROM here so the request carries the Nextcloud session.
	const response = await fetch(romUrl, { credentials: 'same-origin' })
	if (!response.ok) {
		throw new Error(`Could not fetch the ROM: ${response.status} ${response.statusText}`)
	}
	const { rom, system } = await resolveRom(await response.blob(), romName, systemHint)
	if (system === null) {
		throw new Error(t('nostalgist', 'Unsupported ROM type: {file}', { file: romName }))
	}
	const sram = await fetchSram(romPath)

	return await Nostalgist.launch({
		element,
		core: coreForSystem(system.id),
		rom,
		// Only set when there is one: an undefined value is still a present
		// key, which Nostalgist would try to resolve as a file.
		...(sram === null ? {} : { sram }),
		respondToGlobalEvents: settings.respond_to_global_events !== false,
		retroarchConfig: {
			video_smooth: settings.video_smooth === true,
			fastforward_ratio: Number(settings.fastforward_ratio ?? 2),
		},
		retroarchCoreConfig: settings.core_options?.[coreForSystem(system.id)] ?? {},
		resolveCoreJs(coreName) {
			return coreUrl(`${coreName}_libretro.js`)
		},
		resolveCoreWasm(coreName) {
			return coreUrl(`${coreName}_libretro.wasm`)
		},
	})
}

/**
 * The core files are loaded from blob: URLs, where relative paths have no
 * meaningful base, so they are resolved to absolute URLs here.
 *
 * @param {string} file name of the core file
 * @return {string} the absolute URL of the core file
 */
function coreUrl(file) {
	return new URL(
		generateFilePath('nostalgist', 'img', `cores/${file}`),
		window.location.origin,
	).href
}

/**
 * Remember a game as played, for the library's recently played row.
 *
 * @param {string} romPath path of the game
 */
export function recordRecent(romPath) {
	if (!romPath || getCurrentUser() === null) {
		return
	}
	fetch(generateUrl('/apps/nostalgist/recent'), {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			requesttoken: getRequestToken() ?? '',
		},
		body: JSON.stringify({ file: romPath }),
	}).catch((error) => {
		console.error('Could not record the game as played', error)
	})
}

/**
 * @param {string} romPath path identifying the game
 * @return {string} the SRAM endpoint URL
 */
function sramUrl(romPath) {
	return generateUrl('/apps/nostalgist/sram?file={file}', { file: romPath })
}

/**
 * Fetch the stored in-game battery save, if any.
 *
 * @param {string} romPath path identifying the game
 * @return {Promise<?Blob>} the SRAM, or null
 */
async function fetchSram(romPath) {
	if (!romPath || getCurrentUser() === null) {
		return null
	}
	try {
		const response = await fetch(sramUrl(romPath), {
			headers: { requesttoken: getRequestToken() ?? '' },
		})
		if (!response.ok) {
			return null
		}
		const blob = await response.blob()
		return blob.size > 0 ? blob : null
	} catch (error) {
		console.error('Could not fetch the SRAM', error)
		return null
	}
}

/**
 * Periodically upload the in-game battery save, and once more when the
 * page is hidden, so in-game saves survive closing the tab.
 *
 * @param {Nostalgist} instance the running emulator
 * @param {string} romPath path identifying the game
 * @return {Function} stops the synchronization
 */
export function startSramSync(instance, romPath) {
	if (!romPath || getCurrentUser() === null) {
		return () => {}
	}
	const upload = async () => {
		try {
			const sram = await instance.saveSRAM()
			if (sram === undefined || sram.size === 0) {
				return
			}
			await fetch(sramUrl(romPath), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/octet-stream',
					requesttoken: getRequestToken() ?? '',
				},
				body: sram,
				// So the final upload survives the page closing.
				keepalive: true,
			})
		} catch (error) {
			console.error('Could not save the SRAM', error)
		}
	}
	const timer = setInterval(upload, SRAM_SYNC_INTERVAL)
	const onPageHide = () => {
		upload()
	}
	window.addEventListener('pagehide', onPageHide)
	return () => {
		clearInterval(timer)
		window.removeEventListener('pagehide', onPageHide)
		upload()
	}
}
