# Global English Learning Platform

## High-Level Scope, Delivery Plan and AI-Assisted Engineering Estimate

**Prepared for:** Client discussion  
**Prepared by:** Project Delivery Team  
**Version:** 1.0  
**Date:** 29 July 2026  
**Classification:** Confidential

---

## Executive summary

This proposal outlines the Phase 1 design, development, testing and launch of a Kannada-first spoken-English learning platform for adult learners. The solution will be available through:

1. a responsive web application and installable Progressive Web App (PWA);
2. an Android learner application distributed through Google Play; and
3. a secure web console for trainers, content reviewers and administrators.

The product will guide learners in Kannada while they listen to, practise and speak useful English. The daily journey will include audio, recording and playback, guided role-play, short assessments, feedback, progress and notices. A controlled AI Teacher will provide course-grounded explanations and practice support without controlling marks, attendance or level progression.

The recommended Android approach uses Capacitor to package the approved React learner experience as an Android application. This creates a genuine signed Android app with microphone, connectivity and storage integration while allowing the web and Android channels to share most learner-facing code. It is not a separately designed Kotlin-native application.

### Planning baseline

| Item | Planning basis |
|---|---|
| Software delivery effort | 3,400 expected manual professional hours |
| Controlled reserve | 680 hours (20%) |
| Client planning authorization | Approximately 4,100 AI-assisted manual delivery hours |
| Expected delivery band | 3,700–4,500 hours if the agreed assumptions remain stable |
| Indicative elapsed duration | 28–34 delivery weeks with a blended 4–5 person team |
| Pilot learning content | Up to 20 client-approved lessons integrated into one four-week pathway |
| Optional academic/content work | 480–740 specialist hours if commissioned separately |

The hours are cumulative human effort, not AI processing time or calendar time. AI assistance will be used for scaffolding, code generation, test generation, documentation and review support. Human engineers remain responsible for architecture, security, correctness, accessibility, integration, device testing and release decisions.

---

## 1. Project objective

Design, build and launch a reliable English-learning platform that helps Kannada-speaking adults practise practical spoken English with minimal confusion and a clear next step every day.

The Phase 1 product will:

- explain learner actions in simple Kannada;
- use English as the learning and practice language;
- make speaking, recording, playback and retry part of the core lesson;
- work well on mobile devices and unreliable networks;
- provide trainers with clear operational visibility;
- preserve accurate progress across retries, offline work and content updates;
- support a production web launch and Google Play release;
- create a secure foundation for future levels, institutions and advanced learning services.

Phase 1 is an adult pilot. Learners under 18 are excluded until child-consent, child-safety, legal and platform requirements are separately approved.

## 2. Recommended solution

### 2.1 Learner channels

**Web and PWA**

- Responsive web application for mobile, tablet and desktop browsers.
- Installable PWA for supported Android browsers.
- Downloaded lesson access, local progress queue and reconnect syncing.
- One simple home screen focused on today’s activity.

**Android application**

- Google Play-distributed Android application using the shared React learner interface and Capacitor native runtime.
- Native microphone permission, recording, local playback, connectivity status, secure storage and app lifecycle handling.
- App icon, splash screen, package configuration, signing configuration and production Android App Bundle.
- Internal and closed testing support, followed by one production submission.

The trainer, administrator and content-review console will remain web-based in Phase 1.

### 2.2 Shared application platform

- Laravel API and business services.
- PostgreSQL system of record.
- Redis-backed cache, queues and rate limits.
- Secure object storage for audio and approved learner recordings.
- Role-based access and organization/cohort boundaries.
- Versioned curriculum, lessons and assessments.
- Kannada/English localization and publishing workflow.
- Audit trail, operational events and reporting read models.
- AI Teacher service boundary with provider controls and fallback.

### 2.3 Reference production hosting

The estimate assumes a client-owned Google Cloud project with one staging and one production environment. The reference deployment may use:

- Firebase Hosting or equivalent managed web hosting for the React PWA;
- managed Google Cloud container services for the Laravel API and workers;
- Cloud SQL for PostgreSQL;
- managed Redis;
- Cloud Storage for audio and submission assets;
- Secret Manager, logging, monitoring, alerts and managed backups;
- an agreed India region where service availability and client policy permit.

The final service selection will be confirmed during architecture approval. Cloud usage, domain, messaging, AI, monitoring and other third-party charges are paid directly by the client.

## 3. Included Phase 1 scope

### 3.1 Discovery and solution definition

- Stakeholder workshops and confirmation of the adult pilot.
- Learner profiles, target communication situations and critical journeys.
- Phase 1 feature inventory and acceptance criteria.
- Information architecture and mobile-first wireflows.
- Technical architecture, data classification and threat-model outline.
- Content inventory, Kannada terminology and ownership matrix.
- Android device/browser test matrix.
- Release, support, hosting and Google Play readiness plan.

### 3.2 Learner onboarding and access

- Organization-aware learner access.
- Mobile-number OTP sign-in for normal learners.
- Admission ID and one-time setup path where a personal phone is unavailable.
- Kannada-first recovery and support guidance.
- Learner profile, goal, language and accessibility preferences.
- Recording and AI-processing consent.
- Adult-age confirmation for the Phase 1 pilot.
- Short starting-level or baseline placement flow.

SMS/OTP provider charges and regulatory registration, where required, are client-funded.

### 3.3 Learner home and daily learning

- Mobile home with one dominant “continue today’s lesson” action.
- Lesson duration, completion status, offline/download and sync indicators.
- Latest important notice.
- Current level and simple can-do progress.
- Weekly consistency and review-due indicators.
- Clear Kannada help and error recovery.

### 3.4 Daily spoken-English lesson

Each integrated lesson can support:

1. Kannada goal and instructions.
2. Contextual English model dialogue.
3. Audio playback, replay and supported speed.
4. Transcript and Kannada meaning support.
5. Useful English phrases or chunks.
6. Listen-and-repeat practice.
7. Learner recording, playback and retry.
8. Guided substitution practice.
9. Short role-play or personalised spoken response.
10. Short knowledge check, result and next action.

Core lesson controls will use large touch targets and accessible audio behaviour.

### 3.5 Offline and low-bandwidth behaviour

After an assigned lesson is downloaded, the learner can access:

- the lesson instructions;
- low-data model audio;
- transcript and approved Kannada explanation;
- recording and local playback;
- lesson questions;
- queued completion and progress data.

The application will show whether work is online, saved on the device, syncing or failed. Valid queued submissions will use idempotent server processing so reconnects do not create duplicate attempts.

AI Teacher responses, OTP sign-in and new lesson downloads require connectivity. Full offline administration and offline AI are not included.

### 3.6 Assessments, progress and levels

- Formative questions with guided retry.
- First, latest and best practice evidence.
- Bounded summative checks where applicable.
- Speaking-task evidence and trainer review flags.
- Objective-level mastery and review schedule.
- Current and next-level visibility.
- Deterministic, explainable unlock decision for the pilot pathway.
- Separate views for learning progress, learning-day completion and class attendance.

Phase 1 implements the reusable level architecture requested for Pre-Level 1, Pre-Level 2 and Levels 1–17. Only the approved four-week pilot pathway will be populated and advertised as available.

### 3.7 Attendance and learning records

- Digital learning-day completion based on configured meaningful activity.
- Separate trainer-managed class attendance.
- Organization calendar and scheduled-day denominator.
- Attendance correction with actor, reason and audit history.
- Offline-pending state so connectivity does not create an incorrect absence.
- Monthly learner and trainer summaries.

### 3.8 Notice board and reminders

- Notices by category, priority, cohort and active dates.
- Kannada-first notice content with optional English version.
- Urgent and unread states.
- Cached essential notices.
- Optional acknowledgement for critical notices.
- Basic opt-in push-notification configuration for approved notice types.

### 3.9 AI Teacher

The Phase 1 AI Teacher will be a bounded learning assistant.

Included capabilities:

- Kannada explanation of the active lesson or objective;
- simple English examples;
- guided practice prompts and bounded role-play;
- progress reminder and suggested next action;
- one prioritized correction where supported;
- safety filtering, usage limits, cost tracking and provider-failure fallback;
- learner report-response control and human-support route.

Controls:

- AI uses only approved course context and the minimum required learner facts.
- AI cannot modify attendance, marks, mastery or level eligibility.
- AI is feature-flagged and can be disabled without blocking core learning.
- AI responses are assistive and may occasionally be inaccurate.

Live voice conversation, custom model training and authoritative automated pronunciation scoring are excluded.

### 3.10 Trainer workspace

- Cohort and learner overview.
- Today’s learning completion and learners needing help.
- Incomplete work and sync exceptions.
- Selected speaking submissions for trainer feedback.
- Assessment, progress and attendance views.
- Attendance correction with reason.
- Learner support notes and history.
- Basic CSV exports subject to permission.

### 3.11 Administration and content operations

- Organizations, branches, cohorts and academic calendar.
- Learner, trainer and administrator access.
- Role and permission management.
- Enrollment and trainer assignment.
- Account reset and session revocation.
- Level, objective, lesson, audio and question management.
- Notice management and targeting.
- Draft, review, approve, schedule, publish, retire and rollback workflow.
- Kannada translation-review queue and terminology glossary.
- Source revision, reviewer, approval and publication history.
- Basic audit and operational reports.

Phase 1 will be configured for the client’s launch organization. Self-service institution onboarding, commercial tenant billing and marketplace administration are excluded.

### 3.12 Kannada and English localization

- Kannada-first learner navigation, guidance, help, feedback and errors.
- English learning content displayed separately from Kannada explanation.
- `kn-IN` and `en-IN` locale handling.
- Tested Kannada font, wrapping and mixed-language rendering.
- Correct language metadata for assistive technologies.
- Review/approval workflow for learner-critical Kannada.
- Client-approved terminology glossary.

Final linguistic and academic approval remains with the client or its nominated bilingual reviewers.

### 3.13 Reporting and analytics

- Learner progress and completion summary.
- Objective mastery and review-due summary.
- Attendance and learning-day summary.
- Lesson and assessment completion.
- Speaking activity volume and trainer-review status.
- Cohort-level operational dashboard.
- Basic CSV exports.
- AI usage, latency, fallback and cost monitoring.
- Product events needed to evaluate first-session activation and weekly use.

Custom BI dashboards, predictive analytics and external data-warehouse integrations are excluded.

### 3.14 Security, privacy and accessibility

- Organization-scoped authorization and audit.
- Secure session handling and privileged-role MFA.
- Encryption in transit and managed encryption at rest.
- Signed media access and upload restrictions.
- Rate limits and secrets management.
- Privacy notice, consent and account-deletion implementation support.
- Data-retention and deletion configuration.
- Backup, restore and incident-response procedures.
- Automated dependency, secret and static-analysis checks.
- WCAG 2.2 AA target for critical web journeys.
- TalkBack and keyboard testing for agreed critical journeys.

Legal opinions, formal certification and independent penetration testing are external services.

## 4. Pilot content boundary

The software estimate includes platform configuration and integration of up to 20 client-approved lessons in one four-week pilot pathway.

The client may supply the lesson package. Each lesson should include:

- objective and level mapping;
- English model dialogue and useful phrases;
- Kannada explanation;
- audio master and transcript;
- speaking task and retry guidance;
- questions, correct answers and rationale;
- assessment mapping and review schedule;
- confirmation of media and content rights.

If the delivery team is also commissioned to create and validate the learning package, allow an additional **480–740 specialist hours** for learner research, curriculum design, English authoring, Kannada adaptation, assessment, audio production, accessibility review and pilot revision. This is academic/content production, not software coding.

Phase 1 does not promise:

- a completed nineteen-level curriculum;
- formal CEFR certification or equivalence;
- general conversational fluency in four weeks;
- accent elimination;
- guaranteed employment, interview or academic outcomes.

## 5. Deliverables

The client will receive:

- approved high-level requirements and detailed Phase 1 backlog;
- mobile-first learner UX and bilingual design system;
- responsive learner web application and installable PWA;
- signed Android release build and Android App Bundle;
- Laravel backend, database migrations and API contracts;
- trainer, content-review and administrator web console;
- agreed Kannada and English interface resources;
- AI Teacher capability and fallback content;
- offline lesson and sync functionality;
- automated tests for critical business and user journeys;
- staging and production deployments;
- monitoring, backup and operating baseline;
- Google Play store listing support and submission-ready compliance pack;
- source code in the agreed client-owned repository;
- environment, deployment, administration and release documentation;
- one administrator/trainer handover session;
- release test summary and known-limitations register.

## 6. Delivery phases and manual hours

| Phase | Principal outputs | Base hours |
|---|---|---:|
| 1. Discovery and experience design | Requirements, learner journeys, prototype, architecture and acceptance | 260 |
| 2. Secure platform foundation | Repository, CI, identity, organization/cohort model, APIs, localization foundation | 730 |
| 3. Learner web/PWA and offline speaking | Daily journey, media, recording, assessments, progress, offline queue and sync | 680 |
| 4. Trainer, admin and content operations | Cohorts, learners, attendance, content workflow, notices and reports | 300 |
| 5. AI Teacher beta | Grounding, prompts, provider integration, safety, limits, fallback and evaluation | 280 |
| 6. Android application and Google Play | Capacitor integration, permissions, device testing, AAB and store release support | 210 |
| 7. Google Cloud hosting and operations | Staging/production, deployment, secrets, monitoring, backups and runbooks | 170 |
| 8. QA, security, accessibility and UAT | Integration/E2E, devices, offline failures, load, accessibility and defect cycles | 420 |
| 9. Project management, documentation and handover | Reviews, demos, UAT coordination, release management, guides and training | 350 |
| **Expected execution subtotal** |  | **3,400** |
| **Controlled contingency reserve (20%)** | In-scope technical uncertainty; not additional features | **680** |
| **Planning authorization** | Rounded client baseline | **4,100 hours** |

### What the estimate means

- Approximately 2,790 base hours cover hands-on engineering, integration, technical QA, deployment and release work.
- Approximately 610 base hours cover discovery, experience design, project/release management, documentation and handover.
- The reserve is controlled and is not automatically consumed.
- New features, additional languages, additional content volume, new integrations or changed acceptance criteria require written change control.
- AI assistance improves execution efficiency but does not remove human review, testing, security, device and publishing work.

## 7. Indicative schedule and team

The expected elapsed duration is approximately **28–34 delivery weeks** after required inputs and access are available.

A suitable blended team is:

- technical lead/backend engineer;
- backend/platform engineer;
- frontend/mobile engineer;
- QA automation engineer;
- part-time UX/product designer;
- part-time DevOps/security support;
- part-time project/product manager.

Curriculum, Kannada review, audio production and client approvals should run in parallel. A smaller team can complete the same scope, but calendar duration will increase even though total effort remains similar.

Indicative sequence:

1. Weeks 1–4: discovery, content inventory, prototype and architecture.
2. Weeks 4–10: platform foundation, identity, localization and CI.
3. Weeks 8–18: learner journey, backend domains, media and offline sync.
4. Weeks 14–22: trainer/admin, content workflow and reporting.
5. Weeks 18–25: AI Teacher, Android integration and device testing.
6. Weeks 24–30: staging UAT, hardening, performance and accessibility.
7. Weeks 29–34: production deployment, closed testing and Google Play submission.

The schedule excludes client approval delays, content-production delays and Google-controlled review time.

## 8. Web hosting and production operations

Included:

- one staging and one production environment;
- secure domain and TLS configuration support;
- automated build and deployment pipeline;
- database migration process;
- secrets and environment configuration;
- managed backup configuration;
- application logs, basic metrics, alerts and error reporting;
- health/readiness checks;
- basic capacity and load validation for the agreed pilot;
- release and rollback runbook;
- one production launch.

Not included:

- monthly cloud or third-party charges;
- 24/7 managed operations;
- around-the-clock incident response;
- multi-region active-active disaster recovery;
- formal compliance certification;
- unlimited post-launch deployments.

## 9. Google Play release scope

The Android release will:

- use an Android App Bundle;
- target Android 16 / API level 36 to meet the requirement taking effect on 31 August 2026;
- use Play App Signing and a client-owned package identity;
- include app icon, splash screen, versioning and production signing configuration;
- include microphone and storage/network disclosures appropriate to the implemented features;
- include internal testing and one closed-testing release;
- include store title, short/long description, screenshots and release notes based on client-approved branding;
- support completion of target-audience, content-rating and Data safety declarations;
- link to the client-approved public privacy policy and account-deletion page;
- include one production submission and reasonable responses to review questions concerning the delivered software.

Client responsibilities:

- create and verify the appropriate Play Console account;
- retain account ownership, signing authority and payment responsibility;
- provide verified organization information and any required D-U-N-S details;
- approve the app name, package name, descriptions, screenshots and legal disclosures;
- provide the public privacy-policy and account-deletion URLs;
- provide testers and feedback.

If the client uses a newly created personal developer account, Google currently requires at least 12 opted-in closed testers for 14 continuous days before production access can be requested. This waiting period is outside manual engineering hours.

Google controls app review, policy interpretation, approval and timing. Approval cannot be contractually guaranteed. Review changes caused by new client content, policy changes, account verification or newly requested functionality are handled separately.

## 10. Client responsibilities and dependencies

The client will:

- nominate one decision-maker and one academic/Kannada approver;
- provide approved branding and organization details;
- provide or approve the pilot curriculum, Kannada copy, audio and assessment answers;
- confirm content and media usage rights;
- provide sample learner/cohort data in the agreed format;
- provide timely milestone feedback and sign-off;
- fund and provide access to cloud, domain, SMS, email, AI-provider and Play Console accounts;
- provide privacy policy, terms, consent wording and legal decisions;
- provide representative learners, trainers and Android devices for UAT;
- provide at least 12 testers if the selected Play Console account requires them;
- avoid directly changing production configuration outside the agreed process.

Delays in these dependencies may move the delivery schedule.

## 11. Assumptions

- Phase 1 supports Kannada and English only.
- The production pilot is for adults aged 18 or older.
- Android is included; iOS is not included.
- Android shares the approved React learner experience through Capacitor.
- The client launch uses one production region.
- The platform is configured for one launch organization with organization-aware foundations.
- The client supplies or separately commissions the 20-lesson content package.
- AI uses a third-party provider through an abstraction layer; provider charges are client-paid.
- Automated pronunciation scoring is not used for marks or progression.
- One consolidated feedback cycle is included for each milestone and one formal UAT defect cycle.
- Supported browsers, Android versions, device matrix, pilot load and report formats are frozen during discovery.
- Third-party policy or API changes after scope approval may require change control.

## 12. Exclusions unless separately agreed

- iOS application or Apple App Store submission.
- A separately designed native Kotlin or Flutter learner application.
- Live classes, video conferencing or trainer calling.
- Peer-to-peer chat, social feed or learner marketplace.
- Payments, subscriptions, coupons or accounting.
- CRM, ERP, HRMS, school SIS/LMS or external institution integrations.
- Full authoring and translation of all nineteen levels.
- Custom speech-recognition or pronunciation-scoring model development.
- Offline AI Teacher.
- Formal CEFR certification or examination.
- Predictive ML, dropout scoring or automated high-stakes decisions.
- Self-service multi-organization sales, billing and tenant provisioning.
- Large historical-data migration or manual data cleansing.
- Independent penetration test, legal opinion or compliance certification.
- Learner hardware, connectivity or mobile-data reimbursement.
- Marketing, learner acquisition, app-store ranking or download guarantees.
- Continuous content operations, 24/7 support or ongoing managed hosting.

## 13. Acceptance approach

A milestone is accepted when:

- the agreed journeys work on the documented browser and device matrix;
- Kannada and English render correctly;
- role permissions operate as specified;
- online, offline and reconnect scenarios pass the agreed tests;
- attendance and learning progress remain distinct;
- critical submissions are not lost or duplicated;
- AI failure does not block the core lesson;
- no release-blocking defect remains;
- the client completes review within the agreed period and provides written acceptance.

Google Play approval is not a software acceptance condition. The acceptance deliverable is a policy-ready, signed Android App Bundle, completed listing pack and supported submission through the agreed testing/release track.

Enhancements, preference changes, additional content and newly requested workflows are not defects.

## 14. Warranty, support and change control

### Included launch warranty

- 45 calendar days from production launch.
- Covers reproducible defects where delivered behaviour differs from the signed-off scope.
- Includes reasonable correction and redeployment of confirmed defects.

### Warranty exclusions

- new features or changed preferences;
- client content or configuration errors;
- unsupported modifications;
- third-party outages or policy changes;
- AI response variation that does not breach an agreed deterministic safety/format rule;
- device or browser versions outside the agreed matrix.

Ongoing monitoring, security updates, dependency upgrades, content support, Play Store updates and feature development require a separate maintenance agreement.

### Change control

Any requested change to features, languages, integrations, content volume, devices, reports, hosting, compliance scope or schedule will be documented with its hour and timeline impact. Work begins only after written approval.

## 15. Recommended next steps

1. Approve the high-level Phase 1 scope and 4,100-hour planning baseline.
2. Confirm whether the client or delivery team owns the 20-lesson content package.
3. Confirm adult-only pilot, launch organization and expected learner volume.
4. Confirm Google Cloud, domain, messaging, AI-provider and Play Console ownership.
5. Provide branding, curriculum samples, Kannada reviewer and representative learners.
6. Run the discovery and architecture workshops.
7. Convert the approved plan into a milestone-based commercial statement of work.

---

## Current platform references

The following requirements were checked on 29 July 2026:

- Android target API requirement: https://developer.android.com/google/play/requirements/target-sdk
- Android App Bundles: https://developer.android.com/guide/app-bundle
- Google Play testing for new personal accounts: https://support.google.com/googleplay/android-developer/answer/14151465
- Google Play Data safety: https://support.google.com/googleplay/android-developer/answer/10787469
- Google Play privacy and account deletion: https://support.google.com/googleplay/android-developer/answer/10144311
- Capacitor Android and Google Play deployment: https://capacitorjs.com/docs/android
- WCAG 2.2: https://www.w3.org/TR/WCAG22/

