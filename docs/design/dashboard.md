# Dashboard interface

The dashboard uses an open workspace canvas, flat content surfaces and quiet
controls. Navigation, forms and overlays share the same geometry and interaction
rules. Radix Vue supplies the primitives already used by shadcn-vue.

## Density and hierarchy

- Desktop sidebar: 240 px wide, 32 px navigation pills and workspace switcher.
  Navigation is vertically centered; workspace and profile anchor the top and bottom.
- Primary controls: 36 px high with 13 px text. Toolbar actions: 32 px.
- Coarse pointers: controls and menu items expand to at least 40 px.
- Page titles: 20 px. Field labels: 13 px. Supporting text: 12–13 px.
- Content shares the navigation canvas without an enclosing frame. Metrics form a
  single divided strip. Lists use inset neutral headers and spacious rows rather
  than a grid of heavy separators. Cards group content without highlights or gradients.

## Shared styles

`resources/css/app.css` owns the surface and geometry tokens:

| Style | Purpose |
| --- | --- |
| `ui-page` | Generous page gutters and vertical spacing |
| `ui-panel` | Flat content groups with a quiet stroke |
| `ui-control` | Inputs and selection triggers, including open/invalid/disabled states |
| `ui-button`, `ui-icon-button` | Actions and visible keyboard focus |
| `ui-nav-link` | Compact navigation with a persistent selected state |
| `ui-popover`, `ui-menu-item` | Floating menus and their items |
| `ui-dialog`, `ui-drawer`, `ui-overlay` | Modal surfaces and backdrop |

Controls and actions use a 12 px radius, including icon buttons. Navigation,
workspace anchors, avatars and status badges retain their pill/circle silhouette.
Content panels and dialogs use 24 px. Menus use 16 px outside and 12 px inside
with a 4 px inset. List headers and hover rows use 16 px inside a panel's 8 px
padding. A group surrounding 12 px controls with 12 px padding uses 24 px outside.
Segmented controls use 16 px outside / 12 px inside / 4 px padding.

Fields have a darker background and a visible neutral stroke, including composed
URL and tag fields. Avoid overriding those with a transparent border in drawers.
Achromatic surfaces and focus states keep the interface neutral. Semantic colors
remain reserved for status, errors and user-chosen workspace colors.

## Link library

Folders are persistent navigation, not collapsible stacks of cards. A compact
rail provides All links, Unfiled and every folder, with counts reflecting the
current filters. Empty folders remain selectable and available as drop targets.
On narrow screens a labeled Select replaces the rail; selected-folder actions
remain in the list header. Search/status/tag filters apply inside the selected
folder. New links inherit that folder, with the field visible in the drawer.

Selection is stored per workspace and resets to All links if its folder is
removed. Drag/drop and the accessible Move to folder control share the same move
operation. Creation and renaming use an explicit form, validation errors and
submission state rather than submitting on blur. Deletion retains confirmation
and moves the folder's links to Unfiled.

Run folder behavior regression tests with Node 22.18+:
`node --test tests/Frontend/link-folders.test.ts`.

## Components and behavior

- `Button` is the source for all action variants, including legacy button wrappers.
- `Select` and `SelectOption` wrap Radix Select. Use them for single-value choices,
  including filters. Empty choices and numeric IDs retain their types. Explicit
  labels are required outside `Field` or `OptionRow`.
- Native text inputs retain browser editing behavior and share the control tokens.
- `Popover` provides positioning, viewport collision handling, Escape and focus
  return for calendars, icon pickers and contextual controls.
- `Modal` and `Drawer` use Radix Dialog for scroll locking, focus containment and
  dismissal. Give each a contextual title, including when a custom header is used.
- `ConfirmDialog` uses AlertDialog for destructive actions. Focus starts on Cancel.
- `Field` and `OptionRow` share label context with composed form controls.

The occasional appearance of an overlay uses short opacity/transform animations;
frequent choices update immediately. Reduced-motion preferences remove movement.
Do not introduce a separate palette, native select menu, browser confirmation or
manual full-screen click catcher for another dashboard page.
