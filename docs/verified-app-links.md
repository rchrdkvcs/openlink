# Verified app links

App Associations let a workspace's own iOS or Android app open Short URLs on one of its active Domains through iOS Universal Links and Android App Links. The design is recorded in [ADR 0015](adr/0015-publish-app-associations-on-workspace-domains.md). Platform facts and their sources are in [research/verified-app-links.md](research/verified-app-links.md).

## Use case

A shop sends customers `https://go.shop.example/spring`. Customers with the shop's app should land in the app; everyone else should reach the normal Destination URL in a browser. App Associations cover only this verified, two-sided association. They are not Smart Routing, they do not redirect to custom URL schemes, and they do not provide deferred deep linking after an app install.

## What Openlink publishes

| Platform | URL | Content |
| --- | --- | --- |
| iOS 13+ | `https://<domain>/.well-known/apple-app-site-association` | `applinks.details[]` with one `appIDs` entry per app and `components` built from its path patterns |
| Android 6+ | `https://<domain>/.well-known/assetlinks.json` | One `delegate_permission/common.handle_all_urls` statement per package with its SHA-256 fingerprints |

- Both files are generated from typed fields and served as `application/json` with HTTP 200. They never redirect and never reach Short URL resolution or analytics.
- Each response is a 404 when the hostname is unknown, is the Default Domain or the application host, is not active, is disabled, or has no App Association for that platform.
- Every iOS entry starts with exclusions for `/.well-known/*`, `/qr/*`, and `/password/*`. QR Code scans and password submissions therefore always reach Openlink.
- Paths you claim for iOS are checked in order. When two apps on one Domain claim the same path, iOS uses the first match, which is the app added first.
- Android paths come from the app's intent filters, not from `assetlinks.json`. Openlink does not publish Android 15 dynamic App Link rules.

## Setup

### In Openlink

1. Add the Domain and wait until it is **Active**.
2. As a workspace Owner or Admin, open **Settings → Domains**, choose **Mobile apps** from the Domain's menu, and add each app:
   - iOS: the 10-character App ID prefix (usually your Team ID; check the developer portal), the bundle ID, and one or more path patterns such as `/promo/*`. `*` matches any characters and `?` matches one.
   - Android: the package name and every SHA-256 signing-certificate fingerprint as 32 colon-separated hex pairs. With Play App Signing, include the app signing key from Play Console, plus your upload or debug key if you test sideloaded builds.
3. Review the generated files under **Published files**.

### In the iOS app

1. Add the Associated Domains capability with `applinks:<domain>`, with no scheme, path, or port.
2. Handle `NSUserActivityTypeBrowsingWeb` and read `webpageURL`.
3. During development, `applinks:<domain>?mode=developer` bypasses Apple's CDN on devices with Developer Mode and **Associated Domains Development** turned on.

### In the Android app

1. Add an intent filter with `android:autoVerify="true"`, the `VIEW` action, the `DEFAULT` and `BROWSABLE` categories, `http` and `https` schemes, the Domain as host, and the paths the app should handle.
2. Do not claim `/qr/` or `/password/` paths if you want QR Code scan attribution and protected links to keep working in the browser.

### On the reverse proxy

- Forward `/.well-known/apple-app-site-association` and `/.well-known/assetlinks.json` for every workspace Domain to Openlink over HTTPS.
- Answer HTTPS requests for these paths directly. Do not redirect them, for example from `example.com` to `www.example.com`. Apple and Google reject redirected association files.
- Do not serve a static file at these paths from the proxy; it would override Openlink's per-Domain response.
- Keep these paths reachable from any IP address and any User-Agent.

## How links behave

| Situation | What happens |
| --- | --- |
| App installed, association verified, path matches | The platform may open the app with the Short URL. Openlink receives no request: no Visit is counted and no checks run. |
| App not installed | The browser opens the Short URL. Normal resolution applies. |
| Opened in a browser (typed URL, same-domain tap in Safari, user chose the browser, browser without App Link support) | The browser opens the Short URL. Normal resolution applies. |
| Path not claimed, or `/qr/*` and `/password/*` | The browser opens the URL. Normal resolution applies. |
| App Association removed or Domain disabled or transferred | Openlink stops publishing at once. Devices and Apple's CDN may keep a cached association for days. |

Normal resolution is the Web Fallback: lifecycle rules, password protection, visit limits, and Smart Routing all apply exactly as for any other visitor. An app open never unlocks a Protected Link, because the app receives only the Short URL, never the Destination URL. An app that wants the destination must open the Short URL in a browser or let Openlink resolve it. Openlink does not forward incoming query parameters to Destination URLs, and association files never match on them.

## Verifying

Openlink only checks input formats and shows the exact files it serves. A well-formed file does not guarantee that a device opens the app: the app build, entitlements, signing keys, platform caches, browsers, and user choices also decide the outcome. Always test on real devices.

### HTTP checks

```sh
curl -i https://go.example.com/.well-known/apple-app-site-association
curl -i https://go.example.com/.well-known/assetlinks.json
curl "https://digitalassetlinks.googleapis.com/v1/statements:list?source.web.site=https://go.example.com&relation=delegate_permission/common.handle_all_urls"
```

Expect `HTTP/2 200`, `content-type: application/json`, and no `location` header.

### iOS device

1. Install a build that includes the Associated Domains entitlement. Apple's CDN may take up to 24 hours to fetch a new file; use developer mode while testing.
2. Paste a Short URL into Notes and long-press it. A working link offers to open in the app.
3. On iOS, use **Settings → Developer → Universal Links → Diagnostics** with the full Short URL.
4. On a Mac, `sudo swcutil dl -d go.example.com` downloads the file and `sudo swcutil verify -d go.example.com -j apple-app-site-association -u https://go.example.com/promo/1` tests a URL.
5. Check that typing the URL into Safari opens the browser, and that a Short URL on an unclaimed path still resolves in the browser. A claimed path opens the app even for a Protected Link; the app only receives the Short URL.

### Android device

```sh
adb shell pm set-app-links --package com.example.app 0 all
adb shell pm verify-app-links --re-verify com.example.app
adb shell pm get-app-links com.example.app
adb shell am start -a android.intent.action.VIEW -c android.intent.category.BROWSABLE -d "https://go.example.com/promo/1"
```

`get-app-links` should report `verified` for the Domain. Then uninstall the app and confirm the same Short URL opens in the browser and resolves normally.
