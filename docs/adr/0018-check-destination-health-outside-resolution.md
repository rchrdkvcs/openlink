# Check Destination health outside public resolution

Openlink checks whether Destination URLs are reachable with background Destination Checks that never run during public resolution: a visitor is always redirected exactly as the Short Link's lifecycle and Smart Routing decide, whatever the latest Destination Health says. Checks are deduplicated per Workspace and keyed by the exact URL, so one check covers the default destination, Fallback URL, and enabled Routing Rule or split-test variant destinations of every Short Link that references the URL, and references are derived from the current links at read time rather than stored. A Short Link whose destination changes therefore shows the new URL as not checked instead of inheriting a result that described the old one.

Outbound requests are made by a dedicated client that resolves the hostname itself, rejects the request unless every resolved address is public, and pins the connection to the validated address so DNS rebinding cannot swap the target between validation and connection. Redirects are followed manually and every hop is revalidated the same way. Requests are anonymous: they carry a fixed user agent, no cookies, no credentials from the URL, and no visitor data, and response bodies are never read or stored.

## Result classification

A check sends `HEAD` first and retries with `GET` when `HEAD` is not answered with a success, because many servers reject or mishandle `HEAD`. The `GET` response is classified on its status line and headers only:

- `2xx`, and `3xx` without a `Location`, are Reachable.
- `404`, `410`, and `5xx` are HTTP failures.
- `401`, `403`, `429`, any other `4xx`, and challenge responses are Inconclusive, because bot protection and authentication walls do not mean the page was deleted.
- DNS resolution failures, TLS failures, refused or reset connections, and timeouts are reported separately.
- Destinations that resolve to non-public addresses, use alternate numeric host forms, or redirect to such targets are Blocked and are never contacted.
- Too many redirects is an HTTP failure.

Only HTTP, DNS, TLS, connection, and timeout results count as failures. A Destination Incident opens after two consecutive failures and closes at the next Reachable result; Inconclusive and Blocked results neither confirm nor close an incident.

## Consequences

Scheduled Destination Monitoring is an instance-wide opt-in for the Instance Admin, because it makes the server contact third-party hosts on a timer. Explicit checks from the dashboard are always available to Editors and above and run on the queue. Checks are bounded per run, deduplicated per destination with unique queued jobs, limited per host in both rate and concurrency, capped by a small number of global slots, and constrained by short timeouts, an overall deadline per check, and a small redirect limit. The MVP surfaces incidents in the dashboard only: it does not notify, and it never replaces a destination or switches to a Fallback URL automatically.
