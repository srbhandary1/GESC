# Android APK downloads

The web app links to `global-spoken-english-android.apk` in this folder.

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
