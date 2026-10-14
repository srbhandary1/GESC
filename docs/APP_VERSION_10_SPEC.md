# APP-VERSION-10 — visible app and download versions

Owner request: show the version on website Android downloads, include it in the
APK filename, and expose the installed app version for comparison with releases.

Use one build-time manifest for Android versionName/versionCode, bundled app
version and website APK labels/filenames. Show the version in Account settings,
including Android, and on both website download links. Stage a version-named APK
and preserve the old filename only as a compatibility alias. Validate packaging,
labels, physical phone version and published artifact identity. No automatic
update installation, provider calls, new permissions, API or schema changes.
