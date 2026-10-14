---
status: accepted
date: 2026-09-25
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
amends_master_plan: true
approval_reference: Owner explicitly selected actual speaking/listening minutes with tokens tracked separately and a 1,000 simultaneous speaker target in the active task
---

# ADR-0043: Measured AI audio time and shared capacity admission

The owner's explicit billing selection amends ADR-0037 and master-plan §20.4;
the capacity target amends the former 50-concurrent reference in §§20.2 and 21.2.
Existing prices, minute allowances and historical charge rows remain unchanged.
New AI activity charges validated submitted recording duration and actual client
playback progress, rounded to whole seconds per playback. Typed turns, waiting,
local permission controls and speech that never plays have no arbitrary time fee.
Provider input/output tokens, speech units, failures and estimated cost remain
separate platform expense; they are not converted into student minutes.

Playback starts with an authenticated server-issued, expiring, learner/organization-
bound audio grant containing only verified duration and identifiers. Progress is
monotonic, idempotent, bounded by audio duration and elapsed server time, and
transactionally charged against the same entitlement. Playback errors/abort
report only elapsed played audio; a replay is a new listening activity. No
transcript, audio or provider credential belongs in meter records. Clients must
stop at their remaining allowance. Client playback telemetry measures delivery,
not proof of human attention; malicious omission cannot be treated as verified
listening, and independent provider spending/rate limits remain required.

Meter metadata follows existing scoped usage/deletion controls. No marks or
learning completion are inferred. The explicit owner request authorizes these
bounded additive billing records and their cross-tenant/concurrency tests.

Shared provider capacity is enforced before billable turn admission using a
common Redis store. Rejections carry a retry delay and do not spend student time.
Capacity targets do not grant vendor quota or authorize unbounded cloud spend.
The 1,000-speaker planning workload must distinguish connected sessions, turns
per second, tokens/minute, speech calls and simultaneously executing workers.
A local synthetic admission/load test is not a live-provider capacity certificate.

The Laravel modular monolith remains authoritative. A bounded PHP-FPM pool may
replace the single-process development HTTP runtime behind the existing loopback
proxy. Start at eight workers on the existing VPS, with explicit storage/secret
mounts and a tested stop path. Worker count is bounded by memory and database
connections, not by the number of students. This does not authorize claiming
that synchronous provider waits inside transactions support the new peak.

Live conversation clients must declare measured-playback support through
`X-Conversation-Playback: 1`; older clients receive an update response before
starting or continuing AI conversation. This is a version compatibility check,
not authentication or proof of listening. User/session/organization authorization
and bounded signed grants remain mandatory.
