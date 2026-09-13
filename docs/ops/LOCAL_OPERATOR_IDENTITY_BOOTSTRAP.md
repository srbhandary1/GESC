# Local operator identity bootstrap

**Scope:** APP-0049 / D-0146 / ADR-0017 local non-production exception only
**Production use:** Prohibited

This runbook binds one verified phone identity to the exact existing
identity-free Global English Direct administrator. It grants no authority and
does not replace ordinary OTP, membership selection or privileged TOTP.

## Preconditions

1. Run only from `apps/api` with `APP_ENV=local` and
   `AUTH_OTP_DRIVER=local`.
2. Resolve and independently inspect the expected existing user and membership
   ULIDs. The membership must already be active, belong to the immutable
   Direct organization, have type `organization_admin` and select the active
   seeded system `organization_admin` role.
3. Confirm that the user has no identity of any type and that APP-0049 is the
   exact approved reference.
4. Do not place the phone in shell history, process arguments, repository
   files, logs, tickets or documentation.

## Protected input

Create a temporary mode-0600 file without echoing the phone:

```bash
gse_operator_phone_file="$(mktemp)"
chmod 600 "$gse_operator_phone_file"
read -r -s -p 'Local operator phone (E.164): ' gse_operator_phone
printf '\n'
printf '%s\n' "$gse_operator_phone" > "$gse_operator_phone_file"
unset gse_operator_phone
```

The file must contain only one already-normalized E.164 value and an optional
final newline. The command rejects relative paths, symlinks, files outside the
system temporary directory, non-owner files and permissions other than 0400
or 0600.

## One-shot command

Use the exact reviewed IDs; they are intentionally not supplied by this
runbook:

```bash
LOCAL_OPERATOR_IDENTITY_BOOTSTRAP_ENABLED=true \
AUTH_OTP_DRIVER=local \
php artisan auth:bootstrap-local-operator-identity \
  --phone-file="$gse_operator_phone_file" \
  --user='<expected-user-ulid>' \
  --membership='<expected-membership-ulid>' \
  --approval='APP-0049'
```

The inline kill switch exists only for that process. A successful response
contains only user, membership and identity ULIDs, `local`, `APP-0049` and the
binding time. A failure returns only
`local_operator_identity_bootstrap_rejected`.

Immediately remove the input and unset its path:

```bash
unlink "$gse_operator_phone_file"
unset gse_operator_phone_file
```

Never add the kill switch to `.env`. A replay must fail because the user is no
longer identity-free.

## Required post-bootstrap path

1. Request and verify the ordinary local OTP through the first-party web API.
2. Select the exact existing Direct membership.
3. Enroll and confirm authenticator-app TOTP.
4. Use the current Media Library HTTP endpoints with CSRF, session and fresh
   TOTP controls for every upload/finalize/accept/metadata/rights command.
5. Preserve only content-free command evidence. Never preserve the raw phone,
   identity digest, OTP, TOTP secret or recovery codes.

The bootstrap does not authorize direct domain invocation, test
authentication, SQL media writes, a commissioner, production recovery,
identity reassignment or global account administration.
