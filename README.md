# NextCloud Nostalgist

Run emulators of retro consoles directly in NextCloud via [Nostalgist.js](https://nostalgist.js.org/).

[![Screenshot of NextCloud Nostalgist](img/screenshot-thumbnail.jpg)](img/screenshot.png)

## Usage

1. Download the app
	```sh
	cd /path/to/nextcloud/apps
	git clone https://github.com/robloach/nextcloud-nostalgist.git nostalgist
	```

2. Enable the Nostalgist app

## Content Security Policy

The app ships its own Content Security Policy allowing WebAssembly compilation
(`wasm-unsafe-eval`) and `blob:` script/worker sources, which Nostalgist.js
needs to run the RetroArch cores. No changes to the Nextcloud server are
required.

## TODO

- [ ] Make it work

## Credits

- https://www.svgrepo.com/svg/255536/game-controller-gamepad
- https://github.com/arianrhodsandlot/nostalgist
- https://github.com/arianrhodsandlot/retroarch-emscripten-build
