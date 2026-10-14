# Android APK downloads

Current release: **1.0.16 / code 17**.

Download: https://ai.globalspokenenglish.com/downloads/global-spoken-english-1.0.16.apk

Filename: `global-spoken-english-1.0.16.apk`; size **15,911,020 bytes**.
SHA-256: `cec0247ffe9d46d6b94b7c464edd7ac742cec8a72f5a510e823acf9e07bb8e36`.
Requires Android 7.0+ (API 24). Install as an update to preserve app data.

`apps/web/src/appRelease.json` supplies the package and UI version. After bumping
it, run `make android-closed-beta`; its staging script copies the successful APK
to the versioned filename and `global-spoken-english-android.apk` compatibility
alias. Build the public website afterward without VITE_MOBILE_API_ORIGIN set.
APK binaries are gitignored. Both homepage links show the version; Account
settings show the installed version. This release adds Google, Facebook and
Instagram self-registration/sign-in with payment-gated account creation and
remediates VERIFY-FIX-16 audit findings. Install as an update to keep app data.
