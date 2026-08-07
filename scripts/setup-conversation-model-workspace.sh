#!/bin/sh
# Creates the outside-repo Azure OpenAI conversation-model evidence workspace.
# Never writes secrets into the Git repository.

set -eu

repository_root="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
workspace="${GE_CONVERSATION_MODEL_ROOT:-$HOME/ge-conversation-model}"

mkdir -p \
  "$workspace/evidence" \
  "$workspace/secrets" \
  "$workspace/env" \
  "$workspace/reports" \
  "$workspace/ephemeral"

chmod 700 "$workspace" "$workspace/ephemeral" "$workspace/secrets" "$workspace/evidence" "$workspace/env" "$workspace/reports"

secret_file="$workspace/secrets/azure-openai.secret"
if [ ! -f "$secret_file" ]; then
  : > "$secret_file"
fi
chmod 600 "$secret_file"

cat > "$workspace/evidence/vendor-evidence.template.json" <<'EOF'
{
  "schema_version": 1,
  "provider": "azure-openai",
  "region": "REPLACE_WITH_AZURE_OPENAI_REGION",
  "approved_for_conversation_model": false,
  "data_logging_enabled": false,
  "model_improvement_enabled": false,
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
  "approval_id": "APP-0022",
  "approved": false,
  "cap_inr": 500,
  "reviewed_at": "REPLACE_WITH_UTC_RFC3339",
  "valid_until": "REPLACE_WITH_UTC_RFC3339_AFTER_REVIEWED_AT"
}
EOF

cat > "$workspace/env/conversation-model.env.template" <<EOF
# Copy to conversation-model.env and fill after vendor/spend evidence is ready.
# Do not commit real values. Source only for non-production smoke.

APP_ENV=conversation-model-dev
CONVERSATION_PROVIDER_DRIVER=azure-openai-dev
CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED=false
CONVERSATION_AZURE_OPENAI_ENDPOINT=https://REPLACE.openai.azure.com
CONVERSATION_AZURE_OPENAI_ALLOWED_HOST=REPLACE.openai.azure.com
CONVERSATION_AZURE_OPENAI_DEPLOYMENT=REPLACE_DEPLOYMENT_NAME
CONVERSATION_AZURE_OPENAI_API_VERSION=2024-10-21
CONVERSATION_AZURE_OPENAI_CREDENTIAL_KIND=api-key
CONVERSATION_AZURE_OPENAI_SECRET_FILE=$workspace/secrets/azure-openai.secret
CONVERSATION_AZURE_OPENAI_APPROVED_SPEND_CAP_INR=500
CONVERSATION_AZURE_OPENAI_VENDOR_EVIDENCE_PATH=$workspace/evidence/vendor-evidence.json
CONVERSATION_AZURE_OPENAI_SPEND_APPROVAL_PATH=$workspace/evidence/spend-approval.json
CONVERSATION_AZURE_OPENAI_SUBSCRIPTION_EVIDENCE_ID=REPLACE
CONVERSATION_AZURE_OPENAI_CONTRACTING_ENTITY_REVIEW_ID=REPLACE
CONVERSATION_AZURE_OPENAI_AGREEMENT_SHA256=REPLACE_WITH_64_LOWERCASE_HEX_SHA256
CONVERSATION_AZURE_OPENAI_DPA_SHA256=REPLACE_WITH_64_LOWERCASE_HEX_SHA256
CONVERSATION_AZURE_OPENAI_SUBPROCESSOR_SHA256=REPLACE_WITH_64_LOWERCASE_HEX_SHA256
CONVERSATION_AZURE_OPENAI_PRIVACY_LEGAL_REVIEW_ID=REPLACE
CONVERSATION_AZURE_OPENAI_LOGGING_DISABLED_EVIDENCE_ID=REPLACE
CONVERSATION_AZURE_OPENAI_CREDENTIAL_VERSION_ID=REPLACE
CONVERSATION_AZURE_OPENAI_SPEND_APPROVAL_ID=REPLACE
CONVERSATION_AZURE_OPENAI_REGION_ACCEPTANCE_ID=REPLACE
EOF

cat > "$workspace/LIVE_AUTHORIZATION.txt.template" <<'EOF'
# Rename to LIVE_AUTHORIZATION.txt and keep exactly one authorizing line:
Authorize live Confidence Conversation Azure OpenAI up to ₹500
EOF

cat > "$workspace/README.txt" <<EOF
Global English — Confidence Conversation Azure OpenAI workspace
Repository: $repository_root
Authority: APP-0022 / ADR-0010

1. Fill vendor-evidence.json and spend-approval.json from the templates.
2. Paste the API key or Entra token into secrets/azure-openai.secret (mode 0600).
3. Copy env template to conversation-model.env and set LIVE_REQUESTS_ENABLED=true
   only after LIVE_AUTHORIZATION.txt contains the exact authorize line.
4. Never copy secrets into the git tree or browser/demo paths.
5. Production APP_ENV must remain CONVERSATION_PROVIDER_DRIVER=disabled.
EOF

printf 'Created conversation-model workspace at %s\n' "$workspace"
