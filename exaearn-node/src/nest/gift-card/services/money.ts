export function add(a: string, b: string): string {
  return (Number(a) + Number(b)).toFixed(18);
}

export function sub(a: string, b: string): string {
  return (Number(a) - Number(b)).toFixed(18);
}

export function mul(a: string, b: string): string {
  return (Number(a) * Number(b)).toFixed(18);
}

export function bps(amount: string, basisPoints: number): string {
  return mul(amount, String(basisPoints / 10000));
}

export function compare(a: string, b: string): number {
  return Number(a) < Number(b) ? -1 : Number(a) > Number(b) ? 1 : 0;
}

export function assertPositiveAmount(amount: string, field = 'amount'): void {
  if (!/^\d+(\.\d+)?$/.test(amount) || Number(amount) <= 0) {
    throw new Error(`${field} must be a positive numeric string.`);
  }
}
