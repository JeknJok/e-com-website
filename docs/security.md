# MAE portfolio security

MAE is a fictional-data portfolio, including when deployed with HTTPS. Visitors must not enter real names, email addresses, addresses, payment information, or passwords used elsewhere. The login, registration, recovery, and customer account pages say this explicitly. There is no email delivery, shipping workflow, or payment gateway.

## Visitor accounts

Registration collects a username, a made-up email ending in `.test`, and an 8–128-character disposable password. For example, `explorer@example.test` and a demo-only password work without a mailbox. Address/full-name fields are not collected; obsolete posted address fields are ignored. Administrator-created customer accounts follow the same rule.

Customer recovery uses a random private code shown once after registration or rotation in Account security. It expires after 30 days and can be consumed once. Only its SHA-256 hash is stored. Recovery does not depend on owning an email address and sends no mail. A lost recovery code can be replaced with the current password while signed in; otherwise visitors can create another fictional account.

Password/email changes require the current password, rotate the session ID, invalidate other sessions, and revoke older recovery codes. Blank new-password edits preserve the existing hash. Administrators can edit accounts only after recent MFA confirmation. Erased accounts cannot be reactivated.

## Administrator enrollment

There is no shared administrator login. Administrators require a 15–128-character passphrase, an offline common-password check, and an authenticator. TOTP is the current second factor; it is not phishing-resistant. A future passkey implementation would require its own origin/recovery review.

For local Docker, set `ADMIN_EMAIL` and `ADMIN_PASSWORD` privately in your shell, then run:

```sh
docker compose exec -e ADMIN_EMAIL -e ADMIN_PASSWORD web php scripts/create-admin.php
docker compose exec -e ADMIN_PASSWORD web php scripts/admin-mfa.php --email=owner@example.test --begin
```

Use the actual configured administrator email in `--email`. Add the printed setup key to your authenticator privately. Set `MFA_CODE` in the shell to the current code, then confirm:

```sh
docker compose exec -e ADMIN_PASSWORD -e MFA_CODE web php scripts/admin-mfa.php --email=owner@example.test --confirm
```

Save the eight single-use recovery codes privately; they expire after 30 days. Unset password/code environment variables afterward. Do not send setup keys, provisioning URIs, passwords, or recovery codes to logs, screenshots, commits, or CI artifacts. The same commands work natively with `php` instead of `docker compose exec ... web php`.

Signing in first creates only a five-minute pending challenge. No `AdminID` is installed until the second factor succeeds. OTP counters are consumed transactionally, so a code cannot be replayed. Wait for a new code if the enrollment code was just consumed. A valid unused administrator recovery code can supply the second factor only after the correct password.

Factor replacement and recovery-code rotation use the operator command, the current password, and the existing factor or an unused recovery code. Use `--rotate-recovery` to replace recovery codes. Enrollment/replacement confirmation revokes existing administrator sessions. Losing both the authenticator and every recovery code requires an operator-controlled incident procedure; there is no password-only public MFA reset.

Account administration, detailed customer access, and payment changes require an MFA confirmation within five minutes. The Account security page refreshes it with the current password and a new authenticator code.

## Existing store upgrade

Back up the existing store and test on a copy before changing schema. The encrypted backup utility supports the original/v1/v2/v3 application schemas when their tables are InnoDB. It refuses unknown tables and nontransactional legacy tables; use an offline/provider snapshot in those cases. Never treat a running Docker volume copy as a consistent database backup.

1. Preserve the current code/configuration and a recoverable encrypted database/media backup, with its key kept separately. Pause writes during a schema deployment.
2. Rebuild with `docker compose up --build -d` or install the locked Composer dependencies natively.
3. Run `docker compose exec web php scripts/migrate.php` (native: `php scripts/migrate.php`). Do not rerun setup against an existing database.
4. Enroll an authenticator for each existing administrator. Existing customer passwords continue to verify; successful legacy bcrypt logins upgrade to Argon2id. New/changed administrator passwords use the stronger policy.
5. Check accounts, orders, product images, and all six display currencies before reopening traffic.

Version 3 adds account authentication versions, activity/erasure metadata, encrypted MFA state, expiring recovery codes, rate limits, audit events, and an erasure ledger. Existing sessions without this state must sign in again. Migrations are repeatable and preserve IDs, archived records, order facts, and password hashes. MySQL/MariaDB DDL is not an all-or-nothing transaction; maintain the maintenance window until validation finishes. Keep code/config/schema compatible when rolling back. Restoring an old database can discard newer activity.

The default display currency remains USD, and authoritative order amounts remain whole IDR. Legacy external product image URLs use the local logo fallback; replace those custom URLs with a local/uploaded image.

## Sessions and abuse controls

| Control | Portfolio policy |
| --- | --- |
| Customer session | 30-minute idle limit; 12-hour absolute limit |
| Administrator session | 15-minute idle limit; 8-hour absolute limit |
| Sensitive admin confirmation | Five minutes |
| Password storage | Argon2id, 19 MiB, two iterations, one lane; benchmark before raising cost |
| Login attempts | 10 per account/15 minutes; 60 per IP/15 minutes |
| Registration | Five per email/hour; 10 per IP/hour |
| MFA/reauthentication | Six per account/five minutes; 30 per IP/five minutes |
| Recovery | Five per account/15 minutes; 20 per IP/15 minutes |
| Uploads | 20 per product/15 minutes; 30 per IP/15 minutes |
| All CSRF-valid mutations | 120 per account/minute; 240 per IP/minute, additionally limiting order/profile spam |

Counters are atomic shared database records keyed by HMAC, not raw email/IP values. A new cookie does not reset them. Responses include `429` and `Retry-After`; workers do not sleep for authentication cooldowns. There are no permanent automatic lockouts. A rate/audit dependency failure cannot silently grant authentication. `RATE_<ACTION>_IP` permits bounded operator tuning; tests raise only the shared loopback IP budget. These limits are starting engineering policies, not universal security standards.

Session files are private and outside the web root. Each authenticated request checks account availability and its stored authentication version. Password/recovery changes, deactivation, and logout-all revoke old sessions. Reactivation cannot revive them. Browser storage contains only the display-currency preference, not credentials/tokens.

## Production portfolio deployment

Use `compose.production.yaml` as a separate deployment, not as an override of the local development stack. It needs a domain pointing to the host, ports 80/443, Docker Compose, private secret storage, and a protected backup destination. No database/app port is published; Caddy is the public entry point. The edge overwrites forwarding headers and only its exact private IP is trusted by PHP.

Generate secrets **outside the checkout**:

```sh
php scripts/init-secrets.php --directory=/absolute/private/mae-secrets
```

The generator refuses existing secrets. Its private parent is mode 0700; readable child files support the unprivileged container user through explicitly granted file-backed Compose mounts. Keep that parent private and do not copy the files into the checkout/build context. File mounts are not an encrypted secret vault. A managed secret facility can replace them, preserving each service's narrow access. Keep the application and backup keys separately recoverable; losing the application key loses access to encrypted authenticators.

Create a private deployment environment file outside the checkout, containing only host/path settings:

```dotenv
MAE_HOST=mae.your-domain.example
SECRETS_DIRECTORY=/absolute/private/mae-secrets
BACKUP_DIRECTORY=/absolute/private/mae-backups
HSTS_ENABLED=0
```

The backup directory must exist and be writable by container UID 33, with restrictive host permissions. Adapt the fixed edge subnet if it conflicts with host networking, and change `TRUSTED_PROXIES` to the corresponding exact edge address. The example domain must be replaced with a domain you control.

Use `docker compose --env-file /private/deployment.env -f compose.production.yaml` as the prefix for these operations:

1. `--profile operator build`
2. `up -d db`
3. `run --rm provision` to create separate database identities.
4. `run --rm operator php scripts/setup.php --seed` for an empty database, or the migration command for a backed-up existing installation.
5. `run --rm provision` again to grant precise runtime permissions after tables exist.
6. Create/enroll the administrator through `operator`, passing only environment **names** with `-e ADMIN_EMAIL -e ADMIN_PASSWORD`/`-e MFA_CODE`, as in the local example.
7. `up -d web proxy expiry maintenance`.
8. Verify HTTPS, cookies, headers, login/MFA, media restrictions, and a backup/restore drill. Set `HSTS_ENABLED=1` and recreate `web` after HTTPS/domain ownership are verified. The policy does not preload the domain or automatically include every subdomain.

Provisioning is a maintenance operation: it revokes/reapplies grants and can briefly interrupt active requests. Do not run it on every restart. Reuse existing secret files with existing databases; changing a file does not automatically rotate the corresponding database account password.

| Identity/service | Access boundary |
| --- | --- |
| Web | Application CRUD, HMAC rate buckets, recovery state; append-only audit writes; only runtime DB secret and application key |
| Expiry | Narrow order-expiry columns; no customer-data read, application key, or web session storage |
| Maintenance | Retention/cleanup columns and bounded audit retention; no password/email reads, application key, or web sessions |
| Backup | Read-only database and product media; backup key; isolated workspace; no application key or web sessions |
| Operator/migration | Explicit setup, migration, MFA, restoration; isolated workspace and required keys; never a public service |
| Provisioner | Root credential mounted only for explicit account/grant provisioning |

App source/server configuration is immutable, the application runs as an unprivileged user, and capabilities, writable mounts, memory, processes, request sizes, and execution time are bounded. Only a dedicated media-lock volume is shared between the web/backup/cleanup services. Use encrypted host disks/managed volumes for database/session storage. Compose does not itself encrypt those volumes or verify the host firewall.

## Browser and uploaded media

Bootstrap 5.3.9, Bootswatch Lux 5.3.8, Montserrat, and Roboto Slab are bundled with licenses and hashes in `assets/vendor/`. The unused external Google font import was removed. CSP permits local scripts/styles/fonts, local/data images, and same-origin connections/forms; framing and objects are denied. The browser gets exchange rates through MAE, so it does not need direct provider access. Other headers prevent MIME sniffing, suppress referrers, restrict browser permissions, and avoid caching sensitive responses.

Uploads require administrator authorization, POST, CSRF, and abuse limits. Only JPEG/PNG/WebP is accepted: 2 MB input/output, 8 megapixels, and 4096 pixels per side. GD decodes and re-encodes pixels, removing metadata/appended content. Keep GD patched; decoding remains untrusted-input processing. Storage is bounded to 100 MB/500 files. Names are random, database/file updates are coordinated, and unreferenced generated files can be cleaned safely.

The immutable Apache image allowlist disables execution even if the writable upload volume's `.htaccess` is removed/replaced. Uploaded SVG is rejected; bundled trusted catalog SVG remains supported. Equivalent restrictions are required for a native server. The development router also denies private paths and non-image upload files.

## Retention and erasure

Customer Account security offers explicit erasure with the current password and typed confirmation. It scrubs identity/contact fields, invalidates credentials/sessions/recovery codes, and cancels unpaid invoices. Historical item names, quantities, and prices stay linked to a deleted-account placeholder. This is pseudonymization, not a promise that every retained record is anonymous.

Production maintenance runs hourly, erasing accounts after 30 days of inactivity in bounded batches of 100, removing expired throttle/recovery records, clearing expired pending enrollment secrets, keeping security events for 30 days, and cleaning generated orphan media older than one hour. These are portfolio policies, not legal retention advice for a real shop.

Manual commands are dry-run by default:

```sh
php scripts/privacy.php
php scripts/privacy.php --apply
php scripts/cleanup-media.php
php scripts/cleanup-media.php --apply
```

Export `php scripts/privacy.php --export-ledger` to a protected separate file after erasures. The ledger contains customer IDs/timestamps for this database, not contact fields. Keep the latest ledger separately from older snapshots and apply it only to a restore of the same store. Reapply it before exposing a restored database; otherwise an old snapshot can reintroduce erased identities. Retained backup copies also need a documented expiry and restricted access.

## Encrypted backup and recovery

`scripts/backup.php` streams database rows from a consistent InnoDB snapshot, includes product uploads and a checksummed manifest, and encrypts the archive with Sodium's authenticated XChaCha20-Poly1305 secret stream. A separate 32-byte base64 `BACKUP_KEY`/`BACKUP_KEY_FILE` is required. Keys are never included in the archive. There is a 512 MB demo archive/restore bound; this is not a general-purpose backup system for an arbitrarily large store.

With the production Compose prefix:

```sh
run --rm backup php scripts/backup.php --create=/backups/mae-snapshot.maebackup
```

Copy successful snapshots to protected **off-host**, offline or immutable storage whose deletion credentials are unavailable to the web service. The local staging directory and named Docker volumes are not sufficient disaster recovery. Keep keys separately and monitor failed/stale backups. Starting objectives are a 24-hour recovery point and four-hour recovery time, to be measured by restore drills.

The optional `docker/mae-backup.service` and `.timer` are host systemd templates for a daily snapshot. Adapt `/opt/mae` and `/etc/mae/deployment.env`, install them on the deployment host, connect failure monitoring, and add provider-specific off-host copying/retention. They are not installed automatically by the application.

Restore into an **empty isolated database** and an empty media directory outside the website, using a migration/operator identity. The utility refuses occupied targets and verifies the complete authenticated archive/entry allowlist/checksums before importing SQL. In the production operator container, an example is:

```sh
run --rm -e DB_NAME=MaeRestore operator php scripts/backup.php --restore=/backups/mae-snapshot.maebackup --uploads=/backups/restored-media
run --rm -e DB_NAME=MaeRestore operator php scripts/privacy.php --reapply-ledger=/backups/latest-ledger.json --apply
```

Create the isolated database/grants and empty media directory first. Legacy snapshots must be migrated before using the current erasure command. Verify account/order/media integrity, MFA key custody, privacy state, and timing before switching traffic. A failed or partial restore remains an isolated recovery attempt; do not route users to it. Test this procedure regularly and before significant schema changes.

## Detection and incident procedure

Security events contain action, time, actor/target IDs, outcome, and a server-generated request ID. They do not contain email addresses, addresses, passwords/hashes, cookies, CSRF/reset/recovery tokens, or request bodies. The production runtime may insert audit events but cannot read/update/delete them. Redacted structured events also go to stderr for a host-controlled collector. Apache access logs exclude query strings, referrers, and client IPs.

`php scripts/security-report.php` reports 15-minute administrator login/MFA failure thresholds and administrator/security changes. Optional `--backup-directory=/backups` checks local snapshot age. It emits bounded JSON and exits 2 for alerts; wire this and backup-service failures into the host's monitoring with an identified operator. No unsolicited emails or third-party notification integrations are configured. A successful local snapshot is not proof that off-host export succeeded.

If an incident occurs:

1. Restrict traffic and stop the compromised component while preserving relevant logs/snapshots.
2. Record scope and preserve evidence without copying secrets into incident notes.
3. Revoke affected sessions and rotate compromised credentials/keys through the appropriate operator procedure. Plan MFA re-enrollment before replacing an application key.
4. Restore a clean isolated copy, reapply erasures, verify it, and only then switch traffic.
5. Assess notification/reporting duties based on the operator's actual jurisdiction and activities. A portfolio label does not automatically settle legal applicability.
6. Record the cause, correction, validation, and follow-up ownership.

## Verification and maintenance

CI checks functional/legacy migration regressions, focused security HTTP/database cases, RFC TOTP/crypto/proxy tests, currency behavior, locked Composer advisories, local PHP Semgrep rules, redacted repository-history secret scanning, the actual non-root Apache/TLS/proxy/least-privilege deployment, an encrypted backup/restore drill, Trivy's fixable high/critical image findings, and a passive ZAP baseline. Active scans belong only in disposable fictional-data environments. GitHub Actions are pinned to verified commit SHAs; deployed base images and scanner downloads are pinned to verified digests. No production secrets are supplied to pull-request code.

The Gitleaks maintenance notice currently limits future releases to security patches. Recheck it before tool updates. The sole inline scanner exception is the published RFC 6238 test vector in `tests/security-unit.php`, never an account authenticator. The Semgrep rules cover selected PHP request boundaries, not all weaknesses. CodeQL is not counted as PHP coverage. Findings need review; neither scanner success nor these tests establish complete security or ASVS certification.

| Research work | Implementation/evidence |
| --- | --- |
| SEC-01 transport/config | Canonical HTTPS origin, trusted edge, production fail-closed validation, secure cookies, optional verified-host HSTS |
| SEC-02 sessions | Server timeouts, ID rotation, authentication versions/revocation, private storage |
| SEC-03 abuse | Atomic HMAC account/IP counters, shared aliases, bounded recovery/upload attempts |
| SEC-04 sensitive changes | Current customer password, recent admin MFA, transactional epoch checks/audit |
| SEC-05 passwords | Explicit portfolio policy; stronger admins; Argon2id; Unicode; safe bcrypt migration |
| SEC-06 MFA | Maintained OTPHP, pending-only login, replay counters, encrypted seeds, protected operator enrollment/recovery |
| SEC-07 identity/recovery | Portfolio adaptation: reserved fictional email identifiers, no mail dependency, expiring hashed single-use recovery codes |
| SEC-08 privacy | Minimal registration, reduced/paginated lists, erasure, bounded retention, separate erasure ledger |
| SEC-09 deployment boundaries | Separate grants/secrets, non-root immutable source, isolated job storage, private DB |
| SEC-10 browser | Bundled licensed/patched assets; CSP; local product images; response headers |
| SEC-11 media | Bounded canonicalization/quotas; immutable handler allowlist; reference-aware cleanup |
| SEC-12 detection | Restricted structured audit events, sanitized errors/access logs, threshold reporting |
| SEC-13 recovery | Authenticated encrypted snapshots, empty-target restoration, ledger replay, timer/incident procedures |
| SEC-14 verification | PHP-appropriate scanning, actual-server tests, release gates, dependency/image inventory |

Before publishing, assign an operator, verify host/disk/backup protections, enable required monitoring and off-host export, and review applicable ASVS 5.0.0 Level 1/2 requirements with evidence. Treat hosting guarantees as unverified until tested on that host. Reassess the design before adding real accounts, real emails, shipping, delegated staff, customer files, or payment processing.

Primary references: [OWASP ASVS](https://asvs.owasp.org/), [OWASP cheat sheets](https://cheatsheetseries.owasp.org/), [NIST SP 800-63B-4](https://pages.nist.gov/800-63-4/sp800-63b.html), [PHP password hashing](https://www.php.net/manual/en/function.password-hash.php), [OTPHP](https://github.com/Spomky-Labs/otphp), [Docker secrets](https://docs.docker.com/compose/how-tos/use-secrets/), and [GitHub Actions security](https://docs.github.com/en/actions/reference/security/secure-use). The accompanying research report records the complete source review and bibliography.
