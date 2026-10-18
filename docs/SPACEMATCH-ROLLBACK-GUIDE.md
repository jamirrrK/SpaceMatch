# SpaceMatch rollback guide — Phase 3.1

No database restore, dependency replacement or production connection has been performed. This replaces the older rsync rollback commands; do not use those commands with the new inventory-based controller.

## Automatic code-only recovery

Before activation the controller verifies baseline health and snapshots every managed code/static file into a private `release-TIMESTAMP-SHA-ID/previous` tree. It checks snapshot hashes and records old/target inventories. `last-working.json` points to the verified baseline before copying and to the new release only after successful health checks. Snapshots are never pruned automatically. Retain previous frontend assets as part of the same snapshot.

An error during maintenance, copying, cache rebuild, reopening or health verification triggers:

1. Reinstate the standalone maintenance guard, independent of Laravel bootstrap.
2. Verify the retained snapshot's exact file set and SHA-256 values.
3. Restore only managed code/static files; remove only new release files in the explicit managed inventory.
4. Clear/rebuild configuration, route and compiled-view caches without clearing production application cache data.
5. Reopen and verify `/up`, homepage and served JS/CSS bytes against the old inventory.

A deployment that needed recovery always exits nonzero. `status.json` distinguishes `failed-previous-code-restored-healthy`, `failed-closed-maintenance-operator-required`, and `critical-maintenance-unconfirmed-operator-required`. Protected config/entrypoint fingerprints must remain unchanged. No database, private vendor directory, storage or uploaded files are restored. Successful recovery restores the previous application against its existing compatible dependencies/configuration.

## When recovery cannot be confirmed

If old code cannot be copied, caches cannot rebuild or previous health fails, maintenance remains enabled where writable, and the controller emits a critical operator message. If permission loss prevents writing the maintenance guard or status, it explicitly reports that maintenance is **unconfirmed**. Do not claim the site is safely offline in that state: use authorized cPanel/provider controls immediately to restrict web access. Preserve private backups/history and consult hosting support. Never remove down/maintenance files merely to make the page visible before code and entrypoints are verified.

The guard requires the customized index.php to include the app's maintenance.php before loading vendor. This is a mandatory staging/hosting readiness check. If production PHP cannot read the guard under owner-only modes, this workflow must remain blocked. No script can guarantee fail-closed behavior after filesystem failure, host power loss, or a provider killing the process; independent hosting access is required for recovery.

SIGINT and SIGTERM during activation attempt recovery. SIGKILL/power loss cannot be trapped. Durable unfinished or missing status records block the next CLI deployment. An interruption after reopening for health verification can leave new code serving before its health is confirmed; examine the recorded phase, restrict access if needed, and recover under the same deployment lock. Do not erase history to bypass that block. After a crash, revalidate permissions, snapshots, dependencies, unchanged DB compatibility and protected paths before an operator restores selected files. Operator recovery must use the same explicit managed inventory and lock; broad directory synchronization is not a fallback.

## Configuration, uploads and dependencies

Code-only deployments leave these in place. If they are independently damaged, extract the verified full runtime backup into an isolated private directory, compare it to current files and approve a selective restore. Do not replace current uploads with older ones: users may have added data after backup. Restore vendor only with matching lock/code after a separately verified provisioning procedure. Preserve APP_KEY exactly. Backups contain credentials and user data; never serve or post them publicly.

## Database limitations

Never automatically import an older SQL backup or execute down migrations. Even a short release can admit new registrations, bookings or payments. Prefer a forward additive repair when appropriate. Any database restoration needs separate explicit approval identifying the exact backup, lost-activity window, reconciliation plan, downtime and operator. Pause all writers, capture an incident backup, restore the candidate into an isolated DB and verify schema/data first. No production database import command is supplied as a default recovery step.

After code recovery, check login, renter/owner registration, EmailJS OTP, uploads, roles, dashboards, inquiries and rental flows before resuming writers. Record the operator, review evidence and functional result in private history. A green HTTP probe does not approve database changes or prove business correctness.
