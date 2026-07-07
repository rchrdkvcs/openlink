# Smart Routing PRD

Status: ready-for-agent

## Context

Openlink users need Short Links that can send visitors to different Destination URLs based on visitor context, campaign parameters, time windows, or weighted split tests. The feature must be powerful enough for advanced routing while preserving the current drawer's progressive-disclosure workflow.

## Decisions

- Smart Routing belongs to the Short Link and is inherited by all attached QR Codes.
- The UI names the feature `Smart Routing`, the drawer tab `Routing`, and individual objects `Routing Rules`.
- Routing Rules are manually ordered and use first-match-wins semantics.
- A Routing Rule can contain multiple conditions with one logical mode: `all` or `any`.
- Nested boolean groups and regex operators are out of scope.
- Split testing is a Routing Rule type with optional conditions and weighted Routing Variants.
- Split test assignment is stable for the visitor only within the current day.
- Smart Routing runs after domain, lifecycle, visit-limit, and password checks pass.
- The Short Link's `destination_url` is the Default destination when no rule matches.
- Visit limits remain global to the Short Link, not per rule or variant.
- Routing analytics track `routing_rule_id` and `routing_variant_id`; they do not duplicate final Destination URLs into each event.
- Routing and analytics share one Resolution Context.

## Scope

### Rule Types

- Conditional rule: conditions choose a single Destination URL.
- Split test rule: conditions optionally gate weighted Routing Variants.

### Condition Types

- `country`
- `language`
- `device_type`
- `browser`
- `operating_system`
- `referrer_host`
- `referrer_channel`
- `utm_source`
- `utm_medium`
- `utm_campaign`
- `utm_term`
- `utm_content`
- `date_time`
- `day_of_week`
- `time_of_day`

### Operators

- Text/enumerated operators: `is`, `is_not`, `contains`, `does_not_contain`, `starts_with`, `ends_with`, `is_empty`, `is_not_empty`
- Time operators: `before`, `after`, `between`
- Unknown values only match `is_empty`; every other non-empty operator fails on missing data.

## UX

- Create drawer has `Link` and `Routing` tabs.
- Edit drawer has `Link`, `Routing`, and `QR code` tabs.
- The Routing tab shows an empty state with quick actions for Country, Device, Campaign, Time, Split test, and Custom rules.
- Existing rules render as cards with summary text, enable toggle, duplicate, delete, edit, and manual reorder controls.
- One rule is edited at a time inside the Routing tab.
- Conditional rules show a condition builder and one Destination URL.
- Split test rules show optional conditions and at least two active variants with Destination URLs and weights.
- Invalid active rules cannot be saved. Disabled rules are ignored.

## Data Model

- `routing_rules`
  - `short_link_id`
  - `name`
  - `type`
  - `position`
  - `is_enabled`
  - `match_mode`
  - `conditions_version`
  - `conditions`
  - `destination_url`
- `routing_variants`
  - `routing_rule_id`
  - `name`
  - `position`
  - `is_enabled`
  - `destination_url`
  - `weight`
- `analytics_events`
  - `routing_rule_id`
  - `routing_variant_id`

## API

- `POST /api/v1/links` accepts nested `routing_rules`.
- `PATCH /api/v1/links/{id}` replaces nested `routing_rules`.
- `GET /api/v1/links/{id}` returns nested `routing_rules`.
- Dedicated routing-rule endpoints are out of scope for v1.

## Acceptance Criteria

- Users can create a Short Link with Smart Routing configured before the link is saved.
- Users can edit Smart Routing from the link drawer.
- Public resolution uses the first enabled matching Routing Rule.
- Public resolution falls back to the Short Link's Destination URL when no rule matches.
- QR Codes inherit Smart Routing and still record scan attribution.
- Split tests choose weighted variants consistently for the same daily visitor hash.
- Analytics events store the matched rule and variant IDs.
- Active invalid rules and variants are rejected on create and update.
- The Routing tab remains usable with multiple rules without expanding every rule at once.
