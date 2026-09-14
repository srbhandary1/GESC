# Android APK downloads

The web app links to `global-spoken-english-android.apk` in this folder
(`?v=jezweb-c4` on the homepage).

Staff LAN (use this URL — do **not** use the private GitHub release link
without signing in; that saves a 9-byte `Not Found` file and Android reports
**Parse error / There was a problem parsing the package**):

`http://117.254.108.143:8080/downloads/global-spoken-english-android.apk?v=jezweb-c4`

Before installing, confirm the downloaded file is about **53–56 MB**. A file
that is only a few bytes or kilobytes is HTML, not an APK. Requires **Android
7.0+** (API 24). Uninstall any older Global Spoken English App build first.


For soft launch, copy the latest closed-beta artifact here before building
`apps/web/dist`, for example:

```bash
cp ~/ge-soft-launch/artifacts/closed-group/global-spoken-english-android-d0302-20260908.apk \
  apps/web/public/downloads/global-spoken-english-android.apk
make soft-launch-refresh-web
```

`make soft-launch-refresh-web` stages this file into `dist/downloads/` for
serving. Do not rebuild the website with `VITE_MOBILE_API_ORIGIN` set in the
shell; that used to strip the APK from `dist` before the `GE_ANDROID_WEB_BUNDLE`
guard existed.

APK binaries are gitignored; only this README is versioned.
