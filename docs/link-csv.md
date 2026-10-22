# Link CSV format

A Link CSV is a portable file of Short Link configuration. Openlink writes it from the Links page (**Export CSV**) or `GET /api/v1/links/export`, and reads it through a Link Import (**Import CSV** or `POST /api/v1/links/import`). This page describes format version 1.

## Columns

Exports always write these columns in this order. Imports read any subset: columns are matched by header name, or chosen by hand in the import dialog.

| Column | Values | Import default when empty |
| --- | --- | --- |
| `format_version` | `1` | Treated as version 1 |
| `domain` | Domain hostname, for example `go.example.com` | The domain chosen for the import (the preferred domain by default) |
| `slug` | Slug without a leading slash, for example `spring/sale` | A slug is generated |
| `destination_url` | HTTP or HTTPS URL, required | Row is invalid |
| `fallback_url` | HTTP or HTTPS URL | No fallback URL |
| `folder` | Folder name, up to 120 characters | No folder |
| `tags` | Tag names separated by commas inside one cell, for example `"spring,promo"` | No tags |
| `enabled` | `true` or `false` (`1`/`0`, `yes`/`no` are also read) | `true` |
| `archived` | `true` or `false` | `false` |
| `activates_at` | ISO 8601 date-time | No activation date |
| `expires_at` | ISO 8601 date-time | No expiration date |
| `visit_limit` | Whole number of at least 1 | No visit limit |

Other columns are ignored, including IDs, workspace IDs, and visit counts copied from another tool. Common header names from other tools are recognised, such as `url`, `long_url`, `destination`, `key`, `short_code`, and `hostname`.

## Encoding and structure

- Files are UTF-8. Exports start with a byte order mark so spreadsheet applications detect the encoding; imports accept files with or without it and reject other encodings.
- The first row is the header row. Exports separate cells with commas; imports detect commas, semicolons, or tabs from the header row.
- Cells follow RFC 4180 quoting: cells containing the separator, double quotes, or line breaks are wrapped in double quotes, and double quotes inside them are doubled. A backslash has no special meaning.
- Quoted cells can span several lines. Line breaks are kept in folder and tag names; URLs and slugs with line breaks are invalid.
- Leading and trailing spaces are trimmed from every cell. An empty cell means the value is not set.
- Blank lines are skipped. Rows are reported by the line of the file they start on, counting the header as line 1, so blank lines and quoted cells spanning several lines keep the numbers in step with a text editor. Spreadsheet applications show a multiline cell as one row, so their row numbers can be lower.

## Dates and time zones

Exports write dates in UTC as `2026-10-21T09:00:00Z`. Imports read ISO 8601 dates with or without a time and offset, such as `2026-10-21`, `2026-10-21 09:00`, or `2026-10-21T09:00:00+02:00`. A value without an offset is read as UTC. Impossible dates such as `2026-02-31` are invalid.

## Tags

Tag names cannot contain commas, so a single cell holds all of a link's tags separated by commas. Exports sort tag names alphabetically. Imports trim each name and ignore empty names and repeats.

## Spreadsheet formulas

Spreadsheet applications can run a cell as a formula when it starts with `=`, `+`, `-`, `@`, a tab, or a carriage return. Exports prefix such cells with a single quote (`'`), so `=SUM(A1)` is written as `'=SUM(A1)`. A value that already starts with quotes followed by one of those characters gets one more quote. Imports remove exactly one leading quote from cells that match this pattern, so exported values read back unchanged. Unescaped formula-like values are stored as plain text and are never evaluated.

## Not included

Exports leave out visitor passwords and their hashes, Smart Routing rules, QR Codes, visits, scans, and other analytics, and all IDs. Imported links therefore have no visitor password and no routing rules, and start with no visits. Importing never resets or rebuilds the visits or analytics of existing links.

## Importing

A Link Import is create-only and runs in two steps:

1. **Preview.** The file is read and every row is validated. Nothing is created or reserved, including folders, tags, and slugs. Each row is reported as ready or invalid with its reasons.
2. **Import.** After confirmation the file is sent again and every row is validated again, including permissions, domain availability, and slug uniqueness. Ready rows are created; the rest are skipped with a reason. Each row is created on its own, so rows created before an unexpected failure stay created, and a retry skips them as already imported or as slug conflicts.

Rules:

- A file holds at most 1,000 links and 1 MB.
- Folders and tags are matched by name in the current workspace and created there when missing.
- A short URL is kept only when its domain is an active domain of the current workspace, or the instance default domain. Domains of other workspaces and domains the workspace does not control are rejected. Links from a shared domain run by another service cannot keep their public short URL through an import; leave the `domain` column unmapped to give them a short URL on a domain you control.
- A slug already used on its domain, a reserved slug, an invalid slug, or a destination URL that points to its own short URL makes the row invalid. Existing links are never changed.
- Loop detection matches the rest of Openlink: only a destination URL that points to the row's own short URL is rejected. Longer loops, such as two links pointing at each other, are not detected, in the file or against existing links.
- A row repeating the domain and slug of an earlier row, or repeating an earlier row without a slug, is reported as a duplicate.
- Rows without a slug are remembered by their content. Importing the same row again, from a retry or another file, is skipped instead of creating a second link with a new generated slug.
- Archived rows are created as Archived Links and keep their slug reserved.
- Only Owners, Admins, and Editors can import. Every member, including Viewers, can export.
