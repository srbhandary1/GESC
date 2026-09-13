# Governed media ingestion runbook

**Scope:** Private, non-production ADMIN-MEDIA ingestion only
**Authority:** DR-0019 / ADR-0016 / APP-0048
**Last reviewed:** 10 August 2026

If the reviewed local Direct administrator is identity-free, resolve and use
the one-shot [local operator identity bootstrap](LOCAL_OPERATOR_IDENTITY_BOOTSTRAP.md)
before this runbook. That exception creates only a normal sign-in identity;
all media commands still require ordinary OTP, selected membership and fresh
TOTP.

## Security boundary

- Media remains private and is admitted only through the project-owned upload,
  exact-hash finalization, inspection, malware scan and human acceptance path.
- Operator-supplied MIME types and extensions are not evidence.
- An unavailable inspector, unavailable scanner, malformed object, hash
  mismatch or non-clean scan fails closed.
- ClamD is exposed only on workstation loopback. Its unencrypted TCP protocol
  must never be exposed to another host or used as a production topology.
- Quarantine objects are not publication authority. Metadata, English and
  Kannada review, rights evidence and a later immutable publication remain
  separately required.

## Pinned local implementations

The local ClamAV profile uses official image
`clamav/clamav:1.5.4_base@sha256:a3c78b2f808ea797f842c6bca1c5f0a54a3d1182ab2efac1884510f667e44f56`.
Its signature database is persisted in the `clamav-data` Compose volume and
updated by FreshClam. The container is bounded to 4 GiB RAM, carries the image
health check and publishes only `127.0.0.1:${CLAMAV_PORT:-3310}`.

`scripts/provision-media-tools.sh` builds FFmpeg ffprobe 8.1.2 from the official
release tarball. It verifies the detached signature against the official
release-key fingerprint
`FCF986EA15E6E293A5644F10B4322F04D67658D8`, disables network protocols and
non-portable x86 assembly, and installs into ignored
`tmp/toolchain/ffmpeg-8.1.2`. It never commits a binary.

## Prepare and verify

From the repository root:

```text
make media-tools
make media-services-up
tmp/toolchain/ffmpeg-8.1.2/bin/ffprobe -version
docker compose --profile media ps clamav
```

The first ClamAV start downloads the full official signature database and can
take several minutes. Do not ingest while the service is unhealthy.

For an explicitly authorized operation, supply these process-local settings:

```text
MEDIA_INSPECTOR_DRIVER=ffprobe
MEDIA_FFPROBE_BINARY=/absolute/repository/path/tmp/toolchain/ffmpeg-8.1.2/bin/ffprobe
MEDIA_SCANNER_DRIVER=clamav
MEDIA_CLAMAV_HOST=127.0.0.1
MEDIA_CLAMAV_PORT=3310
```

Do not change the committed fail-closed defaults. Never write credentials,
source URLs, learner data, raw transcript text or source-media bytes to logs
or evidence documents.

## Ingestion evidence

For every candidate, record only non-sensitive control evidence:

1. authorization reference and authorized source SHA-256;
2. recomputed byte count and SHA-256 before upload;
3. upload/finalization identifier and exact accepted object hash;
4. inspector name, policy version, detected MIME and duration;
5. scanner name, policy version, clean outcome and scan time;
6. accepting organization membership, decision time and accepted asset ID;
7. proof that the candidate remains unattached and unavailable to learners.

The evidence packet must not reproduce signed URLs, session material,
credentials, audio bytes or transcript content.

## Failure and cleanup

- Stop on any mismatch with the authorized hash or size; do not upload a near
  match or replace the expected value.
- Reject malformed or infected candidates through the normal review path.
- Allow `media:reconcile` to process content-free deletion receipts; do not
  delete authoritative rows or accepted objects manually.
- Stop the profile with `docker compose --profile media stop clamav` when no
  ingestion is active. `docker compose down` preserves the named signature
  volume; `docker compose down --volumes` is destructive and is not part of
  this runbook.

## Primary operational references

- FFmpeg release verification: <https://ffmpeg.org/download.html#release-verification>
- ClamAV Docker guidance: <https://docs.clamav.net/manual/Installing/Docker.html>
- ClamAV release 1.5.4: <https://github.com/Cisco-Talos/clamav/releases/tag/clamav-1.5.4>
