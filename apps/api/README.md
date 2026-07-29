# Global English API

This directory contains the Laravel modular-monolith application shell for
Global English.

PR-002 exposes the versioned operational endpoints:

- `GET /api/v1/health/live` checks only that the API process can respond;
- `GET /api/v1/health/ready` checks the required PostgreSQL and Redis
  dependencies.

Responses carry a correlation UUID and are not cacheable. API failures use the
project problem-details contract without exposing internal exception or
dependency details. Tenancy, identity and authentication begin in PR-003.

Use the repository-root `make` commands rather than running undocumented local
scripts.
