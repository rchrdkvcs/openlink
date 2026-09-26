# Dashboard interface

The dashboard uses a compact workspace shell, inset content surfaces and quiet
controls. Navigation, forms and overlays share the same geometry and interaction
rules. Radix Vue supplies the primitives already used by shadcn-vue.

## Density and hierarchy

- Desktop sidebar: 224 px wide, 32 px navigation rows, 36 px workspace switcher.
  Navigation follows the workspace at the top; the profile stays at the bottom.
- Primary controls: 36 px high with 13 px text. Toolbar actions: 32 px.
- Coarse pointers: controls and menu items expand to at least 40 px.
- Page titles: 20 px. Field labels: 13 px. Supporting text: 12–13 px.
- Content has its own inset frame. Cards group related content; separators mark
  table rows and fixed panel headers or footers. Avoid extra boxes around each label.

## Shared styles

`resources/css/app.css` owns the surface and geometry tokens:

| Style | Purpose |
| --- | --- |
| `ui-page` | Page gutters and vertical spacing |
| `ui-panel` | Content cards with a quiet stroke and shallow depth |
| `ui-control` | Inputs and selection triggers, including open/invalid/disabled states |
| `ui-button`, `ui-icon-button` | Actions and visible keyboard focus |
| `ui-nav-link` | Compact navigation with a persistent selected state |
| `ui-popover`, `ui-menu-item` | Floating menus and their items |
| `ui-dialog`, `ui-drawer`, `ui-overlay` | Modal surfaces and backdrop |

Control radius is 8 px, content panels 16 px, overlays 20 px. Popovers use 12 px
outside and 8 px inside a 4 px inset. Shape follows the nesting and purpose of
the surface. Accent color communicates focus, selection and semantic states.

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
