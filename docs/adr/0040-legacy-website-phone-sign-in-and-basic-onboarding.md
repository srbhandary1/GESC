---
status: accepted
date: 2026-09-24
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted:
  - Cursor agent as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence:
  - Accountable Product Owner instruction in the active Cursor conversation
approval_reference: Exact human instruction to onboard all previous-website students into this app in the basic tier using the same credentials, without modifying https://globalspokenenglish.com
---

# ADR-0040: Previous-website phone sign-in and Direct basic onboarding

## Status

Accepted on 24 September 2026 as D-0341. Amends
[ADR-0021](0021-username-password-only-authentication.md),
[ADMIN-ENT-01](../ADMIN-ENT-01_SPEC.md) §2 (previous-website import exclusion),
and master plan authentication identity format. This does not authorize
production promotion, Play publication, or a write to
`https://globalspokenenglish.com`.

## Context

`https://globalspokenenglish.com` authenticates students with phone number plus
password. Admin edit forms store PHP bcrypt (`$2y$`) hashes. This app required
letter-first usernames `^[a-z][a-z0-9._-]{3,63}$` and 15–128 character
passwords hashed with Argon2id, so those students could not sign in with the
same credentials.

The Accountable Product Owner instructed that every student from that site be
onboarded here in the basic tier, using the same login credentials, and that
this app change to match that authentication format when it does not. The
previous website must not be modified.

ADMIN-ENT-01 previously excluded importing students, passwords or progress
from the previous website. This ADR authorizes a bounded credentials-and-basic-
entitlement import only. Progress, recordings and payments are not copied.

## Decision

1. Sign-in identifiers are either the existing letter-first username or a
   phone number matching `^\+?[0-9]{7,20}$` after stripping spaces, dots,
   hyphens and parentheses. Learner sign-in copy is **Phone or username**.
   Invitation and operator username establishment keep the letter-first rule.
2. New passwords keep the 15–128 character blocklist policy. Imported
   previous-website bcrypt hashes are stored as `algorithm=bcrypt` and verify
   through PHP `password_verify`. First successful sign-in rehashes to
   Argon2id.
3. A read-only extract of previous-website student phone and bcrypt hash is
   stored outside Git. `learners:onboard-legacy-website` provisions each row
   as a Global English Direct learner, sets the phone as the username, imports
   the hash, and operator-grants `monthly_modules` (all published levels, no
   Role-play, 120-minute live-AI pool) as the Direct **basic** membership for
   one calendar month from `Asia/Kolkata`. Replay is idempotent. Duplicate
   phones and rows that already have a paid membership are skipped.
4. No request writes, updates or deletes records on
   `https://globalspokenenglish.com`. Raw passwords never enter Git, logs or
   operator views.

## Alternatives considered

### Keep letter-first usernames and mint new passwords

Rejected. The product owner required the same credentials.

### Proxy each sign-in to the previous website

Rejected. That would touch the previous website on every login.

### Grant Premium with Role-play

Rejected. The instruction is the basic tier, which maps to `monthly_modules`.

## Consequences

### Positive

- Previous-website students can open this app with the phone and password they
  already use.
- Hashes upgrade to Argon2id after first sign-in here.

### Negative and trade-offs

- Short previous-website passwords remain valid until the learner changes
  them. New password changes still require 15 characters.
- One complimentary basic month does not copy paid Prime expiry from the
  previous website.
- Live-AI minutes on `monthly_modules` are a cost exposure for a large imported
  cohort.

## Master-plan amendment

The platform-global credential may be a letter-first username or the phone
number used on the previous website. New operator/invitation usernames and new
password policy are unchanged.
