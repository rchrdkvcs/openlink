# Openlink

Openlink is a URL management product for creating, organising, sharing, and tracking shortened links across personal and team contexts.

## Language

**Workspace**:
An owned space that groups links, domains, QR codes, members, and settings for a personal or team context. Every short link belongs to exactly one workspace.
_Avoid_: Account, organisation, tenant

**Folder**:
A container used to organise links within a workspace. Folders do not grant or restrict access.
_Avoid_: Directory, collection, project, permission group

**Tag**:
A label used to classify and find links without granting access to them.
_Avoid_: Category, label

**Short Link**:
The managed object that connects a domain and slug to a destination URL, along with its access rules, lifecycle rules, and tracking.
_Avoid_: TinyURL, shortened URL, link object

**Destination URL**:
The final target URL a visitor should reach when a short link resolves successfully.
_Avoid_: Target URL, original URL, redirect URL

**Slug**:
The path segment that identifies a short link within a domain. A slug can be generated automatically or chosen manually, is unique within its domain, and remains reserved until the short link is permanently deleted.
_Avoid_: Alias, key, code

**Short URL**:
The public URL made from a domain and slug.
_Avoid_: Shortened URL, public link

**Domain**:
A workspace-owned hostname used to publish short URLs. A domain must be active before it can serve short links: ownership is proven with a DNS TXT record, and the domain becomes active once its DNS points to the server — confirmed by an IP check or by real traffic reaching the server on that hostname.
_Avoid_: Custom domain, host, hostname

**DNS Target**:
The instance-level IP address or hostname that workspace domains must point to. Configured by the instance admin; falls back to the default domain when unset.
_Avoid_: Server address, origin

**Default Domain**:
The instance-level domain available for creating short URLs without adding a workspace-owned domain.
_Avoid_: Primary domain, system domain

**Preferred Domain**:
The workspace-selected domain used by default when creating new short links in that workspace.
_Avoid_: Default workspace domain, primary workspace domain

**Fallback URL**:
An optional destination used when a short link cannot resolve because it is scheduled, expired, disabled, or otherwise unavailable.
_Avoid_: Expiration URL, backup URL, redirect URL

**Enabled Link**:
A short link that the owner allows to resolve when its lifecycle rules also allow it.
_Avoid_: Published link, live link

**Disabled Link**:
A short link that the owner has manually prevented from resolving, regardless of its lifecycle rules.
_Avoid_: Unpublished link, inactive link

**Archived Link**:
A short link removed from primary dashboard views while preserved for history and analytics. An archived link does not resolve and keeps its slug reserved.
_Avoid_: Hidden link, old link

**Scheduled Link**:
A short link whose activation date is in the future.
_Avoid_: Pending link, upcoming link

**Active Link**:
A short link whose lifecycle rules currently allow it to resolve.
_Avoid_: Live link, valid link

**Expired Link**:
A short link whose lifecycle rules no longer allow it to resolve because its expiration date or visit limit has been reached.
_Avoid_: Dead link, invalid link

**Visit**:
A successful resolution of a short link that sends a visitor to the destination URL.
_Avoid_: Click, hit, request

**Visit Limit**:
The maximum number of successful visits a short link allows before it becomes expired.
_Avoid_: Click limit, visitor maximum, max clicks

**Scan**:
A successful resolution that starts from a QR code and sends a visitor to the destination URL.
_Avoid_: QR click, QR hit

**QR Code**:
A named scannable entry point. A QR Code can be attached to a short link, where scans enter through the short link's domain and have their own scan analytics, or it can carry a direct payload such as URL, text, email, phone, SMS, Wi-Fi, vCard, calendar event, location, or a raw QR payload. Exported short-link QR Code images encode a stable Openlink URL so their served destination can be changed later without replacing the image. Exported direct-payload QR Code images encode the native payload so device scanners can handle the QR Code directly.
_Avoid_: QR image, code

**Protected Link**:
A short link that requires a visitor to provide a password before it can resolve to the destination URL.
_Avoid_: Locked link, private link, password link

**Smart Routing**:
The short link capability that chooses a destination URL from ordered routing rules when a visitor resolves the short link. QR codes attached to the short link inherit its smart routing.
_Avoid_: Conditional redirects, routing engine, dynamic rules

**Routing Rule**:
An ordered condition attached to a short link that can send matching visitors to a specific destination URL.
_Avoid_: Rule, redirect rule, target rule

**Smart Destination**:
A destination rule that chooses where to send a visitor based on their device, platform, or app context.
_Avoid_: Deep link, dynamic destination

**App Link Preset**:
A predefined smart destination configuration for a known app or service.
_Avoid_: Deep link preset, app redirect

**Web Fallback**:
The required browser destination used when a smart destination cannot open a native app.
_Avoid_: Fallback link, desktop URL

**Workspace Role**:
A member's baseline permission level within a workspace. Workspace roles are Owner, Admin, Editor, and Viewer.
_Avoid_: User role, account role

**Owner**:
The workspace role that controls all workspace data, settings, members, domains, and ownership-level actions.
_Avoid_: Super admin, creator

**Admin**:
The workspace role that manages members, domains, folders, links, QR codes, and settings without owning the workspace.
_Avoid_: Manager, administrator

**Editor**:
The workspace role that creates and changes links and QR codes.
_Avoid_: Contributor, member

**Viewer**:
The workspace role that reads all links, QR codes, and analytics in the workspace without creating or changing them.
_Avoid_: Reader, guest

**Instance Admin Panel**:
The administrative area for product settings that affect the whole Openlink installation.
_Avoid_: System settings, global admin

**Instance Admin**:
A user who administers the whole Openlink installation.
_Avoid_: Super admin, system admin

**Workspace Settings**:
The settings that affect one workspace and the links, domains, members, and defaults inside it.
_Avoid_: Account settings, organisation settings

**Member**:
A user who belongs to a workspace.
_Avoid_: Collaborator, teammate

**Connected Identity**:
An external OAuth identity linked to a user and used as a sign-in method when its verified provider email matches the user's verified email. A user must always retain at least one valid sign-in method when connected identities are removed.
_Avoid_: Linked account, social account, OAuth account

**Profile Avatar**:
The user-selected image shown as the user's visual representation across Openlink. A profile avatar is chosen from a valid connected identity and follows that identity's current avatar.
_Avoid_: Global avatar, account avatar, social avatar

**Verified Email**:
A user's confirmed email address. A user needs a verified email to use Openlink's main authenticated surfaces.
_Avoid_: Validated email, confirmed account, active account

**API Token**:
A revocable user-owned credential used by external clients to access the Openlink API.
_Avoid_: API key, access key, bearer key

**Invite Link**:
A revocable link created inside a workspace that lets anyone who opens it join that workspace with the link's role (Admin, Editor, or Viewer). An invite link can carry an optional expiration date and an optional usage limit, and a workspace can have several active invite links at once. Joining through an invite link cannot create a user account when the instance registration mode is closed.
_Avoid_: Invitation, invite, invite code, access request

**Analytics**:
Aggregated measurements about visits, scans, referrers, approximate geography, device types, browsers, operating systems, and resolution outcomes. Analytics do not identify individual visitors.
_Avoid_: Tracking, visitor profile, user tracking
