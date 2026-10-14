# LEARN-LEVEL-08 — lesson tutor context and Practice completion

Owner request, 26 September 2026: Ask AI Tutor inside each level must teach that
lesson; passing Introduction/Practice must allow Conversation without a hidden
second completion action. Keep this bounded to the shared all-level path.

1. Start lesson question help through the authorized level/part endpoint. Resolve
   the attached blueprint on the server and pin its ID/hash, objective, patterns
   and examples in existing encrypted dialogue context. Never accept client lesson
   text or quiz answer keys. Keep Speak's general question help unchanged.
2. The tutor explains the current concept, uses level-appropriate examples and
   redirects unrelated questions briefly to the lesson. Keep short English-first
   replies and explicit selected-language explanation. Skill Focus stays a drill.
3. A passing Practice check records Practice completion in the same transaction;
   no second completion tap. Existing genuine passes may be reconciled only with
   Introduction completed and the matching current blueprint, exact organization
   and learner. Failed/missing/stale checks never fabricate completion or marks.
4. Update the UI from the server's successful result, preserve passed status across
   reloads, and keep plan/content/later-level gates. Cover configured levels using
   data-driven tests, replay and tenant/learner isolation.
5. Run verification, browser journeys, AI eval, builds and live phone checks;
   publish the matching APK and record limitations. No schema or retention change.
