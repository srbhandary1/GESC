# API contract

`openapi.yaml` is the canonical OpenAPI 3.1.1 description of the public HTTP
surface. It includes the versioned `/api/v1` operational, authentication and
identity operations plus the standards-based Passport authorization and token
endpoints at the root.

Run `make openapi` from the repository root to lint the contract and prove that
the committed TypeScript client is reproducible. Run
`pnpm run contracts:generate` after an intentional contract change, then review
and commit the generated output under `packages/contracts/src/generated/`.

The operational health checks are the only operations allowed to omit a
specific 4xx response. A repository policy check fails if a later feature
operation relies on that exception. Versioned domain API failures use
RFC 9457-style `application/problem+json`; the root Passport endpoints retain
standards-based OAuth `application/json` error bodies. The policy check enforces
both media-type contracts explicitly.

Passport's `/oauth/authorize` and `/oauth/token` routes live outside
`/api/v1` and are described explicitly. The Android adapter uses the system
browser, S256 PKCE and a public client; the contract also describes the
Passport security scheme used by protected versioned endpoints.

Do not edit generated files directly. Keep invite codes, OTPs, tokens, phone
numbers, personal data, internal exception text and dependency diagnostics out
of public schemas and examples.
