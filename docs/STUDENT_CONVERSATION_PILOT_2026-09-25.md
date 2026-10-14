# STUDENT-CONVERSATION-02 — moderated student pilot packet

Status: **consent confirmed, observations pending**. No ratings or outcomes are claimed.
Coordinator: **S.R.Bhandary**, reported available now on 25 September 2026.
Available sample reported by the coordinator: **one adult student**, Kannada
to English, testing both web and Android. This is one participant across two
surfaces, not two participants or a representative cohort. S.R.Bhandary explicitly
confirmed adult status and voluntary content-free observation consent in this
task on 25 September 2026. Actual task results are still pending.
Deployed/installed candidate: `student-conversation-20260925-r2`, Android 1.0.2
(code 3), 25 September 2026. Exact APK hash and technical checks:
[verification ledger](STUDENT_CONVERSATION_02_EVIDENCE_2026-09-25.md).
Actual observation date and results: **not yet recorded**.

Authority: [APP-0107](APPROVALS.md#app-0107),
[specification](STUDENT_CONVERSATION_02_SPEC.md),
[ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md).
This is a formative pilot, not a replacement for the 15–24-adult,
three-segment UX-HUM-03 study or the broader master-plan beta gates.

## Before inviting a participant

1. Record exact API/web revision and installed APK hash; pass technical gates,
   privacy cleanup checks and a synthetic smoke on that exact candidate.
2. Recruit consenting adults. Aim for 5–8 students representing Kannada-first,
   hesitant/beginner and more confident speakers; include low-connectivity and
   accessibility needs, Android and mobile web. Record missing representation.
   This proposed formative sample does not amend an accepted release gate.
3. Obtain separate, purpose-specific opt-in for observing usability and
   recording content-free results. Ordinary speech-processing consent, the
   recovery acknowledgement, and accepting a local explanation are not
   research consent. Declining observation must not restrict learning access.
4. Explain: “We are testing the app, not your ability. You may skip any task
   or stop without consequence. Please use fictional details. We will record
   task completion, timing and usability issues, not your conversation.”
5. Do not record audio, video, screen capture, learner transcripts, names,
   contacts, account identifiers or personal stories in the repository/chat.
   Any richer research retention requires a separately approved protocol.
6. Use an existing properly entitled test account/process; do not fabricate
   participant accounts, payment, adult consent or provider consent. Have a
   named independent English/local-language reviewer assess synthetic test
   prompts and rubric separately. Reviewers are not yet supplied.

## Moderator procedure (about 20 minutes)

Let the learner attempt each task without coaching. Time from instruction to
completion; record assistance separately. If blocked, note the failure before
helping. Never turn assisted completion into unassisted success.

| Task | Instruction | Observe without storing utterances |
|---|---|---|
| P1 First useful turn | Sign in, find Speak, choose a situation and read the notice. Explain what will happen to temporary text before choosing. | Navigation/notice comprehension; no preselected acknowledgement |
| P2 Role ownership | Start Clinic reception. Listen, then respond as the patient using fictional details. | Only AI partner opens; clear Your turn; no AI-generated learner performance |
| P3 Correction loop | In the synthetic exercise, say/type “I has an appointment.” Follow the correction and retry in your own words. | English correction plus selected-language explanation; no next partner line until accepted; valid alternative allowed |
| P4 Continue and finish | Give two further appropriate replies, then end practice. Explain what reflection means. | Natural alternation; no fabricated success; advisory/unassessed understood |
| P5 Local-language question | Open Ask Swathi; ask a simple non-personal English-learning question in your preferred language. | Original transcript review; initial English answer and English voice |
| P6 Clarification — Yes | Indicate non-understanding in the preferred language. Accept the offer to explain locally. | Offer first; no local explanation/audio before Yes; actual local explanation afterward |
| P7 Clarification — No | Ask a new question, request clarification, decline local explanation, then ask another question. | English remains available; next question resets English-first |
| P8 Recovery | Deny microphone permission and use Type instead. During a synthetic turn, interrupt network briefly and retry. | Typed fallback recorded separately; duplicate does not advance twice; no lost next action |
| P9 Accessibility and exit | Use the participant's normal text size/assistive technology; end the dialogue and sign out. | Focus, readability, touch targets, labels, exit and cleanup; actual TalkBack only if performed |

For spoken tasks the observer records only whether recognition represented
what the participant intended (yes/no/uncertain) and whether repair was needed.
Do not use translated English to rate the participant's grammar. Assess
correctness/usefulness against the synthetic exercise rubric, not participant
identity or accent stereotypes. Optional subjective ease/confidence ratings
use a 1–5 scale and can be declined.

## Empty observation template

Copy locally only under the approved study process. No observations have been
entered here. Use a random non-identifying study code; keep any consent/contact
administration separate and outside this repository.

```text
Study code:                 Candidate revision/APK hash:
Adult observation opt-in confirmed by coordinator: yes/no (no signature here)
Platform/OS family:         UI/guidance language:
Speaking confidence: beginner/intermediate/confident/declined
Connectivity: normal/constrained   Accessibility needs: general category only
Task:                      Unassisted / assisted / failed / skipped:
Time seconds:              Hesitations / backtracks / invalid taps:
Spoken success: yes/no/not attempted    Typed fallback: yes/no
Transcript repair: yes/no/not applicable
Meaning/consent/next-action paraphrase accurate: yes/no/uncertain
Correction accurate/useful: yes/no/uncertain/not applicable
Ease/confidence 1–5: optional
Issue severity/code (content-free):
Stopped/withdrew: yes/no     Exit/recovery deletion checked: yes/no
```

## Reporting and stop rules

- Report numerator/denominator, skipped tasks and assisted outcomes. Do not
  merge spoken and typed success. With a small cohort, report counts before
  percentages; do not claim population significance.
- Target the existing ≥90% first-turn usability and correction-quality gates
  where applicable, with explicit denominators. This pilot alone cannot close
  production speech/latency, schema, academic or multilingual-review gates.
- Stop immediately for a consent bypass, another learner's content, unexpected
  retention, harmful output, inaccessible safe exit or a serious distress
  signal. Preserve only content-free incident metadata and use the incident
  runbook. Do not ask the learner to reproduce sensitive content.
- Repeated valid sentences being rejected, AI speaking both roles, premature
  local explanation or retry advancing twice are release-blocking flow defects.
- Summarize issues, fixes and re-tests truthfully. Coordinator availability,
  synthetic fixtures and automated accessibility checks are not human results.

## Results

Coordinator subsequently confirmed that human app use had begun. The reply to
the multi-item task-outcome request was only “yes”; it does not establish any
individual task result, platform, independence or completion. Automation remains
stopped; no active learner content is inspected. Await separate Android/web
unassisted, assisted, failed or skipped outcomes.

Participants available with adult/observation consent confirmed by coordinator:
**1**, Kannada to English, web and Android. Sessions run: **0 recorded**.
Independent language/academic/accessibility review: **not recorded**.
Decision: **pilot pending candidate verification and real observations**.
