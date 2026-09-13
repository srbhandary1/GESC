# Password-only authentication threat model

**Checkpoint:** AUTH-SIMPLE-01
**Decision:** ADR-0022 accepts one role-neutral login/recovery model, including
its documented privileged-access risk.
**Production status:** Not authorized

| Threat | Control in this checkpoint | Residual risk / gate |
|---|---|---|
| Credential stuffing and guessing | Global username canonicalization, account/IP/global throttles, progressive delay, generic failures, 15-character minimum and versioned local blocklist | Distributed low-rate attacks remain possible; production rate-limit telemetry and blocklist evidence require review |
| Account enumeration | Same public sign-in/recovery shape for unknown, inactive, unready and invalid accounts; dummy-hash verification and content-free evidence | Network and provider timing cannot be claimed perfectly identical; production timing measurements remain required |
| Password database disclosure | Individually salted Argon2id hashes with minimum cost and rehash; recovery tokens use a separate keyed digest | Offline guessing remains possible; secret/key custody and host benchmarks are production gates |
| Phishing or stolen privileged password | Five-minute session-bound re-entry, short privileged sessions, global revocation, command-time permission/appointment checks | Re-entry is the same factor and does not stop a phisher; this risk was explicitly accepted in APP-0055 and must remain visible |
| Session/token theft | Secure HttpOnly web cookies, CSRF, selected-membership binding, PKCE, native secure refresh storage, rotation/reuse revocation | Compromised unlocked devices remain a risk; physical-device negative-storage evidence is open |
| Recovery takeover | Every role uses an already verified contact, one 256-bit 15-minute single-use token, five-attempt expiry, supersession and global revocation. Credential-unready setup additionally requires an exact append-only cutover exclusion and one freshly confirmed exact platform manager; the actor never sees the token or target credential | A compromised verified contact can take over even a privileged account; this increased risk is expressly accepted by APP-0059. Production identity proofing/delivery and independent review remain open |
| Lost-contact support abuse | Only an active allowlisted user with `platform.account_recovery.manage` and a five-minute same-password confirmation can replace a contact; self-targeting, duplicate contact and malformed evidence fail; old/new identities, global revocation, former-contact notification outcome and content-free evidence are atomic/reconciled | The platform manager can falsely attest external verification or may itself be compromised. Organization roles never grant the capability, but no second actor or delay remains |
| Tenant privilege escalation | Credentials identify a global user only; organization context, membership, permissions and appointments are revalidated independently | Misconfigured appointments remain an authorization risk covered by existing tenancy reviews/tests |
| Secret leakage through clients or observability | Passwords/tokens are request-memory only; no browser/ordinary Capacitor storage; safe audit/outbox categories; no credential export | External proxies and production monitoring configuration need independent inspection |
| Mixed legacy authentication after cutover | One atomic marker overrides stale config, globally revokes credentials, clears TOTP material and retires OTP/TOTP endpoints | Post-cutover rollback cannot restore cleared factors; recovery requires a new reviewed decision |
| Excluded-account substitution after cutover | Reconciliation accepts only the exact mode-`0600` manifest digest and current marker inventory, records owner/target evidence append-only, and permits establishment only for the listed active credential-unready target | A compromised local host can attack the commissioning surface; loopback restriction, disabled-by-default configuration, short token lifetime and immediate artifact deletion reduce but do not remove that local risk |

This consolidated implementation review is not an independent penetration test,
privacy assessment or production authorization.
