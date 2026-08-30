# DR-0036 — Close three disclosed quality debts (authored levels, Role-play scripts, 1:1 request)

## Status

Accepted by Accountable Product Owner instruction (14 August 2026,
Asia/Kolkata). Recorded as [APP-0065](APPROVALS.md#app-0065). Named English
academic review of the authored pack is recorded as
[APP-0066](APPROVALS.md#app-0066). Kannada independent review remains open.

## Request

Close these three remaining disclosed gaps:

1. Role-play remains a synthetic prototype.
2. 1:1 booking is still marketing copy.
3. Levels 2–17 lesson text is still provisional.

The Accountable Product Owner gave blanket approval to close the gaps in
software where an agent can do so, and offered to do remaining human work now.

## Accepted interpretation

1. **Levels 2–17:** Replace APP-0063 template objectives/checks/Talk/Apply and
   Kannada summaries with authored lesson text keyed to inventory concepts.
   Independent specialist bilingual review and curriculum publication remain
   outstanding. Do not claim those reviews from this approval.
2. **Role-play:** Author Observe model lines, Rehearse chunks and a Perform
   prompt for each of the 12 scenarios so the journey is a real practice
   sequence. Keep `synthetic_unreviewed` / `development_only` until a named
   specialist curriculum review. Do not change the OpenAPI review-status enum.
3. **1:1 tuition:** Implement an org-scoped waitlist request (preferred window,
   topic, optional notes). Learners create and list their own requests;
   operators with `learners.read` list and acknowledge. No payments, calendar
   sync, trainer assignment marketplace or in-app hiring.

## Out of scope

- Curriculum publication or production / Google Play
- Marking Kannada UI or Role-play as independently reviewed
- Billing, scheduling, or direct trainer assignment
