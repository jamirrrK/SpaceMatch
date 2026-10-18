# SpaceMatch Test Report

## Current verification
All commands below were executed locally on branch `feature/student-experience-platform-upgrade`. No production environment was contacted. The browser workflow used an isolated SQLite database and synthetic accounts/OTP sink.

| Check | Result |
|---|---|
| `php artisan test` | **152 passed; 1,204 assertions** |
| Deployment hardening: `python -m unittest discover -s scripts/deploy -p 'test_*.py' -v` | **54 passed; 2 skipped** |
| Deployment-test skips | Expected Windows limitations: native POSIX `flock`; OS symlink creation. Archive symlink rejection is still covered. |
| `npm.cmd run build` | Passed; Vite production assets built and frontend sync completed. |
| `composer validate --no-check-publish` | Passed. |
| `php artisan route:list` | Passed; **122 routes** registered. |
| Isolated Playwright workflow | **16 scenarios passed; 0 failed.** |
| Browser diagnostics | 0 page errors, 0 console errors, 0 failed responses; no horizontal overflow at 375/768/1366/1920 px. |
| `git diff --check` | Passed; Git emitted only expected CRLF-to-LF normalization warnings. |

## Browser scenarios
1. Owner updates real student-listing metadata.
2. Student registration and single-use OTP verification through isolated mail sink.
3. Preferences affect search filters.
4. Save and compare database-backed listings.
5. Student inquiry reaches owner and AJAX reply remains in the thread.
6. Viewing request and owner scheduling.
7. Rental request additional-information and rejection branches.
8. Accepted terms approval creates booking and unpaid schedule.
9. Move-in coordination and supported active-rental transition.
10. Owner-recorded partial payment updates the ledger; renter proof submission and owner verification.
11. Renter maintenance submission and owner resolution.
12. Date-eligible rental completion and student review.
13. Admin review moderation retains audit history.
14. Master Administrator access change through the restricted workspace.
15. Published-listing carousel controls under reduced-motion preference.
16. Mobile/tablet/desktop/wide layout and keyboard navigation.

## Defects found and fixed during browser validation
- The browser helper waited for navigation on the inquiry reply form, while the intended implementation submits via AJAX. The helper now asserts the success status, rendered message content, and cleared input.
- Carousel controls overlapped: the pause button intercepted the next-slide control. The controls wrapper now spans the carousel while keeping navigation centered and pause at the top corner.
- The payment verification UI displays `Proof Verified`; the browser assertion now matches the actual status label while retaining the amount assertion.

## Safety and limitations
- The new `payment_submissions` migration was exercised only in isolated SQLite test/browser databases. It was not applied to the existing local MySQL database or production.
- No destructive database command, commit, push, merge, production deployment, or production configuration change was performed.
- Browser coverage does not verify real OTP delivery, MySQL behavior, production PHP/extensions, or cPanel hosting.
- Deployment tests skipped only the two OS-specific cases listed above; they did not indicate application test failures.
