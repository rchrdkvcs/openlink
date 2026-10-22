# Link Page icons

Link Pages use a small local catalogue of favicons. Only exact hostnames in `LinkPageIcons` match; subdomains and lookalike suffixes do not match implicitly. The service identifier is shared by the picker, selected entries, live preview, and public page. A Protected Link always receives a generic globe, as does an unknown site. The globe is a generic link symbol, not the site's favicon.

The catalogue identifies the default destination's service only. It does not represent routing-rule destinations or guarantee the final destination a visitor will reach. All links still open their Short URLs, and any displayed URL is a Short URL.

No Link Page surface fetches a remote image or performs DNS resolution. These assets are bundled in the frontend build, with no dependency on the authenticated `/favicons` proxy. Expanding this catalogue is an explicit code change. See [ADR 0025](adr/0025-publish-link-pages-on-a-reserved-application-path.md) for the privacy decision.

## Bundled assets

The PNG files in `resources/js/Components/LinkPages/favicons` were obtained from the services' own favicon assets on 2026-10-22, decoded and re-encoded as 32 × 32 PNGs. Service names and marks belong to their respective owners and identify link services; they do not imply endorsement.

| Service | Exact recognised hosts | Source asset |
| --- | --- | --- |
| GitHub | `github.com`, `www.github.com` | https://github.githubassets.com/favicons/favicon-dark.png |
| Discord | `discord.com`, `www.discord.com`, `discord.gg` | https://discord.com/assets/favicon.ico |
| YouTube | `youtube.com`, `www.youtube.com`, `youtu.be` | https://www.youtube.com/s/desktop/12d6b690/img/favicon_48x48.png |
| Spotify | `spotify.com`, `www.spotify.com`, `open.spotify.com` | https://open.spotifycdn.com/cdn/images/favicon32.b64ecc03.png |
| Reddit | `reddit.com`, `www.reddit.com` | https://www.redditstatic.com/desktop2x/img/favicon/favicon-32x32.png |
| LinkedIn | `linkedin.com`, `www.linkedin.com` | https://www.linkedin.com/favicon.ico |
