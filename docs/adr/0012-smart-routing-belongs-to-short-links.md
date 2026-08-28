# Smart routing belongs to short links

Smart Routing is a capability of a Short Link, not of an individual QR Code: QR Codes linked from the dedicated QR Codes area inherit the Short Link's routing while keeping scan attribution. Routing Rules are ordered and the first matching enabled rule chooses the Destination URL; split tests are rules with weighted variants, assigned from the daily visitor hash so the experience is stable within a day without creating a long-term visitor profile. Routing conditions are stored as versioned JSON and evaluated from the same resolution context captured for analytics, so routing decisions and analytics dimensions cannot drift apart.

**Consequences**

Analytics events record the matched `routing_rule_id` and `routing_variant_id` without storing the final Destination URL. A missing match is reported as the Default destination. Smart Routing runs only after link availability, visit limit, and password checks pass, so it cannot bypass Short Link lifecycle or protection rules.
