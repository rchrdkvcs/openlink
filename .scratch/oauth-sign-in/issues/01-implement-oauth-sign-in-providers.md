# Implement OAuth sign-in providers

Status: done

## Context

Openlink currently supports email/password authentication, password reset, email verification, TOTP two-factor authentication, invite links, and instance-level registration modes. Add OAuth sign-in for Google and Discord without weakening the self-hosted registration and security model.

This issue implements the first increment described by ADR 0009.

## Scope

- Add Laravel Socialite and provider support for Google and Discord.
- Add a `social_accounts` table with `user_id`, `provider`, `provider_user_id`, `email`, `email_verified`, nullable `avatar_url`, timestamps, and a unique constraint on `(provider, provider_user_id)`.
- Make `users.password` nullable for OAuth-only accounts.
- Add a `SocialAccount` model and `User` relationship.
- Configure providers through environment/server config only, never product settings.
- Expose provider availability to Inertia as booleans, with no secrets.
- Add rate-limited OAuth redirect and callback routes under the application host.
- Implement OAuth redirect/callback handling for login and registration contexts.
- Keep email/password login and registration available.
- Respect `registration_mode`, first-user bootstrap, and invite links.
- Link existing accounts only when the provider email is verified and matches the user's email.
- Require verified provider email for account creation and account linking.
- Preserve Openlink TOTP 2FA after OAuth authentication.
- Support invite links for new OAuth users and existing OAuth users.
- Store provider names only when creating a new user; do not overwrite existing user names.
- Store provider avatar URLs on `social_accounts` only.
- Request minimal OAuth scopes and do not store OAuth access or refresh tokens.
- Show neutral user-facing OAuth errors and log detailed server-side causes.

## Out Of Scope

- Profile UI for manually linking or unlinking providers.
- Global user avatars.
- Storing OAuth access tokens or refresh tokens.
- Admin-panel management of OAuth secrets.
- Manual account merge flows.
- Disabling email/password authentication.

## Implementation Notes

- Google should use Socialite's built-in provider.
- Discord may use a Socialite provider extension compatible with the current Laravel and Socialite versions.
- Provider buttons should appear on login when configured.
- Provider buttons should appear on register only when registration is allowed in the current context: first user, open registration, or valid invite link.
- Direct OAuth attempts that would create an account while registration is not allowed must be refused on callback.
- OAuth callback URLs must be configured for the canonical application host, not workspace-owned redirect-only Domains.
- OAuth-only users should be able to set a password later through reset-password behavior.
- Existing profile password-update behavior may need to distinguish "change existing password" from "set first password" in a follow-up if the current screen cannot support that safely.

## Blocking Tests

- Configured providers appear on login.
- Configured providers appear on register only when registration is allowed in that context.
- Unconfigured providers do not appear and direct redirect attempts are refused.
- OAuth creates the first user as an Instance Admin.
- OAuth creates a user when registration mode is open.
- OAuth refuses to create a user in invite-only mode without an invite.
- OAuth creates a user and joins the workspace through a valid invite link.
- OAuth logs in an existing user and joins the workspace through a valid invite link when the user is not already a member.
- OAuth auto-links to an existing account only when provider email is verified.
- OAuth refuses missing or unverified provider email.
- OAuth refuses a provider identity already linked to another user.
- OAuth sends users with confirmed 2FA to the existing TOTP challenge before login completes.
- OAuth-only users with `password = null` cannot log in with password until a password is set.
- Password reset can set a password for an OAuth-only user.
- OAuth callbacks are accepted only on the canonical application host when host classification is testable.

## Comments

- Decisions were validated through a grill-me design interview on 2026-07-07.
- Implemented OAuth sign-in for Google and Discord with Socialite, `social_accounts`, nullable OAuth-only passwords, invite-link handling, registration-mode enforcement, TOTP handoff, provider availability props, and feature coverage on 2026-07-07.
