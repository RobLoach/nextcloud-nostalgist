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

3. Open a ROM (for example a `.nes` file) in the Files app and it starts
   playing right in the file viewer. Files are recognized both by mimetype
   and by file extension, with a "Play with Nostalgist" action available in
   the file's menu.

### Cores

Only the NES core (`fceumm`) ships with the repository to keep it small. To
extract every core from
[retroarch-emscripten-build](https://github.com/arianrhodsandlot/retroarch-emscripten-build)
and unlock the other systems, run:

```sh
npm run cores
```

Supported systems: NES, SNES, Game Boy, Game Boy Color, Game Boy Advance,
Sega Genesis / Mega Drive, Master System, Game Gear, 32X, PC Engine,
Atari Lynx, Neo Geo Pocket, WonderSwan, Virtual Boy, Vectrex, and
ColecoVision.

### Settings

Personal settings → Nostalgist lets you configure the player: which libretro
core is used per system, rewind, video smoothing, fast-forward ratio, and
global input capture.

### Existing files

ROMs uploaded before the app was enabled keep their generic mimetype until
the mimetype repair step runs (on install and upgrades). It can also be run
manually:

```sh
occ maintenance:repair
occ maintenance:mimetype:update-db
```

They will still open through the file action either way, matched by their
file extension.

## Content Security Policy

The app ships its own Content Security Policy allowing WebAssembly compilation
(`wasm-unsafe-eval`) and `blob:` script/worker sources, which Nostalgist.js
needs to run the RetroArch cores. No changes to the Nextcloud server are
required.

## Development

```sh
npm install
npm run build
```

## Credits

- https://www.svgrepo.com/svg/255536/game-controller-gamepad
- https://github.com/arianrhodsandlot/nostalgist
- https://github.com/arianrhodsandlot/retroarch-emscripten-build
