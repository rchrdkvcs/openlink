# Record raw analytics events after the response

The first analytics pipeline wrote pre-aggregated daily rows and lifetime totals from a queued job. In production this failed silently whenever no queue worker was running — the default Docker image runs none — so analytics recorded nothing. Aggregating at write time also fixed the set of answerable questions in advance and made per-link filtering and comparisons impossible.

Openlink now stores one row per resolution attempt in `analytics_events`, carrying every dimension: metric, outcome, referrer host and channel, approximate country, language, device type, browser, operating system, UTM parameters, a bot flag, and a visitor hash salted per day. Reports (time series, breakdowns, previous-period deltas, top links) are computed on demand with covering indexes, and a scheduled command prunes events past the configured retention.

Recording captures request dimensions synchronously — headers are unavailable after the response — then persists via `dispatchAfterResponse`, so the write happens after the redirect is flushed and works on every deployment with zero infrastructure. Instances that run a queue worker can set `OPENLINK_ANALYTICS_VIA_QUEUE=true` to move the write onto the queue. A recording failure is reported, never surfaced to the visitor.

Unique visitors are counted as distinct visitor hashes; the salt rotates daily, so visitors are only ever unique within a day and can never be tracked across days or identified, keeping the "analytics do not identify individual visitors" promise while still giving teams a visitors figure.
