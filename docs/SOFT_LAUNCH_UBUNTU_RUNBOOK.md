# Ubuntu soft-launch runbook (invite-only)

**Authority:** [APP-0053](APPROVALS.md#app-0053) / D-0155 / D-0156 / D-0158
**Host:** Ubuntu `Real-Studioz` / static IP `117.254.108.143`
**Product:** Full repository-implemented learner + operator app (not `make demo`)
**Non-authorization:** Google Play public release; `APP_ENV=production` live
drivers; unlimited Azure spend

## What is live

| Surface | Soft-launch posture |
|---|---|
| Web/PWA Learn / Practice / Progress / offline / video | Real API + DB |
| Confidence Conversation + Role-play coaching | Live Azure when evidence/secret enabled |
| Operator People / Learners / Content / Media / MFA | Real |
| OTP | Mailpit/operator-relayed for ≤7 days of owner-known invitees; then SMS required |
| Public entry | Cloudflare HTTPS tunnel primary; router-forwarded static-IP HTTP `:8080` secondary |

## Deferred

- Play public listing
- LPL Skill Chat / auto quiz-from-audio
- Payments / LMS SSO / notices moderation
- Demo Home attendance/notices cards as product truth
- LPL-01 transcription campaign as critical path (paused)

## One-time setup

```bash
# From repository root
bash scripts/install-soft-launch-caddy.sh
make soft-launch-setup
```

Fill Azure values in `~/ge-conversation-model/env/soft-launch-conversation.env`
and paste the API key into `~/ge-conversation-model/secrets/azure-openai.secret`
(mode `0600`). Keep
`CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED=false` until ready.

`make soft-launch-setup` is idempotent for the conversation env: rerunning it
does not replace operator-filled endpoint, deployment or evidence values. At
runtime, provider settings come from `soft-launch-conversation.env`; the main
`~/ge-soft-launch/env/soft-launch.env` remains authoritative only for
`APP_ENV`, `CONVERSATION_PROVIDER_DRIVER` and the live-request kill switch.
This precedence also ignores stale provider-setting copies left by the first
soft-launch setup implementation.

Exact LIVE_AUTHORIZATION line (already written by setup):

```text
Authorize live Confidence Conversation Azure OpenAI up to ₹10000
```

## Start / stop

```bash
make soft-launch-start      # compose services + API + worker + scheduler + Caddy
make soft-launch-tunnel     # HTTPS URL for phones / mic
make soft-launch-status
make soft-launch-health
make soft-launch-ready      # readiness checklist
make soft-launch-backup
make soft-launch-tunnel-stop
make soft-launch-stop
```

For automatic restart after login and, when user lingering is permitted, after
an Ubuntu reboot, install the repository-owned user service:

```bash
make soft-launch-systemd-install
systemctl --user status global-english-soft-launch.service
loginctl show-user "$USER" -p Linger
```

The installer replaces the earlier transient service, enables the persistent
unit, starts the full stack and attempts to enable user lingering. If Ubuntu
policy rejects that last operation, an administrator must run the exact
`sudo loginctl enable-linger <user>` command printed by the installer. Secrets
stay in the existing mode-`0600` outside-repository environment files and are
not copied into the unit.

The runner starts PHP's repository-pinned Laravel development-server router
directly and records that exact process ID. Do not start a separate
`artisan serve` on port `8000`: its parent/child process can outlive a stale PID
record, retain an obsolete tunnel origin and prevent managed restart. A healthy
`make soft-launch-status` must report `api`, worker, scheduler, both Caddy
listeners and tunnel as running.

Share only the printed `https://*.trycloudflare.com` URL with invitees.
`make soft-launch-restart` recreates the Cloudflare tunnel when it was active;
the hostname normally changes, so share only the newly printed URL. An explicit
stop removes the stale tunnel URL record.

Optional text-only static IP after dual-NAT forward:

```text
http://117.254.108.143:8080
```

Port `8080` is the configured Ubuntu listener and router-forward target. When
an HTTPS soft-launch origin is configured, opening the static-IP URL redirects
the same path and query to that current HTTPS origin. This makes `:8080` a safe
entry point without sending usernames, passwords or session cookies over plain
HTTP. Direct HTTP serving remains only a pre-tunnel diagnostic fallback and
must not be used to sign in. A later stable HTTPS hostname may point to this
Ubuntu service without changing the existing
`https://www.globalspokenenglish.com` site.

## Enable live Azure tutoring (kill-switched)

1. Replace every setup placeholder in the vendor evidence and conversation env
   with current reviewed evidence, endpoint and deployment values. The generated
   vendor scaffold is deliberately unapproved and cannot authorize a request.
2. Confirm the APP-0053 spend evidence and current vendor evidence under
   `~/ge-conversation-model/evidence/`.
3. Confirm a non-empty secret at mode `0600`.
4. Set `CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED=true` in
   `~/ge-soft-launch/env/soft-launch.env`.
5. Run `make soft-launch-ready`; any Azure warning becomes a failure while the
   live switch is enabled.
6. Run `sh scripts/run-conversation-model-live-prepare.sh` (no provider call).
7. `make soft-launch-restart`, then run one explicitly authorized learner smoke.

**Kill switch:** set live requests to `false` and restart. Soft-launch must not
silently fall back to fake AI.

## Close the remaining external blockers

### 1. Rotate the disclosed Azure Speech keys

If either resource key appears in chat, email, a ticket or a log, treat both as
compromised. In Azure Portal open the Speech resource → **Keys and Endpoint**,
regenerate Key 2, install that new key locally, then regenerate Key 1. Never
paste the replacement key into the repository, documentation or task chat.

Install the selected replacement without putting it in shell history:

```bash
umask 077
read -rsp 'New Azure Speech key: ' GE_ROTATED_SPEECH_KEY; printf '\n'
printf '%s' "$GE_ROTATED_SPEECH_KEY" > "$HOME/ge-speech-benchmark/secrets/azure-speech.secret"
unset GE_ROTATED_SPEECH_KEY
chmod 600 "$HOME/ge-speech-benchmark/secrets/azure-speech.secret"
```

Keep `AZURE_SPEECH_LIVE_REQUESTS_ENABLED=false` during rotation and evidence
work.

### 2. Complete Azure Speech benchmark evidence

Set non-secret resource values in
`~/ge-speech-benchmark/env/speech-benchmark.env`:

```text
APP_ENV=speech-benchmark
SPEECH_BENCHMARK_ENABLED=true
AZURE_SPEECH_LIVE_REQUESTS_ENABLED=false
AZURE_SPEECH_ENDPOINT=https://ge-speech-centralindia.cognitiveservices.azure.com/
AZURE_SPEECH_ALLOWED_HOST=ge-speech-centralindia.cognitiveservices.azure.com
AZURE_SPEECH_CREDENTIAL_KIND=subscription-key
AZURE_SPEECH_SECRET_FILE=/home/srbhandary/ge-speech-benchmark/secrets/azure-speech.secret
VOICE_PROVIDER_DRIVER=disabled
```

Replace every remaining evidence, price/FX, runner revision, safe-report and
ephemeral-storage placeholder with current reviewed evidence. Record the exact
credential version only after rotation. Then run the no-call gate:

```bash
sh scripts/run-speech-benchmark-preflight.sh
```

This command loads the protected benchmark environment when present and makes
no Azure request. `status: ready` means the local synthetic-benchmark gates are
complete; `status: blocked` lists the remaining evidence/configuration gates.
Neither result enables learner recording processing in the ordinary app.

Do not enable live requests until preflight is clean, the exact
`Authorize live synthetic Azure benchmark up to ₹1000` authorization exists,
and the evidence reviewers have signed off. Run `live-prepare`, prepare one
bounded run, complete its independent listener slots and purge ephemeral bytes
according to the PR-009 handoff. A passed synthetic benchmark does not itself
authorize ordinary learner audio.

### 3. Obtain ordinary learner Speech activation

ADR-0007/0008 keep ordinary Azure learner STT disabled in production and Play.
APP-0068 / D-0212 authorizes invite-only Direct soft-launch only: set
`VOICE_UPLOAD_ENABLED=true`, `VOICE_PROVIDER_DRIVER=azure`, and
`VOICE_ALLOWED_ORGANIZATION_IDS` to the Direct organization in the outside-repo
soft-launch env (not Git). Typed input remains available after Type instead.
Production/Play, partner tenants, independent privacy-firm review, and
DR-0028 Phase B supervised smoke stay unauthorized.

### 4. Configure the separate Azure OpenAI model resource

The Speech resource/key cannot generate Friend replies. Create or identify an
Azure OpenAI/Foundry resource, deploy an allowlisted chat model and collect its
base endpoint, deployment name and a newly generated credential. Put its key
only in `~/ge-conversation-model/secrets/azure-openai.secret` at mode `0600`.
Complete the existing vendor/spend evidence JSON for APP-0053's capped
₹10,000 soft launch, fill `soft-launch-conversation.env`, and keep the main
kill switch false until `make soft-launch-ready` and
`run-conversation-model-live-prepare.sh` both pass. Run one explicitly
authorized smoke, inspect content-free cost evidence, then retain the kill
switch for incidents.

Current host state (10 August 2026):

- resource group: `ge-ai-softlaunch` (`southindia`);
- Azure OpenAI resource: `ge-conversation-southindia` (`S0`);
- regional deployment: `gpt-4-1-mini-southindia`, model `gpt-4.1-mini`
  version `2025-04-14`, `Standard` capacity 3;
- automatic model upgrade: `NoAutoUpgrade`; inference retirement must be
  planned before 14 April 2027;
- endpoint/deployment/key placeholders: closed; key is outside Git at mode
  `0600`;
- data-plane network: default deny, with only the verified Ubuntu static IPv4
  `117.254.108.143/32` allowlisted;
- readiness: 17 pass / one disabled-live warning / zero failures; and
- remaining live preflight error: `vendor_evidence_logging_enabled`.

The resource does not expose the `ContentLogging=false` capability. Azure's
default abuse monitoring can select flagged prompts/completions for human
review, so the vendor evidence truthfully records logging enabled and the live
switch stays false. Submit Microsoft's
[modified-abuse-monitoring form](https://customervoice.microsoft.com/Pages/ResponsePage.aspx?id=v4j5cvGGr0GRqy180BHbR7en2Ais5pxKtso_Pz4b1_xUOE9MUTFMUlpBNk5IQlZWWkcyUEpWWEhGOCQlQCN0PWcu).
After Microsoft confirms approval, verify before changing evidence:

```bash
az cognitiveservices account show \
  --resource-group ge-ai-softlaunch \
  --name ge-conversation-southindia \
  --query "properties.capabilities[?name=='ContentLogging']" -o json
```

The only acceptable result for activation is a capability whose name is
`ContentLogging` and value is the string `false`. Then update the outside-repo
vendor evidence, run `make soft-launch-ready` and the no-call live prepare,
take a backup, enable the kill switch and execute only the bounded smoke. Never
set the evidence to false merely because the application form was submitted.

If Azure CLI returns `AADSTS530035` or device login says the account has no
access, close that tenant blocker before provisioning:

1. Sign in to Microsoft **Security info** for the Azure account and register an
   allowed MFA method (normally Microsoft Authenticator). Security defaults
   require MFA registration and can block ARM/Graph tokens until it is done.
2. In Azure Portal, open **Directories + subscriptions**, select the directory
   that contains **Azure subscription 1**, then open the subscription's
   **Access control (IAM) → View my access**. If the directory/subscription is
   absent, an existing tenant administrator must invite or re-enable the exact
   account; an old cached CLI subscription is not current authority.
3. To provision through the resource group, an existing Owner, Role Based
   Access Control Administrator or User Access Administrator assigns the
   operator **Contributor** on the chosen resource group. Assign **Cognitive
   Services OpenAI Contributor** on the created Azure OpenAI resource for model
   deployment/management. Do not give the learner application an Azure control
   plane role.
4. Refresh the CLI only after MFA/membership/RBAC are corrected:

   ```bash
   az logout
   az account clear
   az login --tenant '<tenant-id>' --use-device-code
   az account set --subscription 'Azure subscription 1'
   az cognitiveservices account list \
     --query '[].{name:name,group:resourceGroup,kind:kind,region:location}' -o table
   ```

`az account clear` removes only the local Azure CLI subscription cache; the
following login rebuilds it. Never paste an Azure key into any of these
management-plane commands.

### 5. Configure the stable static-IP HTTPS origin

The approved product hostname is `ai.globalspokenenglish.com`. In Cloudflare
DNS create one **DNS-only** A record named `ai` pointing to the current static
public IPv4 address. Do not change the apex or `www` records. Reserve
`192.168.1.3` for this host and configure these TCP router mappings:

| WAN | LAN target |
|---|---|
| `80` | `192.168.1.3:8080` |
| `443` | `192.168.1.3:8443` |

Caddy's `http_port`/`https_port` settings deliberately use unprivileged local
ports; clients still use ordinary public ports 80/443. If UFW is active, allow
only the exact TCP ports 8080 and 8443. After DNS and router forwarding exist:

```bash
make soft-launch-configure-stable HOST=ai.globalspokenenglish.com
make soft-launch-restart
make soft-launch-status
```

The stable edge obtains a public certificate, redirects HTTP to the exact
HTTPS origin, adds HSTS/nosniff/frame/referrer headers and proxies only to the
loopback application edge. Configuration fails closed unless `APP_URL` exactly
matches the stable host. A configured stable host disables Quick-Tunnel start
and the systemd supervisor no longer requires a tunnel process.

Verify from mobile data or an external host because the LAN may not support NAT
loopback:

```bash
curl -I http://ai.globalspokenenglish.com/
curl --fail https://ai.globalspokenenglish.com/api/v1/health/ready
curl --fail https://ai.globalspokenenglish.com/api/v1/auth/password/mode
```

Expected results are an HTTP redirect to the same stable HTTPS hostname,
`ready`, and exactly `password_only`. Do not rebuild or distribute Android
until those checks pass. Then build with
`VITE_MOBILE_API_ORIGIN=https://ai.globalspokenenglish.com`; the old
Quick-Tunnel APK is obsolete.

SSH remains appropriate only for administration and deliberately loopback-only
recovery surfaces. It is not the learner/Web/Android transport. Plain public
HTTP and raw-IP sign-in remain prohibited.

## Content already on this host

See [SOFT_LAUNCH_CONTENT_STATUS.md](SOFT_LAUNCH_CONTENT_STATUS.md). The current
soft-launch database already has active publication heads, media assets and
enrollments. Expand the pilot with invitations and, when needed, a successor
publication of your owned media.

## Publish or replace a pathway (operator)

Use the operator console on the soft-launch HTTPS URL:

1. Sign in as Direct staff (OTP). Enroll TOTP under Account Security.
2. **Media Library:** upload/accept your owned model audio / lesson video /
   lesson audio; record program-scoped rights using APP-0044 umbrella evidence
   plus exact hash/program confirmation.
3. **Content Studio:** create/open a graph-v2 workspace, author one daily-speaking
   mission (or attach existing ready draft), complete structure/English/Kannada
   reviews as appointed, resolve readiness, **Publish now**.
4. **Learners:** issue invitation codes; create/activate enrollment pinned to
   that exact publication.
5. Smoke as learner: Learn → Check → Result; Practice Confidence Conversation;
   Role-play; Progress; offline sync if used; video if published.

Do not use `make demo` or `dist-demo` as the soft-launch product.

## Invite cadence

- Start with 5–10 consented adult learners.
- Transfer invitation codes through a secure channel once.
- Never share OTP/TOTP/recovery codes in tickets or chat logs.

## Backup / incident

```bash
make soft-launch-backup
```

Backups land in `~/ge-soft-launch/backups/`. For incidents use
[ops/INCIDENT_SUPPORT_RUNBOOK.md](ops/INCIDENT_SUPPORT_RUNBOOK.md) without
pasting raw audio, transcripts or credentials.

## Claims guardrail

Say: invite-only soft launch of the repository-implemented product with capped
live Azure tutoring on a private Ubuntu host.

Do not say: production-ready, Play-available, unlimited AI, guaranteed fluency,
or that independent legal/security reviews are complete.
