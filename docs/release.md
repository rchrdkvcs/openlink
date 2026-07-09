# Release Process

Openlink releases are generated from Conventional Commits with `semantic-release`.

## Branch flow

1. Merge day-to-day work into `develop`.
2. Open a pull request from `develop` to `master`.
3. Keep the pull request title in Conventional Commit format, for example `feat: add workspace exports` or `fix(auth): handle expired tokens`.
4. Merge the pull request into `master`.
5. The release workflow runs the quality gates, creates the next semantic tag, and publishes a GitHub Release.

The release workflow only runs from `master` and tags releases as `vX.Y.Z`.

## Version rules

- `fix:` and `perf:` create a patch release.
- `feat:` creates a minor release.
- `feat!:` / `fix!:` or a `BREAKING CHANGE:` footer creates a major release.
- `docs:`, `test:`, `refactor:`, `style:`, `build:`, `ci:`, and `chore:` do not create a release by default.

When using squash merges, the final commit on `master` is usually built from the pull request title. The CI workflow validates PR titles so release-worthy work keeps an analyzable commit message.

## First release

If the repository has no existing semantic tag, `semantic-release` uses the full commit history to calculate the first version. Create an initial tag manually before enabling releases if the project should start from a specific baseline, for example `v0.1.0`.

## Local dry run

Run a local release simulation with:

```bash
pnpm run release:dry
```

The real release requires GitHub Actions because it uses the repository `GITHUB_TOKEN` to create tags and GitHub Releases.
