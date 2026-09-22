# Thumbnails

Where the pictures of your games come from, and how they are matched.

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
