# DR-0035 Soft-launch guidance TTS + Role-play catalog

**Status:** Resolved under [APP-0064](APPROVALS.md#app-0064) / [D-0205](DECISIONS.md).

## Request

Authorize Kannada+English guidance TTS under soft-launch and enable it, and add
at least 10 other common Role-plays.

## Resolution

1. Soft-launch ordinary-product Azure Speech TTS for GuidanceTtsPolicy purposes
   (`model_en` + `guidance_l1`) with ₹500 APP-0064 spend evidence and text-first
   fallback. PR-009 speech-benchmark remains separate.
2. Expand synthetic Role-play catalog to clinic + 11 common scenarios with UI
   selection; seed via FoundationSeeder / `conversation:seed-roleplay-catalog`.
