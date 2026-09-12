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

## What this ships

- Superdesign homepage structure: sticky nav, hero + today’s-lesson card,
  three benefits, twelve levels, four how-it-works steps, dark pricing
  (Free ₹0 / Monthly ₹1,499 / Quarterly ₹3,999), about, demo band, footer.
- Boska + Switzer from Fontshare, brand greens, no Superdesign preview
  runtime (no petite-vue, postMessage bridge, or `sd-ready` opacity hack).
- Accessible viewport (no `maximum-scale` lock), skip link, semantic
  landmarks, 48px targets, visible focus.

## Point the live domain at this site

This repository does not control `globalspokenenglish.com`. After this build
is accepted:

1. Run `make marketing-build` and upload `apps/marketing/dist/` as the
   document root, **or** attach a static host (Cloudflare Pages, Netlify,
   object storage + CDN) to this folder.
2. If you replace only the homepage on the existing Plesk host, keep the
   current PHP `/login` (and other institute routes) in place. The nav
   **Log in** link is `/login` for that reason.
3. If you point the whole domain at a static host, `/login` will 404 until
   you add a reverse-proxy or redirect to the institute login or the
   separately hosted learner app.
4. Update DNS / Plesk document root only after a reviewed upload. This PR
   does not change DNS.

Paid checkout and the learner product demo are not wired. Primary CTAs
scroll to `#demo`.
