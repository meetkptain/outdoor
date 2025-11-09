import { test, expect } from '@playwright/test'

const BRANDING_RESPONSE = {
  data: {
    primaryColor: '#2563eb',
    secondaryColor: '#1f2937',
  },
}

const ACTIVITY_RESPONSE = {
  id: 2,
  organization_id: 1,
  activity_type: 'surfing',
  name: 'Cours de surf branding',
  description: 'Smoke test description',
  pricing_config: {
    model: 'tiered',
    tiers: [
      { up_to: 3, price: 60 },
      { up_to: 6, price: 55 },
    ],
    deposit_amount: 20,
  },
  constraints_config: {
    participants: { min: 2, max: 6 },
    enums: {
      swimming_level: ['beginner', 'intermediate', 'advanced'],
    },
  },
}

test('@branding surfing tenant theme', async ({ page }) => {
  await page.route('**/api/v1/branding', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(BRANDING_RESPONSE),
    })
  })

  await page.route('**/api/v1/activities/2', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(ACTIVITY_RESPONSE),
    })
  })

  await page.goto('/')

  const paletteChip = page.locator('.surf-palette-chip').first()
  await expect(paletteChip).toBeVisible()
  const chipColor = await paletteChip.evaluate((el) =>
    window.getComputedStyle(el).backgroundColor,
  )
  expect(chipColor).toBe('rgb(37, 99, 235)')

  const startButton = page.getByRole('button', { name: 'Commencer la réservation' })
  await expect(startButton).toBeVisible()
  const buttonBg = await startButton.evaluate((el) =>
    window.getComputedStyle(el).backgroundColor,
  )
  expect(buttonBg).toBe('rgb(37, 99, 235)')

  await expect(page.getByText('Étape 1')).toBeVisible()
})


