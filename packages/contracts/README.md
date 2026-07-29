# Global English contracts

This workspace is the generated TypeScript client boundary for
`docs/api/openapi.yaml`.

Edit the OpenAPI document, then run `pnpm run contracts:generate` from the
repository root. Do not edit `src/generated/` by hand. `make openapi` validates
the contract and regenerates into a temporary directory to detect any committed
output drift.
