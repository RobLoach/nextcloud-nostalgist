import { Nostalgist } from 'nostalgist'

let image_path = '/apps/nostalgist/img/app.svg';

Nostalgist.configure({
	element: document.querySelector('.nostalgist-canvas'),
	resolveRom(rom) {
		return image_path.replace('app.svg', rom);
	},
	resolveCoreJs(core) {
		return image_path.replace('app.svg', `cores/${core}_libretro.js`);
	},
	resolveCoreWasm(core) {
		return image_path.replace('app.svg', `cores/${core}_libretro.wasm`);
	},
	retroarchConfig: {
		rewind_enable: false
	}
});

await Nostalgist.launch({
	core: 'fceumm',
	rom: 'flappybird.nes'
});
