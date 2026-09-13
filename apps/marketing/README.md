# Public marketing site

Static homepage for [globalspokenenglish.com](https://globalspokenenglish.com/).
It is **not** the learner web app in `apps/web` and must not be bundled into
that product.

The live domain currently serves a separate Plesk / nginx PHP site
(`home_new_assets`, Bootstrap). This package is the replacement homepage. DNS
and hosting have **not** been pointed at this build.

## Local preview

From the repository root (requires the workspace pnpm install):

```text
pnpm --filter @global-english/marketing dev
```

Open `http://127.0.0.1:5174`.

Or via Make:

```text
make marketing
```

Production-like preview:

```text
make marketing-build
pnpm --filter @global-english/marketing preview
```

The preview server is `http://127.0.0.1:4174`.

Plesk upload artifact:

```text
make marketing-dist
```

That rebuilds `apps/marketing/dist/` and writes gitignored
`apps/marketing/dist.tar.gz`. Operator steps, hard guards, and rollback are
in [CUTOVER.md](CUTOVER.md).

## What this ships

- Superdesign homepage structure: sticky nav, hero + today’s-lesson card,
  three benefits, 17 institute levels (12-theme public overview), four
  how-it-works steps, dark pricing
  (Free ₹0 / Monthly ₹1,499 / Quarterly ₹3,999), about, demo band, footer.
- Boska + Switzer from Fontshare, brand green `#006B3F`, no Superdesign
  preview runtime (no petite-vue, postMessage bridge, or `sd-ready` opacity
  hack).
- Accessible viewport (single tag, no `maximum-scale` lock), skip link,
  semantic landmarks, 48px targets, visible focus.
- Primary CTA **Try free demo**; **Log in** remains `/login`.

## Point the live domain at this site

This repository does not control `globalspokenenglish.com`. Follow
[CUTOVER.md](CUTOVER.md). Short form:

1. Merge PR #5 if it is still open (do not force-merge without a request).
2. Run `make marketing-dist` and upload `apps/marketing/dist/` to a **staging**
   Plesk document root first.
3. On live, serve that static `dist/` at `/` only. Keep PHP `/login`,
   `/register`, `/about-us`, `/blog`, `/student-list`, and
   `/terms-conditions-refund`.
4. Do not upload into `apps/web`, Capacitor, or any learner/audio path.

Paid checkout and the learner product demo are not wired. Primary CTAs
scroll to `#demo`.
