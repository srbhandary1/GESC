# BACKUP-14 — dated VPS backup, 26 September 2026

Scope: back up the live application into this local repository on the computer named `Real-Studioz`. The user clarified that no GitHub upload was requested. Completed: archive, checksum, restore guide and `backup-2026-09-26.pass` are stored under `local-backups/2026-09-26/`. All four copied file hashes match their sources. Directory permissions are 0700 and files 0600; `.git/info/exclude` excludes `/local-backups/`, verified with `git check-ignore`.

## Backup delivered

`global-spoken-english-vps-2026-09-26.tar.gz.enc`, 763,621,824 bytes, on the VPS under `/home/globalenglish/ge-offsite-backups/`.

SHA-256: `73c941b85041c98481bc542c2f85b5f69df86f926ecde43cc8ccf07ddb69ecad`.

Includes deployed 1.0.12/code 13 release, PostgreSQL custom dump, 67 accepted S3 media objects, runtime configuration, Passport keys, systemd user units, internal restore instructions and file manifest. Online database snapshot is transaction consistent; components are not one atomic machine snapshot. Dependencies excluded from the archive must be restored from lockfiles. This is not a whole-VPS OS image.

AES-256-CBC / PBKDF2 / 200,000 iterations follows the existing backup tooling. The decryption key remains outside the encrypted archive. At the user’s explicit request a dated key copy is alongside the archive in the local Git-excluded backup directory, with another recovery copy outside the workspace. Neither backup nor key is committed or uploaded.

## Verification and privacy

- `pg_dump -Fc --no-owner --no-acl` succeeded.
- `pg_restore --no-owner --no-privileges --exit-on-error` into an isolated temporary database succeeded; checked 175 public tables and dropped temporary database.
- Exported all 67 accepted media objects with size/SHA-256 manifest.
- Decrypted archive and verified SHA-256 of all 16,164 regular payload files.
- Checked exclusion of conversation-recovery payloads and backup key. Temporary voice/speech, quarantine, transcription candidates, runtime caches/logs, old artifacts and node_modules excluded. Device-local history is not on VPS.
- Initial container-local media export exhausted temporary storage; removed owned staging and successfully streamed media into VPS staging instead. Initial table-count query quoting failed; corrected and reran the complete restore/check successfully.
- Removed plaintext VPS staging after encrypted archive verification. Services remained running.

Off-VPS ciphertext copy completed outside the repository workspace; its SHA-256 matches the VPS copy. Recovery key is stored in a separate mode-0700 directory. Documentation `git diff --check` passed.

Files changed: this report, STATUS.md, DECISIONS.md and QUALITY_REVIEW.md. No application, schema, migration, API or configuration change. Feature tests/builds are not applicable to this operational backup. No tenant permissions or authoritative records changed; the encrypted database contains existing tenant records and must be restored only in an authorized protected environment.

Requested local backup delivery is complete. Recommended next checkpoint: full infrastructure disaster-recovery rehearsal, separately scoped.
