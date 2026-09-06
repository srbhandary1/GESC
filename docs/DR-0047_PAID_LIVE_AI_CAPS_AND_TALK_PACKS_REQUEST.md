# DR-0047 — Paid live-AI minute caps and talk-time packs

**Status:** Implemented — accepted in the active Cursor conversation
(ADR-0037 / APP-0074).
Live payment credentials, production and Google Play remain unauthorized.
**Needed by:** Hosting plus Azure/Sarvam cost cannot support unlimited Speak
on ₹350 / ₹500
**Authority affected:** [SUBSCRIPTIONS.md](SUBSCRIPTIONS.md); ADR-0035;
ADMIN-ENT-01; master plan §§16.5, 31, 32
**Opened:** 1 September 2026
**Resolved:** 1 September 2026 — Accountable Product Owner instruction to add
paid caps and per-hour talk packs into the app

## 1. Decision needed

Direct paid plans had no live-AI cap. Unlimited STT/chat/TTS can cost more
than the monthly fee. The product owner asked for prepaid extra talk time
sold in amounts per hour, then asked to put that catalogue in the app.

## 2. Recorded commercial model

Keep GST-exclusive ₹350 / ₹500. Add monthly live-AI pools and three packs.

| Code | Display name | Price (ex-GST) | Live AI | Role-play |
|---|---|---|---|---|
| `demo` | Two-day demo | ₹0 | 5 minutes | Level 1–2 only |
| `monthly_modules` | Modules | ₹350 / month | **40 minutes / month** | No |
| `monthly_modules_roleplay` | Premium | ₹500 / month | **90 minutes / month** | Yes |
| `talk_pack_30` | Talk 30 | ₹199 | +30 minutes | Does not unlock |
| `talk_pack_60` | Talk 60 | ₹349 | +60 minutes | Does not unlock |
| `talk_pack_180` | Talk 180 | ₹899 | +180 minutes | Does not unlock |

Talk packs require an **active Modules or Premium** month. They expire at the
sooner of membership end and 30 days. Demo and expired learners must
subscribe first. Lesson MP3/video does not consume the pool.

## 3. Options

| Option | What ships | Status |
|---|---|---|
| **A — Caps + prepaid packs** | 40 / 90 minute pools; three talk SKUs; dummy checkout | **Accepted** |
| B — Unlimited paid AI | Rejected; margin risk | Not selected |
| C — Per-minute overage | Rejected; GST/Play complexity | Not selected |

## 4. Product-owner statement

```text
How about an option to buy more AI talk time? Some anount per hour?
add these suggestions into the app.
```
