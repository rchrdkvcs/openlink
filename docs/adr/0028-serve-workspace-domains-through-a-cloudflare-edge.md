# Serve workspace domains through a Cloudflare edge

The hosted instance moves to a managed platform that routes only the hostnames registered on it and charges for each one. Registering every workspace domain on the platform would tie domain setup to its API and quotas. Openlink therefore keeps a fixed set of hostnames on the platform (the application host, the Default Domain and an origin host) and serves workspace domains through a Domain Edge.

The Domain Edge is chosen with `OPENLINK_DOMAIN_EDGE`. `direct` keeps the self-hosted behaviour (ADR 0003): domains point at the instance's own reverse proxy and become active when their DNS resolves to the DNS Target. `cloudflare` uses Cloudflare for SaaS: workspace members add a single CNAME to the DNS Target, Openlink registers the domain as a custom hostname, and a Worker forwards the traffic to the origin host.

## Decisions

- **Ownership before provisioning.** The TXT record from ADR 0001 is still required. Openlink creates the custom hostname only once ownership is proven, so unverified or abandoned domains never consume paid hostnames.
- **Active means served.** With the Cloudflare edge, a domain becomes active when its custom hostname and certificate are both active, replacing the IP comparison. Observed traffic still activates an ownership-verified domain.
- **HTTP certificate validation.** Custom hostnames use HTTP validation and TLS 1.2 or later, so the CNAME is the only routing record a member adds.
- **Signed forwarding.** The Worker sends the visitor's host, IP address and country in `X-Openlink-*` headers with a shared secret. A global middleware applies them only when the secret matches one of `OPENLINK_EDGE_SECRET`, so the rest of the application keeps reading the request host, IP address and `CF-IPCountry`. The secret is infrastructure configuration and stays in the environment (ADR 0002).
- **Lifecycle stays in Openlink.** Disabling or deleting a domain removes its custom hostname. Cloudflare outages leave a domain waiting with a message and are retried by the hourly check instead of failing the request.

## Considered options

- **Register every workspace domain on the hosting platform.** Rejected: it ties the product to one platform's API, per-domain pricing and limits, and asks members for platform-specific records.
- **Cloudflare Origin Rules to rewrite the host.** Rejected: host header overrides require an Enterprise plan, and the platform rejects unknown hosts.
- **A self-operated proxy with on-demand TLS.** Not chosen: it would support apex domains with a fixed IP address but adds a server to operate. Apex domains remain supported on the Cloudflare edge when the member's DNS provider offers CNAME flattening, `ALIAS` or `ANAME` records.
