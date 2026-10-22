# Import links as create-only CSV

Users need to move links into Openlink and keep a portable copy of them. Link Imports read a versioned Link CSV in a bounded synchronous request (at most 1,000 links and 1 MB) and are create-only: they never update or overwrite an existing link, so a slug conflict is a skipped row, not a merge. The preview and the confirmed import are two independent requests that each upload the file again, so no pending import is stored and the import validates everything again at execution time.

Retries must not create a second link for a row whose slug is generated, because a new random slug makes every attempt look new. Each Short Link created from such a row stores a fingerprint of the row content, unique per workspace, and later rows with the same fingerprint are skipped as already imported. Rows with an explicit slug need no fingerprint: the slug's uniqueness already rejects them. The fingerprint is dropped with the link, so deleting an imported link allows the same row to be imported again.

## Considered Options

- Recording each imported file by its hash would block a retry of the same file after fixing a domain, and would not catch the same rows in an edited file.
- Background jobs with resumable progress were deferred until imports need to exceed the synchronous bound.
- Update or overwrite mode, routing-rule JSON, QR Codes, visitor passwords, and vendor API importers are out of scope for this format version.
