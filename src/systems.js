import { loadState } from '@nextcloud/initial-state'

/**
 * System definitions (extensions, mimetype, cores) provided by lib/CoreMap.php.
 */
const systems = loadState('arcade', 'systems', {})

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
 * @return {?string} the libretro core that runs this system
 */
export function coreForSystem(systemId) {
	return systems[systemId]?.core ?? null
}

/**
 * @param {string} systemId the system id
 * @return {string[]} the BIOS files the system may ask for
 */
export function biosForSystem(systemId) {
	return systems[systemId]?.bios ?? []
}

/**
 * @param {string} systemId the system id
 * @return {string} the short display name of the system
 */
export function systemLabel(systemId) {
	return systems[systemId]?.short ?? systems[systemId]?.label ?? systemId
}

/**
 * The short name of the system of a game at a path, for the folder its
 * saves and screenshots are filed under. Empty when nothing says which
 * system it is, so those stay where they always were.
 *
 * @param {string} path path of the game
 * @return {string} the short name of the system, or an empty string
 */
export function shortNameForPath(path) {
	const basename = (path || '').split('/').pop()
	const system = systemForFile(basename) ?? systemForFolderPath(path)
	return system?.short ?? ''
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
 * "/Games/SNES/NHL 96.zip" is a Super Nintendo game. No-Intro platform
 * names like "Nintendo - Super Nintendo Entertainment System" match too,
 * through their dash-separated segments.
 *
 * @param {string} path path of the file
 * @return {?object} the system definition, with its id, or null
 */
export function systemForFolderPath(path) {
	const folders = (path || '').split('/').filter(Boolean).slice(0, -1)
	for (const folder of folders.reverse()) {
		for (const candidate of folderCandidates(folder)) {
			for (const [id, system] of Object.entries(systems)) {
				if (candidate === id || (system.aliases ?? []).includes(candidate)) {
					return { id, ...system }
				}
			}
		}
	}
	return null
}

/** Words that say nothing about which system a folder holds. */
const NOISE = [
	'rom', 'roms', 'game', 'games', 'iso', 'isos', 'collection', 'collections',
	'library', 'set', 'sets', 'cart', 'carts', 'cartridge', 'cartridges',
	'backup', 'backups', 'my', 'the', 'emulation', 'emulator', 'emulators',
	'nointro', 'redump', 'tosec', 'goodset', 'usa', 'europe', 'japan', 'world',
]

/** Makers, whose name in front of a system says no more than the system. */
const VENDORS = [
	'nintendo', 'sega', 'snk', 'nec', 'atari', 'bandai', 'coleco', 'gce',
	'hudson', 'smithengineering',
]

/**
 * The normalized forms of a folder name worth looking up. Mirrors
 * CoreMap::folderCandidates, which does the same on the server.
 *
 * @param {string} name the folder name
 * @return {string[]} what to look for, in the order worth trying
 */
function folderCandidates(name) {
	const candidates = []
	for (const part of [name, ...name.split(/\s*[-–_+]\s*/)]) {
		const words = part.toLowerCase().split(/[^a-z0-9]+/).filter(Boolean)
		if (words.length === 0) {
			continue
		}
		// Noise is only trimmed off the ends: "Game" in the middle of
		// "Nintendo Game Boy" is the system, not padding.
		const trimmed = [...words]
		while (trimmed.length > 0 && NOISE.includes(trimmed[trimmed.length - 1])) {
			trimmed.pop()
		}
		const lead = [...trimmed]
		while (lead.length > 1 && NOISE.includes(lead[0])) {
			lead.shift()
		}
		for (const form of [words, trimmed, lead]) {
			if (form.length === 0) {
				continue
			}
			candidates.push(form.join(''))
			if (form.length > 1 && VENDORS.includes(form[0])) {
				candidates.push(form.slice(1).join(''))
			}
		}
	}
	return [...new Set(candidates)].filter(Boolean)
}
