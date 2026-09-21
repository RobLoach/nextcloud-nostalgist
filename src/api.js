import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

// Written when the player is closed; StateService knows it as AUTO_SLOT.
export const AUTO_SLOT = 0

/**
 * @param {string} route the app route, e.g. '/state'
 * @param {string} romPath path identifying the game
 * @param {number} [slot] the save state slot
 * @return {string} the endpoint URL
 */
export function stateUrl(route, romPath, slot = null) {
	return slot === null
		? generateUrl(`/apps/nostalgist${route}?file={file}`, { file: romPath })
		: generateUrl(`/apps/nostalgist${route}?file={file}&slot={slot}`, { file: romPath, slot })
}

/**
 * @param {string} url endpoint to call
 * @param {object} [options] extra fetch options
 * @return {Promise<Response>} the response, always ok
 */
export async function api(url, options = {}) {
	const response = await fetch(url, {
		...options,
		headers: {
			requesttoken: getRequestToken() ?? '',
			...(options.headers ?? {}),
		},
	})
	if (!response.ok) {
		throw new Error(`${response.status} ${response.statusText}`)
	}
	return response
}
