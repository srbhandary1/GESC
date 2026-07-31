# Global Spoken English App — Phase 1 Codex Handoff Plan

## Purpose
This document is a step-by-step implementation plan for building Phase 1 of the Global Spoken English App as a production-ready student learning web application. The app structure is centered on five primary modules: Attendance, AI Teacher, Daily Activity, My Levels, and Notice Board.[cite:1]

The plan is written for an AI coding agent that can execute backend, frontend, database, API, testing, and deployment work in sequence. The preferred implementation style is end-to-end, cloud-native, and oriented toward correctness, scalability, and clean module boundaries rather than fast prototyping shortcuts.[cite:2][cite:3][cite:4]

## Critical language rule
All explanations shown to the student in Phase 1 must be in Kannada by default unless a specific screen or lesson explicitly requires English output. This requirement applies to AI Teacher explanations, course structure explanations, level explanations, task guidance, notices meant for comprehension support, and learner-facing help text.[cite:1]

English can still appear as learning content inside the spoken-English curriculum, but the explanatory layer of the product must be Kannada-first so students always understand what to do next.[cite:1]

Codex must therefore implement bilingual capability at the system level but configure Phase 1 learner explanations as:
- Default explanation language: Kannada.
- Learning/practice language: English where relevant.
- Optional alternate explanation language setting: English, but disabled by default in first release.[cite:1]

## Product objective
Phase 1 should deliver a learner-facing platform that helps a student:
- mark and view attendance,
- interact with an AI teacher with Kannada explanations and spoken-English learning support,
- complete one daily audio lesson and one daily quiz,
- track level progression across the full course hierarchy,
- read important notices and trainer updates.[cite:1]

The primary product goal is habit formation through a simple daily flow:
1. Open dashboard.
2. See attendance and today’s task.
3. Complete the daily lesson.
4. Take the quiz.
5. View score and progress.
6. Receive AI guidance and motivation in Kannada.
7. Progress toward level completion.[cite:1]

## Delivery philosophy
The target user prefers enterprise-level planning, cloud-native execution, full-stack AI-assisted development, and avoidance of local-runtime-heavy workflows where possible.[cite:2][cite:4][cite:5]

Implementation should therefore follow these principles:
- Build the system in modular slices, not disconnected screens.
- Keep the data model clean enough for later AI and analytics extensions.
- Prioritize PWA/web-first delivery for speed, with a mobile-app path reserved for later phases.[cite:6]
- Keep AI integration isolated behind service classes so model providers can change later.
- Design for later trainer/admin modules even if they are not fully exposed in Phase 1.
- Keep explanation language separate from learning content language.

## Recommended technical stack
The user’s ecosystem strongly aligns with Laravel, React, PostgreSQL-class databases, cloud deployment, and AI-assisted full-stack development workflows.[cite:7]

### Suggested stack
- Backend: Laravel API application.
- Frontend: React web app or Laravel + React/Inertia; choose one and do not mix paradigms mid-project.
- Database: PostgreSQL.
- Auth: Laravel Sanctum or token-based API auth.
- File storage: S3-compatible object storage for audio files and assignments.
- Queue: Redis-backed queues.
- Realtime/notifications: optional in Phase 1, event-ready design.
- AI layer: provider abstraction supporting OpenAI-compatible APIs and future ML services.
- Deployment: cloud-hosted managed services, no dependency on local-only services.[cite:4][cite:5]

### Architecture recommendation
Use a modular monolith for Phase 1. This keeps development speed high while preserving clean domains:
- `Identity`
- `Students`
- `Attendance`
- `DailyActivity`
- `Levels`
- `Content`
- `AI Teacher`
- `Notices`
- `Reporting`
- `Localization`

This is the right trade-off for a solo founder working with AI coding agents, because it avoids premature microservices but still gives Codex clear boundaries to implement.[cite:2]

## User roles
Phase 1 should support three roles in the model, even if only one UI is fully built now:

| Role | Purpose | Phase 1 status |
|---|---|---|
| Student | Learns, tracks progress, uses AI teacher | Full UI |
| Trainer | Posts notices, reviews progress, monitors activity | API/model ready, limited UI optional |
| Admin | Manages levels, content, users, and reports | API/model ready, internal tools later |

This role separation reduces rework later when the app grows into a trainer-led or institution-led platform.[cite:1]

## Core functional scope

### 1. Dashboard
The dashboard is the operational home screen. It should summarize the student’s current state in one view.[cite:1]

Required dashboard widgets:
- Attendance summary: today status, monthly percentage, quick report link.
- AI Teacher panel: greeting in Kannada, current-level reminder in Kannada, motivation in Kannada.
- Daily Activity panel: today’s audio lesson, quiz status, score.
- My Levels panel: current level, progress percentage, next unlock state.
- Notice Board panel: latest active notices.

### 2. Attendance
Attendance must support:
- Daily attendance status.
- Attendance percentage.
- Monthly attendance report.[cite:1]

Phase 1 business rules:
- One attendance record per student per date.
- Attendance can be auto-marked when the student completes a daily engagement threshold, or manually marked by trainer/admin later.
- Percentage is computed monthly and cumulatively.
- Student-facing attendance explanation text must be shown in Kannada.[cite:1]

### 3. AI Teacher
AI Teacher must support structured learner guidance with Kannada explanations and English-learning support.[cite:1]

Phase 1 feature set:
- Welcome the student in Kannada.
- Explain the course structure in Kannada.
- Explain each level in Kannada.
- Remind the student about current level in Kannada.
- Show attendance status in Kannada.
- Show daily activity score in Kannada.
- Motivate completion of today’s task in Kannada.[cite:1]

### 4. Daily Activity
Daily Activity must support:
- One daily audio lesson.
- One daily quiz with five questions.
- One mark per question.
- Daily total of five marks.
- Monthly score rollup.
- Monthly progress report.[cite:1]

All learner instructions for starting the lesson, answering the quiz, and understanding the score must be explained in Kannada in Phase 1.[cite:1]

### 5. My Levels
The level hierarchy must include:
- Pre-Level 1
- Pre-Level 2
- Levels 1 through 17.[cite:1]

Every level must contain these eight components:
- AI Teacher
- Audio Lessons
- Content Room
- Conversation Room
- Activity Room
- Assignment
- Assessment
- Unlock Next Level.[cite:1]

All level-intro explanations and completion guidance must be in Kannada by default.[cite:1]

### 6. Notice Board
Notice Board must support:
- Daily announcements
- Trainer messages
- Course updates
- Holiday notices
- Motivational messages
- Upcoming events.[cite:1]

Phase 1 rule: notices written for student understanding should be stored and rendered in Kannada first, with optional English version later.[cite:1]

## Product flows

### Student daily flow
1. Student logs in.
2. Dashboard loads personalized summary.
3. Student sees today’s attendance status.
4. Student opens Daily Activity.
5. Student listens to the assigned audio lesson.
6. Student takes the five-question quiz.
7. Score is saved.
8. AI Teacher updates its response using fresh progress data and explains the result in Kannada.
9. Dashboard reflects updated progress.[cite:1]

### Level progression flow
1. Student opens current level.
2. Student sees level guidance in Kannada.
3. Student completes audio/content/conversation/activity sequence.
4. Student submits assignment.
5. Student completes assessment.
6. System verifies unlock criteria.
7. Next level becomes available with Kannada guidance.

### Notice consumption flow
1. Student opens dashboard or notice board.
2. System shows latest active notices by priority and date.
3. Student reads notices in Kannada by default.

## Domain model

### Main entities

| Entity | Purpose | Key fields |
|---|---|---|
| users | auth identity | id, name, phone/email, password_hash, role, status |
| students | learner profile | id, user_id, admission_no, preferred_language, explanation_language, current_level_id, joined_on |
| trainers | trainer profile | id, user_id, display_name, status |
| levels | course hierarchy | id, code, name, sequence_no, is_prelevel |
| level_modules | standard components per level | id, level_id, module_type, title, sort_order |
| student_level_progress | progress per level | id, student_id, level_id, status, unlocked_at, completed_at, progress_pct |
| audio_lessons | audio learning unit | id, level_id, title, language, audio_url, duration_sec, transcript |
| content_items | reading/explanation content | id, level_id, title, body, language, explanation_language, content_type |
| conversation_prompts | speaking practice prompts | id, level_id, prompt_text, language, difficulty |
| activity_items | task/activity units | id, level_id, title, instructions, instruction_language, max_score |
| assignments | assignment definitions | id, level_id, title, instructions, instruction_language, submission_type, max_score |
| student_assignments | learner submissions | id, assignment_id, student_id, submission_url/text, submitted_at, score |
| assessments | level assessment definitions | id, level_id, title, total_marks, pass_marks |
| assessment_attempts | learner assessment attempts | id, assessment_id, student_id, score, passed, attempted_at |
| daily_activities | daily bundle | id, activity_date, level_id, audio_lesson_id, quiz_id, status |
| quizzes | daily quiz definitions | id, title, total_questions, total_marks, instruction_language |
| quiz_questions | questions | id, quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option |
| quiz_attempts | student quiz attempt | id, quiz_id, student_id, score, submitted_at |
| quiz_answers | answer detail | id, quiz_attempt_id, question_id, selected_option, is_correct |
| attendance_records | daily attendance | id, student_id, attendance_date, status, source |
| notices | communication items | id, category, title, body, language, priority, starts_at, ends_at, created_by |
| ai_chat_sessions | AI conversation session | id, student_id, explanation_language, learning_language, context_snapshot_json |
| ai_chat_messages | AI exchange log | id, session_id, role, message_text, message_language, token_count |
| progress_snapshots | monthly analytics cache | id, student_id, snapshot_month, attendance_pct, monthly_score, level_progress_json |
| translations | UI/static content localization | id, namespace, key, language, value |

### Enumerations
Use enums/constants for:
- `role`: student, trainer, admin.
- `attendance_status`: present, absent, excused.
- `level_progress_status`: locked, unlocked, in_progress, completed.
- `notice_category`: announcement, trainer_message, update, holiday, motivation, event.
- `module_type`: ai_teacher, audio_lessons, content_room, conversation_room, activity_room, assignment, assessment, unlock_next_level.
- `preferred_language`: english.
- `explanation_language`: kannada, english.

## Localization design instructions for Codex
Codex must not treat Kannada as a cosmetic label. Kannada explanation support must be implemented as a first-class system requirement.

Implementation requirements:
- Add `explanation_language` to the student profile and default it to `kannada`.
- Build UI labels through a translation layer, not hardcoded English strings.
- Separate explanation text from English-learning content.
- Support future multilingual additions without changing core table structure.
- Ensure every AI Teacher response includes `message_language` metadata.
- Ensure notices and instructional content can be stored in Kannada.

## Database design instructions for Codex
Codex should implement schema in this order:
1. users
2. students and trainers
3. levels and level_modules
4. localization and content tables
5. daily activity and quiz tables
6. attendance tables
7. AI chat tables
8. notices
9. analytics snapshot tables

Implementation notes:
- Use UUIDs or bigint IDs consistently across the project.
- Add `created_at`, `updated_at`, `deleted_at` where soft deletes matter.
- Add indexes on `student_id`, `level_id`, `activity_date`, `attendance_date`, and notice visibility windows.
- Add indexes on `language`, `explanation_language`, and translation keys.
- Use foreign-key constraints everywhere practical.
- Use JSON columns only for derived snapshots or flexible AI context; do not replace relational structure with JSON.

## API design
Use versioned APIs: `/api/v1/...`

### Auth endpoints
- `POST /auth/login`
- `POST /auth/logout`
- `GET /auth/me`

### Dashboard endpoints
- `GET /dashboard/summary`
- `GET /dashboard/notices`

### Attendance endpoints
- `GET /attendance/today`
- `GET /attendance/monthly`
- `GET /attendance/percentage`

### AI Teacher endpoints
- `POST /ai-teacher/session/start`
- `POST /ai-teacher/message`
- `GET /ai-teacher/context`
- `POST /ai-teacher/language`

### Daily Activity endpoints
- `GET /daily-activity/today`
- `GET /daily-activity/:date`
- `POST /quiz-attempts`
- `GET /quiz-attempts/monthly-summary`

### Levels endpoints
- `GET /levels`
- `GET /levels/current`
- `GET /levels/:id`
- `GET /levels/:id/progress`
- `POST /levels/:id/unlock-check`

### Assignment and assessment endpoints
- `POST /assignments/:id/submit`
- `GET /assignments/:id/status`
- `POST /assessments/:id/attempt`

### Notice endpoints
- `GET /notices`
- `GET /notices/:id`

### Localization endpoints
- `GET /localization/bootstrap`
- `POST /student/preferences/explanation-language`

### Admin/trainer-ready endpoints
- `POST /admin/notices`
- `POST /admin/daily-activities`
- `POST /admin/audio-lessons`
- `POST /admin/levels`
- `POST /admin/translations`

## Dashboard response contract
Codex should define a consolidated dashboard DTO shaped like this:

```json
{
  "student": {
    "id": "",
    "name": "",
    "preferred_language": "english",
    "explanation_language": "kannada",
    "current_level": {
      "id": "",
      "name": "Level 2"
    }
  },
  "attendance": {
    "today_status": "present",
    "month_percentage": 92,
    "attended_days": 23,
    "missed_days": 2,
    "kannada_hint": "ಇಂದು ನಿಮ್ಮ ಹಾಜರಾತಿ ದಾಖಲಾಗಿದೆ"
  },
  "daily_activity": {
    "date": "2026-07-29",
    "audio_lesson_title": "",
    "quiz_completed": true,
    "quiz_score": 4,
    "max_score": 5,
    "kannada_instruction": "ಇಂದಿನ ಪಾಠವನ್ನು ಪೂರ್ಣಗೊಳಿಸಿ"
  },
  "level_progress": {
    "current_level_progress_pct": 67,
    "next_unlock_ready": false,
    "kannada_summary": "ನೀವು ಈಗ ಲೆವೆಲ್ 2 ನಲ್ಲಿ ಇದ್ದೀರಿ"
  },
  "ai_teacher": {
    "explanation_language": "kannada",
    "learning_language": "english",
    "message_preview": ""
  },
  "notices": [
    {
      "id": "",
      "category": "announcement",
      "title": "",
      "language": "kannada",
      "priority": 1,
      "published_at": ""
    }
  ]
}
```

This single aggregated response keeps the dashboard fast and reduces frontend orchestration complexity.

## Frontend information architecture

### Main navigation
All navigation labels for student use should render in Kannada by default in Phase 1.[cite:1]
- Dashboard
- Attendance
- AI Teacher
- Daily Activity
- My Levels
- Notice Board

Codex may keep internal route names in English, but visible labels should come from localization files.

### Dashboard sections
- Header: greeting in Kannada, profile, explanation-language indicator.
- KPI row: attendance, monthly score, current level, streak.
- Today panel: audio lesson and quiz action with Kannada instructions.
- AI Teacher panel.
- Level progress panel.
- Notice preview panel.

### Attendance page
- Today status card.
- Attendance percentage card.
- Monthly calendar/table view.
- Kannada explanation helper text.

### AI Teacher page
- Explanation-language selector with Kannada default.
- Chat thread.
- Suggested prompts in Kannada.
- Student status summary ribbon.
- Action button: continue today’s task.

### Daily Activity page
- Today’s audio lesson player.
- Transcript/summary panel.
- Quiz section below audio.
- Submission result block in Kannada.
- Monthly score overview.

### My Levels page
- Vertical list or card grid of all levels.
- Current level highlighted.
- Locked/unlocked/completed states.
- Kannada guidance labels.
- Clicking a level opens level detail page.

### Level detail page
Every level detail view must show the eight components in this order:[cite:1]
1. AI Teacher
2. Audio Lessons
3. Content Room
4. Conversation Room
5. Activity Room
6. Assignment
7. Assessment
8. Unlock Next Level

Explanatory wrappers, empty states, progress hints, and next-step messages must be shown in Kannada.[cite:1]

### Notice Board page
- Filter tabs by category.
- Priority notices pinned.
- Date-based list for the rest.
- Kannada-first student notices.

## UX and UI rules for Codex
The app is a web application, so the UI should follow dashboard and product-UI conventions rather than landing-page aesthetics. Dashboard guidance emphasizes compact typography, clear hierarchy, semantic layout, and a single primary scroll region for the main content area.[cite:8][cite:9]

Implementation rules:
- Use a sidebar on desktop and a bottom nav or drawer on mobile.[cite:8]
- Keep one main scroll area in the app shell.[cite:8]
- Use clear card sections with restrained colors and consistent spacing.[cite:8]
- Avoid decorative gradients, heavy visual effects, or marketing-page patterns in the core app UI.[cite:8]
- Make current task and current level visually dominant.[cite:1][cite:8]
- Use progress bars, chips, and status indicators for level state.
- Support dark mode from the beginning if affordable within timeline.[cite:8]
- Make touch targets at least 44x44 and keep body text at 16px or above.[cite:8]
- Ensure Kannada labels render correctly with font and layout support.

## AI Teacher architecture
AI should not be hardcoded into controllers. Build an abstraction layer.

### Required services
- `StudentContextService`
- `AITeacherPromptBuilder`
- `AITeacherProviderInterface`
- `OpenAICompatibleTeacherProvider`
- `AITeacherResponseSanitizer`
- `AITeacherLoggingService`
- `LocalizationResolver`

### AI context inputs
For each message, the provider should receive:
- student name,
- preferred learning language,
- explanation language,
- current level,
- attendance today,
- current month attendance percentage,
- today’s quiz score,
- today’s activity completion state,
- latest notice snippets if relevant.

### Prompt strategy
System prompt should define the teacher as:
- encouraging,
- simple and clear,
- course-aware,
- Kannada-first for explanations,
- English-aware for learning content,
- non-toxic and non-shaming,
- focused on spoken English progress.

Response constraints:
- Keep answers short by default.
- Explanation language must be Kannada unless the student or screen explicitly switches it.
- Mention current level when relevant.
- Mention attendance or score only when data exists.
- End with one practical next action in Kannada.
- If English examples are used, wrap them with Kannada explanation.

### AI modes in Phase 1
- Welcome mode.
- Explain course structure mode.
- Explain current level mode.
- Progress reminder mode.
- Daily motivation mode.
- Daily task completion nudge mode.

### Kannada prompt contract for Codex
Codex must enforce this logic in prompt builder:
- `learning_language = english`
- `explanation_language = kannada`
- `ui_language = kannada`

Example response style:
- Kannada explanation sentence.
- English phrase or target sentence if needed.
- Kannada instruction about what to do next.

## ML extension roadmap
The user explicitly wants room to integrate AI and ML models later if needed.[cite:10][cite:11]

Phase 1 should therefore leave extension points for:
- speech-to-text for pronunciation evaluation,
- pronunciation scoring models,
- fluency scoring,
- answer recommendation or remediation engine,
- quiz difficulty adaptation,
- dropout-risk prediction,
- attendance risk alerts,
- personalized next-best-lesson recommendations.

### How to design for later ML without overbuilding now
- Store audio interaction metadata even if no scoring model is active yet.
- Store quiz attempt histories in enough detail for later analytics.
- Store progress snapshots monthly.
- Store AI prompts and responses logged with privacy controls and language metadata.
- Create an event layer for key learner actions.

### Recommended event names
- `student.logged_in`
- `student.viewed_dashboard`
- `student.played_audio_lesson`
- `student.completed_quiz`
- `student.submitted_assignment`
- `student.completed_assessment`
- `student.unlocked_level`
- `student.sent_ai_message`
- `student.received_ai_response`
- `student.changed_explanation_language`

These events can later feed analytics, recommendations, and ML pipelines.

## Business rules

### Attendance rules
- One record per student per day.
- Present can be derived when the student completes a minimum meaningful activity, for example opening the day alone is not enough.
- Auto-marking logic should be configurable.
- Attendance explanation must be shown in Kannada in student-facing UI.[cite:1]

### Quiz rules
- Five questions per day.
- One mark per correct answer.
- One attempt by default in Phase 1 unless admin setting says otherwise.
- Monthly score is sum of daily quiz scores.
- Monthly maximum can vary between 150 and 155 depending on number of active days in the month.[cite:1]
- Quiz instructions and result explanation must be in Kannada in Phase 1.[cite:1]

### Level unlock rules
Codex should implement a rule engine, even if simple.

Minimum unlock condition example:
- all mandatory lesson items completed,
- assignment submitted,
- assessment passed,
- any trainer override respected.

Unlock result messaging must be in Kannada in the student interface.[cite:1]

### Notice rules
- Notices have start and end windows.
- Notices have priority.
- Only active notices are shown to students.
- Motivational notices can rotate and need not remain pinned.
- Student-facing notices should support Kannada as primary language.[cite:1]

## Security and privacy
The app handles student identity, learning records, and AI chat logs. Basic production controls are required from day one.

Minimum controls:
- Role-based authorization policies.
- Validation on every mutating endpoint.
- Rate limiting for AI chat endpoints.
- Sanitization of AI output before rendering.
- Safe HTML rendering rules; prefer markdown/plain text over arbitrary HTML.
- Audit log for admin/trainer changes to notices and core content.
- Signed URLs for private audio or submission assets if needed.

## Performance and scaling
Phase 1 can be modest in infrastructure but should avoid patterns that block growth.

Recommendations:
- Cache dashboard summary for short intervals.
- Precompute monthly progress snapshots nightly.
- Queue audio-processing and heavy AI logging work.
- Paginate notices and history views.
- Use CDN-backed object storage for media.
- Add indexes before data grows.
- Cache localization bundles for fast app boot.

## Testing plan
Codex should not treat testing as optional.

### Backend tests
- Authentication tests.
- Authorization policy tests.
- Attendance calculation tests.
- Monthly score aggregation tests.
- Level unlock rule tests.
- Notice visibility tests.
- AI Teacher service tests with mocked provider.
- Kannada explanation-language default tests.
- Localization fallback tests.

### Frontend tests
- Dashboard rendering tests.
- Attendance page states.
- Quiz flow tests.
- Level detail navigation tests.
- Notice board filters.
- Explanation-language rendering tests.
- Kannada label coverage tests.

### End-to-end tests
- Student logs in and sees Kannada-first dashboard.
- Student completes daily quiz and score updates.
- Student opens AI Teacher and receives Kannada explanation with English-learning support.
- Student completes level requirements and unlock state updates.

## Seed data plan
Codex should create realistic seeders.

Minimum seed data:
- 1 admin user.
- 2 trainer users.
- 10 student users.
- Full level master data for Pre-Level 1, Pre-Level 2, and Levels 1–17.[cite:1]
- At least one fully seeded level with all eight components.
- 14 days of daily activity data.
- 20 notices across categories, seeded in Kannada for student-facing use.[cite:1]
- Demo attendance and quiz histories.
- Translation seeds for core student UI.

This is essential so UI and AI behavior can be tested meaningfully from the first deployment.

## Step-by-step development roadmap for Codex

### Phase A — foundation
1. Initialize repository and project structure.
2. Set coding standards, formatter, linter, test runner, and environment variable strategy.
3. Create modular directory structure for domains.
4. Set up auth and role system.
5. Add localization module and Kannada translation bootstrap.
6. Add CI pipeline for linting and tests.

### Phase B — core schema
1. Build migrations for users, students, trainers.
2. Build levels and level module schema.
3. Build localization, content, audio, assignments, assessments schema.
4. Build daily activity and quiz schema.
5. Build attendance schema.
6. Build notices schema.
7. Build AI chat schema.
8. Add seeders and factories.

### Phase C — backend domain services
1. Implement dashboard summary service.
2. Implement attendance calculator service.
3. Implement daily activity assignment service.
4. Implement quiz submission and scoring service.
5. Implement level progress and unlock service.
6. Implement notice visibility service.
7. Implement AI Teacher context service and provider interface.
8. Implement localization resolver and Kannada fallback service.

### Phase D — API layer
1. Build auth endpoints.
2. Build dashboard endpoint.
3. Build attendance endpoints.
4. Build daily activity and quiz endpoints.
5. Build levels and progress endpoints.
6. Build notices endpoints.
7. Build AI Teacher endpoints.
8. Build localization bootstrap endpoints.
9. Add request validation, policies, resources, and error handling.

### Phase E — frontend app shell
1. Build app layout with sidebar/top bar/mobile nav.
2. Build login flow.
3. Build Kannada localization bootstrapping.
4. Build dashboard screen.
5. Build loading, empty, and error states.
6. Build theme foundation and reusable component library.

### Phase F — feature screens
1. Attendance page.
2. AI Teacher page.
3. Daily Activity page.
4. My Levels page.
5. Level detail page.
6. Notice Board page.
7. Ensure explanation UI copy is Kannada-first across all screens.

### Phase G — AI integration
1. Add provider config and secrets handling.
2. Implement prompt builder.
3. Implement context assembly.
4. Implement message endpoint.
5. Add moderation/sanitization layer.
6. Add fallback response logic when model fails.
7. Add Kannada-first response tests.

### Phase H — analytics and reports
1. Monthly score summaries.
2. Attendance monthly report API.
3. Progress snapshot generation.
4. Dashboard trend cards if needed.
5. Language usage metrics if useful later.

### Phase I — hardening
1. Full test pass.
2. Security review.
3. Performance pass.
4. Seeded staging deployment.
5. UAT fixes.

## Detailed development tasks Codex can execute sequentially

### Task 1: scaffold project
Output expected:
- Working repo.
- Configured environment examples.
- Auth bootstrapped.
- Role middleware configured.
- Localization module initialized with Kannada default.

### Task 2: implement level master model
Output expected:
- Level model and migrations.
- Seeder for Pre-Level 1, Pre-Level 2, and Levels 1–17.
- Standard module template attached to every level.[cite:1]

### Task 3: implement student profile and dashboard summary
Output expected:
- Student profile model.
- Current level linkage.
- Explanation language defaulted to Kannada.
- Dashboard summary service returning attendance, daily activity, notices, level state.

### Task 4: implement attendance domain
Output expected:
- Attendance model and APIs.
- Daily and monthly aggregation.
- Attendance percentage service.
- Kannada helper text support.

### Task 5: implement daily activity domain
Output expected:
- Daily activity model.
- Audio lesson linkage.
- Quiz, questions, attempts, scoring.
- Monthly score summary.
- Kannada instruction support.

### Task 6: implement levels and progress domain
Output expected:
- Progress tables.
- Level detail APIs.
- Unlock rule engine.
- Completed/unlocked/locked state logic.
- Kannada next-step messaging.

### Task 7: implement notice board domain
Output expected:
- Notice CRUD APIs for admin/trainer.
- Student-facing filtered list.
- Priority and active window logic.
- Kannada-first notices.

### Task 8: implement AI Teacher base layer
Output expected:
- Provider interface.
- Context builder.
- Prompt templates.
- Session/message logs.
- Kannada explanation support with English-learning content.[cite:1]

### Task 9: implement AI Teacher UI
Output expected:
- Chat screen.
- Suggested quick actions in Kannada.
- Context summary ribbon.
- Graceful loading/error states.

### Task 10: implement reports and analytics
Output expected:
- Monthly attendance report.
- Monthly score report.
- Student progress summary.
- Language-aware summaries where applicable.

### Task 11: finalize app shell and QA
Output expected:
- Consistent navigation.
- Responsive layout.
- Accessibility pass.
- Final seeded demo build.
- Kannada-first explanation verification.

## Definition of done for Phase 1
Phase 1 is complete only when all of the following are true:
- Student can authenticate.
- Dashboard loads real personalized data.
- Student-facing explanatory UI is Kannada-first.
- Attendance status and percentage are accurate.
- Student can access today’s audio lesson.
- Student can complete a five-question quiz and score is saved.[cite:1]
- Monthly score is visible.
- Student can browse all levels.
- Current level detail shows all eight required sections.[cite:1]
- Unlock logic works for at least one full seeded level.
- Notice Board displays active notices correctly.
- AI Teacher responds with Kannada explanations and English-learning support using live student context.[cite:1]
- Test suite covers core business logic.
- Seeded staging environment is demo-ready.

## Suggested prompts to give Codex
These are operational prompts the human lead can use with Codex.

### Prompt 1
Build the Laravel migrations, models, enums, factories, and seeders for the Global Spoken English App Phase 1 according to the attached handoff plan. Start with users, students, levels, level_modules, student_level_progress, attendance_records, quizzes, quiz_questions, quiz_attempts, notices, ai_chat tables, and localization support. Default student explanation language to Kannada and seed all levels from Pre-Level 1 to Level 17.[cite:1]

### Prompt 2
Implement the `/api/v1/dashboard/summary` endpoint and all supporting services so it returns student profile, explanation language, attendance summary, daily activity summary, current level progress, AI preview, and latest notices in one optimized response.

### Prompt 3
Implement the Daily Activity module end to end, including one daily audio lesson, five-question quiz, answer submission, score calculation, monthly score summary, Kannada instructions, and test coverage.[cite:1]

### Prompt 4
Implement the AI Teacher domain using a provider interface, context builder, prompt builder, session storage, Kannada explanation output, English-learning support, and context-aware guidance using attendance, level, and quiz status.[cite:1]

### Prompt 5
Build the React student app screens for Dashboard, Attendance, AI Teacher, Daily Activity, My Levels, Level Detail, and Notice Board using a clean dashboard layout and Kannada-first learner-facing copy.

## Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| AI responses become too generic | Low engagement | Inject live student context into every prompt |
| Kannada support is partial and inconsistent | Learner confusion | Centralize translation layer and test Kannada-first rendering |
| Level logic becomes inconsistent | Broken progression | Centralize unlock rules in one service |
| Attendance logic is unclear | Reporting disputes | Make attendance source and rule configurable |
| Dashboard becomes slow | Poor UX | Use aggregated summary service and caching |
| Seed data is weak | Poor demo quality | Seed realistic multi-week student activity |
| Too much AI coupling | Vendor lock-in | Use provider abstraction from day one |
| UI becomes marketing-like instead of app-like | Reduced usability | Follow dashboard-first UI rules and compact hierarchy[cite:8][cite:9] |

## Recommended immediate build order
The most efficient order for a solo founder using AI coding agents is:
1. Schema + localization + seeders.
2. Dashboard summary service.
3. Daily Activity module.
4. Levels and level detail.
5. Attendance reporting.
6. Notice Board.
7. AI Teacher integration.
8. Reports and hardening.[cite:2][cite:4]

That order ensures the app becomes demoable early while still building toward the full Phase 1 vision.
