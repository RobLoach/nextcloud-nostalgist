import { DefaultType, FileAction, registerFileAction } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { romMimes, systemForFile } from './systems.js'

const ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M7.97,16L5,19C4.67,19.3 4.23,19.5 3.75,19.5A1.75,1.75 0 0,1 2,17.75V17.5L3,10.12C3.21,7.81 5.14,6 7.5,6H16.5C18.86,6 20.79,7.81 21,10.12L22,17.5V17.75A1.75,1.75 0 0,1 20.25,19.5C19.77,19.5 19.33,19.3 19,19L16.03,16H7.97M7,8V10H5V11H7V13H8V11H10V10H8V8H7M16.5,8A0.75,0.75 0 0,0 15.75,8.75A0.75,0.75 0 0,0 16.5,9.5A0.75,0.75 0 0,0 17.25,8.75A0.75,0.75 0 0,0 16.5,8M14.75,9.75A0.75,0.75 0 0,0 14,10.5A0.75,0.75 0 0,0 14.75,11.25A0.75,0.75 0 0,0 15.5,10.5A0.75,0.75 0 0,0 14.75,9.75M18.25,9.75A0.75,0.75 0 0,0 17.5,10.5A0.75,0.75 0 0,0 18.25,11.25A0.75,0.75 0 0,0 19,10.5A0.75,0.75 0 0,0 18.25,9.75M16.5,11.5A0.75,0.75 0 0,0 15.75,12.25A0.75,0.75 0 0,0 16.5,13A0.75,0.75 0 0,0 17.25,12.25A0.75,0.75 0 0,0 16.5,11.5Z"/></svg>'

const mimes = romMimes()

registerFileAction(new FileAction({
	id: 'nostalgist-play',
	displayName: () => t('nostalgist', 'Play with Nostalgist'),
	iconSvgInline: () => ICON,
	// After the Viewer's default action, so the Viewer wins when it can
	// handle the mimetype and this action kicks in as the fallback.
	order: 1000,
	default: DefaultType.DEFAULT,

	enabled(nodes) {
		return nodes.length === 1
			&& systemForFile(nodes[0].basename, nodes[0].mime) !== null
	},

	async exec(node) {
		if (window.OCA?.Viewer !== undefined && mimes.includes(node.mime)) {
			window.OCA.Viewer.open({ path: node.path })
			return null
		}
		// Fall back to the standalone player page, matching by extension for
		// files whose mimetype is not (yet) known to the server.
		window.location.href = generateUrl('/apps/nostalgist/?file={file}', { file: node.path })
		return null
	},
}))

// Zipped ROMs get a menu entry instead of a default action, so a regular
// click on a zip archive keeps its normal behavior.
registerFileAction(new FileAction({
	id: 'nostalgist-play-zip',
	displayName: () => t('nostalgist', 'Play with Nostalgist'),
	iconSvgInline: () => ICON,
	order: 1000,

	enabled(nodes) {
		return nodes.length === 1
			&& nodes[0].basename.toLowerCase().endsWith('.zip')
	},

	async exec(node) {
		window.location.href = generateUrl('/apps/nostalgist/?file={file}', { file: node.path })
		return null
	},
}))
