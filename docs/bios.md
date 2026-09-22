# BIOS files

The systems that ask for one, and how to give it to them.

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
