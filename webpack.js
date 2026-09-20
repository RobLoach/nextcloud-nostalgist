const webpackConfig = require('@nextcloud/webpack-vue-config')
const path = require('path')

// The npm package name is not the app id, so lazy-loaded chunks would end
// up under a wrong name and URL without these overrides. 'auto' derives the
// public path from the script URL, which follows the Nextcloud webroot.
webpackConfig.output.publicPath = 'auto'
webpackConfig.output.chunkFilename = 'nostalgist-chunk-[name].js?v=[contenthash]'

webpackConfig.entry = {
	main: {
		import: path.join(__dirname, 'src', 'main.js'),
		filename: 'main.js'
	},
	files: {
		import: path.join(__dirname, 'src', 'files.js'),
		filename: 'nostalgist-files.js'
	},
	viewer: {
		import: path.join(__dirname, 'src', 'viewer.js'),
		filename: 'nostalgist-viewer.js'
	},
	settings: {
		import: path.join(__dirname, 'src', 'settings.js'),
		filename: 'nostalgist-settings.js'
	}
}

module.exports = webpackConfig
