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
export class RunaService extends BaseGiftCardProvider {
  readonly name = 'runa';

  constructor(config: ConfigService) {
    super(config, 'RUNA');
  }

  async getCatalog(): Promise<GiftCard[]> {
    const response = await this.client.get('/v2/products', { headers: this.bearerHeaders() });
    const items = response.data?.products;
    if (!Array.isArray(items)) {
      throw new Error('Runa catalog response is invalid.');
    }

    return items.map((item) => ({
      brand: String(item.name),
      productId: String(item.id),
      currency: String(item.currency ?? 'USD'),
      minAmount: String(item.minimum_value ?? '0'),
      maxAmount: String(item.maximum_value ?? '0'),
      countryCode: item.country_code,
      metadata: item,
    }));
  }

  async getRate(brand: string, amount: string, currency: string, operation: GiftCardOperation): Promise<ProviderRate> {
    const started = Date.now();
    const response = await this.client.post('/v2/quote', { product: brand, amount, currency, operation }, { headers: this.bearerHeaders() });

    return {
      provider: this.name,
      brand,
      amount,
      currency,
      providerCost: this.numeric(response.data?.price ?? response.data?.cost ?? amount, 'price'),
      providerPayout: response.data?.payout ? this.numeric(response.data.payout, 'payout') : undefined,
      responseTimeMs: Date.now() - started,
      expiresAt: this.ttlDate(60),
      metadata: response.data,
    };
  }

  async purchase(payload: PurchasePayload): Promise<PurchaseResponse> {
    const response = await this.client.post('/v2/orders', {
      product: payload.brand,
      amount: payload.amount,
      currency: payload.currency,
      recipient: {
        email: payload.recipientEmail,
        phone: payload.recipientPhone,
      },
      external_id: payload.transactionId,
    }, {
      headers: { ...this.bearerHeaders(), 'Idempotency-Key': payload.idempotencyKey },
    });

    return {
      provider: this.name,
      providerReference: this.assertProviderReference(response.data?.id ?? response.data?.reference),
      status: response.data?.status === 'processing' ? 'pending' : 'success',
      code: response.data?.voucher?.code,
      pin: response.data?.voucher?.pin,
      redemptionUrl: response.data?.voucher?.url,
      raw: response.data,
    };
  }

  async validateCard(payload: ValidationPayload): Promise<ValidationResponse> {
    const response = await this.client.post('/v2/cards/validate', {
      product: payload.brand,
      amount: payload.amount,
      currency: payload.currency,
      card_number: payload.cardNumber,
      pin: payload.pin,
      external_id: payload.transactionId,
    }, {
      headers: { ...this.bearerHeaders(), 'Idempotency-Key': payload.idempotencyKey },
    });

    return {
      provider: this.name,
      providerReference: this.assertProviderReference(response.data?.id ?? response.data?.reference),
      status: response.data?.valid === false ? 'invalid' : (response.data?.status === 'processing' ? 'pending' : 'valid'),
      payoutAmount: this.numeric(response.data?.payout_amount ?? payload.amount, 'payout_amount'),
      raw: response.data,
    };
  }
}
