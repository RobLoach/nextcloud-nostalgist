import { join, resolve } from 'node:path'
import { createAppConfig } from '@nextcloud/vite-config'

// Entries land in js/ as arcade-<name>.mjs, which is what the PHP side
// loads: script('arcade', 'arcade-main') and the Util::addScript calls
// for arcade-viewer and arcade-settings. Chunks resolve their own URL at
// runtime through OC.filePath, which follows the Nextcloud webroot.
export default createAppConfig({
	main: resolve(join('src', 'main.js')),
	viewer: resolve(join('src', 'viewer.js')),
	files: resolve(join('src', 'files.js')),
	settings: resolve(join('src', 'settings.js')),
}, {
	// The stylesheets in css/ are served as they are with style(); the
	// styles pulled in by imports (the file picker dialog) are injected
	// from the bundles so nothing is emitted into css/.
	inlineCSS: true,
})
