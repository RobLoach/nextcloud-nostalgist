const webpackConfig = require('@nextcloud/webpack-vue-config')
const path = require('path')

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
