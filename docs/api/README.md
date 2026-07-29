# API contract

`openapi.yaml` is the canonical OpenAPI 3.1.1 description of the public
`/api/v1` surface. PR-002 intentionally contains only the operational liveness
and readiness endpoints.

Run `make openapi` from the repository root to lint the contract and prove that
the committed TypeScript client is reproducible. Run
`pnpm run contracts:generate` after an intentional contract change, then review
and commit the generated output under `packages/contracts/src/generated/`.

The operational health checks are the only operations allowed to omit a
specific 4xx response. A repository policy check fails if a later feature
operation relies on that exception.

Do not edit generated files directly. Keep secrets, personal data, internal
exception text and dependency diagnostics out of public schemas.
