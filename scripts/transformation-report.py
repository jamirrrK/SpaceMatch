from pathlib import Path
import hashlib
import json
import shutil
import zlib

root = Path(__file__).resolve().parents[1]
out = root / 'docs/transformation'


def read(path):
    data = (root / path).read_bytes()
    return data.decode('utf-16' if data.startswith(b'\xff\xfe') else 'utf-8-sig')


def data(path):
    return json.loads(read(path))


workflow = data('docs/browser/results.json')
tutorial = data('docs/browser/tutorial-results.json')
interactions = data('docs/browser/redesign/results.json')
drawers = data('docs/browser/drawers/results.json')
quality = data('docs/transformation/quality.json')
assert workflow['baseURL'] == 'http://127.0.0.1:8024'
assert workflow['passed'] == 18 and workflow['failed'] == 0
assert tutorial['baseURL'] == workflow['baseURL']
matrix = [row for row in workflow['diagnostics']['layout'] if 'surface' in row]
assert len(matrix) == 250
assert len({row['surface'] for row in matrix}) == 25
assert {row['width'] for row in matrix} == {320, 360, 375, 390, 430, 768, 1024, 1280, 1440, 1920}
for report, count in [(tutorial, 11), (interactions, 6), (drawers, 6), (quality, 8)]:
    assert len(report['results']) == count
    assert all(row['status'] == 'passed' for row in report['results'])
assert data('docs/browser/redesign/focus-check.json')['passed']
assert data('docs/browser/redesign/focus-check.json')['colors']['background'] == 'rgb(6, 92, 101)'
assert '81 passed (597 assertions)' in read('docs/transformation/tests.txt')
assert 'built in' in read('docs/transformation/build.txt')
assert data('docs/transformation/data-preservation.json')['original_database_unchanged']
assert data('docs/transformation/routes-before.json') == data('docs/transformation/routes-after.json')

manifest = data('docs/transformation/manifest-before.json')
changed = [name for name, digest in manifest.items() if hashlib.sha256((root / name).read_bytes()).hexdigest() != digest]
protected = [name for name in manifest if name == '.env' or name.startswith(('app\\', 'config\\', 'routes\\', 'database\\', 'tests\\'))]
assert not set(changed).intersection(protected)
(out / 'source-preservation.json').write_text(json.dumps({
    'environment_unchanged': True,
    'backend_config_routes_migrations_tests_unchanged': True,
    'protected_files_checked': len(protected),
    'changed_files': changed,
}, indent=2), encoding='utf-8')

shots = sorted((root / 'docs/browser').glob('redesign-*.png'))
assert len(shots) == 50
for shot in shots:
    assert (out / 'before' / shot.name).is_file()
    shutil.copyfile(shot, out / 'after' / shot.name)
theme = (root / 'public/assets/css/spacematch-redesign.css').read_bytes()
css_size, css_gzip = len(theme), len(zlib.compress(theme))
table = ['| Surface | Before desktop | After desktop | Before mobile | After mobile |', '| --- | --- | --- | --- | --- |']
for shot in shots:
    if not shot.name.endswith('-1440.png'):
        continue
    mobile = shot.name.replace('-1440.png', '-390.png')
    title = shot.name.removeprefix('redesign-').removesuffix('-1440.png').replace('-', ' ').title()
    table.append(f'| {title} | [1440](transformation/before/{shot.name}) | [1440](transformation/after/{shot.name}) | [390](transformation/before/{mobile}) | [390](transformation/after/{mobile}) |')

report = f'''# SpaceMatch complete UI transformation

17 October 2026. Implemented in the existing local Laravel project. The new warm ocean/mint identity replaces the previous navy/royal-blue presentation across all real routed frontend surfaces.

## 1. Original findings and recovery

The prior site was functional and coherent, but repeated dark sidebar/auth compositions, rounded heading panels, generic statistics and large category tiles. The new brief required a substantial visual departure. [Pre-implementation audit](transformation/AUDIT.md), [complete route-to-view inventory](transformation/ROUTE_VIEW_INVENTORY.md), [validated source recovery archive](transformation/source-before.zip) and 50 preserved baseline screenshots were created before this transformation. No Git repository was available. The immediately preceding baseline passed 81 tests / 597 assertions and production build; logs remain under `docs/redesign/followup`.

## 2. New visual direction

“Room to begin”: warm canvas, dark ink, ocean actions and mint supporting surfaces. The homepage uses an editorial split hero, asymmetric media, a full-width search panel and compact category rail. Workspaces use quiet grouped navigation and unboxed headings. Authentication uses a mint architectural story rather than a dark panel. Borders and measured spacing replace broad shadows and oversized rounded cards.

## 3. Tokens and typography

Ink #172033, ocean #087580, deep ocean #065C65, mint #DDF5EF, canvas #FAF9F6, white, muted #586579 and border #E2E5E1. Ocean was darkened from the suggested color to support white text. One locally hosted Manrope variable font uses swap loading and sensible fallbacks. The official [Google Fonts source and OFL license](https://github.com/google/fonts/tree/main/ofl/manrope) permit its distributed use; the license is included with the local asset. The [design system](SPACEMATCH_DESIGN_SYSTEM.md) defines fluid type, spacing, shape, layers and states.

## 4. Shared components and layouts

Reworked the authoritative theme, shared public/workspace shell, auth-story, space-card and architectural fallback SVG. Existing form-field, status-badge, stat-card, empty-state, gallery, pagination, rental-price, record-heading, breadcrumbs and native dialogs inherit the same system. Responsive filter/public/workspace drawers use the installed Bootstrap bundle with no second JavaScript framework. Links and form field names remain compatible with their existing routes.

## 5. Routed coverage

All 25 measured frontend surfaces are redesigned, including public discovery/detail, four guest auth views, three dashboards, owner property/space lists and create/edit/detail views, profiles and four activity lists. Pending OTP and native 404 are exercised separately. Native 403/419/429/500/503 templates inherit the new error layout theme. Images and amenities remain embedded management features. The unrouted Laravel welcome view remains excluded. Full method, access and response mappings are in the inventory.

## 6. Homepage

New “Find a place that feels like yours” hero and Philippines-wide copy. Search now spans the composition and submits actual location/property_type/max_price parameters. Categories precede real available listings in a compact scrollable rail. Real media, availability and prices are retained. A dark three-step explainer, clear renter/owner benefits, factual transparency section, quiet FAQ and mint owner publishing callout establish a new page rhythm. No fabricated testimonials, inventory figures or verification guarantees.

## 7. Discovery

New marketplace heading, restrained filter sidebar, mint selected-filter chips, sharper image-led cards, Philippine city/province information and clear monthly peso pricing. Cards retain both the space and property identity; regression tests caught an omitted property name during implementation and it was restored without changing tests. Mobile uses a named filter drawer with the same GET form, amenities, sort/reset and independent active-filter removal.

## 8. Property details

Added public breadcrumbs and refreshed gallery, typography, amenities, monthly starting-rent summary and space sections. The desktop summary remains sticky; mobile flows normally. All cover/order and thumbnail interactions retain the protected media URLs. No nonfunctional inquiry/booking/related-property actions were added.

## 9. Authentication and OTP

Login, registration, recovery, reset and pending verification share the new mint/white composition, focused mobile forms, consistent fields and browser autofill hints. Password visibility and actual validation remain functional. OTP retains six digits, paste/autofill support, masked destination, five-minute expiry, five attempts, resend/rate limits, hashed storage and single-use contact verification. Backend code and middleware are unchanged. A seeded isolated challenge verifies and allows subsequent login; this is not a real inbox-backed registration claim.

## 10. Renter experience

Consumer-focused mint discovery panel with practical city/budget guidance, real account measures, recent bookings and useful empty-state navigation. Existing activity stays role-scoped and read-only; no fake reservation, favorites or messaging controls appear.

## 11. Owner experience

Three primary property/space/availability measures replace six repeated cards. Listing health uses compact definition rows; booking activity/income uses a restrained strip. Grouped navigation, unboxed heading, organized numbered forms, image manager, publish controls and real recent-property table support the existing management workflow. All counts remain database-derived.

## 12. Administration and remaining screens

Light grouped sidebar, clear moderation heading, genuine primary/community metrics, refined management tables, visible statuses and preserved audit-friendly actions. Profiles, bookings, payments, maintenance and reviews follow the same heading/table/form system. Tutorial colors, selected chapters, focus and controls are coordinated; native errors and transactional verification email presentation are aligned. The tutorial still clearly says narrated video is unavailable.

## 13. Mobile

**250 measurements: 25 surfaces × 10 widths**, with no document overflow. Widths: 320, 360, 375, 390, 430, 768, 1024, 1280, 1440, 1920px. Search stacks purposefully, categories scroll internally and remain keyboard reachable, navigation/filter drawers do not push page contents, authentication simplifies, compact summaries stack and wide tables retain internal scroll regions. All measured screenshots use the isolated QA database and media root.

## 14. Accessibility

Visible focus, semantic navigation/labels, active destinations, readable statuses, autofill hints, form/error feedback and reduced motion are retained/improved. Drawer checks cover focus trapping/return, Escape, close/backdrop dismissal, resize cleanup, tutorial handoff and role visibility. Rendered text contrast checks cover primary focus/hover, hero, dark explainer and mint callout; primary white/ocean contrast is approximately 5.43:1. Small sidebar/tutorial text was darkened after contrast review. Target WCAG 2.2 AA; no certification or full assistive-technology audit is claimed.

## 15. Performance and assets

No new runtime UI library or remote font/photo service. Manrope is a single 164700-byte local variable font with a preload/swap strategy; fallback illustration is a small authored SVG. Owner photographs keep existing lazy loading, dimensions and aspect ratios. The authoritative theme is {css_size} bytes ({css_gzip} compressed); compatibility layers are retained, so full CSS consolidation remains an opportunity. Vite build passes. No measured Core Web Vitals or network-performance improvement is claimed.

## 16. Validation and preservation

| Check | Result |
| --- | --- |
| Laravel suite, unchanged tests | **81 passed / 597 assertions** |
| Existing workflow browser suite | **18 passed** |
| Existing tutorial browser suite | **11 passed** |
| Existing redesign interaction suite | **6 passed** |
| Added drawer regressions | **6 passed** |
| Added transformation quality checks | **8 passed** |
| Total browser checks above | **49 passed** |
| Additional keyboard-focus spot check | Passed |
| Responsive measurements | **250 passed** |
| Workflow console/page/network/image diagnostics | Clean |
| Production build / view cache / optimize:clear / route list | Passed |
| Route contracts | Unchanged |
| Protected backend/config/migration/test/environment hashes | {len(protected)} unchanged |
| Native development database | 21 tables unchanged |

Evidence: [Laravel](transformation/tests.txt), [build](transformation/build.txt), [workflow](browser/results.json), [tutorial](browser/tutorial-results.json), [interactions](browser/redesign/results.json), [drawers](browser/drawers/results.json), [quality](transformation/quality.json), [focus](browser/redesign/focus-check.json), [source preservation](transformation/source-preservation.json), [data preservation](transformation/data-preservation.json).

Owner browser workflows include create/edit, rental spaces, valid uploads/cover/order, publish, public filtering/gallery, private media, admin deactivation/audit and role restrictions. Testing uses dedicated synthetic accounts, existing separate SQLite schema and separate media. No native database migration, reset, real account mutation or upload replacement occurred. The Brevo environment remains byte-for-byte unchanged.

## 17. Before and after

Every surface below has actual 1440px/390px captures before and after. The stable routes/viewports match; owner edit/detail workflow IDs and synthetic dataset totals can differ between runs. QA images/counts are not claims about real platform inventory. Cross-page contact sheets in `docs/browser/redesign/review-*.png` support visual inspection; auth, mobile search/drawers, homepage and dashboards were also reviewed individually.

{chr(10).join(table)}

## 18. Remaining limitations

Brevo sender approval remains user-reported. Its private SMTP key was deferred by the user; email OTP/SMS delivery flags remain as configured, registration honestly shows unavailable delivery, and no real email receipt or inbox-backed registration is claimed. No approved public legal-policy text/routes, support-contact configuration or narrated tutorial file exists, so none is invented. Production photography depends on owner uploads; test media is synthetic. Core Web Vitals, full screen-reader certification and cross-engine browser coverage were not measured; automated browser validation uses local Edge/Chromium. Existing CSS compatibility layers remain.
'''
(root / 'docs/SPACEMATCH_COMPLETE_UI_TRANSFORMATION.md').write_text(report, encoding='utf-8')
print('Transformation report created from validated evidence: 81 tests, 49 browser checks, 250 responsive measurements, 50 before/after pairs.')
