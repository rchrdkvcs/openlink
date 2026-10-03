# Interface redesign

Openlink exists to turn a long URL into a short link you can share, then manage and measure it. The interface is organised around that loop: **paste → shorten → copy → share → refine → measure**. Everything else — folders, domains, members, settings — supports the loop and should stay out of the way until needed.

## Principles

1. **Creation is instant.** A short link is one paste and one Return away from anywhere: the composer on Home and Links, the ⌘K palette, or pasting a URL anywhere on those pages. The result is copied to the clipboard automatically. Options are refined *after* creation, never required before it.
2. **No drawers.** Detail and editing happen in context: a non-modal inspector beside the list (full screen on small viewports). Modal dialogs are reserved for short, focused tasks (confirmations, creating a workspace, adding a domain hostname) — never for long forms.
3. **One place for settings.** Workspace (General, Domains, Members), Account (Profile, Sign-in methods, Security, API tokens, Delete account) and Instance live under `/settings` with a shared secondary navigation (`SettingsLayout`).
4. **Folders behave like Finder.** Folders live in the sidebar with counts. Create inline with `+`, rename by double-click or the `⋯` menu, drag links onto a folder to move them. Selecting a folder filters the Links view; new links created there land in it.
5. **Consistency over novelty.** Same control heights, radii, menus, confirmations and feedback everywhere.

## Information architecture

| Area | Route | Notes |
| --- | --- | --- |
| Home | `dashboard` | Greeting, large composer, recent links, 30‑day KPIs, traffic, top links |
| Links | `links.index` (`?folder=`, `?status=archived`, `?link=`) | Composer, search/filters, flat list, inspector |
| QR codes | `qr-codes.*` | |
| Analytics | `analytics.index` | |
| Settings → Workspace | `settings.workspace`, `domains.*`, `members.*` | `/settings/...` |
| Settings → Account | `profile.edit?tab=` | `/settings/account` |
| Settings → Instance | `settings.index` | `/settings/instance`, instance admins only |

## Visual system

Colour tokens are unchanged, with one addition: `canvas`, the content panel colour between `background` (sidebar) and `surface` (cards).

Layering: `background` (sidebar) → `canvas` (inset content panel, `rounded-xl border shadow-panel`) → `surface` (cards, groups) → `elevated` (hover, selected, segmented thumb) → `overlay` (menus, dialogs).

| Token | Value |
| --- | --- |
| Control heights | `sm` 28px (`h-7`), `md` 32px (`h-8`, default), `lg` 40px (`h-10`); hero composer 44–48px |
| Radii | controls and buttons `rounded-lg`, cards and groups `rounded-xl`, dialogs and hero composer `rounded-2xl` — concentric |
| Fields | Filled: transparent border, `bg-elevated/70`; accent border and `ring-2 ring-accent/15` on focus |
| Focus | `ring-2 ring-accent/40` on buttons and interactive rows |
| Page title | `text-[22px] font-semibold tracking-[-0.015em]` |
| Section title | `text-[13px]`/`text-[15px] font-semibold`, sentence case — avoid uppercase tracked labels |
| Body | `text-sm`; secondary `text-[13px] text-muted`; tertiary `text-xs text-faint` |
| Surfaces | Flat: no gradients, no inner shadows, hairline borders only for structure. Only floating layers carry a shadow: `shadow-popover` (menus, popovers, selects), `shadow-dialog` (dialogs, toasts, save bar) |
| Icons | Lucide, stroke 1.5, 14px in controls, 15px in the sidebar; press feedback `scale(0.96)` |
| Motion | `ease-emphasized-out` 200–300ms for entering, 150ms ease-out for leaving; disclosure uses `grid-template-rows` |

## Components

| Need | Component |
| --- | --- |
| Page header | `ui/PageHeader` (or `SettingsLayout` title/description/actions) |
| Settings form | `ui/SettingsGroup` + `ui/SettingsRow` (label + description left, control right; `stacked` for wide controls) |
| Unsaved changes | `ui/SaveBar` (floating, appears only when dirty) |
| Menu / row actions | `ui/Menu`, `ui/MenuItem`, `ui/MenuSeparator`, `ui/MenuLabel` (radix) — never hand-rolled popovers |
| Confirmation | `confirmAction()` from `lib/confirm` — never `window.confirm` |
| Feedback | `toast()` / `copyToClipboard()` from `lib/toast` |
| Short dialog | `ui/Dialog` |
| Segmented choice | `ui/SegmentedControl` |
| Keyboard hint | `ui/Kbd` |

## Keyboard

| Key | Action |
| --- | --- |
| ⌘K / Ctrl K | Command palette (paste a URL to shorten, jump anywhere, switch workspace) |
| Paste (nothing focused) | Prefills the composer on Home and Links |
| `N` | Focus the composer (Links) |
| `/` | Focus search (Links) |
| `J` / `K`, ↑ / ↓ | Move selection (Links) |
| Esc | Close inspector / clear composer |
| ⌘S | Save inspector changes |

## Conventions added after review

- Everything that floats (menus, selects, date picker, icon picker, link filter) is portaled with radix so it is never clipped by a scroll container.
- The app shell is fixed on desktop; only the content panel scrolls, and split views (Links list and inspector) scroll each column independently with `overscroll-contain`.
- Large editors do not live in the inspector: Smart routing opens in a wide dialog, and the inspector shows a compact summary.
- Workspaces are identified by an icon only (no colour).
- Short links are copied by clicking the short URL itself; there is no separate copy button in rows.
