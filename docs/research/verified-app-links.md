# Verified app links: Apple Universal Links and Android App Links

Research date: 2026-10-21. Every claim cites the primary source it came from. Anything not confirmed from a primary source is marked **unconfirmed**.

Apple's current documentation pages are rendered with JavaScript. The text cited here was read from the same pages through Apple's documentation JSON endpoint (`https://developer.apple.com/tutorials/data/documentation/<path>.json`). The links below point to the human-readable pages.

## 1. Apple Universal Links

### 1.1 What a universal link is

- Universal links are standard HTTP or HTTPS links. If the app is installed, the system sends the link straight to the app. If it is not installed, the URL opens in the user's default web browser. ([Allowing apps and websites to link to your content](https://developer.apple.com/documentation/xcode/allowing-apps-and-websites-to-link-to-your-content))
- They rely on a two-way association: an `apple-app-site-association` (AASA) file on the website, plus the Associated Domains entitlement in the app. ([Supporting associated domains](https://developer.apple.com/documentation/xcode/supporting-associated-domains))
- Web browsers, and apps with the `com.apple.developer.web-browser` entitlement, may not register universal links through the `applinks` service. ([TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links))

### 1.2 Where to host the AASA file

- Name the file `apple-app-site-association`, with no extension. ([Supporting associated domains](https://developer.apple.com/documentation/xcode/supporting-associated-domains))
- Serve it at `https://<fully qualified domain>/.well-known/apple-app-site-association`. ([Supporting associated domains](https://developer.apple.com/documentation/xcode/supporting-associated-domains))
- It must be served over `https://` with a valid certificate and **no redirects**. ([Supporting associated domains](https://developer.apple.com/documentation/xcode/supporting-associated-domains))
- A 301 or 302 response is not supported. A 403 or 404 means access was denied. The file must be publicly reachable from every IP address and region, and the server should accept any User-Agent. ([TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links))
- Each subdomain listed in the entitlement needs its own file. A file at `https://example.com/.well-known/...` serves `applinks:example.com` and `applinks:*.example.com`. It does not serve specific subdomains such as `applinks:www.example.com`. ([TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links), [Supporting associated domains](https://developer.apple.com/documentation/xcode/supporting-associated-domains))
- Custom root certificates are not supported. The root certificate must be one the operating system recognizes. ([WWDC19 "What's New in Universal Links"](https://developer.apple.com/videos/play/wwdc2019/717/))
- **Root fallback (`/apple-app-site-association`).** The archived iOS 9 guide accepted the file at either the domain root or `/.well-known/`. ([archived Apple guide, Wayback copy](https://web.archive.org/web/20200103140602/https://developer.apple.com/library/archive/documentation/General/Conceptual/AppSearch/UniversalLinks.html)) WWDC19 says "Other paths are deprecated", and support for JSON files at other paths "will be removed in a future release". ([WWDC19](https://developer.apple.com/videos/play/wwdc2019/717/)) Current documentation mentions only `/.well-known/`. Whether the root path still works today is **unconfirmed**.
- **Content-Type.** Current Apple documentation does not state a required Content-Type. The archived guide says that, from iOS 9, a file served over HTTPS can be "a plain text file that uses the `application/json` MIME type" and does not need to be signed. ([archived Apple guide](https://web.archive.org/web/20200103140602/https://developer.apple.com/library/archive/documentation/General/Conceptual/AppSearch/UniversalLinks.html)) Signed AASA files are deprecated. ([WWDC19](https://developer.apple.com/videos/play/wwdc2019/717/)) Serving `application/json` is the safe choice. A strict requirement in the current docs is **unconfirmed**.
- **Size limit.** The archived guide says that for iOS 9.3.1 and later, "the uncompressed size of the apple-app-site-association file must be no greater than 128 KB". ([archived Apple guide](https://web.archive.org/web/20200103140602/https://developer.apple.com/library/archive/documentation/General/Conceptual/AppSearch/UniversalLinks.html)) Current docs do not repeat this, so whether 128 KB still applies is **unconfirmed**.

### 1.3 `applinks` structure

Top-level keys under `applinks` ([applinks](https://developer.apple.com/documentation/bundleresources/applinks)):

- `details`: an array of `Details` objects. Each one defines a set of apps and the links they handle.
- `defaults`: domain-level defaults for pattern matching.
- `substitutionVariables`: custom named variables used in patterns.

Each `details` entry has these keys ([applinks.Details](https://developer.apple.com/documentation/bundleresources/applinks/details-swift.dictionary)):

- `appIDs`: an array of app identifiers.
- `appID`: a single app identifier. This is the legacy form.
- `components`: an array of URL-matching dictionaries.
- `defaults`: app-level defaults.

- `details` must be an array. A dictionary was supported in the past but is now obsolete. ([WWDC19](https://developer.apple.com/videos/play/wwdc2019/717/))
- The legacy format is `appID` (a string) plus `paths` (an array, where a `NOT ` prefix excludes a path). The current format is `appIDs` plus `components`. Do not mix the two formats, because the result may be unpredictable. ([TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links)) iOS 13, tvOS 13 and macOS 10.15 ignore `paths` when `components` is present. If you still support iOS 12 or earlier, include the `apps` key as an empty array. ([WWDC19](https://developer.apple.com/videos/play/wwdc2019/717/))

Keys in a `components` dictionary ([applinks.Details.Components](https://developer.apple.com/documentation/bundleresources/applinks/details-swift.dictionary/components-swift.dictionary)):

| Key | Meaning | Default |
| --- | --- | --- |
| `/` | Pattern for the URL path | `*` |
| `?` | Pattern string, or a dictionary of query item name to value pattern | `*` |
| `#` | Pattern for the URL fragment | `*` |
| `exclude` | If the URL matches, stop matching and do not open it as a universal link | `false` |
| `comment` | Ignored by the system | none |
| `caseSensitive` | Whether matching is case-sensitive | `true` |
| `percentEncoded` | Whether URLs are percent-encoded | `true` |

- A URL matches only when it matches **every** component the dictionary specifies. ([Components](https://developer.apple.com/documentation/bundleresources/applinks/details-swift.dictionary/components-swift.dictionary))
- In a `?` dictionary, the keys are query item names and the values are patterns, for example `"?": { "productID": "12345" }`. ([Query](https://developer.apple.com/documentation/bundleresources/applinks/details-swift.dictionary/components-swift.dictionary/query))
- Wildcards: `*` matches zero or more characters (greedy), `?` matches exactly one character, and `?*` matches one or more. ([Components](https://developer.apple.com/documentation/bundleresources/applinks/details-swift.dictionary/components-swift.dictionary))
- `caseSensitive` requires iOS 13.5 or macOS 10.15.5. `percentEncoded` and `defaults` require iOS 14 or macOS 11. ([WWDC20 "What's new in universal links"](https://developer.apple.com/videos/play/wwdc2020/10098/))
- `defaults` can be set at domain level (under `applinks`) or app level (under a `details` entry). The more specific level wins, and values set on a component override both. ([applinks.Defaults](https://developer.apple.com/documentation/bundleresources/applinks/defaults-swift.dictionary))
- **Substitution variables.** These are documented. Names are case-sensitive and cannot contain `$`, `(` or `)`. Values may contain `?` and `*` but cannot reference other variables. The predefined variables are `$(alpha)`, `$(upper)`, `$(lower)`, `$(alnum)`, `$(digit)`, `$(xdigit)`, `$(region)` and `$(lang)`. ([applinks.SubstitutionVariables](https://developer.apple.com/documentation/bundleresources/applinks/substitutionvariables-swift.dictionary))

### 1.4 Order and first-match semantics

- The order of patterns in the array is the order the system checks them. "The first match wins, allowing you to designate one app to handle specified URLs in your website, and another app to handle the rest." ([Components](https://developer.apple.com/documentation/bundleresources/applinks/details-swift.dictionary/components-swift.dictionary))
- Patterns are evaluated top to bottom. An `exclude` match stops evaluation and keeps the URL in the browser, so place exclusions before the broader includes. ([WWDC19](https://developer.apple.com/videos/play/wwdc2019/717/))
- The "one app here, another app for the rest" wording implies that ordering also applies across `details` entries. No document states the cross-`details` algorithm explicitly, so that detail is **unconfirmed**.
- The order of domains in the entitlement array is ignored. ([WWDC19](https://developer.apple.com/videos/play/wwdc2019/717/))

### 1.5 App ID format

- Format: `<Application Identifier Prefix>.<Bundle Identifier>`, for example `ABCDE12345.com.example.app`. ([applinks.Details](https://developer.apple.com/documentation/bundleresources/applinks/details-swift.dictionary), [Supporting associated domains](https://developer.apple.com/documentation/xcode/supporting-associated-domains))
- The prefix is "an alphanumeric, 10 character prefix provided by Apple". It "may or may not be equal to your team identifier", so check the value in the developer portal. ([WWDC19](https://developer.apple.com/videos/play/wwdc2019/717/))

### 1.6 Associated Domains entitlement

- Entitlement key: `com.apple.developer.associated-domains`. Each value is `<service>:<fully qualified domain>`, for example `applinks:example.com`. ([Associated Domains Entitlement](https://developer.apple.com/documentation/bundleresources/entitlements/com.apple.developer.associated-domains))
- Do not include a path, a query, or a trailing slash. A `*.` prefix matches all subdomains for every service except `appclips`. ([Supporting associated domains](https://developer.apple.com/documentation/xcode/supporting-associated-domains))
- Port numbers are not allowed. For example, `applinks:example.com:8080` will not open the app. ([TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links))
- Internationalized domain names must be written in Punycode. ([WWDC19](https://developer.apple.com/videos/play/wwdc2019/717/))

### 1.7 Apple CDN fetching and caching

- Since iOS 14 and macOS 11, devices do not request the AASA file from your server. They request it from an Apple-managed CDN dedicated to associated domains. When the CDN's copy is missing or old, the CDN fetches it from your server. ([Associated Domains Entitlement](https://developer.apple.com/documentation/bundleresources/entitlements/com.apple.developer.associated-domains), [WWDC20](https://developer.apple.com/videos/play/wwdc2020/10098/))
- The CDN requests your file within 24 hours. Devices check for updates about once a week after the app is installed. ([Supporting associated domains](https://developer.apple.com/documentation/xcode/supporting-associated-domains))
- When the app is installed, the device downloads the file from the CDN immediately. To get a newer version on a device, reinstall the app. "There is no direct CDN invalidation option." ([TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links))
- The CDN hostname `app-site-association.cdn-apple.com` (queried as `/a/v1/<domain>`) appears only in developer-written posts on the Apple Developer Forums, for example [thread 805663](https://developer.apple.com/forums/thread/805663). Apple's documentation does not name it, so it is **unconfirmed**.

### 1.8 Developer mode (bypassing the CDN)

- Append `?mode=<alternate mode>` to the entitlement entry, for example `applinks:dev.example.com?mode=developer`. The modes are `developer`, `managed` (requires an MDM profile) and `developer+managed`. ([Associated Domains Entitlement](https://developer.apple.com/documentation/bundleresources/entitlements/com.apple.developer.associated-domains))
- `developer` mode works only on devices in developer mode and only for apps signed with a development profile. The user must opt in on each device. In this mode any valid SSL certificate is accepted, including one the system doesn't trust. ([Associated Domains Entitlement](https://developer.apple.com/documentation/bundleresources/entitlements/com.apple.developer.associated-domains))
- To opt in on iOS: turn on Developer Mode, then go to Settings > Developer > Universal Links and turn on "Associated Domains Development". ([TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links))
- To opt in on macOS, run `swcutil developer-mode -e true`. This applies per user. ([WWDC20](https://developer.apple.com/videos/play/wwdc2020/10098/))

### 1.9 When a universal link does NOT open the app

- **Typed URL.** Typing the URL into the browser's address bar never opens the app. ([TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links))
- **Same-domain navigation.** In Safari, tapping a universal link on the same domain as the current page keeps the user in Safari. A link to a different domain opens the app. ([Allowing apps and websites to link to your content](https://developer.apple.com/documentation/xcode/allowing-apps-and-websites-to-link-to-your-content)) The workaround is to use a different subdomain. ([TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links))
- **User preference.** The user's choice (open in the app or in the browser) becomes the device default for that domain until they change it. ([TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links)) The archived guide describes this mechanism: tapping the status-bar breadcrumb switches the domain to Safari, and tapping OPEN on the Smart App Banner switches it back. ([archived Apple guide](https://web.archive.org/web/20200103140602/https://developer.apple.com/library/archive/documentation/General/Conceptual/AppSearch/UniversalLinks.html))
- **Opened by the app itself.** If the app opens its own universal link with `open(_:options:completionHandler:)`, `openURL` and similar calls, the link does not open in the app. ([Allowing apps and websites to link to your content](https://developer.apple.com/documentation/xcode/allowing-apps-and-websites-to-link-to-your-content))
- **Third-party browsers.** These may not implement universal links. Safari implements everything described in TN3155. ([TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links))
- **Redirects.** These are allowed, though not preferred, when a universal link is opened from another app. A non-universal link that redirects to a universal link goes through the browser before reaching the app. ([TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links))
- **macOS default.** On macOS, universal links open in the browser by default until the user chooses the app. ([WWDC19](https://developer.apple.com/videos/play/wwdc2019/717/))

### 1.10 Verifying on a device or Mac

All items below come from [TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links):

- **Notes app.** Paste the link into Notes, then long-press it (iOS) or Control-click it (macOS). If the link is configured correctly, the menu offers both "open in app" and "open in browser".
- **Diagnostics.** On iOS, go to Settings > Developer > Universal Links > Diagnostics and enter the full URL. It reports whether the link is valid for an installed app.
- **Inspect the hosted file.** Run `curl -v https://<domain>/.well-known/apple-app-site-association`. To test User-Agent handling, run `curl -A "MyAgent-Bot/*" ...`.
- **`swcutil` on a Mac.** `sudo swcutil dl -d <domain>` downloads the file. `sudo swcutil verify -d <domain> -j <file.json> -u <URL>` tests pattern matching and prints "matched" or "blocked match".
- **sysdiagnose.** Open `swcutil_show.txt` and check `User Approval`, `Site/Fmwk Approval` (`approved` means it works), `Last Checked` and `Next Check`.

## 2. Android App Links

### 2.1 Availability

- App Links: Android 6 (API 23) and later, on devices with Google services. ([About App Links](https://developer.android.com/training/app-links/about))
- Dynamic App Links: Android 15 (API 35) and later, on devices with Google services. ([About App Links](https://developer.android.com/training/app-links/about))
- Ordinary deep links (user-managed): all Android versions. ([About App Links](https://developer.android.com/training/app-links/about))

### 2.2 Hosting `assetlinks.json`

- Location: `https://<domain>/.well-known/assetlinks.json`. Lists at any other name or location are not valid for the site. ([Configure assetlinks](https://developer.android.com/training/app-links/configure-assetlinks), [DAL getting started](https://developers.google.com/digital-asset-links/v1/getting-started))
- The file must be served with `Content-Type: application/json`. ([Configure assetlinks](https://developer.android.com/training/app-links/configure-assetlinks), [DAL create statement](https://developers.google.com/digital-asset-links/v1/create-statement))
- It must be reachable over HTTPS, even if the intent filters declare only http. ([Configure assetlinks](https://developer.android.com/training/app-links/configure-assetlinks))
- No redirects: 301 and 302 are not followed. Any response other than HTTP 200 is treated as an empty statement list. ([DAL create statement](https://developers.google.com/digital-asset-links/v1/create-statement))
- Redirects such as `http→https` or `example.com→www.example.com` stop verification of all App Links for the app. ([Troubleshoot](https://developer.android.com/training/app-links/troubleshoot))
- Subdomains are separate hosts, and each needs its own file. For `*.example.com`, publish the file at the root host `example.com`. ([Add App Links](https://developer.android.com/training/app-links/add-applinks))
- A list can contain at most 10 `include` statements, which means at most 11 files in total. ([DAL statement syntax](https://developers.google.com/digital-asset-links/v1/statements))

### 2.3 Statement format

The following example comes from [Configure assetlinks](https://developer.android.com/training/app-links/configure-assetlinks):

```json
[{
  "relation": ["delegate_permission/common.handle_all_urls"],
  "target": {
    "namespace": "android_app",
    "package_name": "com.example",
    "sha256_cert_fingerprints": ["14:6D:E9:83:C5:73:06:50:D8:EE:B9:95:2F:34:FC:64:16:A0:83:42:E6:1D:BE:A8:8A:04:96:B2:3F:CF:44:E5"]
  }
}]
```

- `package_name` is the application ID from `build.gradle`. ([Configure assetlinks](https://developer.android.com/training/app-links/configure-assetlinks))
- `sha256_cert_fingerprints` holds the **uppercase** SHA-256 fingerprint of the signing certificate. The documented example is 32 colon-separated hex byte pairs. You can compute it with `keytool -printcert -file $CERTFILE | grep SHA256` or `openssl x509 -in $CERTFILE -noout -fingerprint -sha256`. ([DAL statement syntax](https://developers.google.com/digital-asset-links/v1/statements)) A lowercase fingerprint is listed as a common error. ([Troubleshoot](https://developer.android.com/training/app-links/troubleshoot))
- The field accepts multiple fingerprints, for example for debug and release builds. ([Configure assetlinks](https://developer.android.com/training/app-links/configure-assetlinks))
- **Play App Signing.** If you use it, the fingerprint from your local keystore usually does not match the one on users' devices. Use the fingerprint shown in Play Console (Release > Setup > App signing / App Integrity), which also provides a ready-made JSON snippet. ([Configure assetlinks](https://developer.android.com/training/app-links/configure-assetlinks), [DAL statement syntax](https://developers.google.com/digital-asset-links/v1/statements))
- One file can list several apps, and several sites can point to the same app. ([Configure assetlinks](https://developer.android.com/training/app-links/configure-assetlinks))

### 2.4 Manifest intent filter

All items below come from [Add App Links](https://developer.android.com/training/app-links/add-applinks):

- The intent filter needs:
  - `android:autoVerify="true"`
  - the `android.intent.action.VIEW` action
  - the `DEFAULT` and `BROWSABLE` categories
  - `<data>` elements for **both** the `http` and `https` schemes, plus the hosts
- Every `<data>` element in one intent filter is merged with the others. Use separate filters when you need specific scheme and host combinations.
- Hosts may use a `*.` wildcard. Path-level routing can be declared in the manifest (`path`, `pathPrefix`, `pathPattern`). For Android 15+ it can also go in `assetlinks.json` dynamic rules.
- Verification covers intent filters that have VIEW, BROWSABLE + DEFAULT, and the http or https scheme. For each unique host, the system fetches `https://hostname/.well-known/assetlinks.json`. ([Verify App Links](https://developer.android.com/training/app-links/verify-applinks))
- Android 12+ verifies each host independently, and any verified host becomes default for its links. Android 11 and lower verify nothing unless every host verifies. ([Add App Links](https://developer.android.com/training/app-links/add-applinks), [Verify App Links](https://developer.android.com/training/app-links/verify-applinks))

### 2.5 Android 12+ behavior changes

- From Android 12 (API 31), a generic web intent resolves to an app only if the app is approved for that domain. Otherwise it opens in the user's default browser. An app gets approval through App Links verification or when the user approves the domain in settings. ([Android 12 behavior changes: all apps](https://developer.android.com/about/versions/12/behavior-changes-all#web-intent-resolution))
- Apps targeting Android 12 get the updated verification process. Their intent filters must include `BROWSABLE` and the `https` scheme. ([Android 12 behavior changes: targeting 12](https://developer.android.com/about/versions/12/behavior-changes-12))
- Unverified web links on Android 12+ generally open in the browser. On earlier versions a disambiguation dialog may appear instead. ([Create deep links](https://developer.android.com/training/app-links/create-deeplinks))
- Only one app per device can be associated with a given domain. ([Verify App Links](https://developer.android.com/training/app-links/verify-applinks))
- If two apps resolve the same host and path, only the most recently installed one handles the link. ([Add App Links](https://developer.android.com/training/app-links/add-applinks))
- The user's choice in "Open by default" settings takes precedence over programmatic verification. ([Test App Links](https://developer.android.com/training/app-links/test-applinks))

### 2.6 Verification and testing commands

- Wait at least 20 seconds after install, then run `adb shell am start -a android.intent.action.VIEW -c android.intent.category.BROWSABLE -d "http://domain.name:optional_port"`. ([Verify App Links](https://developer.android.com/training/app-links/verify-applinks))
- Manual verification on Android 12+ ([Verify App Links](https://developer.android.com/training/app-links/verify-applinks)):
  - `adb shell am compat enable 175408749 PACKAGE_NAME` turns on the new process for apps that don't target Android 12.
  - `adb shell pm set-app-links --package PACKAGE_NAME 0 all` resets the state.
  - `adb shell pm verify-app-links --re-verify PACKAGE_NAME` runs verification again.
  - `adb shell pm get-app-links PACKAGE_NAME` reports the result. The states are `none`, `verified`, `approved`, `denied`, `migrated`, `restored`, `legacy_failure`, `system_configured`, or an error code of 1024 or higher.
  - `adb shell pm get-app-links --user cur PACKAGE_NAME` shows the per-user selection state.
- Older listing command: `adb shell dumpsys package domain-preferred-apps` (or `dumpsys package d`). ([Test App Links](https://developer.android.com/training/app-links/test-applinks))
- Generic deep link test: `adb shell am start -W -a android.intent.action.VIEW -d <URI> <PACKAGE>`. ([Create deep links](https://developer.android.com/training/app-links/create-deeplinks))
- On Android 17+, `adb shell am start --debug-link -a android.intent.action.VIEW -d "<URL>"` shows the matching candidates, the manifest filter matches, the verification state, and the dynamic rules that were evaluated. ([Test App Links](https://developer.android.com/training/app-links/test-applinks))
- Statement API: `https://digitalassetlinks.googleapis.com/v1/statements:list?source.web.site=https://<domain>&relation=delegate_permission/common.handle_all_urls`. Add `&return_relation_extensions=true` to see dynamic rules. ([Test App Links](https://developer.android.com/training/app-links/test-applinks), [statements.list reference](https://developers.google.com/digital-asset-links/reference/rest/v1/statements/list)) The response includes `statements[]`, a required `maxAge`, and an optional `debugString`. ([statements.list reference](https://developers.google.com/digital-asset-links/reference/rest/v1/statements/list))
- Statement List Generator and Tester: <https://developers.google.com/digital-asset-links/tools/generator>. ([Test App Links](https://developer.android.com/training/app-links/test-applinks))

### 2.7 Caching and update latency

All items below come from [Verify App Links](https://developer.android.com/training/app-links/verify-applinks):

- **Android 15+.** The system periodically verifies domains again in the background. Changes can take up to seven days to reach all devices.
- **Android 14 and lower.** There is no periodic background re-verification. Changes are typically picked up only when the app is installed or updated.
- **Server-side caches.** These can delay updates by a few hours even after you reinstall.

### 2.8 Android 15 Dynamic App Links

- Supported on Android 15 (API 35)+ with Google services. Android 14 and lower ignore the new fields. ([About App Links](https://developer.android.com/training/app-links/about), [Configure assetlinks](https://developer.android.com/training/app-links/configure-assetlinks))
- Rules go in `relation_extensions["delegate_permission/common.handle_all_urls"].dynamic_app_link_components`, which is an array of objects. Each object can have:
  - `/`: a path pattern
  - `#`: a fragment pattern
  - `?`: a dictionary of query parameters. Every entry must match, but in any order, and the URL may contain extra parameters.
  - `exclude`

  Wildcards are `*`, `?` and `?*`. ([Configure assetlinks](https://developer.android.com/training/app-links/configure-assetlinks))
- Rules are evaluated in order and the first match wins. If no rule matches, the URL is excluded. If any field is malformed or empty, all dynamic rules are discarded and the manifest rules apply. Declare only one `dynamic_app_link_components` per site, relation and app. ([Configure assetlinks](https://developer.android.com/training/app-links/configure-assetlinks))
- Dynamic rules cannot widen the scope of the manifest. Google recommends a broad manifest filter (scheme and host only) with refinement on the server side. ([Configure assetlinks](https://developer.android.com/training/app-links/configure-assetlinks), [Add App Links](https://developer.android.com/training/app-links/add-applinks))
- Backward-compatibility pattern: use `<uri-relative-filter-group>` together with a non-matching `<data android:path>`. On Android 14 and lower, scheme and host alone would otherwise capture every path. ([Add App Links](https://developer.android.com/training/app-links/add-applinks))
- In September 2026, Google fixed an issue in Google Play services that sometimes prevented Android 15+ devices from applying dynamic rules. No app or file change is needed. ([About App Links](https://developer.android.com/training/app-links/about))

## 3. App not installed, and deferred deep linking

- **iOS.** If the app is not installed, the URL opens in the user's default browser. ([Allowing apps and websites to link to your content](https://developer.apple.com/documentation/xcode/allowing-apps-and-websites-to-link-to-your-content), [TN3155](https://developer.apple.com/documentation/technotes/tn3155-debugging-universal-links))
- **watchOS.** If the app is not installed, the user is told to view the URL on their iPhone. ([Supporting universal links in your app](https://developer.apple.com/documentation/xcode/supporting-universal-links-in-your-app))
- **Android.** If the app is not installed, the link goes to the next default handler, often the browser. ([DAL getting started](https://developers.google.com/digital-asset-links/v1/getting-started)) Users without the app go to the website. ([About App Links](https://developer.android.com/training/app-links/about))
- **Deferred deep linking.** Neither Apple's nor Android's App Links documentation describes a mechanism that carries the original link through an App Store or Play install into the app's first launch. Both features act only when the app is already installed. Firebase's own FAQ recommends App Links and Universal Links for "deep-linking to my app after it has already been installed". It lists deferred deep linking as a feature of Firebase Dynamic Links, which shut down on August 25, 2025. ([Firebase Dynamic Links FAQ](https://firebase.google.com/support/dynamic-links-faq))
- On Android, the Play Install Referrer API gives the installed app the referrer URL and click and install timestamps. ([Play Install Referrer](https://developer.android.com/google/play/installreferrer)) That is a building block, not a guaranteed deferred deep link.
- A platform-level guarantee of deferred deep linking on either OS: **unconfirmed (none found)**.
