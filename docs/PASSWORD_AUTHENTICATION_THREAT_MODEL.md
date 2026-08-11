# Password-only authentication threat model

**Checkpoint:** AUTH-PASSWORD-01
**Decision:** ADR-0021 accepts password-only authentication, including its
documented privileged-access risk.
**Production status:** Not authorized

| Threat | Control in this checkpoint | Residual risk / gate |
|---|---|---|
| Credential stuffing and guessing | Global username canonicalization, account/IP/global throttles, progressive delay, generic failures, 15-character minimum and versioned local blocklist | Distributed low-rate attacks remain possible; production rate-limit telemetry and blocklist evidence require review |
| Account enumeration | Same public sign-in/recovery shape for unknown, inactive, unready and invalid accounts; dummy-hash verification and content-free evidence | Network and provider timing cannot be claimed perfectly identical; production timing measurements remain required |
| Password database disclosure | Individually salted Argon2id hashes with minimum cost and rehash; recovery tokens use a separate keyed digest | Offline guessing remains possible; secret/key custody and host benchmarks are production gates |
| Phishing or stolen privileged password | Five-minute session-bound re-entry, short privileged sessions, global revocation, command-time permission/appointment checks | Re-entry is the same factor and does not stop a phisher; this risk was explicitly accepted in APP-0055 and must remain visible |
| Session/token theft | Secure HttpOnly web cookies, CSRF, selected-membership binding, PKCE, native secure refresh storage, rotation/reuse revocation | Compromised unlocked devices remain a risk; physical-device negative-storage evidence is open |
| Recovery takeover | Verified-contact ordinary recovery; privileged accounts require two distinct platform operators, content-minimized evidence and a 24-hour delay. The APP-0057 local excluded-account path additionally requires an exact append-only cutover exclusion, its manifest owner, an existing verified contact, fresh password confirmation by both actors, and a 15-minute single-use establishment token | Contact compromise, local-host compromise and support collusion remain possible; the protected-file adapter is local-only and production identity proofing/delivery plus independent review are open |
| Tenant privilege escalation | Credentials identify a global user only; organization context, membership, permissions and appointments are revalidated independently | Misconfigured appointments remain an authorization risk covered by existing tenancy reviews/tests |
| Secret leakage through clients or observability | Passwords/tokens are request-memory only; no browser/ordinary Capacitor storage; safe audit/outbox categories; no credential export | External proxies and production monitoring configuration need independent inspection |
| Mixed legacy authentication after cutover | One atomic marker overrides stale config, globally revokes credentials, clears TOTP material and retires OTP/TOTP endpoints | Post-cutover rollback cannot restore cleared factors; recovery requires a new reviewed decision |
| Excluded-account substitution after cutover | Reconciliation accepts only the exact mode-`0600` manifest digest and current marker inventory, records owner/target evidence append-only, and permits establishment only for the listed active credential-unready target | A compromised local host can attack the commissioning surface; loopback restriction, disabled-by-default configuration, short token lifetime and immediate artifact deletion reduce but do not remove that local risk |

This consolidated implementation review is not an independent penetration test,
privacy assessment or production authorization.
