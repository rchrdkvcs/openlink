# Dashboard interface

The dashboard uses a open workspace canvas, flat content surfaces and quiet
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

Controls use a 16 px radius; actions and navigation use pills. Content panels use
24 px and overlays 28 px. Popovers use 20 px outside and 16 px inside a 4 px inset. Shape follows the nesting and purpose of
the surface. Achromatic surfaces and focus states keep the interface neutral. Semantic colors
remain reserved for status, errors and user-chosen workspace colors.

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
