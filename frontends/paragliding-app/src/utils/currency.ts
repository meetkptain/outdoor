export function formatCurrency(
  amount: number,
  currency = 'EUR',
  locale = 'fr-FR',
) {
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency,
  }).format(amount)
}

