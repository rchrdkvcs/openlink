# Domain Docs

This repo is a single-context Openlink codebase.

## Before exploring, read these

- `CONTEXT.md` at the repo root for domain language.
- Relevant ADRs in `docs/adr/` for architectural decisions.
- Product and implementation docs in `docs/` when the task touches product scope, behavior, security, or deployment.

If a file does not exist, proceed silently. Do not create domain docs upfront; producer skills create them lazily when terms or decisions are resolved.

## File structure

```text
/
├── CONTEXT.md
├── docs/
│   ├── adr/
│   ├── product-scope.md
│   ├── functional-spec.md
│   ├── technical-spec.md
│   ├── security-and-privacy.md
│   └── deployment.md
└── src/
```

## Use the glossary vocabulary

When output names a domain concept, use the term as defined in `CONTEXT.md`. Do not drift to synonyms the glossary explicitly avoids.

If the concept needed is not in the glossary yet, note it as a gap for `grill-with-docs` instead of inventing competing language.

## Flag ADR conflicts

If output contradicts an existing ADR, surface it explicitly rather than silently overriding it.
