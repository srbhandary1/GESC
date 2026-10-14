# CAPACITY-USAGE-04 — 1,000 simultaneous speakers

Authority: owner's 25 September 2026 instruction to fix concurrent capacity,
student usage visibility and economical provider selection. Target explicitly
confirmed as 1,000 simultaneous speakers. Owner selected actual speaking/listening
minutes, with provider tokens tracked separately; fixed per-turn time is superseded.

Work in bounded vertical slices: (A) correct metering and student/operator usage
visibility; (B) shared admission control, independent-session locking and runtime
concurrency; (C) measured provider economics and capacity verification. Preserve
current consent, short-lived encrypted recovery, organization authorization and
model-validation boundaries. Never load-test paid providers with 1,000 requests
or claim that fake/provider-free load proves purchased vendor throughput.

Initial verified findings:
- Live model: Azure gpt-4.1-mini deployment, last measured 3 RPM / 3,000 TPM.
- Live STT: Indic/Sarvam Saaras v3 with Azure fallback; TTS: Azure. ElevenLabs
  disabled. Sarvam Bulbul adapter is currently a rejecting placeholder.
- VPS: 4 CPUs, 8 GiB RAM; single-process PHP development HTTP server, one queue
  worker. PostgreSQL/Redis already available.
- Turn service locks joined shared persona/scenario rows, serializing unrelated
  students using the same content; provider calls occur inside transactions.
- Audio upload deducts validated recording duration; turns and opener speech
  deduct fixed seconds. Tokens are independently scoped in ai_cost_ledger.
- Learn tutor does not propagate the balance-refresh callback.

Implementation plan: inspect existing contracts/tests; replace arbitrary turn
charges with bounded playback/recording measurement under a recorded billing ADR;
expose scoped provider-unit summaries without text; fix refresh and shared locks;
add shared provider admission/retry reporting before billable work; provide a
bounded production HTTP worker path and real concurrency checks; verify current
provider prices/limits, preserve cheap working routes and state unavailable
account capacity plainly. Add API/client, tenancy, replay, concurrent-spend and
failure coverage. Run verify, E2E, AI evaluation and builds as applicable.

External constraints: quota allocation needs authorized vendor management access;
provider enterprise throughput and larger hosting cannot be fabricated in code.
Current paid-plan amounts/caps and historic charges are not silently rewritten.


Delivered status: measured metering/reporting, shared admission, scoped row locks
and the bounded FPM runtime are deployed and verified. Provider economics and
account limits are documented in CAPACITY_USAGE_04_OPERATIONS.md. The 1,000-speaker
acceptance target remains open: authorized vendor capacity, isolation of provider
waits from database transactions, complete fallback expense reconciliation and
sustained authenticated voice load still require completion. This issue must not
be described as a passed scale certification.
