# Local review walkthrough

Start from the repository root with `npm.cmd run build`, then `node scripts/local-product-preview.mjs`. The launcher chooses port 8014 or the next available port and prints the exact loopback URL. Leave it running; stopping it stops the demo. Each launch creates a new private SQLite fixture database, so previous development data is untouched. Nothing connects to production, EmailJS or an external mail service. The demo is not exposed to the network.

## Synthetic accounts

Use `Browser-Student-2026!` for the following demonstration accounts only:

| Role | Email |
| --- | --- |
| Student renter | student@student-browser.example.test |
| Regular renter | other@student-browser.example.test |
| Owner | owner@student-browser.example.test |
| Administrator | admin@student-browser.example.test |
| Master Administrator | master-admin@student-browser.example.test |

These credentials are fixture data, never real user credentials. The two Cebu City rooms, school, prices, images and people are synthetic. The image is explicitly marked as a demonstration. No production listings are copied into this demo.

## Review sequence

1. Open the homepage as a guest. Try the search, five rental-type links, city links, student-friendly discovery, FAQ, tutorial and mobile menu. City results can be empty: this is accurate for the small fixture dataset. Marketing illustrations are labeled and do not replace listing photos.
2. Sign in as the student. Open preferences; choose Cebu City, private rooms, a budget including PHP 5,000 and the synthetic Browser Test University Main campus. Review the owner-reported distance disclaimer, compare/save rooms and open a property.
3. Send an inquiry and propose a viewing. In another browser profile, sign in as the owner. Reply in the same thread and manage the viewing. Confirm notifications on both sides.
4. As the student, submit a rental request after reviewing and accepting its exact terms. As the owner, request more information or approve the request. Review the booking and unpaid ledger; approval does not invent a completed payment.
5. Submit a clearly synthetic receipt/proof file as the student. Review it as the owner, record the manual decision, inspect the remaining balance, transaction history and PDF receipt. This is manual verification, not a payment gateway.
6. Try maintenance with a synthetic attachment, owner status updates and participant history. Review lifecycle actions and completed-rental review eligibility; date guards remain active. The automated isolated workflow suite advances its own clock to test completion without changing the real clock.
7. Sign in as the regular renter. Browse, filter, save, message and request a room through the same protected workflows; student-only preferences must not be silently imposed.
8. As the owner, inspect dashboard data, edit property/room details, upload a test photo, manage publishing and review notifications, payments, maintenance and reports.
9. As the administrator, inspect actual fixture metrics, property/user oversight, bookings, payment reports, moderation and audit records. Pending-application metrics include requests awaiting more information. As Master Administrator, review the separate restricted access-management workspace. Do not use or create real administrative credentials.
10. Review mobile, tablet and desktop layouts and a nonexistent URL for the styled 404. Use separate browser profiles for simultaneous roles, or sign out between accounts.

Public registration's existing OTP flow is exercised by the automated browser suite through the private array-mail sink. The demo accounts are already verified so a reviewer does not need real mail credentials. Real OTP behavior and delivery settings are unchanged.

## Assets and production gate

`scripts/prepare-marketing-images.py` requires Pillow only when regenerating the committed optimized illustrations from the original supplied folder. Responsive JPEG/WebP variants never upscale the originals. The hero derivative excludes fake controls/verification claims; small city thumbnails preserve their source limits.

Normal pages and styled errors use the compiled Vite entry. Legacy base/theme CSS and Bootstrap remain only as an emergency error-page fallback when a build manifest is unavailable. Obsolete public feature scripts and intermediate stylesheets were moved to Vite sources. CI builds before rendering Laravel tests.

Production status: **NOT DEPLOYED**. This walkthrough is a development-stage review, not final acceptance. Additional requirements and explicit approval of the local browser demonstration are outstanding. See `FINAL-PRODUCT-LOCAL-AUDIT.md` and existing deployment-hardening documentation for the separate compatibility, dependencies, backup, migration and recovery gates. No npm or Node runtime is needed on hosting once the release includes its compiled assets.
