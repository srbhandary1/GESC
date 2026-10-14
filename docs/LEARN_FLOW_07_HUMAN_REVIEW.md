# Learn-flow independent review handoff — 26 September 2026

Status: **pending named independent reviewers**. Codex prepared this handoff and
implementation evidence; it has not signed a human approval or contacted anyone.
Engineering draft-status text is removed from the student flow without changing
the notice/catalogue review status or broader production release gates.

To close the review:

1. Assign a privacy/security reviewer and a Kannada-speaking English educator
   who did not author the notice/content. Record their names and roles here.
2. Review the exact shipped notice revision/hash from the voice-consent API,
   `packages/localization/src/catalogues/governance.json`, `public/privacy.html`,
   ADR-0007/0042/0044 and the final Learn-flow release evidence. Review each other
   supported language with an appropriate fluent reviewer before declaring it
   independently accepted.
3. On the APK, test first consent, refused microphone, withdrawal, Send/Retry,
   a wrong/corrected English turn, native-language help, interrupted audio,
   expired server context, history opt-in/off, individual/all deletion, account
   switch, offline replay and storage failure. Use separately consented/synthetic
   material; do not retain real learner recordings for research by default.
4. Record findings, required fixes, reviewer decision, date and exact artifact
   hashes. Only the actual reviewer signs their acceptance. Resolve failures and
   repeat affected checks before closing their review.
5. Append approval evidence in `docs/APPROVALS.md` and update reviewed catalogue/
   notice metadata only for the exact accepted versions. A changed notice needs
   a successor review and, when processing purposes change, fresh consent.

The supplied request authorizes implementation and this handoff. It cannot stand
in for an independent reviewer personally performing these checks.
