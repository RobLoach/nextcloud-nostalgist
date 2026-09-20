import { Nostalgist } from 'nostalgist'
import { defaultRemoteURL, defaultRootPath } from '@nextcloud/files/dav'
import { generateFilePath } from '@nextcloud/router'

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
 * Fetch a ROM and launch it with Nostalgist.js.
 *
 * @param {object} options launch options
 * @param {HTMLCanvasElement} options.element the canvas to render into
 * @param {string} options.romUrl URL to fetch the ROM from
 * @param {string} options.romName file name of the ROM
 * @param {string} options.core the libretro core to run
 * @param {object} options.settings the user settings
 * @return {Promise<Nostalgist>} the running Nostalgist instance
 */
export async function launchRom({ element, romUrl, romName, core, settings = {} }) {
	// Fetch the ROM here so the request carries the Nextcloud session.
	const response = await fetch(romUrl, { credentials: 'same-origin' })
	if (!response.ok) {
		throw new Error(`Could not fetch the ROM: ${response.status} ${response.statusText}`)
	}
	const rom = new File([await response.blob()], romName)

	return await Nostalgist.launch({
		element,
		core,
		rom,
		respondToGlobalEvents: settings.respond_to_global_events !== false,
		retroarchConfig: {
			rewind_enable: settings.rewind_enable === true,
			video_smooth: settings.video_smooth !== false,
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
