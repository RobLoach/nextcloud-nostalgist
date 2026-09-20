import { unzipSync } from 'fflate'
import { Nostalgist } from 'nostalgist'
import { defaultRemoteURL, defaultRootPath } from '@nextcloud/files/dav'
import { translate as t } from '@nextcloud/l10n'
import { generateFilePath } from '@nextcloud/router'
import { coreForSystem, systemForFile } from './systems.js'

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
 * @return {Promise<Nostalgist>} the running Nostalgist instance
 */
export async function launchRom({ element, romUrl, romName, settings = {}, systemHint = null }) {
	// Fetch the ROM here so the request carries the Nextcloud session.
	const response = await fetch(romUrl, { credentials: 'same-origin' })
	if (!response.ok) {
		throw new Error(`Could not fetch the ROM: ${response.status} ${response.statusText}`)
	}
	const { rom, system } = await resolveRom(await response.blob(), romName, systemHint)
	if (system === null) {
		throw new Error(t('nostalgist', 'Unsupported ROM type: {file}', { file: romName }))
	}

	return await Nostalgist.launch({
		element,
		core: coreForSystem(system.id, settings),
		rom,
		respondToGlobalEvents: settings.respond_to_global_events !== false,
		retroarchConfig: {
			video_smooth: settings.video_smooth === true,
			fastforward_ratio: Number(settings.fastforward_ratio ?? 10),
		},
		resolveCoreJs(coreName) {
			return generateFilePath('nostalgist', 'img', `cores/${coreName}_libretro.js`)
		},
		resolveCoreWasm(coreName) {
			return generateFilePath('nostalgist', 'img', `cores/${coreName}_libretro.wasm`)
		},
	})
}
