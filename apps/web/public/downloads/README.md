# Android APK downloads

The learner landing and Home download use `/downloads/global-english-demo.apk`.
`make demo-android` writes that file to `apps/web/dist-demo/downloads/` and
the Vite demo server serves the Gradle debug APK at the same URL.

For soft launch, copy the latest closed-beta artifact here before building
`apps/web/dist`, for example:

```bash
cp ~/ge-soft-launch/artifacts/closed-group/global-spoken-english-in-app-login-20260901.apk \
  apps/web/public/downloads/global-english-demo.apk
```

APK binaries are gitignored; only this README is versioned.
