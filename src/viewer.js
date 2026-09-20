import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { davUrl, launchRom } from './player.js'
import { coreForSystem, romMimes, systemForFile } from './systems.js'
import { attachToolbar } from './toolbar.js'

const settings = loadState('nostalgist', 'settings', {})

/**
 * Viewer handler component. Written as a plain options object with a render
 * function so it works with the Viewer's own Vue instance without needing a
 * template compiler.
 */
const NostalgistViewer = {
	name: 'NostalgistViewer',

	props: {
		active: {
			type: Boolean,
			default: false,
		},
		basename: {
			type: String,
			required: true,
		},
		// file path relative to the user folder
		filename: {
			type: String,
			required: true,
		},
		// alternative file source URL, used on public share pages
		source: {
			type: String,
			default: undefined,
		},
		mime: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			instance: null,
			started: false,
			errorMessage: null,
			detachToolbar: null,
		}
	},

	watch: {
		active(isActive) {
			if (isActive && !this.started) {
				this.start()
			} else if (this.instance !== null) {
				isActive ? this.instance.resume() : this.instance.pause()
			}
		},
	},

	mounted() {
		// The emulator needs the arrow keys, so no swiping to other files.
		this.$emit('update:canSwipe', false)
		if (this.active) {
			this.start()
		}
	},

	beforeDestroy() {
		this.detachToolbar?.()
		try {
			this.instance?.exit()
		} catch (error) {
			console.error('Nostalgist failed to exit', error)
		}
	},

	methods: {
		async start() {
			this.started = true
			try {
				const system = systemForFile(this.basename, this.mime)
				if (system === null) {
					throw new Error(t('nostalgist', 'Unsupported ROM type: {file}', { file: this.basename }))
				}
				this.instance = await launchRom({
					element: this.$refs.canvas,
					romUrl: this.source ?? davUrl(this.filename),
					romName: this.basename,
					core: coreForSystem(system.id, settings),
					settings,
				})
				this.detachToolbar = attachToolbar({
					container: this.$el,
					instance: this.instance,
					romPath: this.filename,
					romName: this.basename,
				})
			} catch (error) {
				console.error('Nostalgist failed to start', error)
				this.errorMessage = t('nostalgist', 'Could not start the emulator: {error}', { error: error.message })
			}
			this.$emit('update:loaded', true)
		},
	},

	render(h) {
		const child = this.errorMessage !== null
			? h('p', { style: { color: '#fff' } }, this.errorMessage)
			: h('canvas', {
				ref: 'canvas',
				style: {
					width: '100%',
					height: '100%',
					objectFit: 'contain',
					backgroundColor: '#000',
				},
			})
		return h('div', {
			class: 'nostalgist-viewer',
			style: {
				width: '100%',
				height: '100%',
				display: 'flex',
				alignItems: 'center',
				justifyContent: 'center',
			},
		}, [child])
	},
}

function register() {
	if (window.OCA?.Viewer?.registerHandler === undefined) {
		return false
	}
	window.OCA.Viewer.registerHandler({
		id: 'nostalgist',
		group: null,
		mimes: romMimes(),
		component: NostalgistViewer,
	})
	return true
}

if (!register()) {
	document.addEventListener('DOMContentLoaded', register)
}
