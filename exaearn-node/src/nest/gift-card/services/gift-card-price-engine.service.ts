import { Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { GiftCardProviderEntity } from '../entities/gift-card-provider.entity';
import { GiftCardOperation, ProviderRate } from '../interfaces/gift-card-provider.interface';
import { add, bps, sub } from './money';

export interface ProviderQuote {
  provider: string;
  operation: GiftCardOperation;
  brand: string;
  amount: string;
  currency: string;
  providerCost: string;
  userCharge: string;
  userPayout: string;
  profit: string;
  responseTimeMs: number;
  successRateBps: number;
  score: number;
  metadata?: Record<string, unknown>;
}

@Injectable()
export class GiftCardPriceEngine {
  constructor(private readonly config: ConfigService) {}

  buildQuote(rate: ProviderRate, provider: GiftCardProviderEntity, operation: GiftCardOperation): ProviderQuote {
    const markupBps = Number(this.config.get<string>('GIFT_CARD_MARKUP_BPS') ?? 400);
    const sellSpreadBps = Number(this.config.get<string>('GIFT_CARD_SELL_SPREAD_BPS') ?? 800);
    const latencyPenalty = Math.max(0, rate.responseTimeMs - provider.maxLatencyMs) / 1000;

    if (operation === 'purchase') {
      const userCharge = add(rate.amount, bps(rate.amount, markupBps));
      const profit = sub(userCharge, rate.providerCost);
      return {
        provider: rate.provider,
        operation,
        brand: rate.brand,
        amount: rate.amount,
        currency: rate.currency,
        providerCost: rate.providerCost,
        userCharge,
        userPayout: '0',
        profit,
        responseTimeMs: rate.responseTimeMs,
        successRateBps: provider.successRateBps,
        score: Number(profit) * 100 + provider.successRateBps / 100 - latencyPenalty,
        metadata: rate.metadata,
      };
    }

    const providerPayout = rate.providerPayout ?? sub(rate.amount, bps(rate.amount, sellSpreadBps));
    const userPayout = sub(providerPayout, bps(rate.amount, sellSpreadBps));
    const profit = sub(providerPayout, userPayout);
    return {
      provider: rate.provider,
      operation,
      brand: rate.brand,
      amount: rate.amount,
      currency: rate.currency,
      providerCost: '0',
      userCharge: '0',
      userPayout,
      profit,
      responseTimeMs: rate.responseTimeMs,
      successRateBps: provider.successRateBps,
      score: Number(profit) * 100 + provider.successRateBps / 100 - latencyPenalty,
      metadata: rate.metadata,
    };
  }

  sort(quotes: ProviderQuote[]): ProviderQuote[] {
    return [...quotes].sort((a, b) => {
      if (b.score !== a.score) {
        return b.score - a.score;
      }
      if (Number(a.providerCost) !== Number(b.providerCost)) {
        return Number(a.providerCost) - Number(b.providerCost);
      }
      return b.successRateBps - a.successRateBps;
    });
  }
}
