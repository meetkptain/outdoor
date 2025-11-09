import { test, expect } from '@playwright/test'

const BRANDING_RESPONSE = {
  data: {
    primaryColor: '#1d4ed8',
    secondaryColor: '#0f172a',
  },
}

const ACTIVITY_RESPONSE = {
  id: 5,
  organization_id: 1,
  activity_type: 'paragliding',
  name: 'Baptême parapente branding',
  description: 'Smoke test description',
  duration_minutes: 25,
  pricing_config: {
    model: 'fixed',
    base_price: 160,
    deposit_amount: 60,
  },
  metadata: {
    flight_windows: ['morning', 'afternoon', 'sunset'],
    max_total_weight: 120,
  },
}

test('@branding paragliding tenant theme', async ({ page }) => {
  await page.route('**/api/v1/branding', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(BRANDING_RESPONSE),
    })
  })

  await page.route('**/api/v1/activities/5', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(ACTIVITY_RESPONSE),
    })
  })

  await page.goto('/')

  const paletteChip = page.locator('.paragliding-palette-chip').first()
  await expect(paletteChip).toBeVisible()
  const chipColor = await paletteChip.evaluate((el) =>
    window.getComputedStyle(el).backgroundColor,
  )
  expect(chipColor).toBe('rgb(29, 78, 216)')

  const cta = page.getByRole('button', { name: 'Préparer mon vol' })
  await expect(cta).toBeVisible()
  const buttonBg = await cta.evaluate((el) =>
    window.getComputedStyle(el).backgroundColor,
  )
  expect(buttonBg).toBe('rgb(29, 78, 216)')

  await expect(page.getByText('Étape 1')).toBeVisible()
})


