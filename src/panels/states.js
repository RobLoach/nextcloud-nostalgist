import { translate as t } from '@nextcloud/l10n'
import { AUTO_SLOT, api, stateUrl } from '../api.js'

/**
 * Build the save states panel.
 *
 * @param {object} options options
 * @param {import('nostalgist').Nostalgist} options.instance the running emulator
 * @param {string} options.romPath path identifying the game
 * @param {Function} options.flash shows a status message
 * @param {Function} options.onDone called after a slot was saved or loaded
 * @return {{element: HTMLElement, refresh: Function, load: Function}} the panel
 */
export function createStatesPanel({ instance, romPath, flash, onDone }) {
	const element = document.createElement('div')
	element.className = 'nostalgist-states hidden'

	element.setAttribute('role', 'dialog')
	element.setAttribute('aria-modal', 'false')
	element.setAttribute('aria-label', t('nostalgist', 'Save states'))

	const heading = document.createElement('h3')
	heading.textContent = t('nostalgist', 'Save states')
	element.appendChild(heading)

	const slotsContainer = document.createElement('div')
	element.appendChild(slotsContainer)

	const smallButton = (label, onClick, disabled = false) => {
		const buttonElement = document.createElement('button')
		buttonElement.type = 'button'
		buttonElement.textContent = label
		buttonElement.disabled = disabled
		buttonElement.addEventListener('click', (event) => {
			event.stopPropagation()
			onClick()
		})
		return buttonElement
	}

	const save = async (slot) => {
		try {
			let { state, thumbnail } = await instance.saveState()
			if (thumbnail === undefined) {
				// Not every core provides a state thumbnail; fall back to a
				// plain screenshot so the slot always has one.
				thumbnail = await instance.screenshot().catch(() => undefined)
			}
			await api(stateUrl('/state', romPath, slot), {
				method: 'POST',
				headers: { 'Content-Type': 'application/octet-stream' },
				body: state,
			})
			if (thumbnail !== undefined) {
				await api(stateUrl('/state/thumbnail', romPath, slot), {
					method: 'POST',
					headers: { 'Content-Type': 'image/png' },
					body: thumbnail,
				}).catch(() => {})
			}
			flash(slot === AUTO_SLOT
				? t('nostalgist', 'Game saved')
				: t('nostalgist', 'State saved to slot {slot}', { slot }))
			onDone()
		} catch (error) {
			console.error('Could not save the state', error)
			flash(t('nostalgist', 'Could not save the state'))
		}
	}

	const load = async (slot) => {
		try {
			const response = await api(stateUrl('/state', romPath, slot))
			await instance.loadState(await response.blob())
			flash(slot === AUTO_SLOT
				? t('nostalgist', 'Game restored')
				: t('nostalgist', 'State loaded from slot {slot}', { slot }))
			onDone()
		} catch (error) {
			console.error('Could not load the state', error)
			flash(t('nostalgist', 'Could not load the state'))
		}
	}

	const remove = async (slot) => {
		try {
			await api(stateUrl('/state', romPath, slot), { method: 'DELETE' })
			await refresh()
		} catch (error) {
			console.error('Could not delete the state', error)
			flash(t('nostalgist', 'Could not delete the state'))
		}
	}

	const refresh = async () => {
		let data
		try {
			const response = await api(stateUrl('/states', romPath))
			data = await response.json()
		} catch (error) {
			console.error('Could not list the states', error)
			return
		}
		const bySlot = new Map(data.states.map((state) => [state.slot, state]))
		// The slots offered now, plus anything left in slots earlier
		// versions offered, so those saves stay reachable.
		const slots = []
		if (bySlot.has(AUTO_SLOT)) {
			slots.push(AUTO_SLOT)
		}
		for (let slot = 1; slot <= data.slots; slot++) {
			slots.push(slot)
		}
		for (const state of data.states) {
			if (state.slot > data.slots) {
				slots.push(state.slot)
			}
		}

		slotsContainer.innerHTML = ''
		for (const slot of slots) {
			const state = bySlot.get(slot)
			const row = document.createElement('div')
			row.className = 'nostalgist-states-slot'

			const thumbnail = document.createElement('img')
			thumbnail.className = 'nostalgist-states-thumbnail'
			thumbnail.alt = ''
			if (state?.hasThumbnail) {
				thumbnail.src = stateUrl('/state/thumbnail', romPath, slot) + `&mtime=${state.mtime}`
			}
			row.appendChild(thumbnail)

			const label = document.createElement('span')
			label.className = 'nostalgist-states-label'
			const when = state === undefined ? '' : new Date(state.mtime * 1000).toLocaleString()
			if (slot === AUTO_SLOT) {
				label.textContent = t('nostalgist', 'When closing — {date}', { date: when })
			} else if (slot > data.slots) {
				label.textContent = t('nostalgist', 'Slot {slot} — {date}, from an older version', {
					slot,
					date: when,
				})
			} else {
				label.textContent = state === undefined
					? t('nostalgist', 'Slot {slot} — empty', { slot })
					: t('nostalgist', 'Slot {slot} — {date}', { slot, date: when })
			}
			row.appendChild(label)

			// The automatic slot is written by the player itself, and slots
			// beyond the ones offered now are only there to be emptied.
			if (slot !== AUTO_SLOT && slot <= data.slots) {
				row.appendChild(smallButton(t('nostalgist', 'Save'), () => save(slot)))
			}
			row.appendChild(smallButton(t('nostalgist', 'Load'), () => load(slot), state === undefined))
			if (state !== undefined) {
				row.appendChild(smallButton(t('nostalgist', 'Delete'), () => remove(slot)))
			}
			slotsContainer.appendChild(row)
		}
	}

	return { element, refresh, load, save }
}
