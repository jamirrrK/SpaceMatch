# SpaceMatch design system

Updated 17 October 2026. The authoritative theme is `public/assets/css/spacematch-redesign.css`. Bootstrap and the previous local styles remain compatibility layers for existing templates. No framework, remote asset service, or font dependency was added.

## Design direction

A Philippine rental marketplace and shared role workspace. Navy anchors navigation and account storytelling; blue identifies actions and active destinations; teal supports quiet informational labels. Use real records and photos, with the existing authored illustration when no photo is supplied. No verification, safety, growth or inventory claims without supporting data.

## Palette

| Token | Value | Purpose |
| --- | --- | --- |
| --sm-ink | #14243A | Brand, sidebar, display headings |
| --sm-blue | #2563EB | Primary action, active navigation, links |
| --sm-blue-deep | #1D4ED8 | Primary hover |
| --sm-teal | #0D766E | Supporting accents, readable on pale surfaces |
| --sm-canvas | #F8FAFC | Page background |
| --sm-surface | #FFFFFF | Cards and forms |
| --sm-text | #0F172A | Body text |
| --sm-muted | #596A80 | Secondary text |
| --sm-border | #E2E8F0 | Dividers and panel borders |

Statuses pair readable foreground/background colors: green #166534/#ECFDF5, amber #9A3412/#FFF7ED, red #B91C1C/#FEF2F2, slate #475569/#F1F5F9. Always include the status text; color alone conveys no state.

## Typography

One sans-serif stack: Inter, Aptos, Segoe UI, sans-serif. Fonts resolve locally; Inter is a preference, not a bundled font claim. Body 15px with 1.55 line height; longer description copy 1.8. Hero 2.15?4.1rem responsive, page titles 1.7?2.4rem, section titles 1.55?2.15rem, panel titles 1rem. Display weights 700?780, body 400?500, controls 600?650. Negative heading tracking is restrained. Labels are approximately .8rem; table headings .62rem with consistent uppercase tracking. Long text wraps instead of forcing page overflow.

## Space, shape and layers

Spacing tokens: 4, 8, 12, 16, 24, 32 and 48px; sections 40px mobile and 68px desktop. Small radius 8px, controls 9?10px, cards 14?16px, large composition 24px. Default shadow 0 8px 28px rgb(20 36 58 / 6%). Site max width 1400px, auth 1180px. Use Bootstrap's existing stacking contract: sticky navigation 1020, dropdown 1000; native dialogs enter the browser top layer. Focus is always visible and not hidden by sticky content.

## Components

- `icon`: reusable decorative inline SVG, hidden from assistive technology, no external icon package.
- `auth-story`: shared navy introduction, brand and authored rental illustration. Hidden below 768px to focus on the form.
- `workspace-breadcrumb`: route-title context with a link to the user's authorized dashboard.
- `record-heading`: activity/profile page heading and descriptive copy.
- `rental-price`: minimum monthly rent derived from the property's actual published spaces.
- `space-card`/`property-card`: 4:3 media, linked image and title, availability, monthly rent, truthful metadata and functional actions.
- `filter-form`: one GET form for all existing backend filters. Mobile disclosure, desktop persistent panel; URL state and individual removal chips are retained.
- `form-field`: old input, required labels, helpers and inline validation. Property/space forms use automatically numbered sections without a new multi-step contract.
- `stat-card`: restrained blue indicator and actual count; admin secondary metrics are compact definition rows rather than ten identical cards.
- `status-badge`, `pagination`, `empty-state`, `image-gallery`: one shared presentation across roles.
- `confirm-dialog`, `tutorial-modal`: native dialogs with Escape support, visible controls, role-specific content, honest text-tour fallback and focus restoration.
- `errors/layout`: consistent 403, 404, 419, 429, 500 and 503 recovery screens, preserving HTTP status.

## Responsive behavior

| Range | Behavior |
| --- | --- |
| Below 576px | Smaller heading/card spacing; numbered form sections stack; two-column search; wrapped footer |
| Below 768px | Focused authentication form; no decorative auth sidebar |
| Below 992px | Workspace menu collapses; discovery filter disclosure; property summary returns to normal flow |
| Below 1200px | Main navigation collapses with an accessible toggle |
| Desktop | Sticky navy sidebar and property summary; persistent filters; split authentication |

QA widths: 320, 360, 390, 430, 768, 1024, 1280, 1440 and 1920px. Tables retain horizontal scrolling inside labeled, keyboard-focusable regions rather than widening the document.

## Interaction and accessibility rules

Primary controls are at least 44px high; compact controls 40px. Cards lift 3px on hover with 160ms transitions. Respect reduced motion for transforms, transitions and smooth scrolling. Maintain :focus-visible outlines, real labels, validation feedback and semantic headings. SVGs are decorative; functional links and buttons supply names. Native confirmation runs before submission feedback; validated submissions become aria-busy, show progress text and reject accidental repeats. Browser back/forward restores submit controls. Current navigation receives aria-current. Tutorial close returns focus to its replay button or main content without scrolling.

## Content boundaries

Currency is Philippine pesos/PHP per month. No favorites, messaging, online payment checkout, notification center, reservation control, or narrated video is advertised as active. Registration remains unavailable when OTP delivery is unconfigured. Approved legal policy text and configured public contact details were not supplied; do not invent those documents or link to nonexistent pages.
