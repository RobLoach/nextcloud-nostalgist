# Emulator cores

One core per system, what each one is, and where it comes from.

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
| PlayStation | pcsx_rearmed |

To extract them again from the submodule, run:

```sh
npm run cores
```

They come from
[retroarch-emscripten-build](https://github.com/arianrhodsandlot/retroarch-emscripten-build),
which is a git submodule of this repository.
