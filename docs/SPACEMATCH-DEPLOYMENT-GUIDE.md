# SpaceMatch deployment guide — hardened Phase 3.1

No production connection, credential setup, push, merge or deployment was performed. This document supersedes the Phase 3 command sequence. The supplied workflow builds artifacts; it cannot activate production.

## Architecture and default block

Reviewed develop → PR → protected main → Linux deployment tests → Laravel tests and frontend build → exact-commit artifact → manually dispatched, protected approval checkpoint → separately approved operator activation. A develop push never deploys. There is no SSH/SFTP transport job.

`deploy.sh` is a thin wrapper around the tested Python controller. It requires the real POSIX account `tejzvrgp`, fixed production paths, explicit approval token, private deployment state and reviewed readiness records. It does not require server-side Composer, rsync, GNU realpath, systemd, sudo, Node, or a full VPS. It **does** require cPanel shell/Terminal, Bash, PHP CLI >=8.2, Python >=3.10 with TLS/gzip/tar/fcntl, HTTPS egress, sufficient quota and compatible web-server ownership. These capabilities remain unverified, and deployment is blocked until they are verified.

Application: `/home/tejzvrgp/spacematch-app`; document root: `/home/tejzvrgp/public_html/spacematch/public`; state: `/home/tejzvrgp/spacematch-deploy`. Release extraction and backup directories must be separate sibling trees, **outside** these three roots (for example `/home/tejzvrgp/spacematch-incoming/RELEASE_ID` and `/home/tejzvrgp/spacematch-backups/BACKUP_ID`). Do not reuse the old incoming-under-state layout: overlapping roots are rejected.

## Windows PowerShell validation

```powershell
Set-Location 'C:\xampp\htdocs\spacematch'
python -m unittest discover -s scripts/deploy -p 'test_*.py' -v
php artisan test --compact
npm.cmd run build
php -l scripts/deploy/runtime-check.php
php vendor/symfony/yaml/Resources/bin/yaml-lint .github/workflows/release.yml
& 'C:\Program Files\Git\bin\bash.exe' -n scripts/deploy/deploy.sh
git diff --check
```

After a separately reviewed local commit, use a clean checkout and freshly built assets:

```powershell
python scripts/deploy/package.py release
$releaseSha = git rev-parse HEAD
python scripts/deploy/verify.py release $releaseSha
tar -czf "release-$releaseSha.tar.gz" -C release .
Get-FileHash "release-$releaseSha.tar.gz" -Algorithm SHA256
```

Source bytes come from Git objects, not the Windows working-tree encoding/line endings. Dirty tracked application/build inputs are rejected. Vite output must be built from those inputs; approved CI is the trust source. The manifest is not a signature. Verify the archive digest from the approved main workflow run and review its source; an attacker can rewrite an artifact and its internal hashes together.

## Readiness record — not supplied or automatically approved

Provision `/home/tejzvrgp/spacematch-deploy` as the account owner, mode 700. Reviewed tools belong in its private `tools` directory. Put `readiness.json` inside state, mode 600, using this schema (placeholders are intentionally not deployment-ready):

```json
{
  "schema": 1,
  "enabled": false,
  "reviewed_by": "",
  "valid_until": 0,
  "php_binary": "/VERIFIED/CPANEL/PHP/CLI",
  "dependency_record": "/home/tejzvrgp/spacematch-deploy/dependencies.json",
  "capabilities": {
    "web_cli_php_match": false,
    "private_modes_readable_by_web_php": false,
    "entrypoint_maintenance_path_verified": false,
    "managed_paths_contain_no_uploads": false,
    "file_maintenance_driver": false,
    "writers_paused": false,
    "opcache_revalidates_and_no_persistent_workers": false,
    "staging_recovery_rehearsed": false
  },
  "managed_inventory": {}
}
```

Every capability needs actual evidence before it can be set true. `valid_until` is a Unix timestamp no more than seven days ahead. Refresh writer/maintenance approval for every deployment; expiration alone does not make a stale pause attestation valid. Confirm web PHP equals CLI PHP, private 700/600 app files work with the hosting PHP handler, and the customized public index includes **the app's** storage/framework/maintenance.php before autoloading. Verify Laravel console public_path resolves to the actual document root (an existing reviewed app/public symlink can provide this) and storage_path resolves to the verified runtime root. A web-only custom path override that differs from CLI must be reconciled through a separately reviewed hosting adaptation. Verify file maintenance driver, uncached OPcache changes (timestamp validation with no stale interval during activation), and paused cron/queues/writers. Unsupported long-lived workers or OPcache restrictions block activation until a separate supported restart plan is implemented.

`managed_inventory` is a reviewed exact map of current managed relative filenames to SHA-256. Obtain a candidate during authorized cPanel inspection, but do not approve uploads as static assets. Example read-only inspection (review root paths first):

```bash
cd /home/tejzvrgp/spacematch-deploy/tools
python3 - <<'PY'
import json
from deploy import Deployment
app = '/home/tejzvrgp/spacematch-app'
public = '/home/tejzvrgp/public_html/spacematch/public'
d = Deployment(app, public, '/home/tejzvrgp/spacematch-deploy', '/UNUSED', 'a'*40, '/UNUSED', '/UNUSED')
print(json.dumps(d.inventory(), indent=2))
PY
```

Candidate inspection is not approval. Compare the inventory to known code/static files and check for uploads, hidden runtime files and drift. After each healthy release, review its new inventory and update readiness for the next release; the controller never silently blesses a new baseline.

## Dependencies without production Composer

No dependency verification record exists yet: deployment is blocked. A working autoloader or equal lock files alone do not establish compatibility. Composer's generated runtime platform check defaults to PHP-only and is not sufficient proof of all extensions/version constraints.

An operator must first establish a **separately verified production dependency bundle** from the same composer.json/lock on an isolated build environment matching the exact production PHP version and loaded extension versions. Run locked production dependency installation with Composer 2, review plugins/scripts, verify both lock and installed platform requirements, inventory production packages, and test the app. Never use ignore-platform-reqs. Compare every installed production vendor file against that verified bundle; any difference requires a separately approved provisioning/reconciliation procedure. The deploy controller never installs or updates vendor. Autoload classmap-authoritative mode is rejected; removed/moved application classmap paths are rejected. Review changed class declarations/namespaces too: path checks cannot prove semantic autoload compatibility.

Only after that review, create private `dependencies.json`:

```json
{
  "schema": 1,
  "method": "verified-production-bundle",
  "platform_check": "passed",
  "reviewed_by": "REVIEWER",
  "provisioning_record": "PRIVATE_VERIFIED_BUILD_AND_COMPOSER_CHECK_RECORD",
  "composer_lock_sha256": "FULL_SHA256",
  "composer_json_sha256": "FULL_SHA256",
  "php_version_id": 80212,
  "extensions": {"EXACT_LOWERCASE_EXTENSION_NAME": "EXACT_VERSION"},
  "vendor_files": {"autoload.php": "FULL_SHA256"}
}
```

Maps shown are abbreviated examples: **all** loaded extensions and every regular vendor file are required. This is an operator trust record, not a machine-issued attestation or a new way to bless unknown existing dependencies. The runtime probe returns PHP/extension fingerprints, maintenance/autoload configuration, classmap paths, database name and applied migration names without printing credentials. It performs a SELECT only against migrations; no DDL, migration execution or database restore occurs. Laravel bootstrap may still write ordinary logs. Never paste configuration or probe diagnostics into public reports.

## Backups

Create consistent, private, fresh DB and full runtime backups after pausing writers. The deploy script does not create SQL dumps. Use cPanel Backup or an independently verified dump tool/privileges; do not assume mysqldump is installed. Never put passwords in commands. Retain encrypted off-host copies and establish retention without automatic pruning.

A backup directory must contain exactly `database.sql.gz`, `runtime.tar.gz`, and `backup.json`. The SQL gzip must be nonempty and readable to EOF. The runtime tar must contain the complete app and public roots with paths `spacematch-app/...` and `public_html/spacematch/public/...`; inspection requires protected config, autoload and entrypoints, but does not itself prove restore completeness. Restore both backups into isolated storage/database, verify records, uploads and login, and record that review:

```json
{
  "schema": 1,
  "database": "tejzvrgp_spacematch_db",
  "commit": "FULL_APPROVED_40_CHARACTER_COMMIT",
  "created_at": 0,
  "restore_tested_at": 0,
  "writers_paused": true,
  "reviewed_by": "REVIEWER_AND_RESTORE_EVIDENCE",
  "files": {"database.sql.gz": "FULL_SHA256", "runtime.tar.gz": "FULL_SHA256"}
}
```

Both timestamps must be within the last hour; restore verification must follow creation. Hashes, gzip CRC and tar contents are checked. Restore testing and backup consistency remain reviewed operator evidence. This replaces the old RESTORE-TESTED/SHA256SUMS directory format. Do not merely touch a marker or reuse an old snapshot.

## Future approved cPanel activation

These are documentation commands for a future approved release. No connection or command here was executed against hosting. Install all Python files plus runtime-check.php and deploy.sh together under private tools. Authenticate the artifact/run and obtain its independent SHA-256 before extraction. The safe extractor rejects links, traversal, devices, duplicate members and oversized archives, then validates the full extracted release:

```bash
umask 077
cd /home/tejzvrgp/spacematch-deploy/tools
python3 verify.py /home/tejzvrgp/spacematch-incoming/RELEASE_ID FULL_COMMIT --archive /home/tejzvrgp/spacematch-incoming/release-FULL_COMMIT.tar.gz --sha256 TRUSTED_ARCHIVE_SHA256
bash deploy.sh /home/tejzvrgp/spacematch-incoming/RELEASE_ID FULL_COMMIT /home/tejzvrgp/spacematch-backups/BACKUP_ID APPROVED-CODE-ONLY --preflight
```

Preflight acquires the lock, validates everything, probes writable runtime directories and checks baseline HTTPS health. It does not activate code, but is not a purely read-only filesystem operation: permission probes create temporary files and the lock file. It also runs a read-only DB migration-status query. No preflight should be run on production without separate access authorization.

After the successful preflight and a specific activation approval, run the same command without `--preflight`. Writers must remain paused through health checks and functional review. No schema/dependency changes are supported in this code-only path. Current migration filenames/content and the live applied migration list must match exactly. Review live schema separately for drift; migration names are not proof of schema equality. The reported count of 27 is not an approval.

## Update and recovery boundaries

Only app/, config/, resources/, routes/, artisan, bootstrap/app.php/providers.php and public assets/, vendor/, build/, favicon.ico/robots.txt are managed. **public/vendor is Bootstrap static content; app/vendor is private Composer dependencies.** No recursive deletion runs. Only a specific obsolete file from the reviewed old/new inventory may be removed. `.env`, storage, public uploads, index.php/.htaccess, composer files and migrations are never copied/deleted. Existing directory permissions are not recursively changed or copied from Windows. New private/public directories use 700/755; replaced private/public files use 600/644. Review existing directory modes during readiness.

Verified previous code/assets are retained before maintenance. Deployment records preserve phase, old/target inventories and last-working pointer. Errors trigger code-only recovery; failed recovery keeps a standalone 503 guard when writable and exits nonzero. See the rollback guide for interrupted-process and operator escalation limitations. Health checks require exact HTTP 200 without redirects, expected `/up` and homepage markers, and byte hashes of served built JS/CSS; HTTPS verification is enabled, responses bounded and retries limited. They do not replace OTP/auth/business-flow tests. Brief reopening for post-copy health checks can admit requests; pauses must cover writers, and database rollback is never automatic.

Authoritative references: [Composer runtime checks](https://getcomposer.org/doc/07-runtime.md), [Composer platform checks](https://getcomposer.org/doc/03-cli.md#check-platform-reqs), [cPanel Terminal prerequisites](https://support.cpanel.net/hc/en-us/articles/1500006547541-How-do-I-access-the-terminal-within-a-cPanel-account), [GitHub environment approvals](https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments).
