import { Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { BaseGiftCardProvider } from './base-provider.service';
import {
  GiftCard,
  GiftCardOperation,
  ProviderRate,
  PurchasePayload,
  PurchaseResponse,
  ValidationPayload,
  ValidationResponse,
} from '../interfaces/gift-card-provider.interface';

@Injectable()
export class ReloadlyService extends BaseGiftCardProvider {
  readonly name = 'reloadly';

  constructor(config: ConfigService) {
    super(config, 'RELOADLY');
  }

  async getCatalog(): Promise<GiftCard[]> {
    const response = await this.client.get('/giftcards/products', { headers: this.bearerHeaders() });
    const items = Array.isArray(response.data?.content) ? response.data.content : response.data?.products;
    if (!Array.isArray(items)) {
      throw new Error('Reloadly catalog response is invalid.');
    }

    return items.map((item) => ({
      brand: String(item.productName ?? item.brand?.brandName),
      productId: String(item.productId),
      currency: String(item.recipientCurrencyCode ?? item.senderCurrencyCode ?? 'USD'),
      minAmount: String(item.minRecipientDenomination ?? item.minAmount ?? '0'),
      maxAmount: String(item.maxRecipientDenomination ?? item.maxAmount ?? '0'),
      countryCode: item.country?.isoName,
      metadata: item,
    }));
  }

  async getRate(brand: string, amount: string, currency: string, operation: GiftCardOperation): Promise<ProviderRate> {
    const started = Date.now();
    const response = await this.client.post('/giftcards/quote', {
      brand,
      amount,
      currency,
      operation,
    }, { headers: this.bearerHeaders() });

    return {
      provider: this.name,
      brand,
      amount,
      currency,
      providerCost: this.numeric(response.data?.totalPrice ?? response.data?.cost ?? amount, 'totalPrice'),
      providerPayout: response.data?.payout ? this.numeric(response.data.payout, 'payout') : undefined,
      responseTimeMs: Date.now() - started,
      expiresAt: this.ttlDate(60),
      metadata: response.data,
    };
  }

  async purchase(payload: PurchasePayload): Promise<PurchaseResponse> {
    const response = await this.client.post('/giftcards/orders', {
      productId: payload.brand,
      amount: payload.amount,
      recipientEmail: payload.recipientEmail,
      recipientPhone: payload.recipientPhone,
      customIdentifier: payload.transactionId,
    }, {
      headers: { ...this.bearerHeaders(), 'Idempotency-Key': payload.idempotencyKey },
    });

    return {
      provider: this.name,
      providerReference: this.assertProviderReference(response.data?.transactionId ?? response.data?.id),
      status: response.data?.status === 'PENDING' ? 'pending' : 'success',
      code: response.data?.cardNumber,
      pin: response.data?.pinCode,
      redemptionUrl: response.data?.redeemInstruction?.url,
      raw: response.data,
    };
  }

  async validateCard(payload: ValidationPayload): Promise<ValidationResponse> {
    const response = await this.client.post('/giftcards/validate', {
      brand: payload.brand,
      amount: payload.amount,
      currency: payload.currency,
      cardNumber: payload.cardNumber,
      pin: payload.pin,
      customIdentifier: payload.transactionId,
    }, {
      headers: { ...this.bearerHeaders(), 'Idempotency-Key': payload.idempotencyKey },
    });

    return {
      provider: this.name,
      providerReference: this.assertProviderReference(response.data?.validationId ?? response.data?.id),
      status: response.data?.valid === false ? 'invalid' : (response.data?.status === 'PENDING' ? 'pending' : 'valid'),
      payoutAmount: this.numeric(response.data?.payoutAmount ?? response.data?.amount ?? payload.amount, 'payoutAmount'),
      raw: response.data,
    };
  }
}
