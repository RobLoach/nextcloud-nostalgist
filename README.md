# NextCloud Arcade

Play retro console games directly in NextCloud, through
[Nostalgist.js](https://nostalgist.js.org/) and the RetroArch libretro cores
compiled to WebAssembly. Nothing is emulated on the server: ROMs are streamed
from your files and run in the browser.

[![Screenshot of NextCloud Arcade](img/screenshot-thumbnail.jpg)](img/screenshot.png)

## Features

- Plays ROMs straight from the Files app, in the file viewer, with box art
  as their preview.
- A games library page with grid, list and table views.
- Three save state slots per game plus an automatic one, each with a
  screenshot, unique per user.
- In-game battery saves (SRAM) synchronized automatically.
- Player controls: pause, restart, mute, fast-forward, the RetroArch menu,
  screenshots, fullscreen, and a virtual gamepad on touch devices.
- Zipped ROMs, extracted in the browser.
- Thumbnails for your games, matched from a folder of images, downloaded
  from the libretro thumbnail server, or taken from their own screenshots
  and save states — and matched on the name the cartridge gives itself when
  the file name says nothing.
- Favorites shared with the Files app -- the same star -- and how long
  each game was played.
- Works on publicly shared files and folders.

## Installation

Nextcloud 34 or 35, on PHP 8.3 or newer. A browser with WebAssembly, which
is every current one.

It needs the **Files** app, for the games themselves, the favorites and the
mimetypes, and the **Viewer** app, to play a ROM from the Files app. Both are
always-enabled apps of Nextcloud, so there is nothing to install and no way
to turn them off. That is stated here rather than declared in
`appinfo/info.xml`: the app store schema has elements for PHP, databases,
libraries, commands, architectures and the Nextcloud version, and none for
depending on another app.

1. Download the app

	```sh
	cd /path/to/nextcloud/apps
	git clone https://github.com/robloach/nextcloud-arcade.git arcade
	```

2. Enable the Arcade app

Updating is `git pull` in that folder: the built JavaScript and the emulator
cores are committed, so nothing needs to be built on the server.

## Usage

### From the Files app

Open a ROM (for example a `.nes` file) and it starts playing in the file
viewer, which is what the app registers itself with. ROMs are recognized by
their mimetype, which the app teaches Nextcloud for every system it runs.

The file menu has a **Play with Arcade** entry as well, for everything the
viewer cannot take: a zipped ROM, whose `.zip` says nothing about what is
inside, and a ROM whose mimetype Nextcloud has not learned yet. It goes by
the file extension and by the folder the game is stored in, the way the
Arcade page does, and opens the game there.

The system of a zipped game is detected from the file inside the archive, or
from the folder it is stored in. Short names, spelled out names and No-Intro
platform names all work, with or without the maker in front and with a word
like "ROMs" hung off the end, so `Games/SNES/NHL 96.zip`,
`Games/Super Nintendo Games/NHL 96.zip` and
`Games/Nintendo - Super Nintendo Entertainment System/NHL 96.zip` are all
recognized as Super Nintendo.

ROMs uploaded before the app was enabled keep their generic mimetype until
the mimetype repair step runs, which happens on install and on upgrades. It
can also be run manually:

```sh
occ maintenance:repair
occ maintenance:mimetype:update-db
```

Until then they open from the Arcade page, which goes by the file
extension.

ROMs uploaded while the app is enabled are filed correctly as they arrive.
That needs the app to be loaded during the upload itself, and `remote.php`,
which every upload goes through, loads only apps that declare themselves a
`filesystem` app — so Arcade declares it. Nextcloud does not let apps of that
type be enabled for selected groups, so Arcade is enabled for everybody on
the instance or for nobody.

Cartridges carry the name the console shows, and the app reads it: Game Boy,
Game Boy Advance, Super Nintendo and Mega Drive headers all say what the game
is called, and Super Nintendo and Mega Drive say which region it was sold in.
That name is what box art is matched on when the file name finds nothing, so
a ROM called `rom1.gb` still gets the cover of Super Mario Land. It is read
once, in the background, and filed against the file by Nextcloud, along with
the MD5 of the ROM, if the upload brought one: desktop clients send a
checksum, browsers do not. Working one out instead means reading the whole
file, so an administrator has to ask for that. All four are searchable, and
a ROM with nothing to read — an NES cartridge carries no title, and without
checksums there is nothing else to look for — is never opened at all.

Nextcloud reads a file's metadata when the file is written, so ROMs that were
already there when the app arrived have never been asked. The rescan button
of the games library asks for them: it queues a background job that walks the
library fifty games at a time, queuing the reading of each file behind it, and
comes back for the rest until there is nothing left to ask. Nothing of it
happens while the page waits.

Games are given their box art as their Nextcloud preview, so a folder of
ROMs looks like a shelf of games in the Files app. The picture is the one
already in your thumbnails folder, only scaled; a game without one keeps the
icon of its mimetype.

### From the Arcade page

The app's own page lists the games in your library folder (`/Games` by
default). Three views are available and the choice is remembered:

| View | Shows |
| --- | --- |
| Grid | Large thumbnails, the default |
| List | Compact rows with small thumbnails |
| Table | Sortable columns: name, system, size, modified |

Favorites and the games played last are shown in rows above the library, so
picking up where you left off is one click, whether the game was started
here or from the Files app. The star on a game card is the same star as the
one in the Files app: starring a game here shows it in the Files favorites,
and a ROM starred in Files is a favorite here. Because Files keeps it by
file id, a game stays a favorite when it is renamed or moved. The favorites
row shows the games of your library folder; a ROM starred somewhere else is
still starred, it just has no place in the library to be shown in. How long
each game was played is kept by the app, alongside.

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

The keyboard works the controller: the arrow keys, X and Z for A and B, S
and A for X and Y, Q and W for the shoulders, Enter for start and the right
shift for select. Space pauses, T fast-forwards, F goes fullscreen, O opens
the save states, P takes a screenshot, and Escape closes whatever panel is
open. All of them can be changed in the personal settings, and a key that
works a button of the controller is left to the game.

A control bar overlays the bottom of the player with pause/resume, restart,
a save state menu, mute, fast-forward, the RetroArch menu (core options,
control remapping and more), screenshot, and fullscreen. On touch devices a
virtual gamepad is overlaid too — a D-pad with diagonals, A/B/X/Y, L/R,
Start and Select — toggleable from the control bar.

Next to the screenshot button, a gallery button opens every screenshot taken
of the game, newest first, where they can be opened in the Files app or
deleted. It appears once there is something to show.

The save state menu has three slots per game, each with a screenshot
thumbnail and a timestamp; saving or loading a slot closes the menu and
returns to the game. Above them sits the Auto slot, which the player writes
itself: when the player is closed, and at an interval while playing if one
is set. A game can always be picked up where it was left that way, and it
is the state the player offers to continue from next time — or loads
straight away, if that is turned on in the settings.

States are stored per user and per game on the server, so every NextCloud
user has their own saves, even for a shared ROM. A game is known by the id
Nextcloud gave the file, so renaming a ROM or moving it to another folder
keeps its saves, its battery save and how long it was played — the folder in
the saves folder is brought along to the new name.

In-game battery saves (SRAM) are restored when a game starts, and uploaded
every minute and when the page closes, so progress saved through a game's own
save system survives. When a game has save states, the player offers to
continue from the most recent one at launch.

Save states and SRAM are not available on public share links, since there is
no user to store them for.

### Settings

Personal settings → Arcade:

| Setting | Default | Description |
| --- | --- | --- |
| Smooth video filtering | Off | Bilinear filtering instead of sharp pixels |
| Pixel-perfect scaling | Off | Scale by whole pixels, with borders |
| Capture input globally | On | Send gamepad and keyboard input to the game while playing |
| Pause in the background | On | Stop the game while its tab is hidden |
| Save when closing | On | Write the Auto save state when the player is closed |
| Continue on start | Off | Load the latest save as a game starts, without asking |
| Save every | Never | Write the Auto save state while playing, from 30 seconds to 10 minutes |
| Fast-forward speed | 3× | Speed of the fast-forward button, 1× to 5× |
| Volume | 0 dB | Gain in decibels, -20 to 10 |
| Audio latency | 64 ms | Raise it if the sound crackles |
| Games library folder | `/Games` | Scanned for the games library page |
| Saves folder | empty | Save states and battery saves, in your own files. Without one, a game cannot be saved |
| Screenshots folder | empty | Where the screenshot button saves images |
| Thumbnails folder | empty | Images used as game thumbnails |
| System folder | empty | Where BIOS files are read from |
| Picture shown for each system | Box art | Box art, title screen, screenshot or logo, per system |
| Controls | see above | The key of every button and of the player itself |

Folder settings have a browse button that opens the NextCloud file picker.
The thumbnails folder also has a button that goes looking for the box art of
the games that have none; it runs as a background job, so it carries on
after the page is closed, and says how it went when the page is opened
again.

Administration settings → Arcade holds what is the same for everybody:

| Setting | Default | Description |
| --- | --- | --- |
| Folder defaults | as above | What new users start with, and each can still change |
| Look up box art | On | Whether the server may ask the libretro thumbnail server at all |
| Work out ROM checksums | Off | Whether to read a whole ROM to hash it, when the upload brought no checksum |
| Games listed at most | 5000 | How many games one library scan lists |
| Folders deep at most | 6 | How far into a library folder the scan goes |
| Seconds a scan is kept | 86400 | How long the result of a scan is cached |
| Core options | Core default | Options of the emulator cores, which users cannot change |
| Picture each system starts out shown with | Box art | The default for the personal setting of the same name |

An option of a core belongs to the core rather than to whoever is playing,
so those are the administrator's alone. Looking up box art is the only thing
in the app that has the server itself fetch from the internet, which is why
it can be turned off for the instance: with it off, the button is gone from
the personal settings and the endpoint refuses. The kind of picture a system
is shown with is a matter of taste, so the administration page only sets
where everybody starts.

Box art, title screen, screenshot and logo are the `Named_*` folders a
picture is taken from, so a system shown with title screens is matched
against `Named_Titles` rather than `Named_Boxarts`.

### BIOS files

A few systems ask for a BIOS file, and the ones that do look for it by the
name their core expects. Point the system folder at a folder holding them
and they are handed to the emulator as a game starts:

| System | File |
| --- | --- |
| ColecoVision | `colecovision.rom` |
| PC Engine CD | `syscard3.pce` |
| Sega 32X | `32X_G_BIOS.BIN`, `32X_M_BIOS.BIN`, `32X_S_BIOS.BIN` |
| Game Boy, Game Boy Color | `gb_bios.bin`, `gbc_bios.bin` |
| Game Boy Advance | `gba_bios.bin` |
| Master System, Game Gear | `bios.sms`, `bios.gg` |
| Mega Drive | `bios_MD.bin` |
| Atari Lynx | `lynxboot.img` |

Only ColecoVision really needs one; for the rest the file is optional, and
a missing one is quietly left out rather than keeping a game from starting.

A BIOS is the one thing a player cannot make for themselves, so an
administrator can put one where every player reaches it, instead of every
user finding their own copy:

```sh
occ arcade:bios                      # what is asked for, and what is held
occ arcade:bios /path/to/gb_bios.bin # offer this one to everybody
occ arcade:bios --remove gb_bios.bin # take it back
```

Only the names the cores ask for are accepted, so this cannot become a
place to keep files in general. A player's own system folder comes first;
what it has not got is taken from the instance.

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
location ~ ^/apps/arcade/img/cores/ {
    types { application/wasm wasm; }
    add_header Cache-Control "public, max-age=604800";
    gzip on;
    gzip_types application/wasm application/javascript;
}
```

A memory cache (Redis or APCu) makes the games library page faster, since
that is where the scanned library is cached.

The script the Files app loads carries no more than what it takes to
register the player with the file viewer; the emulator itself is fetched
the first time a game is opened. The core of a system is asked for as soon
as a game starts, rather than after the ROM has been read, so the two
downloads overlap.

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
npm run l10n      # collect the strings to translate

composer install
composer test     # the unit test suite
composer psalm    # static analysis
```

Every push and pull request runs the same through GitHub Actions: PHP
linting on 8.3 and 8.4, the test suite, static analysis, the JavaScript
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
lib/Preview/                Box art as the Nextcloud preview of a ROM
lib/Migration/              Repair steps: mimetypes on install, caches on disable
lib/Command/                The occ cleanup and uninstall commands
lib/BackgroundJob/          Looking for box art and reading ROMs, away from the browser
lib/Controls.php            What the keyboard does, and what it does by default
lib/RomHeader.php           The name a cartridge gives itself
build/extract-l10n.mjs      Collects the strings to translate
l10n/                       Translations, as Nextcloud reads them
lib/Service/                Settings, library, save states, thumbnails, history
lib/Settings/               Personal settings section
src/main.js                 The app page: player or games library
src/library.js              Games library views and pagination
src/viewer.js               The Viewer handler, loaded on every Files page
src/fileaction.js           The "Play with Arcade" entry in the file menu
src/session.js              Everything a running game needs, loaded on demand
src/player.js               Launcher, ROM fetching, zip extraction, SRAM
src/toolbar.js              Player control bar
src/panels/                 Save states, screenshots and resume panels
src/api.js                  Save state endpoints shared by the panels
src/icons.js                The icons of the player
src/touch.js                Virtual gamepad
src/settings.js             Personal settings page
src/systems.js              System lookup shared by the frontend
src/keys.js                 Keys, as the browser and RetroArch each name them
tests/unit/                 Unit tests of the logic that has no dependencies
templates/                  App page and settings markup
css/player.css              The player overlay, also loaded inside Files
img/cores/                  Emulator cores
```

### HTTP endpoints

All of them are user-scoped and require a session.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/apps/arcade/` | The app page, `?file=` plays a game |
| GET | `/apps/arcade/library` | Games: `offset`, `limit`, `sort`, `order`, `search`, `system`, `refresh` |
| GET/POST | `/apps/arcade/settings` | Personal settings |
| GET | `/apps/arcade/states` | Save state slots of a game |
| GET/POST/DELETE | `/apps/arcade/state` | A save state slot |
| GET/POST | `/apps/arcade/state/thumbnail` | The screenshot of a slot |
| GET/POST | `/apps/arcade/sram` | The in-game battery save |
| POST | `/apps/arcade/recent` | Remember a game as played, and for how long |
| POST | `/apps/arcade/favorite` | Make a game a favorite, or stop |
| GET/POST | `/apps/arcade/thumbnails/fetch` | Ask for missing box art, and how it went |
| GET/DELETE | `/apps/arcade/screenshots` | The screenshots of a game |

Save states and battery saves are removed along with the game they belong
to, and with the user they belong to — but a game deleted into the trash
keeps them, since it can be restored, with the same file id and the same
name. They go when the trash lets go of it. `occ arcade:cleanup` sweeps up
what event listeners cannot catch, such as a whole folder of games deleted
in one go; `--dry-run` reports without removing. States written by versions before
0.14 live in one flat folder instead of one per user; they are still read,
and are cleaned up when their game is deleted.

### What the app stores

Outside of the files of a user, the app writes:

| Where | What |
| --- | --- |
| `oc_preferences` | Personal settings, recently played, how long each game was played (by file id), and how a box art run went |
| `oc_appconfig` | Core options, thumbnail types, and the folder defaults of the instance |
| `oc_jobs` | A queued box art lookup, while one is running |
| `oc_mimetypes`, `oc_filecache` | The ROM mimetypes, and the files given them |
| `oc_files_metadata` | The system, title, region and MD5 of each ROM, by file id |
| `appdata_*/arcade/` | Save states and battery saves, for as long as no saves folder is set |

Favorites are not in that list: they are the favorites of the Files app,
kept in its own tables under the file id. `occ arcade:uninstall` leaves
them alone, as it leaves any other file of a user alone.

Nextcloud removes the code of an app and nothing else, so `occ app:remove`
would leave all of that behind. Run **`occ arcade:uninstall` first**: it puts
the ROMs back to `application/octet-stream`, drops the settings of every user
and of the instance, removes the save states kept by the app, and cancels
queued work. `--dry-run` reports without removing, `--force` skips the
question. Games, saves, screenshots and thumbnails in the folders of a user
are their own files, and are left alone.

Disabling the app does not do any of that. Nextcloud runs uninstall repair
steps on disable, and it disables apps by itself when a server upgrade leaves
them behind — a library wiped by an upgrade would be a poor welcome back — so
the step that runs then only drops the queued lookups and the caches.

## Credits

- https://www.svgrepo.com/svg/255536/game-controller-gamepad
- https://github.com/arianrhodsandlot/nostalgist
- https://github.com/arianrhodsandlot/retroarch-emscripten-build
