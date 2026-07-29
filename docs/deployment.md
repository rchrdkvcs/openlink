# Deployment

Publishing a new stable GitHub Release triggers the `deploy.yml` workflow. The
workflow confirms that it is GitHub's latest release, then calls the Coolify
deployment webhook.

Configure these repository secrets:

- `COOLIFY_WEBHOOK`
- `COOLIFY_TOKEN`

Drafts, prereleases, and releases that are not marked as latest are not deployed.
