# Global Spoken English learner user guide

**Document status:** Repository baseline guide, pre-production
**Prepared:** 10 August 2026
**Audience:** Adult learners using the web/PWA or Android application
**Primary learning-language audience:** Kannada-speaking adults learning
practical spoken English

This guide explains the learner experience that is implemented in the
repository, the privacy choices available to a learner, and the capabilities
that are still planned. It must be read with the current
[product status](../STATUS.md). Production and public release are not yet
authorized, and the repository does not yet contain a complete, independently
reviewed production pathway or approved production media library.

## Quick navigation

- [Before you begin](#3-before-you-begin)
- [Sign in](#4-sign-in)
- [Complete a daily mission](#7-complete-a-daily-learning-mission)
- [Use offline learning](#8-use-offline-learning-safely)
- [Watch a level video](#9-watch-a-level-video)
- [Practise with Confidence Conversation](#10-practise-with-confidence-conversation)
- [Privacy and account safety](#13-protect-your-privacy-and-account)
- [Troubleshooting](#15-troubleshooting)
- [Planned lesson-practice experience](#17-planned-lesson-practice-experience)

## 1. How to read this guide

The following labels prevent prototype or roadmap behavior from being mistaken
for a live product promise:

| Label | Meaning |
|---|---|
| **Available in repository** | Implemented and covered by repository tests, but still subject to deployment and release gates |
| **Demonstration only** | Synthetic browser-memory experience used to show a product direction; it is not connected to authoritative learner records |
| **Planned** | Designed in an approved or queued plan but not yet available as an end-to-end learner feature |
| **Deferred** | Deliberately outside the current delivery scope |

The lesson names, media and questions that appear for a learner depend on the
learner's organization, enrollment and exact published curriculum version.
Screens can therefore contain different content while following the same
workflow described below.

## 2. What you can do

The current learner experience provides two main learning routes:

- **Learn:** complete a short daily speaking mission with model English,
  Kannada guidance, optional local recording, a knowledge check and a result;
- **Practice:** hold a private, structured Confidence Conversation with text
  or optional voice, then review advisory coaching feedback; and
- **Level videos:** watch private lesson videos when an authorized video is
  included in the exact curriculum publication assigned to you.

The app is designed around practical confidence: understand a useful English
pattern, hear it, try it safely, check meaning, and return for review. AI
feedback is coaching, not an official mark. Marks, mastery, progression and
content access remain deterministic application decisions.

## 3. Before you begin

Have the following ready:

- a supported phone or computer with a modern browser, or the authorized
  Android build;
- the username and password issued for your account, or an invitation code
  from a college, employer or training organization;
- headphones and a quiet place if you want to listen or practise aloud.

A microphone is optional. You can complete the current daily mission without
recording, and Confidence Conversation always retains a text route.

Use offline learning only on a personal device. Do not opt in on a shared
computer or shared phone.

## 4. Sign in

### 4.1 Web or PWA

1. Open the application and select **Sign in**, or open the `#sign-in` page
   directly.
2. Enter your username (at least four characters, starting with a letter).
3. Enter your password.
4. Select **Sign in**.

If your organization gave you an invitation code, select **I have an
invitation code** on the sign-in page, enter the code, choose a username and
password, then create the account. After that you sign in with those
credentials.

A direct learner can sign in without an invitation. An institutional learner
uses the show-once code supplied by the organization to accept the intended
membership and enrollment.

The web/PWA uses a secure cookie session. The application does not need you to
copy or manage an access token.

### 4.2 Android

The Android app opens the system browser for authorization and returns to the
app after successful sign-in. The app keeps its access token only in memory;
the refresh credential and authorization state belong in reviewed Android
secure storage backed by the device Keystore. They are not stored in ordinary
browser storage or app preferences.

If the browser does not return to the app, leave the browser open long enough
to finish authorization and use only the expected application callback. Do
not paste sign-in links or codes into another person's device.

### 4.3 Sign out

Use **Sign out** when you finish, especially if someone else can access the
device. Signing out ends the current product session. If you believe an
account or device is unsafe, contact the support channel provided by your
organization rather than sharing passwords, invitation codes or recovery
codes.

## 5. First-time setup

The first learner setup asks you to:

1. confirm that you are an adult; and
2. choose a learning goal: **Everyday**, **Travel** or **Workplace** English.

The goal helps the product select and describe suitable learning objectives.
An authorized administrator can later record a goal correction without
erasing the history of earlier choices.

### Interface language

The interface offers Kannada (`kn-IN`) by default and selectors for approved
catalogue variants of English, Hindi, Malayalam, Tamil and Telugu. The five
non-English catalogues still require independent language review. Until that
review is accepted for production, affected production wording falls back
visibly to the approved English source. Interface-language availability does
not imply that every curriculum, speech model or guidance voice supports that
language.

## 6. Main navigation

The regular learner shell contains these primary destinations:

| Destination | Purpose | Current status |
|---|---|---|
| **Home** | Product landing and orientation | Shell available; richer dashboard cards are demonstration-only |
| **Learn** | Today's lesson or mission | Available in repository |
| **Practice** | Confidence Conversation practice | Available in repository within the provider/release limits below |
| **Progress** | Learning progress destination | Shell available; a complete standalone progress dashboard is not yet delivered |

After a mission, the result view provides the most useful current progress
information: attempt history, knowledge outcome, review timing and objective
progress.

## 7. Complete a daily learning mission

### 7.1 Open today's learning

Go to **Learn**. The page identifies the next available mission and its
estimated duration. Access is based on your active enrollment and the exact
immutable curriculum publication pinned to that enrollment.

If no mission is available, this can mean that:

- there is no active enrollment;
- your organization has not activated a verified publication;
- the required content or media is not ready;
- the mission has already moved into a review schedule; or
- the application cannot currently reach the service.

Retry once after checking connectivity. If the message remains, send the safe
error reference and approximate time to your organization's support contact;
do not send OTPs or recordings.

### 7.2 Learn: understand the model

The **Learn** step can show:

- the English model sentence or pattern;
- Kannada meaning or guidance;
- a model-audio control; and
- the authored transcript.

Listen more than once if helpful. Read the transcript, then connect the English
form to the meaning and situation. The transcript and text alternative also
provide a non-audio route.

Current published daily-speaking missions require their exact approved model
audio. The media is private and is delivered only when the current publication,
rights decision and learner enrollment authorize it.

### 7.3 Speak: try it privately

The **Speak** step lets you practise the model aloud. Where browser permission
is available, you can record a short local attempt, play it back and try again.

Important current behavior:

- local recording is optional;
- you can continue without recording;
- this local attempt does not create an automatic pronunciation score;
- the daily-mission result reports speaking as **unassessed**; and
- local capture is separate from the explicit upload consent used by voice
  conversation features.

Use the playback as a private self-check: Was the sentence complete? Was it
easy to understand? Did the important word sound clear? Avoid trying to erase
your natural accent; the goal is intelligible, confident communication.

### 7.4 Check: answer the knowledge question

The **Check** step asks an authored multiple-choice question about the current
mission. Choose the answer that best matches the taught meaning or usage and
submit it.

The current daily-mission content contract contains one authored check. A
larger 3–7 item quiz with varied question types is **planned** as part of the
future lesson-practice loop; it is not the current runtime behavior.

### 7.5 Read the result

The result separates knowledge evidence from speaking evidence. Depending on
the answer, you will see a knowledge outcome such as mastered/correct or
developing/needs retry. Speaking remains unassessed in this mission type.

The result can also show:

- whether a local capture receipt was recorded;
- first, latest and best attempt information;
- when review is due; and
- progress toward the related learning objective.

Do not read an **unassessed** speaking label as a failure. It means the current
mission intentionally did not turn local audio into an authoritative mark.

## 8. Use offline learning safely

Offline mission access is **available in repository** for bounded learning
bundles. It is intentionally conservative.

1. On a personal device, explicitly opt in to offline access.
2. Download the offered mission bundle while connected.
3. Complete the supported mission steps before the lease expires.
4. Reconnect and allow the app to synchronize.
5. Wait for server confirmation before treating the result as authoritative.

Offline data is partitioned by an opaque learner context and encrypted in
browser IndexedDB. The current bundle lease is 24 hours. Authentication
credentials and raw voice recordings are not stored in the offline learning
store.

An offline result is provisional. The server can reject or reconcile it if the
enrollment, publication, attempt or authorization changed while the device was
offline. Follow the on-screen recovery instruction; never create a second
account to work around a conflict.

Do not enable offline storage on a library computer, shared office computer or
shared family device. Clear product data and contact support if the device is
lost.

## 9. Watch a level video

When the exact publication assigned to you contains an approved level video,
the relevant level can show a private video entry.

- Select the video to stream it in the browser.
- Use captions/transcript or the authored text alternative when needed.
- On Android, the current implementation may load a bounded authenticated
  video object and releases it after playback.

Video files are private, authenticated, non-public assets with a current 20 MB
limit. A video is hidden when its publication pin, current rights, approved
metadata, integrity or enrollment authorization is no longer valid. A hidden
video should not be treated as a broken public URL; ask the organization to
check its governed content state.

Offline video download is not currently supported.

## 10. Practise with Confidence Conversation

Confidence Conversation is a private practice flow. It is structured around
four stages:

1. **Warm-up** — choose the coaching style and prepare for the topic;
2. **Listen** — hear or read the opening prompt;
3. **Talk** — reply by text or optional voice over a bounded number of turns;
4. **Reflect** — review the practice and choose what to improve next.

### 10.1 Choose a coaching mode

| Mode | Intended feel |
|---|---|
| **Gentle** | More reassurance and less interruption |
| **Tutor** | Balanced practice and explanation |
| **Fluency** | More momentum and concise correction |

The mode changes coaching style; it does not change an official grade.

### 10.2 Conversation controls

During a turn you can use controls such as:

- **Say that again** to repeat the prompt;
- **Type instead** to switch to text;
- **Skip** when a prompt is not useful;
- **Hint** for bounded help; and
- **Finish** after the minimum practice-turn requirement is met.

The conversation should answer your meaning first, then show no more than one
priority correction with first-language explanation. This limits correction
overload and keeps the exchange focused on communication.

### 10.3 Current provider status

The repository's ordinary local and automated-test configuration uses a
deterministic fake conversation provider. It proves the product workflow but
is not a live generative tutor. A provider-neutral live adapter and policy
boundary exist, but live or production requests require the exact approved
configuration, credentials, spend controls and release evidence. When those
conditions are absent, the live path fails closed rather than silently
pretending to use AI.

### 10.4 Use voice only when you choose

Text practice is always available. Voice requires explicit choices for the
relevant processing stages, including upload/transcription and conversation
provider processing.

The safe voice flow is:

1. read the consent explanation;
2. allow microphone access only if you agree;
3. record and play the attempt locally;
4. explicitly approve upload/transcription when offered;
5. inspect the transcript;
6. correct the transcript or discard the attempt; and
7. approve any separate provider-processing step only if you want it.

The app must not treat microphone permission alone as consent to upload or AI
processing. A transcript can be useful even when speech recognition is
imperfect. Do not include passwords, payment information, medical details or
another person's private information in a practice recording.

Deletion is managed through durable requests and receipts. If a voice source
does not disappear immediately, the system can continue a controlled deletion
retry without placing raw audio in logs. Contact the authorized support path
with the safe receipt/reference if deletion remains pending.

### 10.5 Review coaching feedback

Conversation feedback is formative and advisory. It may identify a useful
phrase or one priority improvement, but it cannot:

- award an authoritative mark;
- change mastery;
- unlock a level;
- alter an enrollment; or
- replace a human teacher, reviewer or examiner.

If feedback is confusing or inappropriate, use text instead, finish the
session, and report the safe session/error reference through the support
channel. Do not forward the whole transcript unless an approved support process
specifically requests it.

## 11. Control AI memory

The product supports a small, learner-visible practice memory. You can add,
edit and delete notes that are intended to help future conversation practice,
for example a preferred learning goal or a topic you want to rehearse.

There is no authorized hidden learner-memory route. Keep memory notes short and
learning-related. Do not store secrets or sensitive third-party information.
Deleting a note removes it from the active memory context; statutory retention
or audit handling, where applicable, follows the documented privacy policy.

## 12. Use role-play practice

The current repository includes a synthetic clinic-reception role-play shaped
as **Observe → Rehearse → Perform → Reflect**. It is a non-production learning
fixture and provides advisory coaching only. It is not medical advice and does
not represent a complete, specialist-approved curriculum pathway.

Future role-plays can use the same safe structure for everyday, travel and
workplace situations after the content and review evidence is approved.

## 13. Protect your privacy and account

- Never share a password, invitation code, recovery code or sign-in callback.
- Use voice only after reading the consent shown for that operation.
- Prefer text when the surroundings are not private.
- Do not record another person without their knowledge and authorization.
- Do not enter secrets or highly sensitive personal information in AI chat.
- Use offline access only on a personal device.
- Sign out before giving a device to another person.
- Report a lost device or suspected account misuse promptly.

The product is designed not to place raw sensitive audio, transcripts,
credentials or personal learner content in ordinary logs, operator search or
support diagnostics. Operators receive bounded summaries according to their
permissions, not unrestricted access to a learner's practice content.

## 14. Accessibility and low-bandwidth use

The learner surfaces are designed for keyboard operation, responsive reflow,
labels and non-audio alternatives. Automated checks include narrow 320 CSS
pixel layouts, but independent human accessibility review remains a release
gate.

For a lower-bandwidth experience:

- use the transcript when audio is slow;
- use text instead of voice conversation;
- download an eligible offline mission while connected to Wi-Fi;
- avoid repeatedly starting a private video on an unstable connection; and
- retry a failed resource without refreshing an entire completed flow when the
  screen offers an individual retry.

## 15. Troubleshooting

| Problem | What to do |
|---|---|
| Username or password is rejected | Check the username is at least four characters and starts with a letter. Use **Forgot password?** for recovery. Never ask another person to receive a recovery message. |
| Invitation is rejected | Re-enter the exact show-once code. It may be expired, revoked, already used or intended for another organization; contact the issuer. |
| No lesson appears | Check connectivity and active organization/enrollment. Retry once, then send the safe error reference to support. |
| Audio does not play | Check media volume and browser permission, use the transcript, and retry the individual audio resource. |
| Microphone is blocked | Enable permission for this trusted app or choose **Type instead**/continue without recording. |
| Speech transcript is wrong | Correct it before submitting, retry, or discard it and use text. Do not assume the transcript is an official record of what you said. |
| Video is unavailable | The exact publication, rights or media state may no longer authorize delivery. Use the text alternative and ask the organization to check the publication. |
| Offline result is pending | Reconnect and keep the app open long enough to synchronize. The server result is authoritative. |
| AI practice is unavailable | The approved provider may be disabled or unconfigured. Use the deterministic/text learning routes and retry later. |
| Feedback feels unsafe or unrelated | Stop the practice, avoid sending more personal information, and report the safe reference through the authorized support channel. |

## 16. What the product demo means

`make demo` launches a synthetic demonstration with browser-memory data. It can
show richer Home cards such as a dashboard, attendance, daily activity, levels,
notices and a scripted AI Teacher. The demo does not use the authoritative
database, real sign-in, a live provider or approved production curriculum.

Assignment, formal assessment and unlock actions shown as unavailable in a
demo level room remain unavailable. A demo screen is not evidence that a
feature is deployed for learners.

## 17. Planned lesson-practice experience

The queued [Lesson Practice Loop plan](../LESSON_PRACTICE_LOOP_PLAN.md) extends
each governed lesson into:

**Learn → Talk → Check → Apply → Review**

The plan includes transcription and topic extraction for new audio, an
operator-reviewed AI lesson blueprint, topic-grounded Skill Chat, a 3–7 item
quiz, an applied speaking task, delayed review and deterministic progression.
It is designed so administrators can add future lessons without hand-coding a
new chat and quiz for each file. AI drafts content; authorized humans publish
it; deterministic services own marks and progression.

This loop is **planned**, not current runtime behavior. Uploading an audio file
today does not automatically publish its topic, quiz or AI chat to learners.

## 18. Current limitations

- Production/public release is not authorized as of 10 August 2026.
- A complete human-reviewed pathway and approved production media set are not
  yet present.
- The current daily mission has one authored knowledge check, not the planned
  multi-item dynamic assessment.
- Local daily-mission speech is unassessed; there is no authoritative automatic
  pronunciation score.
- Live AI and speech behavior depends on separately approved provider,
  credential, spend, privacy and release configuration.
- A full standalone learner Home/Progress dashboard is not yet implemented in
  the regular runtime shell.
- Offline video is not supported.
- Non-English interface catalogues still require independent review.

## 19. Support information to provide

When asking for help, provide only:

- the organization name, if known;
- the screen or step, such as **Learn – Check**;
- the approximate date and time;
- the safe error or receipt reference shown by the app;
- device/browser type and app version; and
- whether the issue occurs online, offline, with text or with voice.

Do not send a password, invitation code, access token, raw recording, full
conversation transcript or another learner's information in an ordinary
support message.

## 20. Glossary

| Term | Meaning |
|---|---|
| **Enrollment** | Your authorized participation in an organization's learning program |
| **Publication pin** | The exact immutable curriculum version assigned to an enrollment |
| **Mission** | A short governed learning activity with model, practice, check and result |
| **Confidence Conversation** | Private structured AI-assisted practice with text and optional voice |
| **Knowledge result** | Deterministic outcome from an authored check |
| **Unassessed speaking** | Speech was practised but was not converted into an authoritative mark |
| **Provisional offline result** | A local result waiting for authoritative server reconciliation |
| **Model audio** | Approved example pronunciation attached to exact lesson content |
| **AI memory** | Learner-visible, editable notes used only within the authorized practice context |

For operational policy and release state, consult the current
[status ledger](../STATUS.md) and your organization's approved support channel.
