# Soft-launch content status (APP-0053)

**Updated:** 10 August 2026
**Authority:** [SOFT_LAUNCH_UBUNTU_RUNBOOK.md](SOFT_LAUNCH_UBUNTU_RUNBOOK.md)

## Current host database (observed during soft-launch standup)

| Item | Count / note |
|---|---|
| Active publication heads | 2 programs already have `active_publication_id` |
| Curriculum publications | 2 |
| Media assets | 30 |
| Media rights revisions | 30 |
| Enrollments | 2 |

This means the soft-launch host already has real published pathway material and
enrollments from prior Direct-organization work. Soft launch does **not** need
to invent demo fixtures for Learn to exist.

## Operator checklist to expand the pilot

1. Open the HTTPS soft-launch URL from `make soft-launch-tunnel`.
2. Sign in as Direct staff; enroll/confirm TOTP.
3. Verify **Learners** shows expected enrollments; issue new invitation codes
   for additional adults only.
4. In **Content Studio**, confirm the active publication is the intended
   tuition pathway; if not, publish a successor with your owned media under
   APP-0044 umbrella evidence + exact rights confirmation.
5. In **Media Library**, confirm model audio / lesson video / lesson audio for
   the pathway are clean, rights-confirmed and attached.
6. Smoke as a learner invitation: Learn → Check → Result; Practice (live Azure
   after secret fill); Role-play; Progress.

## Azure tutoring gate

Live Confidence Conversation / role-play coaching remains kill-switched until:

1. `~/ge-conversation-model/secrets/azure-openai.secret` is non-empty mode 0600;
2. endpoint/deployment are filled in soft-launch conversation env;
3. `CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED=true`;
4. `make soft-launch-restart` after the change.

## Invite policy

- Consented adults only.
- Owner-known invitees may use Mailpit/operator-relayed OTP for ≤7 days.
- Keep soft launch invite-only; no Play public listing under APP-0053.
