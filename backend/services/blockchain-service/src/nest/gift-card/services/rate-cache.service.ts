import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { MoreThan, Repository } from 'typeorm';
import { RateCacheEntity } from '../entities/rate-cache.entity';
import { GiftCardOperation, ProviderRate } from '../interfaces/gift-card-provider.interface';

@Injectable()
export class RateCacheService {
  constructor(@InjectRepository(RateCacheEntity) private readonly cache: Repository<RateCacheEntity>) {}

  key(provider: string, operation: GiftCardOperation, brand: string, amount: string, currency: string): string {
    return [provider.toLowerCase(), operation, brand.toLowerCase(), amount, currency.toUpperCase()].join(':');
  }

  async get(provider: string, operation: GiftCardOperation, brand: string, amount: string, currency: string): Promise<ProviderRate | null> {
    const entry = await this.cache.findOne({
      where: {
        cacheKey: this.key(provider, operation, brand, amount, currency),
        expiresAt: MoreThan(new Date()),
      },
    });

    if (!entry) {
      return null;
    }

    return {
      provider: entry.provider,
      brand: entry.brand,
      amount: entry.amount,
      currency: entry.currency,
      providerCost: entry.providerCost,
      providerPayout: entry.providerPayout,
      responseTimeMs: Number(entry.payload?.responseTimeMs ?? 0),
      expiresAt: entry.expiresAt,
      metadata: entry.payload,
    };
  }

  async put(operation: GiftCardOperation, rate: ProviderRate): Promise<void> {
    await this.cache.upsert({
      cacheKey: this.key(rate.provider, operation, rate.brand, rate.amount, rate.currency),
      provider: rate.provider,
      brand: rate.brand,
      amount: rate.amount,
      currency: rate.currency,
      providerCost: rate.providerCost,
      providerPayout: rate.providerPayout,
      payload: { ...rate.metadata, responseTimeMs: rate.responseTimeMs },
      expiresAt: rate.expiresAt,
    }, ['cacheKey']);
  }
}
