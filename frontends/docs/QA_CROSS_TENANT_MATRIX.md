# Phase 5 – QA Cross-Tenant Matrix & Smoke Tests

This document outlines the scenarios, environments, and automation hooks required to verify branding and UX consistency across tenants (Paragliding, Surfing, Corporate demo).

## 1. Tenant Profiles

| Tenant            | Activity Types        | Branding Highlights                              | Seed / Auth Notes                    |
|------------------|-----------------------|--------------------------------------------------|-------------------------------------|
| `paragliding`    | `paragliding`         | Deep blue gradient, shuttle messaging            | Seeds via `MultiNicheDemoSeeder`     |
| `surfing`        | `surfing`             | Ocean palette, swimming levels, equipment rental | Same seeder, `activity_type=surfing` |
| `corporate-demo` | `paragliding+surfing` | Neutral palette, combined activities             | Use `organization_id=demo`           |

## 2. Manual QA Checklist

For each tenant/app combination:

1. **Branding Load**
   - Colors applied (primary/secondary chips, CTA buttons).
   - Logo / hero text matches tenant.
   - Step indicator (Étape 1/2/3) rendered with correct palette.

2. **Booking Flow Smoke**
   - Fill minimum required fields; ensure CTA enables/disables correctly.
   - Submit and verify payload metadata (`activity_source`, tenant-specific fields).
   - Success screen copy includes tenant reference.

3. **Session History (Paragliding)**
   - Upcoming/completed/cancelled sections render.
   - Upsell actions update status & show feedback alert.

4. **Admin Portal Snapshot**
   - Dashboard loads with tenant stats.
   - Reservation filters show tenant-specific activities.

Log findings in `docs/QA_SESSION_LOG.md` (create per run).

## 3. Automated Smoke Tests (Playwright)

### Setup
- Extend `frontends/surfing-app/tests/e2e/booking.spec.ts` to accept `tenant`, `orgId`, and verify palette tokens from CSS variables.
- Create new Playwright suite in each app:
  - `frontends/surfing-app/tests/e2e/branding.spec.ts`
  - `frontends/paragliding-app/tests/e2e/branding.spec.ts`

### Test Scenarios
1. **Branding Snapshot**
   - Load `/` with provided tenant (`?organization_id=` override if needed).
   - Assert CSS custom properties match expected HEX values.
   - Capture screenshot for diffing.

2. **Booking Smoke**
   - Complete minimal booking, assert success message includes tenant-specific wording.

3. **History Smoke (Paragliding)**
   - Log in with seeded reservation.
   - Verify upcoming card presence & CTA labels.

### CI Hook
- Add workflow job `branding-smoke`:
  - Matrix over `app ∈ {surfing, paragliding}` × `tenant ∈ {paragliding, surfing, corporate-demo}`.
  - Run `pnpm --filter <app> test:e2e -- --grep @branding`.
  - Upload screenshots & HTML reports as artifacts.

## 4. Tooling & Utilities

- **Env Overrides:** expose `VITE_DEFAULT_ORGANIZATION_ID` per app; allow CLI override (`pnpm dev -- --tenant=...`).
- **Seed Script:** ensure `pnpm db:seed demo-multitenant` populates both activities per tenant.
- **Snapshot Baseline:** store approved screenshot hash in repo (`tests/e2e/__snapshots__`).

## 5. Next Steps

1. Implement Playwright smoke suites with tagging strategy (`@branding`, `@booking`).
2. Add GitHub Actions workflow `ci-branding-smoke.yml`.
3. Document manual QA protocol & attach screenshots for beta release.


