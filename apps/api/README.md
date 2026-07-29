# Global English API

This directory contains the Laravel modular-monolith application shell for
Global English.

The current foundation exposes:

- `GET /api/v1/health/live` checks only that the API process can respond;
- `GET /api/v1/health/ready` checks the required PostgreSQL and Redis
  dependencies;
- OTP-backed same-origin cookie sessions for the web/PWA, including session
  listing and revocation;
- Passport Authorization Code with S256 PKCE for the first-party Android
  client, including rotating token-family tracking and revocation;
- current identity and server-derived membership-context endpoints; and
- transactional Direct-tenant provisioning and institution-invite acceptance.

Responses carry a correlation UUID and are not cacheable. API failures use the
project problem-details contract without exposing internal exception or
dependency details.

After migrations and seeding, generate local Passport signing keys and create a
public Android client with the reviewed redirect URI:

```text
php artisan passport:keys
php artisan passport:client --public
```

Never commit Passport private keys or a client secret. The Android client must
remain public, request only `mobile:api`, use the system browser and store its
installation identifier and refresh credential only through reviewed native
secure storage. Both OAuth endpoints reject HTTP client-authentication headers
and clients that are confidential, revoked, owned, use another provider or
lack the authorization-code/refresh grants.

Any future route that reads or writes an organization-owned model must run
`auth.first-party` followed by `organization.context`. The second middleware
reconstructs a selected or single active membership and clears its
request-scoped authority after the response. Identity discovery, membership
selection and invitation redemption intentionally establish context and do not
consume that middleware.

The local OTP adapter is disabled outside `local` and `testing`;
production requires a separately reviewed provider. In local development, use
the six-digit `AUTH_OTP_LOCAL_FIXED_CODE` from the ignored API `.env` file.
The default example value is `000000`. It is ignored outside `APP_ENV=local`,
and WireMock request journaling remains disabled so phone numbers and codes are
not retained as a provider request history.

Existing ignored local `.env` files should set `SESSION_ENCRYPT=true`, define
the local-only `AUTH_IDENTITY_DIGEST_KEY` shown in `.env.example`, and add
`localhost:5173,127.0.0.1:5173` to `SANCTUM_STATEFUL_DOMAINS`. The application
also merges those Vite origins in local mode for backward compatibility.
Database and Redis session drivers are both revocable through Laravel's session
handler; the authoritative public-session registry remains in PostgreSQL.

Use the repository-root `make` commands rather than running undocumented local
scripts.
