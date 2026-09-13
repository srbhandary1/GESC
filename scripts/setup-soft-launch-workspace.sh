#!/usr/bin/env bash
# Creates outside-repo soft-launch env + Azure spend evidence scaffolds (APP-0053).

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNTIME="${GE_SOFT_LAUNCH_RUNTIME:-${HOME}/ge-soft-launch}"
CONV="${GE_CONVERSATION_MODEL_ROOT:-${HOME}/ge-conversation-model}"

mkdir -p \
  "${RUNTIME}/env" \
  "${RUNTIME}/logs" \
  "${RUNTIME}/run" \
  "${RUNTIME}/backups" \
  "${CONV}/evidence" \
  "${CONV}/secrets" \
  "${CONV}/env" \
  "${CONV}/reports"

chmod 700 "${RUNTIME}" "${CONV}" "${CONV}/secrets" "${CONV}/evidence" "${CONV}/env"

SECRET="${CONV}/secrets/azure-openai.secret"
if [[ ! -f "${SECRET}" ]]; then
  : > "${SECRET}"
fi
chmod 600 "${SECRET}"

REVIEWED_AT="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
VALID_UNTIL="$(date -u -d '+180 days' +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || date -u -v+180d +%Y-%m-%dT%H:%M:%SZ)"
cat > "${CONV}/evidence/spend-approval.soft-launch.json" <<EOF
{
  "schema_version": 1,
  "approval_id": "APP-0053",
  "approved": true,
  "cap_inr": 10000,
  "reviewed_at": "${REVIEWED_AT}",
  "valid_until": "${VALID_UNTIL}"
}
EOF
chmod 600 "${CONV}/evidence/spend-approval.soft-launch.json"

if [[ ! -f "${CONV}/evidence/vendor-evidence.json" ]]; then
  cat > "${CONV}/evidence/vendor-evidence.json" <<EOF
{
  "schema_version": 1,
  "provider": "azure-openai",
  "region": "centralindia",
  "approved_for_conversation_model": false,
  "data_logging_enabled": false,
  "model_improvement_enabled": false,
  "subscription_evidence_id": "REPLACE_WITH_CURRENT_SUBSCRIPTION_EVIDENCE_ID",
  "contracting_entity_review_id": "REPLACE_WITH_CURRENT_ENTITY_REVIEW_ID",
  "agreement_sha256": "REPLACE_WITH_64_LOWERCASE_HEX_SHA256",
  "dpa_sha256": "REPLACE_WITH_64_LOWERCASE_HEX_SHA256",
  "subprocessor_sha256": "REPLACE_WITH_64_LOWERCASE_HEX_SHA256",
  "privacy_legal_review_id": "REPLACE_WITH_CURRENT_PRIVACY_REVIEW_ID",
  "logging_disabled_evidence_id": "REPLACE_WITH_CURRENT_LOGGING_REVIEW_ID",
  "reviewed_at": "${REVIEWED_AT}",
  "valid_until": "${VALID_UNTIL}"
}
EOF
  chmod 600 "${CONV}/evidence/vendor-evidence.json"
fi

printf '%s\n' 'Authorize live Confidence Conversation Azure OpenAI up to ₹10000' \
  > "${CONV}/LIVE_AUTHORIZATION.txt"
chmod 600 "${CONV}/LIVE_AUTHORIZATION.txt"

CONVERSATION_ENV="${CONV}/env/soft-launch-conversation.env"
if [[ ! -f "${CONVERSATION_ENV}" ]]; then
  cat > "${CONVERSATION_ENV}" <<EOF
# Source alongside soft-launch.env for live Azure tutoring (APP-0053).
# Fill endpoint/deployment/secret before enabling live requests.

APP_ENV=soft-launch
CONVERSATION_PROVIDER_DRIVER=azure-openai-dev
CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED=false
CONVERSATION_AZURE_OPENAI_ENDPOINT=https://REPLACE.openai.azure.com
CONVERSATION_AZURE_OPENAI_ALLOWED_HOST=REPLACE.openai.azure.com
CONVERSATION_AZURE_OPENAI_DEPLOYMENT=REPLACE_DEPLOYMENT_NAME
CONVERSATION_AZURE_OPENAI_API_VERSION=2024-10-21
CONVERSATION_AZURE_OPENAI_CREDENTIAL_KIND=api-key
CONVERSATION_AZURE_OPENAI_SECRET_FILE=${SECRET}
CONVERSATION_AZURE_OPENAI_APPROVED_SPEND_CAP_INR=10000
CONVERSATION_AZURE_OPENAI_VENDOR_EVIDENCE_PATH=${CONV}/evidence/vendor-evidence.json
CONVERSATION_AZURE_OPENAI_SPEND_APPROVAL_PATH=${CONV}/evidence/spend-approval.soft-launch.json
CONVERSATION_AZURE_OPENAI_SUBSCRIPTION_EVIDENCE_ID=REPLACE_WITH_CURRENT_SUBSCRIPTION_EVIDENCE_ID
CONVERSATION_AZURE_OPENAI_CONTRACTING_ENTITY_REVIEW_ID=REPLACE_WITH_CURRENT_ENTITY_REVIEW_ID
CONVERSATION_AZURE_OPENAI_AGREEMENT_SHA256=REPLACE_WITH_64_LOWERCASE_HEX_SHA256
CONVERSATION_AZURE_OPENAI_DPA_SHA256=REPLACE_WITH_64_LOWERCASE_HEX_SHA256
CONVERSATION_AZURE_OPENAI_SUBPROCESSOR_SHA256=REPLACE_WITH_64_LOWERCASE_HEX_SHA256
CONVERSATION_AZURE_OPENAI_PRIVACY_LEGAL_REVIEW_ID=REPLACE_WITH_CURRENT_PRIVACY_REVIEW_ID
CONVERSATION_AZURE_OPENAI_LOGGING_DISABLED_EVIDENCE_ID=REPLACE_WITH_CURRENT_LOGGING_REVIEW_ID
CONVERSATION_AZURE_OPENAI_CREDENTIAL_VERSION_ID=REPLACE_WITH_CURRENT_CREDENTIAL_VERSION_ID
CONVERSATION_AZURE_OPENAI_SPEND_APPROVAL_ID=APP-0053
CONVERSATION_AZURE_OPENAI_REGION_ACCEPTANCE_ID=REPLACE_WITH_CURRENT_REGION_ACCEPTANCE_ID
EOF
fi
chmod 600 "${CONVERSATION_ENV}"

# Base soft-launch API env (OTP local/Mailpit for ≤7 day invitees).
API_ENV_EXAMPLE="${ROOT_DIR}/apps/api/.env"
SOFT_ENV="${RUNTIME}/env/soft-launch.env"
if [[ ! -f "${SOFT_ENV}" ]]; then
  if [[ -f "${API_ENV_EXAMPLE}" ]]; then
    cp "${API_ENV_EXAMPLE}" "${SOFT_ENV}"
  else
    : > "${SOFT_ENV}"
  fi
fi

# Ensure soft-launch markers exist / override.
python3 - <<'PY' "${SOFT_ENV}"
import sys
from pathlib import Path

soft = Path(sys.argv[1])
text = soft.read_text(encoding="utf-8") if soft.exists() else ""
overrides = {
    "APP_ENV": "soft-launch",
    "APP_DEBUG": "false",
    "CONVERSATION_PROVIDER_DRIVER": "azure-openai-dev",
    "CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED": "false",
}

lines = []
seen = set()
for raw in text.splitlines():
    if not raw.strip() or raw.strip().startswith("#") or "=" not in raw:
        lines.append(raw)
        continue
    key = raw.split("=", 1)[0]
    if key in overrides:
        lines.append(f"{key}={overrides[key]}")
        seen.add(key)
    else:
        lines.append(raw)
for key, value in overrides.items():
    if key not in seen:
        lines.append(f"{key}={value}")
soft.write_text("\n".join(lines) + "\n", encoding="utf-8")
soft.chmod(0o600)
print(f"Wrote {soft}")
PY

cat > "${RUNTIME}/README.txt" <<EOF
Global English — Ubuntu soft launch (APP-0053)
Repository: ${ROOT_DIR}

1. Fill Azure endpoint/deployment in ${CONV}/env/soft-launch-conversation.env
2. Paste API key into ${SECRET} (mode 0600)
3. Set CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED=true in soft-launch.env
   only after LIVE_AUTHORIZATION.txt contains the ₹10000 phrase
4. make soft-launch-start && make soft-launch-tunnel
5. Publish one pathway and invite learners (see docs/SOFT_LAUNCH_UBUNTU_RUNBOOK.md)

Kill switch: set CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED=false and
make soft-launch-restart
EOF

printf 'Soft-launch workspace ready at %s\n' "${RUNTIME}"
printf 'Conversation evidence at %s\n' "${CONV}"
