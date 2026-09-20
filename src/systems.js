import { loadState } from '@nextcloud/initial-state'

/**
 * System definitions (extensions, mimetype, cores) provided by lib/CoreMap.php.
 */
const systems = loadState('nostalgist', 'systems', {})

/**
 * @return {string[]} every ROM mimetype the player can handle
 */
export function romMimes() {
	return [...new Set(Object.values(systems).map((system) => system.mime))]
}

/**
 * Find the system for a file, by mimetype first and file extension second.
 *
 * @param {string} basename the file name
 * @param {string} [mime] the file mimetype, if known
 * @return {?object} the system definition, with its id, or null
 */
export function systemForFile(basename, mime = '') {
	const entries = Object.entries(systems)
	if (mime) {
		const byMime = entries.find(([, system]) => system.mime === mime)
		if (byMime) {
			return { id: byMime[0], ...byMime[1] }
		}
	}
	const extension = (basename || '').split('.').pop().toLowerCase()
	const byExtension = entries.find(([, system]) => system.extensions.includes(extension))
	return byExtension ? { id: byExtension[0], ...byExtension[1] } : null
}

/**
 * @param {string} systemId the system id
 * @param {object} settings the user settings
 * @return {?string} the libretro core to use for a system
 */
export function coreForSystem(systemId, settings = {}) {
	return settings?.cores?.[systemId] ?? systems[systemId]?.cores?.[0] ?? null
}

/**
 * @param {string} systemId the system id
 * @return {string} the human readable name of the system
 */
export function systemLabel(systemId) {
	return systems[systemId]?.label ?? systemId
}

/**
 * @param {string} basename the file name
 * @param {string} [mime] the file mimetype, if known
 * @return {boolean} whether the player can (try to) run this file
 */
export function isPlayable(basename, mime = '') {
	return systemForFile(basename, mime) !== null
		|| (basename || '').toLowerCase().endsWith('.zip')
}

/**
 * @param {string} systemId the system id
 * @return {?object} the system definition, with its id
 */
export function systemById(systemId) {
	return systems[systemId] ? { id: systemId, ...systems[systemId] } : null
}

/**
 * Detect the system from the folders a file is stored in, e.g.
 * "/Games/SNES/NHL 96.zip" is a Super Nintendo game.
 *
 * @param {string} path path of the file
 * @return {?object} the system definition, with its id, or null
 */
export function systemForFolderPath(path) {
	const segments = (path || '').split('/').filter(Boolean).slice(0, -1)
	for (const segment of segments.reverse()) {
		const normalized = segment.toLowerCase().replace(/[^a-z0-9]/g, '')
		if (normalized === '') {
			continue
		}
		for (const [id, system] of Object.entries(systems)) {
			if (normalized === id || (system.aliases ?? []).includes(normalized)) {
				return { id, ...system }
			}
		}
	}
	return null
}
