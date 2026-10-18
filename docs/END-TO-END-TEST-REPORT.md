# End-to-End and Automated Test Report

**Audit date:** 2026-10-19
**Scope:** Isolated test suite and limited anonymous local browser checks.

## Automated suite

`php artisan test` ran with the checked-in PHPUnit configuration, which forces SQLite `:memory:`. Result after fixes:

```text
Tests: 159 passed (1,509 assertions)
```

The suite includes authentication/OTP, renter and owner workspaces, student workflow, listing discovery, image access, inquiries, viewing appointments, rental requests/bookings, manual payment submissions, review eligibility/moderation, admin authorization, and marketing assets.

After the full run, the map query was optimized and the map-popup rating was added. The focused `ListingSearchSchemaCompatibilityTest` was rerun after those changes: 2 passed, 8 assertions. The entire suite was not rerun after that final map-only delta.

The standalone test-runner integration did not discover test files when invoked without explicit files; `php artisan test` was used as the authoritative runner.

## Browser verification

Observed the app at `http://127.0.0.1:8000/` (the requested port `8014` was not listening):

- Page title: `Find your next space | SpaceMatch`.
- With the browser page visible and reduced motion set to `no-preference`, the carousel advanced automatically between promotional slides. When the page was hidden, autoplay correctly paused.
- Leaflet map initialized without a click.
- Eight OpenStreetMap-compatible tiles loaded.
- No map markers appeared because the active MySQL schema has no owner-confirmed location fields.
- No “Load map” button was present.
- Browser reported no JavaScript console/page errors during this check.
- The page stylesheet was applied; Manrope was the computed body font.

A browser automation attempt to traverse multiple public routes timed out during navigation and did not yield a complete route-by-route browser report. No login, registration submission, file upload, payment submission, or role-specific mutation was run against the live MySQL database. Role workflows were tested only on the isolated SQLite suite.

## Frontend and manifest

- `npm run build` passed after the popup rating enhancement.
- Build emitted CSS, JavaScript, Leaflet images, and font assets.
- Every CSS/JS file referenced by the Vite manifest existed.
- `composer validate --no-check-publish --no-interaction` passed.

## Migration/schema boundary

`php artisan migrate:status` showed three pending migrations. Metadata-only schema inspection confirmed the active MySQL schema is behind the application. No migration or data-modifying command was run. SQLite migration success in automated tests does not establish MySQL migration success.

## Not tested

- Production site or Z.com hosting.
- Actual email/SMS delivery.
- Real renter/owner/admin browser logins.
- Live photo storage or private payment evidence access.
- High-load performance or large map result sets.
- Tablet/small-mobile, keyboard-only, or screen-reader workflows.
- MySQL migration execution, rollback/recovery, or a verified database restore.
