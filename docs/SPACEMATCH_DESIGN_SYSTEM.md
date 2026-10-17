# SpaceMatch — Room to begin

17 October 2026. Warm, editorial rental discovery paired with a quiet property workspace. The authoritative implementation is `public/assets/css/spacematch-redesign.css`; Bootstrap and retained local styles provide compatibility. This theme replaces the previous navy/royal-blue identity across all routed surfaces.

## Color and meaning

| Token | Value | Use |
| --- | --- | --- |
| `--sm-ink` | #172033 | Headings, body, dark explainer |
| `--sm-ocean` | #087580 | Actions, links, focus |
| `--sm-ocean-deep` | #065C65 | Action hover and pressed states |
| `--sm-mint` | #DDF5EF | Selected navigation, supporting surfaces |
| `--sm-canvas` | #FAF9F6 | Warm page background |
| `--sm-surface` | #FFFFFF | Forms, cards, tables |
| `--sm-muted` | #586579 | Secondary text |
| `--sm-border` | #E2E5E1 | Dividers and input groups |

Ocean was darkened from the suggested #087F8C to support readable white button text. Rendered button focus/hover and key public copy receive contrast checks. Status colors use green #166534/#EDF8EF, amber #92400E/#FFF3DF, red #B91C1C/#FEF2F2 and slate #4D5D58/#F0F2EF. Always retain text labels. Legacy `--sm-blue`, `--blue`, `--sm-teal` aliases point to ocean for retained components; they do not introduce a second action color.

## Typography

One locally hosted variable **Manrope** font, 200–800, with Segoe UI/Arial fallbacks. `font-display:swap` keeps text available while loading; the main layout preloads the same font. The official font and [OFL license](../public/assets/fonts/Manrope-OFL.txt) are distributed locally. Source: [Google Fonts Manrope](https://github.com/google/fonts/tree/main/ofl/manrope). No font-service request occurs at runtime.

Body .9375rem / 1.65; supporting prose .875–1rem / 1.85; hero fluid 2.45–4.7rem; page heading 1.65–2.4rem; section heading 1.65–2.5rem; panel/card heading 1–1.125rem; controls .8125rem; captions .75rem; table headers .6875rem. Weights: 400–500 prose, 650 labels/navigation, 700 controls, 750 headings, 800 prices/brand. Negative tracking is limited to headings and numeric prices. Heading wrapping and fluid sizes protect narrow screens.

## Layout, space and elevation

Spacing tokens: 4, 8, 12, 16, 24, 32, 48px. Sections 40px mobile / 64px desktop; marketplace width 1400px; authentication 1180px; desktop workspace sidebar 220px with a 40px gutter. Controls have a 7px radius; table/card surfaces 8–12px; auth composition 14px. The asymmetric hero corner is the main expressive shape. Default surfaces use borders; shadow is reserved for the search panel, dropdowns and dialogs.

Bootstrap layers: header 1020, backdrop 1040, drawer 1045. Native confirmation/tutorial dialogs use the browser top layer. Anchored sections have a 110px scroll margin. Touch controls are normally 44–48px high; close buttons are 44px. Reduced motion removes animation and hover transforms.

## Component contracts

| Component | Design and retained behavior |
| --- | --- |
| Shared navigation | Warm public header, white workspace header, role-aware account dropdown and active destinations |
| Workspace sidebar | Quiet navigation groups, mint active item, real rental activity/account links |
| Mobile drawers | Named responsive offcanvas panels; no duplicated links/forms; Escape/backdrop/close dismissal, focus trap/return, scroll lock and desktop resize cleanup |
| Search/filter form | Existing GET parameters, visible selected filters, independent removal, reset and supported sort |
| Listing cards | 4:3 actual media, distinct space/property names, city/province, real capacity/amenities, prominent monthly pesos and working detail/edit action |
| Image gallery | Protected image endpoints, cover/order preserved, keyboard thumbnail controls and selected state |
| Property detail | Breadcrumb, large gallery, monthly starting rent, sticky desktop summary; normal mobile flow |
| Auth story | Mint architectural composition; hidden below 768px to focus on forms |
| Form fields | Visible labels/helpers/errors, old input, native validity, password visibility and appropriate autofill hints |
| OTP | Single six-digit paste-friendly input, masked destination, expiry/resend feedback; availability derives from the existing service |
| Statistics | Three primary owner measures with compact listing/activity summaries; real role-scoped values only |
| Tables | Quiet tinted header, consistent row spacing, labeled internal scroll region, preserved row actions |
| Empty states | Pale supporting surface, relevant action and explanation; no fake inventory/activity |
| Tutorial/confirmation | Coordinated palette, native dialogs, retained role chapters, navigation/skip/replay/opt-out and focus restoration |
| Errors | Branded native HTTP 403/404/419/429/500/503 recovery screens |

The existing `icon`, `empty-state`, `space-card`, `property-card`, `auth-story`, `form-field`, `stat-card`, `status-badge`, `record-heading`, `rental-price`, `workspace-breadcrumb`, `pagination`, `image-gallery` and dialog components remain shared. Use them before introducing a duplicate component.

## Responsive rules

Public navigation becomes a right drawer below 1200px; workspace and filter navigation become drawers below 992px. Search spans the hero width, becomes a three-field row plus submit below 992px, then a full-width location with type/budget and full-width submit below 768px. Categories use an internally scrollable, keyboard-reachable rail. Authentication becomes one column below 768px. Secondary owner activity becomes compact stacked definition rows. Wide records scroll inside their labeled table region.

Required measurement widths: 320, 360, **375**, 390, 430, 768, 1024, 1280, 1440 and 1920px. Do not solve overflow by clipping the document. Drawers are at most 380px and never wider than the viewport.

## Content, assets and security

Use recorded Philippine locations, pesos/month, actual availability and actual published listings. The locally authored architectural SVG is an illustration, never a listing photograph or verification claim. Owner uploads remain unchanged. No fake booking, messaging, legal or inquiry action is introduced. Keep registration/provider availability honest and preserve OTP, role and private-image gates. Test photographs/accounts belong exclusively to the isolated QA database.

## Validation limits

Target WCAG 2.2 AA through visible focus, semantic labels, text status, keyboard navigation, contrast checks and reduced motion. These checks are not a full screen-reader audit or certification. Performance decisions avoid new runtime libraries and remote font/image services; no Core Web Vitals or network-speed improvement is claimed without measurement. See [the complete transformation report](SPACEMATCH_COMPLETE_UI_TRANSFORMATION.md) for final evidence.
