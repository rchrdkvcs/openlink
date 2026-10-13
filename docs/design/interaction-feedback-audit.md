# Interaction feedback audit

Audit performed on 13 October 2026. The search covered click handlers, interactive roles, cursors and focus styles across the 145 Vue templates, followed by inspection of the shared controls and affected dashboard, link-list, analytics and QR-code components.

## Discoverable actions

| Severity | Location | Before | After | Why |
| --- | --- | --- | --- | --- |
| MEDIUM | `resources/css/app.css:13`; `Components/ui/MenuItem.vue:22`, `MenuSub.vue:21`, `Select.vue:80`; `Components/analytics/LinkFilter.vue:126`; `Pages/Links/list/LinkRow.vue:53`, `LinkRowMenu.vue:60` under `resources/js` | Clickable rows and options explicitly used the default cursor; the shared rule covered only buttons. | Interactive native controls and action roles share a pointer cursor. Explicit default cursors are removed. Native, ARIA and Radix disabled states are excluded. | Cursor feedback consistently identifies available actions. |

## Keyboard feedback and native controls

| Severity | Location | Before | After | Why |
| --- | --- | --- | --- | --- |
| MEDIUM | `resources/js/Pages/Dashboard/RecentLinks.vue:32` | Opening a recent link required clicking a non-focusable container. | A named native button covers the row and supports Tab, Enter and Space, with an inset focus ring. Copy remains a separate named button above it. | Pointer actions also have a keyboard path; copying does not open the inspector. |
| MEDIUM | `resources/css/app.css:44`; `resources/js/Pages/Links/list/LinkRow.vue:53`; `resources/js/Pages/QrCodes/Library/QrCodeCard.vue:36` | Several controls relied on browser focus defaults; link rows suppressed the outline; QR cards only changed their border subtly. | Shared two-pixel focus outline where no component overrides it, an explicit link-row ring and a ring around the full QR card. | Keyboard focus identifies the actionable area while existing component styles retain precedence. |

## State and accessible names

| Severity | Location | Before | After | Why |
| --- | --- | --- | --- | --- |
| MEDIUM | `resources/js/Components/ui/IconButton.vue:26`, `Switch.vue:14`, `Button.vue:50` | Icon buttons and switches lacked a shared disabled appearance; loading buttons did not expose busy state. | Disabled controls appear dimmed; icon buttons suppress pointer interaction; loading buttons expose `aria-busy`. Icon-button press scale matches the shared button. | Unavailable actions and ongoing work are distinguishable. |
| LOW | `resources/js/Components/ui/TagInput.vue:76`, `DateTimeField.vue:108`, `DateTimeField.vue:117` | Tag removal and calendar navigation lacked descriptive accessible names. | Removal names the tag; previous/next month buttons name their actions. | Icon actions announce their purpose. |

## Verification

Browser verification used an isolated temporary Vue page importing the actual components and application stylesheet, without the Laravel backend. It was removed after verification.

- Confirmed computed pointer cursors for recent-link actions, enabled buttons, switches, links, menu items, submenus and select options.
- Confirmed computed disabled cursors and reduced opacity for disabled buttons, icon buttons, switches and selects.
- Confirmed recent-link opening by click, Enter and Space; mocked clipboard writing confirmed copying the URL does not trigger opening.
- Confirmed keyboard focus rings on recent-link opening and copying, the shared fallback outline on a plain link, and the full-card QR focus ring.
- Confirmed select changes with ArrowDown and Enter, and Escape closes the menu.
- Hover and pressed styles, existing selected states, empty states and reduced-motion rules were inspected in source. Hover behavior was not reliably verified in the collaborative browser.
- Frontend lint and formatting passed. All 138 tests across 21 test files passed. The isolated component page compiled for production with Vite.
- Not verified: full authenticated application flows, screen-reader behavior, forced-colors appearance, focus-indicator contrast and motion replay. PHP and Composer are absent; TypeScript checking stops at the missing `vendor/tightenco/ziggy` dependency, so the full application build is not verified.

Approve for the scoped corrections and verified component interactions. This is not approval of unverified application flows or a complete accessibility audit.
