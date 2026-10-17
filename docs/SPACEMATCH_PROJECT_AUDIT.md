# SpaceMatch Project Audit

**Audit date:** 2026-10-16
**Workspace:** `C:\xampp\htdocs\spacematch`

## Executive Summary

The checkout is a Laravel 12 application with an existing authentication foundation, owner property and space management, private property-image delivery, public listing discovery, and administrator property moderation. The reported baseline was reproduced before edits: 49 tests, 304 assertions, and no failures. All 12 migrations present in this checkout are applied, and Laravel registers 47 routes.

This is not yet the complete rental workflow described in the project brief. In particular, favorites, inquiries, viewing appointments, reservation requests, conversations, and application notifications have no corresponding models, tables, or routes. Existing bookings, payments, maintenance requests, and reviews have storage and list routes, but no full renter/owner transaction workflows.

During the audit, booking-linked list pages were found to return fabricated sample rows while their controllers loaded records without participant scoping. The booking, payment, maintenance, and review lists now use real paginated records and a shared role-aware booking scope. The focused regression test passes for renter, owner, and admin views.

## Stack and Runtime

- PHP requirement: `^8.2`; Laravel framework requirement: `^12.0`.
- Server-rendered Blade application with Bootstrap 5.3 assets and a custom `public/assets/css/spacematch.css` design layer.
- Vite 7, Tailwind CSS 4, Laravel Vite plugin, and Playwright are declared in `package.json`. The current shared layout references vendored Bootstrap and copied `public/assets` rather than Vite-generated entry points.
- Authentication uses Laravel's session guard and Eloquent `User` provider. Database connection is selected through `DB_CONNECTION`; credentials and `.env` values were not read or changed.
- The test configuration forces isolated SQLite `:memory:` and array-backed cache/session/mail.

## Database Structure

The applied application migrations create or extend `users`, `user_roles`, `properties`, `spaces`, `bookings`, `payments`, `maintenance_requests`, `reviews`, `audit_logs`, `property_images`, `amenities`, and the `amenity_space` pivot. Laravel's cache and queue tables are also applied.

The property-management extension adds listing/moderation fields, location fields, space details, image associations, amenity pivots, and indexes. No favorites, inquiries, viewing-appointment, reservation, conversation, participant, message, or notification table is present. `user_roles` exists, while the active role checks observed in the application use `users.role`.

## Verified Existing Functionality

### Authentication and roles

- Login, logout, registration, password-reset request and reset routes are implemented.
- Public registration validation permits only renter and owner roles; admin cannot be selected.
- Password hashing, login session regeneration, logout invalidation/token regeneration, and throttles on login, registration, and reset endpoints are present.
- Role middleware protects the admin, owner, and renter dashboard groups. Profile editing and password update routes are authenticated.
- The automated suite covers the main authentication redirects and role boundary. Real external email delivery and exhaustive profile/password edge cases have not been verified.

### Property owner

- Owner property create, edit, list, detail, archive/status, and publish routes exist.
- Owners can create/edit spaces, associate configured amenities, publish spaces, upload multiple property images, select ordering/cover images, and remove images.
- Owner property and space queries enforce ownership; policies and form requests are present. Tests cover cross-owner access, validation, publication requirements, and image handling.
- Owner dashboard counts are based on owner-scoped database queries. Booking and payment figures are only as meaningful as the currently limited booking/payment workflows.

### Public discovery

- The homepage loads up to three real published spaces and property-type links.
- Search supports property name, city/barangay/address/province, property type, min/max price, amenities, capacity, sorting by newest/lowest/highest price, and pagination with query-string persistence.
- Public detail and image access hide unpublished, inactive, archived, or unavailable records according to the existing visibility rules.
- Existing tests cover filtering, same-space filter composition, validation, ordering, empty results, and listing visibility.
- Oldest-first sorting, a public favorites state, renter inquiry/viewing actions, and broader homepage sections are absent.

### Administrator

- Dashboard statistics and recent users/properties/bookings are database-backed.
- Property search/filter, detail, moderation status changes, moderation reason validation, and audit-log writes are implemented and tested.
- User management, account activation/deactivation, dedicated audit activity browsing, and a complete listing approval/rejection workflow are absent.

### Existing booking-related records

- `bookings`, `payments`, `maintenance_requests`, and `reviews` have models and read-only index routes, but no create/respond/approve/cancel flows were found.
- Their former Blade pages displayed hard-coded sample data despite controller-provided records. Their controller queries also did not limit results to the renter, owner, or admin.
- This audit adds a `Booking::visibleTo` query scope and applies it to booking-linked lists. The pages now render actual database rows with pagination and empty states. Admin can see platform-wide records; owners see records for their properties; renters see records tied to their bookings; unknown roles receive no records.
- This read-only repair does not implement reservations, payments, maintenance submission, or review creation. No real payment gateway was added.

## UI Recovery Findings

- No `.git` directory is present in this workspace, so commit history, branches, and reflog are unavailable for design recovery.
- The available visual evidence is the current custom layout and assets: SpaceMatch wordmark/monogram, navy `#122644`, blue `#245ce6`, pale neutral surfaces, Bootstrap components, responsive navigation, a shared management sidebar, custom cards, and an SVG space placeholder.
- Source CSS and the copied public CSS use different font declarations (Instrument Sans in the Vite source theme and Inter in the public design layer). This is a design-system consistency issue to resolve during the UI restoration stage.
- The current home page has a location search, featured listings, property-type links, and an owner call to action. It does not yet include the requested how-it-works, benefits, FAQ, or fuller about sections.
- Exact restoration of a prior Copilot design cannot be claimed from this checkout. VS Code Local History and backups outside the workspace were not inspected in this audit.
- The existing browser-validation harness initially reproduced the owner dashboard overflowing at 390px (428px document width). The cause was a visually-hidden table heading expanding the document; its accessible label is now on the table header itself. A fresh isolated browser run passed all 14 checks, including the 390px owner dashboard.

## Current Automated Baseline

| Check | Result |
|---|---|
| `php artisan optimize:clear` | Passed |
| `php artisan migrate:status` | All 12 listed migrations applied |
| `php artisan route:list --except-vendor` | 47 routes |
| `php artisan test` before audit repair | 49 tests, 304 assertions, 0 failures |
| Focused booking-linked visibility regression after repair | 1 test, 40 assertions, passed |
| Final `php artisan test` | 50 tests, 344 assertions, 0 failures |
| `php artisan view:cache` | Passed |
| `npm run build` | Passed; Vite output and Bootstrap asset sync completed |
| Isolated Playwright browser suite | 14 passed, 0 failed; no console/page errors, failed requests/responses, broken images, or overflow |

The full suite and browser checks were rerun after the audit repair. Browser screenshots and machine-readable results are in `docs/browser/`; the browser checks cover 1440px, 768px, and 390px, not every viewport listed in the project brief.

## Known Gaps and Risks

1. No favorites, inquiry, viewing, reservation, messaging, or database-notification workflow is implemented.
2. Admin user management and activity-log browsing are missing.
3. Existing booking-related records have no full workflow; avoid presenting them as an operational reservation/payment system.
4. The renter dashboard currently centers on booking/payment statistics and has no renter discovery or engagement management.
5. Current navigation includes booking/payment/maintenance/review list pages, but these remain read-only and do not replace the missing owner/renter action flows.
6. The browser run does not cover every requested width (1920px, 1280px, 1024px, 430px, and 360px remain unverified).
7. Original UI history outside the workspace remains unverified; exact design restoration is not established.

## Recommended Resume Order

1. Expand responsive checks to the remaining viewport widths and improve cross-role dashboard navigation.
2. Normalize the current UI tokens/fonts while preserving the existing brand evidence.
3. Design renter engagement schema only after checking constraints against the existing `bookings` table; implement favorites and inquiries with ownership/privacy tests.
4. Continue with viewing requests and reservation conflict handling, then messaging and database notifications.
5. Add admin user management and audit-log browsing, then complete security/regression/browser coverage.

No database migrations were added during this audit. The user's `.env` and existing non-test records were not modified.