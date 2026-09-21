# WEB-UX-09 — Web push

**Status:** Skipped.

WEB-UX-08 implements a privacy-gated delayed-review return email (fail-closed until APP + recovery-contact delivery review). That envelope already covers return-to-Home for due reviews (ADR-0028).

Web Push (VAPID + Notification permission after a completed lesson) would duplicate that return cue, requires a separate APP, and must never prompt on first paint. It is not implemented in this programme.

Reopen only if email cannot reach the learner (no verified recovery contact / no approved delivery adapter) **and** an APP authorizes a post-lesson, once-per-profile, dismissible permission prompt with no lesson content in the payload.
