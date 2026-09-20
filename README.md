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
   the file's menu. Zipped ROMs work too, through the same menu action on
   `.zip` files — the archive is extracted in the browser, and the system
   is detected from the file inside or from the folder the game is stored
   in (`Games/SNES/NHL 96.zip` is recognized as Super Nintendo).

4. The Nostalgist page itself lists the games found in your games library
   folder (`/Games` by default, configurable in the personal settings), so
   you can start playing from there too.

5. Games shared through public links play too, when browsing a shared
   folder with the file viewer.

### Player controls

A control bar overlays the bottom of the player with pause/resume, restart,
a save state menu, mute, fast-forward, the RetroArch menu (core options,
control remapping and more), screenshot, and fullscreen.

The save state menu has six slots per game, each with a screenshot
thumbnail and timestamp. States are stored per user and per game on the
server, so every Nextcloud user has their own saves, even for a shared ROM.
Save states are not available on public share links.

Screenshots are downloaded by default, or saved into a Nextcloud folder if
one is configured in the personal settings.

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
core is used per system, the games library folder, a thumbnails folder
(images matched by file name and subfolder, so `Thumbs/NES/Mario.png` is
the thumbnail of `Games/NES/Mario.nes`), a screenshots folder, video
smoothing, fast-forward ratio, and global input capture.

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

## Performance

The emulator cores are WebAssembly files of a few megabytes that the browser
downloads and compiles on every launch. On Apache, the app ships an
`.htaccess` in `img/cores/` that sets the `application/wasm` mimetype
(streaming compilation), a week-long `Cache-Control`, and gzip compression —
no configuration needed.

On nginx, add the equivalent to the Nextcloud server block:

```nginx
location ~ ^/apps/nostalgist/img/cores/ {
    types { application/wasm wasm; }
    add_header Cache-Control "public, max-age=604800";
    gzip on;
    gzip_types application/wasm application/javascript;
}
```

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
