# SpaceMatch GitHub Actions setup — Phase 3.1

No repository settings, secrets or connections have been changed. No workflow has been run on GitHub.

## Current behavior

The workflow has repository `contents: read` permission, disables persisted checkout credentials and has no production secrets, remote commands, transport, or activation. PRs to develop/main and main pushes run Linux deployment tests before PHP/Composer validation, isolated SQLite Laravel tests, npm ci, Vite/Bootstrap build and artifact verification. Artifacts are uploaded only for non-PR events, with full commit identity and archive SHA-256; retention is 30 days. Preserve approved releases privately beyond that window.

Manual dispatch accepts `approved_commit`, validates lowercase 40-character SHA and equality to the checked-out workflow SHA, and permits the build only on main. The approval job has an empty token permission set, no checkout and no credentials. It uses protected environment `production`, a production concurrency group without cancelling a running job, and runs only when repository variable `PRODUCTION_APPROVAL_ENABLED` is exactly `true`. That variable is absent by default, so the checkpoint is disabled. Even an approved checkpoint cannot deploy.

## Administrator prerequisites — separate authorization needed

1. Protect main with reviewed PRs, no force pushes, required `deployment-tests` and `build` checks, restricted bypass rights, and explicit review of deployment/workflow changes.
2. Review and pin checkout/setup-php/setup-node/upload-artifact to independently verified immutable commit SHAs. Current major-version tags remain a documented supply-chain limitation; resolve this before production enablement.
3. Create `production` with required reviewers, prevent self-review, main-only deployment branches and restricted bypass. Check current GitHub plan/repository visibility support. The YAML cannot create or prove these settings. A variable or manual dispatch alone is not reviewer approval.
4. After confirming protection, separately approve setting `PRODUCTION_APPROVAL_ENABLED=true`. This enables only the checkpoint.
5. Review a successful main build's source SHA, deployment tests, Laravel tests and artifact digest. No untrusted PR code should receive secrets; do not switch to pull_request_target with contributor code.
6. Verify cPanel capabilities and restoration rehearsal from the deployment guide. Separately approve the exact SHA, backup and operator activation.

No GitHub secrets are required now. A future separately reviewed SSH transport could require `ZCOM_SSH_PRIVATE_KEY` and independently pinned `ZCOM_KNOWN_HOSTS`, plus host/port/user variables. Do not create or connect them in this phase. Keep .env, APP_KEY, DB passwords and mail credentials out of Actions. SFTP alone cannot execute server safety checks; cPanel Git hooks are not configured. Do not add automatic deployment for develop pushes.

Native Linux flock and real filesystem symlink tests are included in the offline deployment suite. Windows skips those native cases; CI must demonstrate they pass before enablement. Test PHP/HTTP responses are simulated; a passing Linux unit suite is not an actual cPanel deployment rehearsal.

Reference: [GitHub environment approval and availability](https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments).
