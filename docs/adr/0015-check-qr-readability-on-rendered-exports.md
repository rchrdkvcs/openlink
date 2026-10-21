# Check QR Code readability on the rendered exports

A Readability Check renders the current QR Code draft through the PNG export path and decodes the image with a local decoder (`chillerlan/php-qrcode`). The decoded text must exactly match the expected encoded content: the stable entry URL for a linked QR Code, or the native payload for a direct-payload QR Code. Checks never send payloads to a third-party service and never resolve a Short URL, so they cannot record visits or scans or consume visit limits. The draft travels in a POST body so native payloads such as Wi-Fi passwords or vCard details stay out of URLs and access logs. The route still has no side effects, and it is rate limited per user.

For each format, a check reports one of five distinct states instead of a score: readable, mismatch, unreadable, unsupported, or unavailable. Heuristic warnings (contrast, inverted colors, transparent background, quiet zone, pixel module size, estimated logo coverage against the effective error correction, and module size in millimetres when a print width is given) are guidance only. They never block an export.

**Considered Options**

- **Rasterising the SVG export directly.** This needs Imagick or librsvg, which are system dependencies that the Docker image and self-hosted installs do not have (ADR 0003). No maintained pure-PHP SVG rasteriser exists.
- **A hand-written rasteriser for the SVG subset Openlink emits.** It was tried and rejected: it is unmaintained code that drifts from real SVG rendering.
- **Decoding in the browser.** This would put a decoder and a WebAssembly binary in the client bundle and leave the fixtures untestable on the server.

**Consequences**

The SVG verdict is derived from the PNG decode, and the response marks it with `derived_from: png`. This is sound because both renderers draw from the same module matrix (`QrGrid`) and the same appearance model: colors, margin, module and eye styles, error correction, logo box, and padding. They differ only in output encoding and anti-aliasing. One case breaks the equivalence: a logo that GD cannot read. The PNG would omit that logo while the SVG still embeds it, so the SVG is then reported as unsupported.

Decoding is bounded. The PNG is rendered at the export size capped at 1024 × 1024 pixels, so exports larger than that are checked at 1024 px with the same design. Transparent backgrounds are decoded over white, the most common printing surface, and the interface states that contrast depends on the real surface.

Module size on paper uses 0.4 mm per module as a stated rule of thumb for phone scanning at close range. It is not a guarantee. A screen decode never guarantees real-world scanning, so the interface always recommends a physical print test.
