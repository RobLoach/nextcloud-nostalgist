# Development

Building the app, what lives where, and what it stores.

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

## Project structure

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

## HTTP endpoints

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

## What the app stores

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
