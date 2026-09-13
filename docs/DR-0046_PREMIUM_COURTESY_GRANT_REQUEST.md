# DR-0046 — Premium flagship and courtesy month for existing Direct learners

**Status:** Implemented — Option A (ADR-0036 / APP-0073).
Live payment credentials, production, Google Play and old-website account
import remain unauthorized.
**Needed by:** Clearer Direct packaging; complimentary Premium for learners
already in this tenant
**Authority affected:** [SUBSCRIPTIONS.md](SUBSCRIPTIONS.md); ADR-0035;
ADMIN-ENT-01; master plan §§16.5, 31, 32; ADR-0014 legacy-import gate
**Opened:** 1 September 2026
**Resolved:** 1 September 2026 — Accountable Product Owner instruction in the
active Cursor conversation

## 1. Decision needed

After comparing SuperNova Premium, the previous `globalspokenenglish.com`
SKUs, and the Direct catalogue, the product owner asked to implement the best
Direct model and to put existing students on a 1-month Premium plan.

## 2. Recorded commercial model

Keep the GST-exclusive prices from DR-0045. Change packaging, not prices.

| Code | Display name | Price (ex-GST) | Access |
|---|---|---|---|
| `demo` | Two-day demo | ₹0 | Level 1–2; 48 hours from first sign-in; 300-second live-AI cap |
| `monthly_modules` | Modules | ₹350 / month | All published levels; no Role-play |
| `monthly_modules_roleplay` | **Premium** (flagship) | ₹500 / month | All published levels plus Role-play |

This is SuperNova-like in having one recommended paid SKU (Premium), with
Modules kept as a cheaper downsell. It is not SuperNova Premium: no Play
Billing, no unlimited-AI marketing SKU, no dynamic store prices. It is not
the old website’s ₹320 / ₹3000 / ₹9000 pack and does not sell 1:1.

## 3. Existing students

**In this product:** every active Direct learner membership receives one
complimentary calendar month of Premium (`operator_grant` with event
reason `courtesy_premium_month`). Idempotent. Institutional orgs are not
paywalled and are not granted.

**Old website (`www.globalspokenenglish.com`):** accounts, passwords, Prime
flags, progress and media are **not** imported. That site remains out of
scope (D-0160). Usernames here cannot be email addresses (ADR-0021). An
operator-held CSV of usernames that already exist in this tenant may limit
the courtesy grant; unmatched names are counted only.

## 4. Options

| Option | What ships | Status |
|---|---|---|
| **A — Premium flagship + courtesy month in this tenant** | Rename, recommended checkout, bulk grant, artisan/CSV filter | **Accepted** |
| B — Copy SuperNova Premium / Play IAP | Unauthorized | Not selected |
| C — Scrape or copy the old admin database | Production PII import | Not selected |

## 5. Product-owner statement

```text
Implement the best model you can think of. Migrate all existing students into a 1 month premium plan.
```
