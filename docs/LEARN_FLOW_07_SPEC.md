# LEARN-FLOW-07 — clear voice turns and honest recovery

Owner request, 26 September 2026: stop repeated recording/consent notices,
reduce transcript review to Send and Retry, and repair the apparent missing AI
reply. The owner additionally selected encrypted Android text/audio history and free
offline replay. After the voice-control repairs pass focused checks, complete
that privacy/history vertical slice under ADR-0044 within this bounded issue.

- Show generic recording guidance once per installation/browser profile, not
  after every turn/reset. Store only a non-sensitive seen flag; OS permission
  remains independently checked.
- Reuse active server consent; put withdrawal and detailed advisory/privacy
  information in an accessible Privacy panel. Never infer consent from an OS
  permission, installation, or previous account's choice.
- Remove engineering draft status from the learner conversation. Preserve its
  true pending status in an actionable independent-review packet.
- Keep the transcript visible with Send and Retry. Retry deletes the pending
  recording/candidate; typing is an explicit alternative, not a parallel form.
- Keep a compact conversation-options menu for end/restart rather than a
  persistent destructive label. Navigation/explicit end still performs cleanup.
- Diagnose model rejection separately from failed audio. Preserve safe text,
  idempotency and actual playback metering; recover committed outcomes without
  another model call or duplicate recorded-input charge. Actual live playback
  remains metered; saved local replay is free. Do not synthesize unusable fallback text.
- Regression tests, browser journey, AI evaluation if orchestration changes,
  Android build/device validation and published artifact checks are required.

No authentication, marks, prices, cloud retention or schema change in this
checkpoint. Independent human acceptance cannot be completed by Codex.

Android history must cover opt-in, actual text/audio capture, list/read/replay,
individual/all deletion, account isolation and visible storage failure. No
cloud history or changed authoritative learning records.
