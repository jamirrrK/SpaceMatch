# Critical Issues and Fixes

**Audit date:** 2026-10-19

## P0 — Existing MySQL schema is behind application code

**Status: OPEN — requires reviewed backup and user approval before schema change.**

`php artisan migrate:status` reports these migrations pending:

- `2026_10_18_120000_add_student_workflow_details`
- `2026_10_18_130000_create_payment_submissions_table`
- `2026_10_19_010000_add_marketplace_integrity_and_locations`

Read-only schema inspection confirmed the active local database lacks `properties.is_synthetic`, location columns, student workflow columns, `reviews.hidden_at`/moderation columns, and the `payment_submissions` table. The application contains defensive compatibility for homepage listing visibility, review summaries, and proximity search, but it cannot provide the new location, student, moderation, or payment-proof features without their schema.

**Impact:** map has no public pins; proximity filtering returns no results; owner saves with location data, student workflow persistence, review moderation/reporting, and payment proof submissions are not safe to treat as available against this database.

**Action:** no migration was run and no database backup was claimed. Review all migration operations against an isolated MySQL copy, create/verify a restorable backup, then get explicit approval before changing the existing local database.

## P1 — Optional location confirmation rejected ordinary property saves

**Status: FIXED AND TESTED.**

`location_confirmed` used an `accepted` validation rule, but the owner checkbox is optional and is unchecked/omitted for an unconfirmed location. The request now validates it as an optional boolean. `PropertyManagementTest` and `PropertyModerationTest` pass, including ownership and moderation protections.

## Test expectation drift

**Status: FIXED AND TESTED.**

- The development seeder deliberately creates `is_synthetic=true`; it must not be publicly listed. Updated the test to assert synthetic listings remain hidden.
- The homepage now shows clearly labeled promotional carousel artwork when no listing photos exist. Updated the test to assert the honest promotional state rather than an obsolete no-carousel state.
- Focused result after these updates: 27 tests passed, 192 assertions.

## P2 — Map endpoint loaded unnecessary listing relations

**Status: FIXED AND TESTED.**

The map endpoint previously loaded every matching space and eager-loaded images and amenities it did not need. It now aggregates minimum price by property, loads property review summaries once, filters to Philippines coordinates, and returns public locations only. The test verifies grouping, minimum price, property link, and review count.

## P2 — Map popup omitted available rating data

**Status: FIXED AND TESTED AT API/BUILD LEVEL.**

Map popup content now displays a real average and review count only when the count is positive. The endpoint regression test verifies rating/count fields. The current live MySQL has no confirmed-location columns, so browser rendering of a real marker popup could not be exercised against live local data.

## Security review summary

The focused security review found no confirmed critical/high vulnerabilities in the reviewed route, auth/OTP, listing/location, image, booking/payment, review, and map surfaces. It explicitly did not claim an exhaustive repository-wide review. Human/runtime checks remain: production debug and HTTPS/cookie settings, exact-location consent, storage ACLs, and external provider configuration.

The tracked `.env.example` had non-placeholder mail account identifiers. Those values were replaced with blank/generic template values; the ignored runtime `.env` was not inspected or changed. Do not put real account identifiers or credentials into tracked templates.

## Verification ledger

| Check | Result |
|---|---|
| Full `php artisan test` after P1 and stale-test fixes | 159 passed, 1,509 assertions |
| Focused property/seeder/homepage tests | 27 passed, 192 assertions |
| Focused map tests after map query/rating changes | 2 passed, 8 assertions |
| Email readiness tests after template sanitization | 5 passed, 25 assertions |
| `npm run build` after popup enhancement | Passed |
| `composer validate --no-check-publish --no-interaction` | Valid |
| Local browser homepage | Carousel advanced; map initialized; 8 tiles loaded; 0 markers; no console errors; stylesheet applied |
| Migrations / live data modifications | Not run |
