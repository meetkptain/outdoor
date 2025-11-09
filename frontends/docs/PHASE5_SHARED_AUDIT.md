# Phase 5 — Shared Library Audit

This note captures the current state of cross-app utilities before starting consolidation.  
Scope covers copywriting, i18n readiness, and workflow helpers used by `surfing-app` and `paragliding-app`.

## 1. Copywriting Inventory

### Booking Flows
- `frontends/surfing-app/src/routes/BookingPage.tsx`
- `frontends/paragliding-app/src/routes/BookingPage.tsx`

Shared phrasing:
- Step labels (`Étape 1 / Étape 2 / Étape 3 / Terminé`)
- Primary CTA copy (`Commencer la réservation`, `Continuer`, `Confirmer la réservation`)
- Success messaging pattern (`Merci, ta demande est enregistrée !`, `Référence de réservation : …`)
- Form labels for customer identity (`Prénom`, `Nom`, `Email`, `Téléphone`)

Deviations per niche:
- Surf keeps swimming level, participants grid, equipment rental.
- Paragliding swaps in flight-specific copy (weight, shuttle, photo pack) but still embeds large static strings.

Opportunities:
- Extract common CTAs and form labels into a shared dictionary.
- Parameterise per-niche variants (e.g. success headings, intro paragraphs) via a copy config.
- Consolidate `<Card heading/subheading>` text blocks (e.g. “Ce qui est inclus …” sections) to avoid drift.

### Session History
- Surfing app lacks an equivalent page; `SessionHistoryPage` now exists only in `paragliding-app`.
- Upsell descriptions and alert messages are inline. We should model them as copy entries so admin portal (or future frontends) can reuse wording.

## 2. Internationalisation Readiness

Current state:
- All user-facing strings are hard-coded in French.
- No shared formatter for dates, times, or enum labels (window values, statuses, etc. handled ad hoc).

Recommendations:
- Introduce `frontends/shared/src/i18n` with:
  - A minimal `t(key, params)` helper using JSON dictionaries (can default to French).
  - Enumerations for session status labels (`scheduled`, `upsell_pending`, …).
  - Formatting helpers for dates & currency (currently duplicated between apps).
- Align metadata-driven labels (`flight_windows`, `session_strategy`) through lookup tables instead of `switch` statements per app.

## 3. Workflow / Stage Helpers

Observed metadata usage:
- Both apps rely on activity metadata (`session_strategy`, `flight_windows`, etc.) but interpret them locally.
- Stage indicators (`Étape 1/2/3`) are hard-coded arrays inside each booking page.
- Shuttle availability & upsell flows expect consistent payload shapes yet no shared types exist.

Suggested consolidations:
- Create `frontends/shared/src/workflow/bookingStage.ts` exporting a shared step sequence builder.
- Add shared TypeScript interfaces for:
  - Reservation payload metadata (`flight_date`, `needs_shuttle`, `activity_source`, …).
  - Session history records / upsell offer structures.
- Move shuttle and upsell API clients under `shared/api` with explicit response typing so both apps consume the same contract.

## 4. Next Actions

1. Draft a `copywriting.ts` module exposing dictionaries for:
   - Generic CTA/label strings.
   - Niche-specific sections (intro heading, summary cards, success paragraphs).
2. Introduce `shared/i18n` scaffold and start replacing repeated `format` logic (date, currency, status).
3. Extract booking step definitions & metadata interfaces into `shared/workflow`.
4. Update existing pages to consume the new shared helpers, accompanied by Vitest snapshots to ensure copy parity.

This groundwork positions us to complete Phase 5 tasks (cross-tenant QA, accessibility, performance) on a consistent foundation.


