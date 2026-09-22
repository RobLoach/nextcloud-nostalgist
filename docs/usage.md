# Using Arcade

How a game is found, opened and played, from the Files app and from the Arcade page.

## From the Files app

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

## From the Arcade page

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

## Player controls

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
