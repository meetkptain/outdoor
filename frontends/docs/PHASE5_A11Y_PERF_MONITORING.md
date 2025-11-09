# Phase 5 – Accessibility, Performance & Monitoring Baselines

This playbook defines the technical steps to integrate accessibility tooling, performance audits, and release monitoring for all frontend apps (shared UI, surfing-app, paragliding-app, admin-portal).

## 1. Accessibility Baseline

### 1.1 Dev-time Checks (axe-core)
- Install `@axe-core/react` in `frontends/shared` (peer dependency shared across apps).
- Create `frontends/shared/src/testing/axeSetup.ts`:
  ```ts
  import React from 'react'
  import ReactDOM from 'react-dom'
  import axe from '@axe-core/react'

  if (process.env.NODE_ENV !== 'production') {
    axe(React, ReactDOM, 1000)
  }
  ```
- Import the setup file in each app’s `main.tsx` when `import.meta.env.DEV`.
- Gate the runtime with `VITE_ENABLE_AXE=true` so local dev can opt-in without bundling the dependency in test/CI environments.
- Add documentation snippet for disabling warnings in specific tests (use `data-testid="axe-ignore"` or jest axe overrides).

### 1.2 Automated A11y Tests
- Extend Vitest suites with `axe-core` scan for critical screens:
  - Booking form (surf & paragliding).
  - Session history.
  - Admin dashboard.
- Example snippet:
  ```ts
  const results = await axe(container)
  expect(results.violations).toHaveLength(0)
  ```
- For Playwright E2E runs, leverage [`axe-playwright`](https://github.com/abhinaba-ghosh/axe-playwright) to spot regressions in CI.

### 1.3 Manual Checklist
- Keyboard navigation (focus outlines, skip links).
- Screen reader labels for CTA buttons (verify `aria-live` for loading spinners).
- Contrast ratios (use Chrome DevTools or Lighthouse a11y score).
- Store findings in `docs/QA_SESSION_LOG.md`.

## 2. Performance Baseline (Lighthouse)

### 2.1 Scripted Audits
- Add `pnpm` scripts per app:
  ```json
  "audit:lighthouse": "node scripts/run-lighthouse.mjs"
  ```
- Create `scripts/run-lighthouse.mjs`:
  - Uses `lighthouse` npm package.
  - Runs against local build or preview URL (`http://localhost:4173`).
  - Outputs HTML & JSON reports to `reports/lighthouse/<app>/<timestamp>`.

### 2.2 Metrics & Thresholds
- Target PWA/mobile score ≥ 85, desktop ≥ 90.
- Track Core Web Vitals: FCP, LCP, CLS, TBT.
- Add Citations in README for interpreting regressions.

### 2.3 CI Integration
- GitHub Actions job `lighthouse-audit` triggered on PRs touching frontend.
- Use `treosh/lighthouse-ci-action` with `--budget-path=./lighthouse-budget.json` to fail builds if thresholds regress.
- Archive reports as build artifacts.

## 3. Release Monitoring Checklist

### 3.1 Error & Session Tracking
- **Sentry**
  - Install `@sentry/react` and `@sentry/tracing`.
  - Create shared `initMonitoring.ts` in `frontends/shared/src/monitoring/`.
  - Pass tenant/org context via `beforeSend(event)` using `tenantStore`.
- **LogRocket** (or PostHog alternative)
  - Set project key per environment.
  - Tag sessions with `tenantStore.organizationId` and `activity_type`.

### 3.2 Deployment Checklist
- Ensure DSN/api keys present in `.env.front` templates.
- Verify CI injects environment variables (production vs staging).
- Add monitoring toggle (feature flag) to disable on demand.

### 3.3 Runbook Updates
- Update `docs/ADMIN_PORTAL_TESTS.md` to include verification steps for Sentry breadcrumbs.
- Document alert routing (Slack/Email) – link to ops sheet.
- Add incident response section to `docs/RELEASE_PLAYBOOK.md` (create if absent).

## 4. Action Items

| Task | Owner | Status |
|------|-------|--------|
| Add axe-core setup & Vitest checks | Frontend team | TODO |
| Create Lighthouse script & CI job | Frontend team | TODO |
| Implement shared monitoring init (Sentry/LogRocket) | Frontend team | TODO |
| Update documentation (playbook, admin tests) | Tech writer | TODO |

This document will evolve alongside Phase 5 executions; keep the action table current.


