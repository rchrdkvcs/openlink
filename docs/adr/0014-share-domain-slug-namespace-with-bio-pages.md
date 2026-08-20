# Share the Domain and Slug namespace with Bio Pages

Bio Pages use the same Domain-and-Slug public addressing model as Short Links. For a given Domain, one Slug can identify either a Short Link or a Bio Page, never both. Public Domain requests therefore resolve through a shared namespace and may return either a redirect or a rendered Published Version, while authenticated application routes remain confined to the application host.

Using paths such as `example.com/alice` makes Bio URLs suitable for public profiles and preserves the value of workspace-owned Domains. A separate `/bio/` prefix would simplify routing but produce less desirable public addresses, while independent namespaces would make the same public URL ambiguous. Because existing Domain documentation described these hosts as redirect-only, this decision deliberately expands them to public-resolution surfaces without turning them into authenticated application hosts.

This ADR revises only the “workspace-owned Domains remain redirect-only” constraint recorded in ADR 0009. Its authentication decision remains unchanged: OAuth callbacks and every authenticated surface continue to use the canonical application host.
