import { test, expect } from '@playwright/test'

test('admin can log in and see reservations overview', async ({ page }) => {
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

  await page.route('**/api/v1/auth/login', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          token: 'playwright-token',
          user: {
            id: 1,
            name: 'Demo Admin',
            email: 'admin@tenant.com',
            role: 'admin',
          },
          organization: {
            id: 1,
          },
        },
      }),
    })
  })

  await page.route('**/api/v1/admin/dashboard/summary', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          reservations_today: 2,
          instructors_active: 1,
        },
      }),
    })
  })

  await page.route('**/api/v1/admin/reservations**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          data: [
            {
              id: 1,
              uuid: 'abc123456789',
              status: 'scheduled',
              participants_count: 2,
              customer_first_name: 'Léa',
              customer_last_name: 'Vague',
              created_at: '2025-11-08T10:00:00Z',
              activity: { display_name: 'Surf collectif' },
            },
          ],
          pagination: {
            total: 1,
            per_page: 10,
            current_page: 1,
            last_page: 1,
          },
        },
      }),
    })
  })

  await page.route('**/api/v1/admin/instructors**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [] }),
    })
  })

  await page.route('**/api/v1/admin/sites**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: [] }),
    })
  })

  await page.goto('/login')

  await expect(
    page.getByRole('heading', { name: 'Connexion administrateur' }),
  ).toBeVisible()

  await page.getByLabel('Email').fill('admin@tenant.com')
  await page.getByLabel('Mot de passe').fill('password')
  await page.getByRole('button', { name: 'Se connecter' }).click()

  await expect(page.getByText('Réservations')).toBeVisible()
  await expect(page.getByText('Total 1')).toBeVisible()
  await expect(page.getByText('Surf collectif')).toBeVisible()
})

