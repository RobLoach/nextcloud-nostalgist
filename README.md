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

4. The Nostalgist page itself lists the games found in your games library
   folder (`/Games` by default, configurable in the personal settings), so
   you can start playing from there too.

### Cores

The default libretro core for each supported system ships with the app:

| System | Core |
| --- | --- |
| NES | fceumm |
| SNES | snes9x |
| Game Boy / Game Boy Color | gambatte |
| Game Boy Advance | mgba |
| Sega Genesis / Mega Drive, Master System, Game Gear | genesis_plus_gx |
| Sega 32X | picodrive |
| PC Engine / TurboGrafx-16 | mednafen_pce_fast |
| Atari Lynx | handy |
| Neo Geo Pocket | mednafen_ngp |
| WonderSwan | mednafen_wswan |
| Virtual Boy | mednafen_vb |
| Vectrex | vecx |
| ColecoVision | gearcoleco |

To also extract the alternative cores (selectable in the personal settings)
from
[retroarch-emscripten-build](https://github.com/arianrhodsandlot/retroarch-emscripten-build),
run:

```sh
npm run cores
```

### Settings

Personal settings → Nostalgist lets you configure the player: which libretro
core is used per system, the games library folder, rewind, video smoothing,
fast-forward ratio, and global input capture.

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
