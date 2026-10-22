# Product overview

Openlink is a self-hosted URL management application for individuals and teams
that want to retain control of their domains and data.

## Links and organisation

- Create short links with generated or custom slugs on the instance's Default Domain or verified workspace Domains.
- Organise links with folders and tags. Workspaces separate team content and access.
- Schedule activation, set expiration dates and visit limits, require a visitor password, or configure a Fallback URL.
- Move, tag, enable, disable, or archive selected links with bulk actions.
- Export filtered links as CSV and preview create-only imports before confirming them.
- Build campaign URLs with the UTM builder and reusable workspace UTM Templates.
- Review who changed a link, preview an earlier revision, and restore it as a new revision.

Slugs are unique within a domain and stay reserved until permanent deletion.
Generated slugs default to six non-ambiguous characters. Reserved slugs and
prefixes protect application routes. Destination URLs must use HTTP or HTTPS
and must not create obvious resolution loops.

## Routing and publishing

- Use Smart Routing with ordered conditions and weighted split tests.
- Edit routing visually and simulate visits with a Routing Preview before saving.
- Check Destination Health outside the redirect path, see confirmed incidents, and optionally enable scheduled Destination Monitoring.
- Customise social Link Previews with a title, description, and uploaded image.
- Publish Link Pages of selected Short Links at `/p/{path}` on the application host.
- Publish App Associations for iOS Universal Links and Android App Links on active workspace-owned Domains.

Availability, visit limits, and password checks run before Smart Routing.
Destination Checks never change a redirect. When a short link is unavailable
and has no applicable Fallback URL, visitors see a neutral unavailable page;
detailed reasons remain in the dashboard and analytics.

## QR codes

Create named, workspace-owned QR Codes linked to Short Links or containing a
native payload such as a URL, Wi-Fi configuration, or contact details.

- Design in the QR studio with colours, module and eye styles, quiet zones, transparency, error correction, and an optional logo.
- Save and apply workspace QR Code Templates. Applying copies the design; later template changes do not alter existing QR Codes.
- Run a Readability Check on the rendered design, with contrast, quiet-zone, logo, and print-size guidance.
- Export PNG or SVG at a chosen size.

Linked QR Codes inherit the Short Link's rules and routing and have their own
scan analytics. Direct-payload QR Codes encode content without contacting
Openlink, so they do not produce scan analytics and must be re-exported when
their content changes.

## Analytics and integrations

- Report visits, scans, referrers, campaigns, approximate countries, devices, and resolution outcomes without creating visitor profiles.
- Filter reports, compare periods, export CSV, and keep personal Saved Views.
- Schedule weekly Workspace Summary emails for selected members.
- Enable conversion goals per link and redeem single-use Attribution Tokens through a server-to-server API call.
- Subscribe to signed webhooks for short link changes, with delivery history and retries.
- Use user-owned API tokens to automate the documented HTTP API endpoints.

Analytics retention defaults to 12 months; lifetime totals remain while the
related link or QR code exists. Conversion tracking is optional. Link Page
views are not currently recorded, and Link Pages have no API management.
The API covers the [documented endpoints](api.md), rather than every dashboard
feature. Webhook endpoints are managed in workspace settings.

## Access and administration

Members join through invite links and receive an Owner, Admin, Editor, or Viewer
role. Folders and tags organise content; they do not restrict access within a
workspace.

Accounts support email/password, optional email verification, two-factor
authentication, and optional Google or Discord sign-in. Users manage connected
identities and provider avatars from their profile.

Instance administrators configure product settings, inspect infrastructure
Diagnostics, and view release information. Infrastructure secrets stay in the
deployment configuration. An optional Docker Compose updater can update
installations using the stable `latest` image tag.

## Run Openlink

The GHCR image supports `linux/amd64` and `linux/arm64`. Choose `latest` for
stable releases, `beta` to follow beta publications, or an exact release tag to
pin a version. Publishing an image does not deploy an installation.

See the [Docker guide](../docker/README.md) to start the example stack, the
[deployment guide](deployment.md) for production, or the
[functional reference](functional-spec.md) for detailed behaviour.
