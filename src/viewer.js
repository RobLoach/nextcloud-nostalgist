import { translate as t } from '@nextcloud/l10n'
import { isPlayable, romMimes } from './systems.js'

/**
 * The player, as the Viewer app shows it.
 *
 * This script is loaded on every page of the Files app, so it carries no
 * more than the shell: the emulator, a few hundred kilobytes of it, is
 * fetched the first time a game is opened.
 *
 * Written as a plain options object with a render function so it works with
 * the Viewer's own Vue instance without needing a template compiler, and so
 * the Viewer can apply its own mixin to it.
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
			started: false,
			errorMessage: null,
			stopSession: null,
		}
	},

	watch: {
		active(isActive) {
			if (isActive && !this.started) {
				this.start()
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

	// Vue 2 calls this beforeDestroy, Vue 3 beforeUnmount; both are here so
	// the handler keeps working if the Viewer ever moves on.
	beforeDestroy() {
		this.stopSession?.()
	},

	methods: {
		beforeUnmount() {
			this.beforeDestroy()
		},

		async start() {
			this.started = true
			try {
				if (!isPlayable(this.basename, this.mime)) {
					throw new Error(t('nostalgist', 'Unsupported ROM type: {file}', { file: this.basename }))
				}
				const { startSession } = await import(/* webpackChunkName: 'player' */ './session.js')
				this.stopSession = await startSession({
					canvas: this.$refs.canvas,
					container: this.$el,
					filename: this.filename,
					basename: this.basename,
					source: this.source,
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
