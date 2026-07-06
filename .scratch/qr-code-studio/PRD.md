# QR Code Studio

Status: Shipped

## Problem

Three issues with the MVP QR codes, plus a feature request:

1. **PNG export broken in production.** The production image (FrankenPHP) did not install the `gd` PHP extension, so PNG rendering failed with a 500 while SVG worked.
2. **QR entry URLs built on the application host.** The QR image encoded `route('public.qr')` on the application base URL instead of the domain selected for the short link. Scans entered through the app host, contradicting the app-host/redirect-domain separation in the technical spec.
3. **No dedicated QR page.** Viewing a QR code opened the raw SVG in a browser tab; there was no controlled page to display, customize, or export a QR code.
4. **Customization was limited** to size, two colors, margin, and error correction.

## Solution

### Fixes

- Add `gd` to the production Docker image.
- Encode `https://{link-domain}/qr/{token}` in the QR image (`QrCode::publicUrl()`), matching how `short_url` is built. Resolution already routes `/qr/{token}` on any host through the link's own domain.
- Margin is now interpreted as a quiet zone in modules (was pixels, which made the default 2px margin nearly invisible).

### QR studio page (`/qr-codes/{token}`)

Authenticated Inertia page (`QrCodes/Show.vue`) with:

- Large live preview that re-renders on every unsaved settings change (preview endpoint accepts validated query overrides).
- Copyable public entry URL and per-QR scan count.
- PNG/SVG downloads at a selectable size (512–4096 px), filenames derived from the QR name.
- Full customization form and delete.

Creating a QR from the Links drawer now only asks for a name and redirects to the studio.

### Customization

New `qr_codes` columns: `style` (`square`/`rounded`/`dot`), `eye_style` (`square`/`rounded`/`circle`), `background_transparent`, `logo_path`.

The renderer (`App\Services\QrCodeRenderer`) draws SVG and PNG (GD) from the bacon-qr-code module matrix directly, replacing endroid/qr-code. Finder eyes are drawn as ring + pupil in the chosen eye style; logos are centered on a padded background chip; error correction is raised to at least quartile when a logo is present. Dot rendering was verified scannable with ZXing.

### API parity

`PATCH`/`DELETE /api/v1/qr-codes/{token}` added; create/update accept all customization fields including logo upload; payloads share `App\Actions\QrCodes\QrCodePayload`.

## Non-goals

- Gradients, per-corner eye colors, frame templates ("scan me" labels).
- Serving logos publicly (logos are only embedded server-side into rendered images).
