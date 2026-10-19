# Final Release Plan

**Current state:** local development; no release approval.

## Phase 1 — Preserve and reconcile schema

1. Keep the current dirty worktree intact; review changes before staging or committing.
2. Create a database backup using the project's approved local procedure and verify restoration to an isolated database.
3. Review the three pending migration files, their indexes/foreign keys, and MySQL/MariaDB version compatibility.
4. Apply migrations only to an isolated MySQL copy first.
5. Obtain explicit user authorization before applying any migration to the existing local MySQL database.
6. Run focused owner, student, location privacy, map, moderation, and payment-submission tests against the migrated MySQL copy.

## Phase 2 — Verify services and workflows

1. Configure a real email sender and verified From address; test successful delivery, rejection, retry, expiry, and resend limits.
2. Keep SMS unavailable unless a real sender is configured and tested.
3. Verify private image/payment-evidence storage and authorization using non-production test accounts.
4. Verify scheduled commands and queue execution in the intended host model.
5. Test renter, student, owner, admin, and master-admin browser paths against isolated test data.
6. Test mobile and accessibility workflows, plus map behavior with approved exact/approximate/hidden location fixtures.

## Phase 3 — Operational and legal review

1. Set production `APP_DEBUG=false`; verify HTTPS, secure cookies, session and cache configuration.
2. Confirm operator identity, support escalation, complaint/dispute process, retention/deletion requests, and manual-payment language.
3. Obtain qualified legal review for Terms, Privacy Notice, and Philippine regulatory obligations.
4. Verify OSM-compatible tile-provider policy and capacity; select a provider appropriate for expected production traffic.

## Phase 4 — Authorized staging and release

1. Build a release artifact and verify all Vite and public assets.
2. Deploy only to an authorized staging target; run smoke tests and role-based workflows.
3. Verify backup, health checks, logs, scheduler/queue, and rollback.
4. Present evidence and unresolved risks to the user.
5. Wait for explicit user approval before any GitHub push, merge, production access, DNS change, or production deployment.

## No-go conditions

Do not release while schema drift, unverified email, unverified hosting/rollback, unresolved high-severity security issues, unsupported payment claims, incomplete legal/operational review, or missing user approval remain.
