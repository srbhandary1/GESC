#!/bin/sh
# Creates the outside-repo Azure Speech benchmark evidence workspace.
# Never writes secrets into the Git repository.

set -eu

repository_root="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
workspace="${GE_SPEECH_BENCHMARK_ROOT:-$HOME/ge-speech-benchmark}"

mkdir -p \
  "$workspace/evidence" \
  "$workspace/secrets" \
  "$workspace/env" \
  "$workspace/reports" \
  "$workspace/ephemeral"

chmod 700 "$workspace" "$workspace/ephemeral" "$workspace/secrets" "$workspace/evidence" "$workspace/env" "$workspace/reports"

# Empty secret file for the operator to paste a key/token into.
secret_file="$workspace/secrets/azure-speech.secret"
if [ ! -f "$secret_file" ]; then
  : > "$secret_file"
fi
chmod 600 "$secret_file"

cat > "$workspace/evidence/vendor-evidence.template.json" <<'EOF'
{
  "schema_version": 1,
  "provider": "azure-speech",
  "region": "centralindia",
  "approved_for_synthetic_benchmark": false,
  "data_logging_enabled": false,
  "model_improvement_enabled": false,
  "custom_speech_enabled": false,
  "subscription_evidence_id": "REPLACE_WITH_VENDOR_REGISTER_REFERENCE",
  "contracting_entity_review_id": "REPLACE_WITH_CONTRACTING_ENTITY_REVIEW_REFERENCE",
  "agreement_sha256": "REPLACE_WITH_64_LOWERCASE_HEX_SHA256",
  "dpa_sha256": "REPLACE_WITH_64_LOWERCASE_HEX_SHA256",
  "subprocessor_sha256": "REPLACE_WITH_64_LOWERCASE_HEX_SHA256",
  "privacy_legal_review_id": "REPLACE_WITH_PRIVACY_LEGAL_REVIEW_REFERENCE",
  "logging_disabled_evidence_id": "REPLACE_WITH_LOGGING_DISABLED_EVIDENCE_REFERENCE",
  "reviewed_at": "REPLACE_WITH_UTC_RFC3339",
  "valid_until": "REPLACE_WITH_UTC_RFC3339_AFTER_REVIEWED_AT"
}
EOF

cat > "$workspace/evidence/spend-approval.template.json" <<'EOF'
{
  "schema_version": 1,
  "benchmark_id": "pr009-azure-speech-synthetic-v1",
  "approval_id": "REPLACE_WITH_PROVIDER_SPEND_APPROVAL_REFERENCE",
  "approved": false,
  "cap_inr": 1000,
  "reviewed_at": "REPLACE_WITH_UTC_RFC3339",
  "valid_until": "REPLACE_WITH_UTC_RFC3339_AFTER_REVIEWED_AT"
}
EOF

cat > "$workspace/evidence/listener-evidence.template.json" <<'EOF'
{
  "benchmark_id": "pr009-azure-speech-synthetic-v1",
  "reviewer_ids": [
    "REPLACE_WITH_DEVELOPMENT_LISTENER_REFERENCE_1",
    "REPLACE_WITH_DEVELOPMENT_LISTENER_REFERENCE_2"
  ],
  "output_count": 96,
  "intelligibility_accepted_by_both": 0,
  "acceptability_accepted_by_both": 0,
  "critical_pronunciation_defects": 0
}
EOF

chmod 600 \
  "$workspace/evidence/vendor-evidence.template.json" \
  "$workspace/evidence/spend-approval.template.json" \
  "$workspace/evidence/listener-evidence.template.json"

cat > "$workspace/env/speech-benchmark.env.example" <<EOF
# Isolated benchmark process environment only.
# Copy to speech-benchmark.env (mode 0600), fill real values, then:
#   set -a; . "$workspace/env/speech-benchmark.env"; set +a
# Do not merge these into the ordinary apps/api/.env used by make demo/dev.

APP_ENV=speech-benchmark
SPEECH_BENCHMARK_ENABLED=true
AZURE_SPEECH_LIVE_REQUESTS_ENABLED=false

# Flip to true only after APO live-request authorization for the exact phase.
# AZURE_SPEECH_LIVE_REQUESTS_ENABLED=true

VOICE_UPLOAD_ENABLED=false
VOICE_PROVIDER_DRIVER=disabled
CONVERSATION_PROVIDER_DRIVER=disabled

AZURE_SPEECH_ENDPOINT=https://REPLACE_REGION.api.cognitive.microsoft.com
AZURE_SPEECH_ALLOWED_HOST=REPLACE_REGION.api.cognitive.microsoft.com
AZURE_SPEECH_CREDENTIAL_KIND=subscription-key
AZURE_SPEECH_SECRET_FILE=$workspace/secrets/azure-speech.secret
AZURE_SPEECH_CREDENTIAL_VERSION_ID=REPLACE_WITH_CREDENTIAL_VERSION_REFERENCE

AZURE_SPEECH_VENDOR_EVIDENCE_PATH=$workspace/evidence/vendor-evidence.json
SPEECH_BENCHMARK_SPEND_APPROVAL_PATH=$workspace/evidence/spend-approval.json
SPEECH_BENCHMARK_LISTENER_EVIDENCE_PATH=$workspace/evidence/listener-evidence.json
SPEECH_BENCHMARK_SAFE_REPORT_PATH=$workspace/reports/safe-report.json
SPEECH_BENCHMARK_EPHEMERAL_ROOT=$workspace/ephemeral
SPEECH_BENCHMARK_EPHEMERAL_STORAGE_ACKNOWLEDGED=false

SPEECH_BENCHMARK_RUNNER_COMMIT=REPLACE_WITH_EXACT_GIT_COMMIT
SPEECH_BENCHMARK_APPROVED_SPEND_CAP_INR=1000
SPEECH_BENCHMARK_SPEND_APPROVAL_ID=REPLACE_WITH_PROVIDER_SPEND_APPROVAL_REFERENCE

AZURE_SPEECH_SUBSCRIPTION_EVIDENCE_ID=REPLACE_WITH_VENDOR_REGISTER_REFERENCE
AZURE_SPEECH_CONTRACTING_ENTITY_REVIEW_ID=REPLACE_WITH_CONTRACTING_ENTITY_REVIEW_REFERENCE
AZURE_SPEECH_AGREEMENT_SHA256=REPLACE_WITH_64_LOWERCASE_HEX_SHA256
AZURE_SPEECH_DPA_SHA256=REPLACE_WITH_64_LOWERCASE_HEX_SHA256
AZURE_SPEECH_SUBPROCESSOR_SHA256=REPLACE_WITH_64_LOWERCASE_HEX_SHA256
AZURE_SPEECH_PRIVACY_LEGAL_REVIEW_ID=REPLACE_WITH_PRIVACY_LEGAL_REVIEW_REFERENCE
AZURE_SPEECH_LOGGING_DISABLED_EVIDENCE_ID=REPLACE_WITH_LOGGING_DISABLED_EVIDENCE_REFERENCE
AZURE_SPEECH_PRICE_SNAPSHOT_ID=REPLACE_WITH_PRICE_SNAPSHOT_REFERENCE
SPEECH_BENCHMARK_CONTENT_SCAN_EVIDENCE_ID=REPLACE_WITH_CONTENT_SCAN_EVIDENCE_REFERENCE
AZURE_SPEECH_INR_PER_STT_SECOND=REPLACE_WITH_REVIEWED_RATE
AZURE_SPEECH_INR_PER_TTS_CHARACTER=REPLACE_WITH_REVIEWED_RATE
AZURE_SPEECH_INR_CONVERSION_SOURCE=REPLACE_WITH_CONVERSION_SOURCE
AZURE_SPEECH_INR_CONVERSION_INSTANT=REPLACE_WITH_UTC_RFC3339
EOF
chmod 600 "$workspace/env/speech-benchmark.env.example"

cat > "$workspace/README.md" <<EOF
# Global English — Azure Speech benchmark workspace

This directory is **outside the Git repository** on purpose.

- Repository docs: \`$repository_root/docs/PROFESSIONAL_CLIENT_SETUP.md\`
- Handoff schema: \`$repository_root/docs/PR-009_SPEECH_BENCHMARK_EVIDENCE_HANDOFF.md\`
- Presentation demo (free speech): \`$repository_root/docs/CLIENT_DEMO_RUNBOOK.md\`

## Quick start

1. Read \`OPERATOR_CHECKLIST.md\`.
2. Create an Azure Speech resource in \`centralindia\`.
3. Paste the key or Entra token into \`secrets/azure-speech.secret\` (mode 0600).
4. Copy each \`*.template.json\` to the matching non-template filename and replace every \`REPLACE_*\` value with real reviewed evidence.
5. Copy \`env/speech-benchmark.env.example\` to \`env/speech-benchmark.env\`, fill real values, keep mode 0600.
6. Obtain explicit APO live-request authorization before flipping \`AZURE_SPEECH_LIVE_REQUESTS_ENABLED=true\`.
7. Follow \`WAITING_FOR_LIVE_AUTHORIZATION.md\` until that authorization exists.

Do not commit this directory. Do not copy secrets into \`$repository_root\`.
EOF

cat > "$workspace/OPERATOR_CHECKLIST.md" <<EOF
# Operator checklist — Phase B Azure Speech

## Before any live request

- [ ] Phase A client demo can be shown without Azure (\`make demo-client\` + tunnel)
- [ ] Azure Speech resource created in **centralindia**
- [ ] Logging / custom speech / model improvement disabled (evidence recorded)
- [ ] \`secrets/azure-speech.secret\` filled and mode \`0600\`
- [ ] \`evidence/vendor-evidence.json\` filled from template; every REPLACE_* gone
- [ ] \`evidence/spend-approval.json\` filled; \`approved: true\`; \`cap_inr\` ≤ 1000
- [ ] \`env/speech-benchmark.env\` filled; paths point at this workspace
- [ ] \`SPEECH_BENCHMARK_EPHEMERAL_STORAGE_ACKNOWLEDGED=true\` only after verifying ephemeral/ is local, mode 0700, outside web roots
- [ ] \`SPEECH_BENCHMARK_RUNNER_COMMIT\` equals the exact implementation commit
- [ ] Ordinary \`apps/api/.env\` still has Azure live flags **false**
- [ ] Accountable Product Owner live-request text recorded:
      \`Authorize live synthetic Azure benchmark up to ₹1000\`

## Commands (repository root, pinned PHP)

\`\`\`bash
# Fail-closed check (no spend expected when evidence incomplete)
sh scripts/run-speech-benchmark-preflight.sh

# After live-prep authorization only:
# set -a; . "\$HOME/ge-speech-benchmark/env/speech-benchmark.env"; set +a
# php artisan speech:benchmark --phase=prepare
\`\`\`

Full sequence: repository \`docs/PR-009_SPEECH_BENCHMARK_EVIDENCE_HANDOFF.md\`.
EOF

cat > "$workspace/WAITING_FOR_LIVE_AUTHORIZATION.md" <<EOF
# Waiting for live Azure authorization

Status: **blocked**

Live \`prepare\` / \`complete\` / \`make ai-eval\` must not run until the
Accountable Product Owner provides all of:

1. Azure Speech **endpoint** host in centralindia
2. Real credential in \`secrets/azure-speech.secret\`
3. Real vendor + spend evidence JSON (not templates)
4. Explicit sentence in \`LIVE_AUTHORIZATION.txt\`:
   \`Authorize live synthetic Azure benchmark up to ₹1000\`

APP-0013 and APP-0014 authorize implementation only. They do **not** authorize
provider spend or live requests.

Until then:

- use Phase A presentation speech (browser Web Speech, ₹0);
- keep \`AZURE_SPEECH_LIVE_REQUESTS_ENABLED=false\`;
- run only fail-closed preflight to prove gates reject incomplete evidence:
  \`sh $repository_root/scripts/run-speech-benchmark-preflight.sh\`
- live prepare is gated by:
  \`sh $repository_root/scripts/run-speech-benchmark-live-prepare.sh\`

When the four items above are present, set
\`AZURE_SPEECH_LIVE_REQUESTS_ENABLED=true\` in \`env/speech-benchmark.env\` and
run the live-prepare script, then continue the handoff from review slots.
EOF

printf 'Speech benchmark workspace ready at %s\n' "$workspace"
printf 'Secret file (paste key here): %s\n' "$secret_file"
printf 'Docs: %s/docs/PROFESSIONAL_CLIENT_SETUP.md\n' "$repository_root"
