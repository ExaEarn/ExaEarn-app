export function formatTransactionAmount(
  amountMinor: number,
  asset?: string,
  precision = 2,
) {
  const safePrecision = Number.isInteger(precision) && precision >= 0 && precision <= 8 ? precision : 2;
  const amount = new Intl.NumberFormat(undefined, {
    minimumFractionDigits: safePrecision,
    maximumFractionDigits: safePrecision,
  }).format(amountMinor / (10 ** safePrecision));
  return asset ? `${amount} ${asset}` : amount;
}

export function formatTransactionDate(value: string) {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "Date unavailable" : date.toLocaleString();
}
