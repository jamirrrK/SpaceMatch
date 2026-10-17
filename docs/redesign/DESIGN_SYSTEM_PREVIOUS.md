# SpaceMatch Design System

**Status:** Active Blade/Bootstrap theme in `public/assets/css/spacematch-stage2.css`, layered after the existing `spacematch.css` theme.

## Design Intent

SpaceMatch is a Philippine rental marketplace with a shared professional workspace for renters, property owners, and administrators. Use clear listing information and calm operational UI; do not imply that listings are guaranteed or individually verified.

## Tokens

### Colors

| Token | Value | Use |
|---|---|---|
| `--sm-ink` | `#122644` | Brand/navy, headings, footer |
| `--sm-blue` | `#245ce6` | Primary actions, links, active navigation |
| `--sm-blue-deep` | `#1947bf` | Hover/pressed primary actions |
| `--sm-teal` | `#39786f` | Supporting accents and discovery labels |
| `--sm-teal-soft` | `#eaf2f0` | Quiet accent surfaces |
| `--sm-canvas` | `#f5f7f7` | Application background |
| `--sm-surface` | `#ffffff` | Forms, panels, cards |
| `--sm-surface-soft` | `#eef2f4` | Secondary surfaces |
| `--sm-text` | `#22354c` | Main body text |
| `--sm-muted` | `#67788a` | Secondary text, helper copy |
| `--sm-border` | `#dce4e8` | Borders/dividers |
| `--sm-success` | `#247257` | Positive status |
| `--sm-warning` | `#93631a` | Warning status |
| `--sm-danger` | `#ad4545` | Error/destructive status |

Primary blue is reserved for the main action and navigation state; teal is a supporting accent. Status colors use low-contrast backgrounds with readable foregrounds.

### Typography

- Body: `Inter`, `Aptos`, `Segoe UI`, sans-serif fallbacks.
- Display: `Iowan Old Style`, `Palatino Linotype`, `Georgia`, serif fallbacks.
- Body copy: approximately `.9rem`, line height 1.55-1.75.
- Page headings: 2rem desktop, 1.6-1.75rem mobile.
- Section headings: 1.45-1.65rem.
- Labels/captions: `.68-.82rem`, semibold.
- No network font dependency is required; use local font fallbacks.

### Shape, Surface, and Spacing

- Common radius: `--sm-radius: 8px`; smaller controls use 5-7px.
- Common border: `1px solid var(--sm-border)`.
- Cards avoid nested decoration and use no heavy shadow; elevated shadow is reserved for search/dialog surfaces.
- Page content max width: 1440px through `.site-width`.
- Desktop application shell: 218px sidebar, 32px gap, fluid content.
- Mobile shell: single column, 14-18px outer padding.
- Use Bootstrap spacing/grid utilities for local rhythm; keep sections full-width with constrained inner containers.

## Breakpoints

Responsive rules align with Bootstrap and the Stage 2 stylesheet:

- `max-width: 1199.98px`: collapsed primary navigation (`navbar-expand-xl`).
- `max-width: 991.98px`: dashboard sidebar becomes a collapsible top section; property detail summary is no longer sticky.
- `max-width: 767.98px`: compact page spacing/headings, single-column hero search, two-column stat cards.
- `max-width: 430px`: compact hero heading, single-column detail metadata, full-width closing CTA.
- `max-width: 380px`: tutorial actions stack to fit narrow devices.

## Components and Patterns

- Buttons: `.btn-primary-custom` for primary action; `.btn-outline-dark` for secondary; maintain a 42px minimum target (36px small actions).
- Forms: `.form-control`/`.form-select` with explicit labels, 44px minimum input height, inline validation, and visible focus rings.
- Listing cards: `<x-space-card>` and `<x-property-card>` use real model values, 4:3 media, monthly PHP pricing, and existing status/availability.
- Status/stats: `<x-status-badge>` and `<x-stat-card>` use database-driven values.
- Data views: `.table-wrap` owns horizontal table scrolling; table action headers use `aria-label` instead of absolutely positioned hidden text.
- Empty/error states: `<x-empty-state>` and the shared layout's role/status alerts. Empty state copy must not fabricate properties or statistics.
- Gallery: `<x-image-gallery>` preserves private-image URL policy and keyboard-operable thumbnail buttons.
- Confirmation: native `<dialog>` via `<x-confirm-dialog>`; keep confirmation text specific.
- Navigation: one shared navbar, role-specific sidebar, Bootstrap collapse/dropdown, route-aware active states, and a pending-account verification entry.
- OTP: numeric one-time-code input, masked destination, countdown/resend feedback, and fail-closed channel states.
- FAQ/tutorial: native `<details>` and `<dialog>` controls; no dead links or fictitious MP4.

## Motion and Accessibility

- Focus: `:focus-visible` outline with offset; never remove keyboard focus indication.
- Reduced motion: respect `prefers-reduced-motion: reduce` and avoid essential information conveyed only through animation.
- Use semantic headings, fieldsets/legends for grouped controls, explicit labels, `aria-live` for feedback, and native keyboard behavior.
- Use `aria-label` on otherwise unlabeled table action columns; do not use screen-reader-only text that expands the page canvas.
- Prefer real buttons for actions and anchors for navigation.

## Data and Feature Honesty

- Homepage/card data must come from published, available listings. Show the real empty state when none exist.
- Do not claim listings are guaranteed or individually verified.
- Do not surface favorites, inquiries, reservations, messaging, notifications, or admin user management as active workflows until implemented.
- OTP registration remains disabled unless an explicitly enabled, non-log email transport or an authorized SMS adapter is configured.