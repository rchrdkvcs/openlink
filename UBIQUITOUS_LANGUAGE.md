# Ubiquitous Language

## Product and ownership

| Term | Definition | Aliases to avoid |
| --- | --- | --- |
| **Workspace** | An owned space that groups links, domains, QR codes, members, permissions, and settings for a personal or team context. | Account, organisation, tenant |
| **Member** | A user who belongs to a workspace. | Collaborator, teammate |
| **Workspace Role** | A member's baseline permission level within a workspace. | User role, account role |
| **Owner** | The workspace role that controls all workspace data, settings, members, domains, and ownership-level actions. | Super admin, creator |
| **Admin** | The workspace role that manages members, domains, folders, links, QR codes, and settings without owning the workspace. | Manager, administrator |
| **Editor** | The workspace role that creates and changes links and QR codes in accessible folders. | Contributor, member |
| **Viewer** | The workspace role that reads links, QR codes, and analytics in accessible folders. | Reader, guest |
| **Workspace Settings** | Settings that affect one workspace and the links, domains, members, and defaults inside it. | Account settings, organisation settings |
| **Instance Admin** | A user who administers the whole Openlink installation. | Super admin, system admin |
| **Instance Admin Panel** | The administrative area for product settings that affect the whole Openlink installation. | System settings, global admin |

## Organisation and access boundaries

| Term | Definition | Aliases to avoid |
| --- | --- | --- |
| **Folder** | A shareable container used to organise links and control member access within a workspace. | Directory, collection, project |
| **Folder Permission** | A member's access level for a folder. | Sharing permission, folder role |
| **Tag** | A label used to classify and find links without granting access to them. | Category, label |
| **Invite Link** | A revocable link that lets anyone who opens it join a workspace with the link's role while the link is usable. | Invitation, invite, invite code, access request |

## Bio pages

| Term | Definition | Aliases to avoid |
| --- | --- | --- |
| **Bio Page** | A workspace-owned, profile-style public page that presents an identity and ordered destinations independently from Folders. | Link in bio, profile page, landing page, microsite |
| **Bio URL** | The public URL of a Bio Page, made from a Domain and Slug in the same namespace as Short URLs. | Bio link, profile URL, page URL |
| **Bio Element** | An ordered destination, social destination, section heading, or short text on a Bio Page. | Block, widget, card |
| **Draft Version** | The editable Bio Page version that can be previewed but does not affect visitors until publication. | Draft page, unpublished page |
| **Published Version** | The most recently published Bio Page version served at its Bio URL. | Published page, live page |

## Short link lifecycle

| Term | Definition | Aliases to avoid |
| --- | --- | --- |
| **Short Link** | The managed object that connects a domain and slug to a destination URL with access rules, lifecycle rules, and analytics. | TinyURL, shortened URL, link object |
| **Short URL** | The public URL made from a domain and slug. | Shortened URL, public link |
| **Destination URL** | The final target URL a visitor should reach when a short link resolves successfully. | Target URL, original URL, redirect URL |
| **Slug** | The path segment that identifies a short link within a domain. | Alias, key, code |
| **Fallback URL** | An optional destination used when a short link cannot resolve because it is unavailable. | Expiration URL, backup URL, redirect URL |
| **Enabled Link** | A short link that the owner allows to resolve when its lifecycle rules also allow it. | Published link, live link |
| **Disabled Link** | A short link that the owner has manually prevented from resolving. | Unpublished link, inactive link |
| **Archived Link** | A short link removed from primary dashboard views while preserved for history and analytics. | Hidden link, old link |
| **Scheduled Link** | A short link whose activation date is in the future. | Pending link, upcoming link |
| **Active Link** | A short link whose lifecycle rules currently allow it to resolve. | Live link, valid link |
| **Expired Link** | A short link whose lifecycle rules no longer allow it to resolve because its expiration date or visit limit has been reached. | Dead link, invalid link |
| **Protected Link** | A short link that requires a visitor to provide a password before it can resolve to the destination URL. | Locked link, private link, password link |
| **Visit Limit** | The maximum number of successful visits a short link allows before it becomes expired. | Click limit, visitor maximum, max clicks |

## Domains and resolution

| Term | Definition | Aliases to avoid |
| --- | --- | --- |
| **Domain** | A workspace-owned internet name used to publish Short URLs and Bio URLs. | Custom domain, host, hostname |
| **DNS Target** | The instance-level IP address or hostname that workspace domains must point to. | Server address, origin |
| **Default Domain** | The instance-level Domain available for creating Short URLs and Bio URLs without adding a workspace-owned Domain. | Primary domain, system domain |
| **Preferred Domain** | The workspace-selected Domain used by default for new Short Links and Bio Pages. | Default workspace domain, primary workspace domain |
| **Visit** | A successful resolution of a short link that sends a visitor to the destination URL. | Click, hit, request |
| **Resolution Outcome** | The result recorded for a short URL resolution attempt. | Redirect result, request status |

## QR codes and smart routing

| Term | Definition | Aliases to avoid |
| --- | --- | --- |
| **QR Code** | A named scannable entry point that attaches to a Short Link or Bio Page, or carries a direct payload. | QR image, code |
| **Scan** | A successful Openlink resolution that starts from a QR Code and opens its attached Short Link or Bio Page. | QR click, QR hit |
| **Smart Routing** | The short link capability that chooses a destination URL from ordered routing rules. | Conditional redirects, routing engine, dynamic rules |
| **Routing Rule** | An ordered condition attached to a short link that can send matching visitors to a specific destination URL. | Rule, redirect rule, target rule |
| **Smart Destination** | A destination rule that chooses where to send a visitor based on device, platform, or app context. | Deep link, dynamic destination |
| **App Link Preset** | A predefined smart destination configuration for a known app or service. | Deep link preset, app redirect |
| **Web Fallback** | The required browser destination used when a smart destination cannot open a native app. | Fallback link, desktop URL |

## Identity and credentials

| Term | Definition | Aliases to avoid |
| --- | --- | --- |
| **User** | An authentication identity that can sign in to Openlink. | Account, login |
| **Connected Identity** | An external OAuth identity linked to a user and usable for sign-in when its verified provider email matches the user's verified email. | Linked account, social account, OAuth account |
| **Profile Avatar** | The user-selected image shown as the user's visual representation across Openlink. | Global avatar, account avatar, social avatar |
| **Verified Email** | A user's confirmed email address. | Validated email, confirmed account, active account |
| **API Token** | A revocable user-owned credential used by external clients to access the Openlink API. | API key, access key, bearer key |

## Analytics

| Term | Definition | Aliases to avoid |
| --- | --- | --- |
| **Analytics** | Aggregated measurements about Visits, Scans, Bio Views, Bio Activations, referrers, approximate geography, devices, and outcomes. | Tracking, visitor profile, user tracking |
| **Bio View** | A visitor loading the Published Version of a Bio Page. | Page visit, impression, view event |
| **Bio Activation** | A visitor following a destination from a Published Version. | Bio click, link click, engagement |
| **Visitor** | A person or automated client attempting to resolve a short URL or QR code entry point. | User, customer |
| **Unique Visitor** | A privacy-preserving daily visitor hash counted once per day. | Visitor profile, identified visitor |

## Relationships

- A **Workspace** has one or more **Members**.
- A **Member** has exactly one **Workspace Role** per **Workspace**.
- A **Workspace** owns zero or more **Domains**, **Folders**, **Short Links**, **Bio Pages**, **QR Codes**, and **Invite Links**.
- A **Short Link** belongs to exactly one **Workspace**.
- A **Short Link** combines exactly one **Domain** and exactly one **Slug** into one **Short URL**.
- A **Short Link** has exactly one primary **Destination URL** and may have zero or one **Fallback URL**.
- A **Short Link** may belong to zero or one **Folder** and may have zero or more **Tags**.
- A **Folder** controls access through **Folder Permissions**; a **Tag** never grants access.
- A **Bio Page** has one **Draft Version** and may have one **Published Version**.
- A **Bio URL** and **Short URL** cannot share the same **Domain** and **Slug**.
- A **Bio Element** may reference one **Short Link** or carry one direct destination.
- A **QR Code** may attach to exactly one **Short Link** or **Bio Page**, or carry one direct payload.
- A **Scan**, **Bio View**, **Bio Activation**, and **Visit** are distinct analytics measurements.
- **Smart Routing** belongs to a **Short Link**, and a **QR Code** attached to that short link inherits it.
- A **Routing Rule** belongs to exactly one **Short Link** and may choose a **Destination URL**.
- A **Connected Identity** belongs to exactly one **User** and is valid for sign-in only while its provider email matches the user's **Verified Email**.
- A **Profile Avatar** is selected from a valid **Connected Identity** avatar or falls back to initials.
- **Analytics** aggregate **Visits**, **Scans**, **Bio Views**, **Bio Activations**, and **Resolution Outcomes** without identifying individual **Visitors**.

## Example dialogue

> **Dev:** "When a visitor opens a **Short URL**, do we count it as a **Visit** before checking the **Visit Limit**?"
> **Domain expert:** "No. A **Visit** is only a successful resolution to the **Destination URL**. If the **Visit Limit** is already reached, the **Short Link** is an **Expired Link** and records a failed **Resolution Outcome** instead."
> **Dev:** "If the **Short Link** has **Smart Routing**, can a **QR Code** choose a different routing setup?"
> **Domain expert:** "No. **Smart Routing** belongs to the **Short Link**. A **QR Code** is a named scannable entry point and inherits the short link's routing while keeping separate **Scan** analytics."
> **Dev:** "Should a **Tag** restrict who can see that short link?"
> **Domain expert:** "No. A **Folder** and its **Folder Permissions** control access. A **Tag** is only for classification and discovery."
> **Dev:** "Can a **Connected Identity** remain visible after the user changes email?"
> **Domain expert:** "Yes, but it stops being a valid sign-in method until its provider email matches the user's **Verified Email** again."

## Flagged ambiguities

- "Account" is ambiguous. Use **Workspace** for the owned product space, **User** for the authentication identity, and **Connected Identity** for an external OAuth identity.
- "Link" is too broad in product language. Use **Short Link** for the managed object, **Short URL** for the public address, **Invite Link** for joining a workspace, and **Fallback URL** or **Destination URL** for resolution targets.
- "Redirect URL" is ambiguous. Use **Destination URL** for the successful target and **Fallback URL** for the unavailable-link target.
- "Click" is not the canonical analytics term. Use **Visit** for successful Short Link resolution, **Scan** for successful QR-Code-started resolution, and **Bio Activation** for following a Bio Page destination.
- "Custom domain" should not be used as the canonical product term. Use **Domain** for a workspace-owned hostname, **Default Domain** for the instance-level fallback, and **Preferred Domain** for the workspace default.
- "Role" is overloaded. Use **Workspace Role** for Owner/Admin/Editor/Viewer and **Folder Permission** for Can view/Can edit/Can manage.
- "Owner" is a workspace role, not the same concept as **Instance Admin**.
- "Social account" and "OAuth account" should not name the product concept. Use **Connected Identity**; implementation details may still use legacy table names where already recorded.
- "Tracking" can imply visitor identification. Use **Analytics** and keep the definition tied to aggregate, non-identifying measurements.
