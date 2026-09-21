# NextCloud Nostalgist

Play retro console games directly in NextCloud, through
[Nostalgist.js](https://nostalgist.js.org/) and the RetroArch libretro cores
compiled to WebAssembly. Nothing is emulated on the server: ROMs are streamed
from your files and run in the browser.

[![Screenshot of NextCloud Nostalgist](img/screenshot-thumbnail.jpg)](img/screenshot.png)

## Features

- Plays ROMs straight from the Files app, in the file viewer.
- A games library page with grid, list and table views.
- Three save state slots per game plus an automatic one, each with a
  screenshot, unique per user.
- In-game battery saves (SRAM) synchronized automatically.
- Player controls: pause, restart, mute, fast-forward, the RetroArch menu,
  screenshots, fullscreen, and a virtual gamepad on touch devices.
- Zipped ROMs, extracted in the browser.
- Thumbnails for your games, matched from a folder of images, or taken
  from their own screenshots and save states.
- Works on publicly shared files and folders.

## Installation

1. Download the app

	```sh
	cd /path/to/nextcloud/apps
	git clone https://github.com/robloach/nextcloud-nostalgist.git nostalgist
	```

2. Enable the Nostalgist app

Updating is `git pull` in that folder: the built JavaScript and the emulator
cores are committed, so nothing needs to be built on the server.

## Usage

### From the Files app

Open a ROM (for example a `.nes` file) and it starts playing in the file
viewer. Files are recognized both by mimetype and by file extension, and a
"Play with Nostalgist" action is available in the file's menu.

Zipped ROMs work through the same menu action on `.zip` files. The system is
detected from the file inside the archive, or from the folder the game is
stored in — short names and No-Intro platform names both work, so
`Games/SNES/NHL 96.zip` and
`Games/Nintendo - Super Nintendo Entertainment System/NHL 96.zip` are both
recognized as Super Nintendo.

ROMs uploaded before the app was enabled keep their generic mimetype until
the mimetype repair step runs, which happens on install and on upgrades. It
can also be run manually:

```sh
occ maintenance:repair
occ maintenance:mimetype:update-db
```

They still open through the file action either way, matched by extension.

### From the Nostalgist page

The app's own page lists the games in your library folder (`/Games` by
default). Three views are available and the choice is remembered:

| View | Shows |
| --- | --- |
| Grid | Large thumbnails, the default |
| List | Compact rows with small thumbnails |
| Table | Sortable columns: name, system, size, modified |

The games played last are shown in a row above the library, so picking up
where you left off is one click, whether the game was started here or from
the Files app.

Games can be filtered by name and by system, and large libraries are paged
(24 to 240 games per page). Filtering, sorting and paging all happen over
the whole library, not just the page being shown. Pages are kept for the
tab, so switching views, paging back and returning from a game are drawn
from what was already loaded and revalidated in the background.

The scan of the library folder is cached and keyed on the folder's ETag, so
it is only walked again when something in it changes; the refresh button in
the header forces a rescan. Up to 5000 games and six folder levels deep are
listed.

### Player controls

A control bar overlays the bottom of the player with pause/resume, restart,
a save state menu, mute, fast-forward, the RetroArch menu (core options,
control remapping and more), screenshot, and fullscreen. On touch devices a
virtual gamepad is overlaid too — a D-pad with diagonals, A/B/X/Y, L/R,
Start and Select — toggleable from the control bar.

A screenshots button opens a gallery of every screenshot taken of the game,
newest first, where they can be opened in the Files app or deleted. It needs
a screenshots folder to be set, since that is where they are kept.

The save state menu has three slots per game, each with a screenshot
thumbnail and a timestamp; saving or loading a slot closes the menu and returns to the
game. Closing the player with its own button writes a seventh, automatic
state first, so a game can always be picked up where it was left; it is the
one the player offers to continue from next time. States are stored per user and per game on the server, so every
NextCloud user has their own saves, even for a shared ROM.

In-game battery saves (SRAM) are restored when a game starts, and uploaded
every minute and when the page closes, so progress saved through a game's own
save system survives. When a game has save states, the player offers to
continue from the most recent one at launch.

Save states and SRAM are not available on public share links, since there is
no user to store them for.

### Settings

Personal settings → Nostalgist:

| Setting | Default | Description |
| --- | --- | --- |
| Smooth video filtering | Off | Bilinear filtering instead of sharp pixels |
| Pixel-perfect scaling | Off | Scale by whole pixels, with borders |
| Capture input globally | On | Send gamepad and keyboard input to the game while playing |
| Pause in the background | On | Stop the game while its tab is hidden |
| Save when closing | On | Write a save state when the player is closed |
| Fast-forward speed | 3× | Speed of the fast-forward button, 1× to 5× |
| Volume | 0 dB | Gain in decibels, -20 to 10 |
| Audio latency | 64 ms | Raise it if the sound crackles |
| Games library folder | `/Games` | Scanned for the games library page |
| Saves folder | empty | Save states and their screenshots, in your own files |
| Screenshots folder | empty | Where the screenshot button saves images |
| Thumbnails folder | empty | Images used as game thumbnails |
| Core options | core defaults | Options of the emulator cores, per core |

Folder settings have a browse button that opens the NextCloud file picker,
and each core has a button putting all of its options back to the defaults.

Thumbnails are matched by file name. With a thumbnails folder of `Thumbs`,
`Games/NES/Mario.nes` uses `Thumbs/NES/Mario.png` and falls back to
`Thumbs/Mario.png`. PNG, JPEG, WebP and GIF are supported.

Platform folders work too, both with the libretro-thumbnails
`Named_*` subfolders — so a pack from
[libretro-thumbnails](https://github.com/libretro-thumbnails) can be dropped
in unchanged — and with the images straight in the platform folder:

```
Thumbs/Nintendo - Nintendo Entertainment System/Named_Boxarts/Mario.png
Thumbs/Nintendo - Nintendo Entertainment System/Named_Titles/Mario.png
Thumbs/Nintendo - Nintendo Entertainment System/Named_Snaps/Mario.png
Thumbs/Nintendo - Nintendo Entertainment System/Named_Logos/Mario.png
Thumbs/Nintendo - Nintendo Entertainment System/Mario.png
```

Platform folders are matched by their No-Intro name as above, or by a short
name like `SNES`. The library picks the image that suits the size it is
drawing: box art in the grid, logos in the list and table, falling back to
title screens and screenshots.

A game with no image of its own shows itself instead: the most recent of
the screenshots taken of it and the screenshots of its save states. That
needs no configuration, and it keeps up as the game is played.

Matching is forgiving. An identical file name wins, and otherwise region and
revision tags, articles, punctuation and accents are ignored, so
`Batman Returns.zip` finds `Batman Returns (USA).png` and
`The Legend of Zelda.nes` finds `Legend of Zelda, The (USA) (Rev 1).png`.
Titles joined with "and", "+" or "&" match each other, so
`Super Mario All-Stars and Super Mario World (Europe).zip` finds
`Super Mario All-Stars + Super Mario World.png`. When several images fit,
the most widely released one is used — World before USA before Europe
before Japan. Names containing `&*/:` and friends match
the underscores libretro-thumbnails replaces them with.

With a saves folder set, save states are written to your own files as
`Saves/Mario/Slot 1.state` with `Slot 1.png` next to it, the automatic one
as `Saves/Mario/Auto.state`, and the battery save as `Saves/Mario/Mario.srm`,
so they sync to your devices. Left empty, they
are kept in the app's internal storage instead. Switching the setting does
not move existing saves.

### Cores

One proven libretro core is used per system, and they all ship with the app,
so there is nothing to choose or install. Each core has its own options in
the personal settings — palettes, region, sprite limits, video filters and
the like — which apply to every game that core runs. Left alone, the core's
own defaults are used.

| System | Core |
| --- | --- |
| NES | fceumm |
| SNES | snes9x |
| Game Boy / Game Boy Color | gambatte |
| Game Boy Advance | mgba |
| Genesis / Mega Drive, Master System, Game Gear | genesis_plus_gx |
| Sega 32X | picodrive |
| PC Engine / TurboGrafx-16 | mednafen_pce_fast |
| Atari Lynx | handy |
| Neo Geo Pocket | mednafen_ngp |
| WonderSwan | mednafen_wswan |
| Virtual Boy | mednafen_vb |
| Vectrex | vecx |
| ColecoVision | gearcoleco |

To extract them again from the submodule, run:

```sh
npm run cores
```

They come from
[retroarch-emscripten-build](https://github.com/arianrhodsandlot/retroarch-emscripten-build),
which is a git submodule of this repository.

## Performance

The emulator cores are WebAssembly files of a few megabytes that the browser
downloads and compiles on every launch. On Apache, the app ships an
`.htaccess` in `img/cores/` that sets the `application/wasm` mimetype
(streaming compilation), a week-long `Cache-Control`, and gzip compression —
no configuration needed.

On nginx, add the equivalent to the NextCloud server block:

```nginx
location ~ ^/apps/nostalgist/img/cores/ {
    types { application/wasm wasm; }
    add_header Cache-Control "public, max-age=604800";
    gzip on;
    gzip_types application/wasm application/javascript;
}
```

A memory cache (Redis or APCu) makes the games library page faster, since
that is where the scanned library is cached.

## Content Security Policy

The app adds the allowances Nostalgist.js needs — WebAssembly compilation
(`wasm-unsafe-eval`) and `blob:` sources for scripts, workers, frames,
connections, images and media — to the policy of every page, since the player
also runs inside the Files app. No changes to the NextCloud server are
required.

## Development

```sh
npm install
npm run build     # production bundles into js/
npm run watch     # rebuild on change
npm run cores     # extract the cores from the submodule

composer install
composer test     # the unit test suite
composer psalm    # static analysis
```

Every push and pull request runs the same through GitHub Actions: PHP
linting on 8.2 to 8.4, the test suite, static analysis, the JavaScript
build, and a check that `appinfo/info.xml` validates against the app store
schema and agrees with `package.json` on the version.

The built bundles in `js/` are committed, so rebuild and commit them along
with any change to `src/`.

### Project structure

```
appinfo/info.xml            App metadata, repair steps, settings registration
lib/CoreMap.php             Systems: extensions, mimetypes, cores, folder aliases
lib/CoreOptions.php         The core options offered in the settings
lib/AppInfo/Application.php Mimetype registration and event listeners
lib/Controller/             Page, library, settings and save state endpoints
lib/Listener/               Files and Viewer script loading, Content Security Policy
lib/Migration/              Mimetype repair step
lib/Service/                Settings, save states, thumbnails, recently played
lib/Settings/               Personal settings section
src/main.js                 The app page: player or games library
src/library.js              Games library views and pagination
src/player.js               Shared launcher, ROM fetching, zip extraction, SRAM
src/toolbar.js              Player control bar
src/panels/                 Save states, screenshots and resume panels
src/api.js                  Save state endpoints shared by the panels
src/icons.js                The icons of the player
src/touch.js                Virtual gamepad
src/files.js                Files app actions
src/viewer.js               Viewer handler
src/settings.js             Personal settings page
src/systems.js              System lookup shared by the frontend
tests/unit/                 Unit tests of the logic that has no dependencies
templates/                  App page and settings markup
css/player.css              The player overlay, also loaded inside Files
img/cores/                  Emulator cores
```

### HTTP endpoints

All of them are user-scoped and require a session.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/apps/nostalgist/` | The app page, `?file=` plays a game |
| GET | `/apps/nostalgist/library` | Games: `offset`, `limit`, `sort`, `order`, `search`, `system`, `refresh` |
| GET/POST | `/apps/nostalgist/settings` | Personal settings |
| GET | `/apps/nostalgist/states` | Save state slots of a game |
| GET/POST/DELETE | `/apps/nostalgist/state` | A save state slot |
| GET/POST | `/apps/nostalgist/state/thumbnail` | The screenshot of a slot |
| GET/POST | `/apps/nostalgist/sram` | The in-game battery save |
| POST | `/apps/nostalgist/recent` | Remember a game as played |
| GET/DELETE | `/apps/nostalgist/screenshots` | The screenshots of a game |

Save states and battery saves are removed along with the game they belong
to, and with the user they belong to. States written by versions before
0.14 live in one flat folder instead of one per user; they are still read,
and are cleaned up when their game is deleted.

## Credits

- https://www.svgrepo.com/svg/255536/game-controller-gamepad
- https://github.com/arianrhodsandlot/nostalgist
- https://github.com/arianrhodsandlot/retroarch-emscripten-build
