# A5 guidance TTS authorization request

**Status:** Accepted under [APP-0024](APPROVALS.md#app-0024).  
**Plan step:** [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) A5  
**Governing slice auth:** [APP-0021](APPROVALS.md#app-0021)  
**ADR:** [ADR-0011](adr/0011-confidence-conversation-guidance-tts-policy.md) (accepted)  
**Prior begin-implementation:** [APP-0023](APPROVALS.md#app-0023)

## Accepted statements

### 1) Accept ADR

```text
Accept ADR-0011 Confidence Conversation guidance TTS policy as proposed.
```

### 2) APO authorize (fakes / text-first)

```text
APO authorize only A5 guidance TTS policy with fakes and text-first fallback;
live multi-locale TTS spend remains unauthorized until a separate APP.
```

## Authorized scope (APP-0024)

1. Mark ADR-0011 accepted (amends ADR-0008 for Confidence Conversation product
   TTS policy only; PR-009 en-IN benchmark unchanged).
2. Confirm `GuidanceTtsPolicy` voice map + purpose codes (`model_en`,
   `guidance_l1`).
3. Product UI text-first; optional on-device English browser speech as
   non-evidence interim.
4. Fake TTS contract for allowlisted voices; no human lesson recordings.
5. Close plan Step A5; advance Active step to A6.

## Explicit non-goals

- Live multi-locale Azure TTS spend / ordinary DI expansion
- Promoting demo Vite `/demo/tts` (D-0092) into Laravel DI
- Changing ADR-0008 locked PR-009 benchmark allowlist
- Production promote / Play
- A6 locale eval hardening (next step)
- PR-012 Role-play
