# Homepage hero carousel — local review

## Investigation and root cause

The supplied `/property-images/1` URL returned HTTP 200, `image/png`, and a decodable 960×600 image at the start of this task. It is the explicitly labeled synthetic demonstration picture, not a real owner's photograph. The earlier local fixture used a 1×1 PNG, which explains the blank appearance in earlier previews. No current 404 at this particular URL was reproduced, and no existing image files or database records were overwritten.

The underlying reliability gap was that the homepage treated the existence of an image record as proof that its file was usable. Missing files, malformed paths and non-image content could therefore become broken hero URLs, and no next-candidate/category fallback existed. In addition, later fixed-height hero CSS overrode the carousel's image-fill rule; image labels and the pause control competed for the same top-right position. Pagination, swipe and arrow-key controls were missing, while focusing the carousel permanently stopped rotation instead of pausing only while focus remained inside.

## Changes

- `HomeController.php`: eager-load up to five genuinely published, available spaces from publicly listed properties. Try ordered space uploads, then property-level images on the configured private disk. Validate candidates before selecting them. Keep real property names, locations, room names, prices and detail anchors. Include eligible listings without photos using clearly labeled illustrations; show the separate promotional hero when there are no eligible spaces.
- `PropertyImageService.php`: resolve only readable, regular image files inside the configured media root, reject linked path segments and malformed/out-of-root paths, and verify supported image metadata/MIME. No public-storage symlink or copying private media into `public/` is needed.
- `PropertyImageController.php`: preserve existing public/owner/admin access checks; return supported image bytes with their detected MIME, `nosniff` and the existing private/no-store policy. Missing/invalid media returns 404 rather than being served as HTML under a successful image response.
- `home.blade.php`: render actual database content, category-appropriate responsive illustrations, pagination controls and accessible slide labels. Original uploads remain unchanged; no fabricated responsive upload variants are advertised.
- `resources/js/features/spacematch.js`: previous/next, six-second rotation, indicators, keyboard arrows/Home/End, horizontal touch swipe, immediate post-swipe click suppression, temporary hover/focus pause, explicit pause/resume, reduced-motion preference changes, hidden/inactive accessibility states and client decode-error illustration recovery.
- `resources/css/theme/final-product.css`: consistent rounded frame, responsive aspect ratios, `object-fit: cover`, caption gradient, readable overlays, separated controls, clear indicators, focus rings and reduced-motion-safe fade transitions.
- Tests: expanded `HomepageExperienceTest.php`; added `scripts/hero-carousel-browser.mjs`. Updated the existing workflow browser image count to exclude the new per-slide fallback containers; that older full journey was not rerun during this focused carousel task.

## Images

All 17 source-pack illustrations were already integrated under `public/assets/images/spacematch/`; they were preserved and reused. Browser requests verified HTTP 200 and image MIME responses for both full-size JPEG and WebP variants of every illustration. Private rooms use the larger bedroom illustration; other known categories use their corresponding category images, with a private-room illustration for unmatched types. Illustrations are labeled **Illustrative image** and never assigned to property photo records. The no-listing promotional hero uses the existing clean skyline derivative.

## Validation

- Frontend build: passed with a complete Vite manifest and compiled CSS/JavaScript/font.
- Full Laravel suite: **158 passed, 1,504 assertions** (27.70 seconds).
- Focused image/homepage regressions passed, including valid MIME, missing/invalid content, authorization, image candidate order, genuine space-upload priority and labeled fallback behavior.
- Carousel browser suite: **10 checks passed**, with no console errors, page errors or failed HTTP responses. It tests all 17 image assets, real image endpoints/data, automatic rotation, previous/next, indicators, keyboard states, hover/focus recovery, manual pause, emulated touch swipe, reduced motion, decode-failure recovery and actual property navigation.
- Captured and visually inspected carousel screenshots at 375, 768 and 1366 pixels, plus the illustration fallback. Images fill their frames without document overflow. Screenshots/results remain local under ignored `docs/final-product-browser/hero/`.
- `git diff --check`: passed; no unresolved index conflicts.

## Preview and limitations

**http://127.0.0.1:8014** remains running. Refresh the homepage to load the rebuilt assets. Its existing synthetic listing picture is deliberately still a demonstration image; genuine owner uploads will display first when available and valid. No production photos or listings were invented. Fallback artwork is limited by the supplied source resolution. Browser testing uses Edge/Chromium with emulated touch; other engines and physical devices were not tested. A file that disappears or fails to decode after rendering is replaced visually; its underlying image URL correctly remains an error response rather than concealing the storage problem.

Changes remain local and uncommitted for review. No push, pull, reset, merge, production access, deployment, migration or database change was performed. Existing local work and the source image pack were preserved. Production status: **NOT DEPLOYED**.
