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
export class GIFQService extends BaseGiftCardProvider {
  readonly name = 'gifq';

  constructor(config: ConfigService) {
    super(config, 'GIFQ');
  }

  async getCatalog(): Promise<GiftCard[]> {
    const response = await this.client.get('/v1/catalog', { headers: this.hmacHeaders('catalog') });
    const items = response.data?.data;
    if (!Array.isArray(items)) {
      throw new Error('GIFQ catalog response is invalid.');
    }

    return items.map((item) => ({
      brand: String(item.brand),
      productId: String(item.sku ?? item.product_id),
      currency: String(item.currency),
      minAmount: String(item.min_value ?? '0'),
      maxAmount: String(item.max_value ?? '0'),
      countryCode: item.country,
      metadata: item,
    }));
  }

  async getRate(brand: string, amount: string, currency: string, operation: GiftCardOperation): Promise<ProviderRate> {
    const started = Date.now();
    const response = await this.client.post('/v1/rates', { brand, amount, currency, operation }, { headers: this.hmacHeaders(`${brand}:${amount}`) });

    return {
      provider: this.name,
      brand,
      amount,
      currency,
      providerCost: this.numeric(response.data?.cost ?? response.data?.total_cost ?? amount, 'cost'),
      providerPayout: response.data?.payout ? this.numeric(response.data.payout, 'payout') : undefined,
      responseTimeMs: Date.now() - started,
      expiresAt: this.ttlDate(60),
      metadata: response.data,
    };
  }

  async purchase(payload: PurchasePayload): Promise<PurchaseResponse> {
    const response = await this.client.post('/v1/orders', payload, { headers: this.hmacHeaders(payload.idempotencyKey) });

    return {
      provider: this.name,
      providerReference: this.assertProviderReference(response.data?.reference ?? response.data?.order_id),
      status: response.data?.status === 'pending' ? 'pending' : 'success',
      code: response.data?.code,
      pin: response.data?.pin,
      redemptionUrl: response.data?.redemption_url,
      raw: response.data,
    };
  }

  async validateCard(payload: ValidationPayload): Promise<ValidationResponse> {
    const response = await this.client.post('/v1/cards/validate', payload, { headers: this.hmacHeaders(payload.idempotencyKey) });

    return {
      provider: this.name,
      providerReference: this.assertProviderReference(response.data?.reference ?? response.data?.validation_id),
      status: response.data?.status === 'invalid' ? 'invalid' : (response.data?.status === 'pending' ? 'pending' : 'valid'),
      payoutAmount: this.numeric(response.data?.payout_amount ?? payload.amount, 'payout_amount'),
      raw: response.data,
    };
  }
}
