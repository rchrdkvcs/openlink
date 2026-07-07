# Short URLs are editable after creation

The update endpoint originally accepted only a link's settings — the domain and slug were fixed at creation, though no documented decision required that. Short URLs are now editable: the edit drawer reuses the same destination hero and domain/slug composer as creation, and the update action accepts `domain_id` and `slug` (validated through `SlugService` only when the address actually changes, so an unchanged address never re-triggers uniqueness checks against itself).

Changing a short URL invalidates the resolution cache for the old address as well as the new one — the `ShortLink` saved hook reads the original slug and domain before Laravel syncs them — so the old address stops resolving immediately instead of lingering for the cache TTL. Printed QR codes survive an address change because they encode `/qr/{token}`, not the short URL; copies of the old URL already shared do break, which the edit drawer warns about inline before saving.
