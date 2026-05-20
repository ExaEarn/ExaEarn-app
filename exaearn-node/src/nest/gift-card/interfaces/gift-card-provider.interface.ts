export type GiftCardOperation = 'purchase' | 'sell';

export interface GiftCard {
  brand: string;
  productId: string;
  currency: string;
  minAmount: string;
  maxAmount: string;
  countryCode?: string;
  metadata?: Record<string, unknown>;
}

export interface ProviderRate {
  provider: string;
  brand: string;
  amount: string;
  currency: string;
  providerCost: string;
  providerPayout?: string;
  responseTimeMs: number;
  expiresAt: Date;
  metadata?: Record<string, unknown>;
}

export interface PurchasePayload {
  transactionId: string;
  idempotencyKey: string;
  userId: string;
  brand: string;
  amount: string;
  currency: string;
  recipientEmail?: string;
  recipientPhone?: string;
  metadata?: Record<string, unknown>;
}

export interface PurchaseResponse {
  provider: string;
  providerReference: string;
  status: 'success' | 'pending';
  code?: string;
  pin?: string;
  redemptionUrl?: string;
  raw: Record<string, unknown>;
}

export interface ValidationPayload {
  transactionId: string;
  idempotencyKey: string;
  userId: string;
  brand: string;
  amount: string;
  currency: string;
  cardNumber: string;
  pin?: string;
  metadata?: Record<string, unknown>;
}

export interface ValidationResponse {
  provider: string;
  providerReference: string;
  status: 'valid' | 'invalid' | 'pending';
  payoutAmount: string;
  raw: Record<string, unknown>;
}

export interface GiftCardProvider {
  readonly name: string;
  getCatalog(): Promise<GiftCard[]>;
  getRate(brand: string, amount: string, currency: string, operation: GiftCardOperation): Promise<ProviderRate>;
  purchase(payload: PurchasePayload): Promise<PurchaseResponse>;
  validateCard(payload: ValidationPayload): Promise<ValidationResponse>;
}
