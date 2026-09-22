# Performance and security

What the app does to stay quick, and what it asks of the browser.

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
