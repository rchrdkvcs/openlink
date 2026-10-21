# Check QR Code readability on the rendered exports

A Readability Check renders the current QR Code draft through the same PNG and SVG export paths at the selected export size, rasterises each result, and decodes it with a local decoder (`chillerlan/php-qrcode`). The decoded text must equal the expected encoded content exactly: the stable entry URL for linked QR Codes, or the native payload for direct-payload QR Codes. Checks never send payloads to a third-party service and never resolve a Short URL, so they cannot record visits, scans, or consume visit limits.

Per format, a check reports one of five distinct states instead of a score: readable, mismatch, unreadable, unsupported, or unavailable. Heuristic warnings (contrast, inverted colors, transparent background, quiet zone, module size, logo coverage against the effective error correction) are guidance only and never block export.

**Considered Options**

Decoding in the browser would rasterise SVG natively but would put the decoder and its WebAssembly binary in the client bundle and make fixtures untestable on the server. A generic SVG rasteriser (Imagick or librsvg) would add a system dependency that self-hosted installs do not have, which conflicts with ADR 0003.

**Consequences**

SVG exports are rasterised by a strict rasteriser that only accepts the subset Openlink emits: a square viewBox at the origin, hex-filled `rect` and `path` elements (M, L, H, V, A, Z commands with nonzero or even-odd fills), and base64 PNG, JPEG, WebP, or GIF `image` logos. Anything else is reported as unsupported rather than guessed. Arcs are flattened to short segments and edges are sampled at pixel centres without anti-aliasing, so results can differ slightly from browser or print rendering. Transparent backgrounds are decoded over white, the most common printing surface, and the UI states that contrast depends on the real surface. Decoding is bounded to at most 1024 × 1024 pixels: larger exports are rendered at full size and downscaled before decoding. Checks are rate limited per user. A screen decode never guarantees real-world scanning, so the UI always recommends a physical print test.
