import { test, expect } from '@playwright/test'

test('complete surfing booking flow', async ({ page }) => {
  await page.route('**/api/v1/branding', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          primaryColor: '#2563eb',
          secondaryColor: '#1f2937',
        },
      }),
    })
  })

  await page.route('**/api/v1/activities/2', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        id: 2,
        organization_id: 1,
        activity_type: 'surfing',
        name: 'Cours de surf Playwright',
        description: 'Session 2h coachée pour groupe',
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
      }),
    })
  })

  await page.route('**/api/v1/reservations', async (route) => {
    const body = await route.request().postDataJSON()
    expect(body.activity_id).toBe(2)
    expect(body.customer_email).toBe('lea@example.com')
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        reservation: {
          uuid: 'surf-e2e',
          status: 'pending',
        },
      }),
    })
  })

  await page.goto('/')

  await page.getByRole('button', { name: 'Commencer la réservation' }).click()
  await page.getByLabel('Prénom').fill('Léa')
  await page.getByLabel('Nom').fill('Vague')
  await page.getByLabel('Email').fill('lea@example.com')
  await page.getByLabel('Téléphone').fill('+33600000000')
  await page.getByLabel('Niveau de natation').selectOption('intermediate')

  await page.getByRole('button', { name: 'Continuer' }).click()
  await page.getByRole('button', { name: 'Confirmer la réservation' }).click()

  await expect(
    page.getByText('Merci, ta demande est enregistrée !'),
  ).toBeVisible()
  await expect(page.getByText('surf-e2e')).toBeVisible()
})

