import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { GiftCardProviderEntity } from '../entities/gift-card-provider.entity';
import { ProviderLogEntity } from '../entities/provider-log.entity';

@Injectable()
export class ProviderAnalyticsService {
  constructor(
    @InjectRepository(GiftCardProviderEntity) private readonly providers: Repository<GiftCardProviderEntity>,
    @InjectRepository(ProviderLogEntity) private readonly logs: Repository<ProviderLogEntity>,
  ) {}

  async activeProviders(operation: 'purchase' | 'sell'): Promise<GiftCardProviderEntity[]> {
    const providerRows = await this.providers.find({ where: { enabled: true }, order: { priority: 'ASC' } });
    return providerRows.filter((provider) => operation === 'purchase' ? provider.supportsPurchase : provider.supportsSell);
  }

  async log(input: {
    provider: string;
    transactionId?: string;
    operation: string;
    success: boolean;
    responseTimeMs: number;
    providerCost?: string;
    profit?: string;
    error?: string;
    metadata?: Record<string, unknown>;
  }): Promise<void> {
    await this.logs.save(this.logs.create({
      provider: input.provider,
      transactionId: input.transactionId,
      operation: input.operation,
      success: input.success,
      responseTimeMs: input.responseTimeMs,
      providerCost: input.providerCost ?? '0',
      profit: input.profit ?? '0',
      error: input.error,
      metadata: input.metadata,
    }));
  }

  async updateProviderHealth(provider: string, success: boolean, responseTimeMs: number): Promise<void> {
    const row = await this.providers.findOne({ where: { name: provider } });
    if (!row) {
      return;
    }

    row.avgResponseTimeMs = Math.round((row.avgResponseTimeMs * 9 + responseTimeMs) / 10);
    const target = success ? 10000 : 0;
    row.successRateBps = Math.round((row.successRateBps * 19 + target) / 20);
    await this.providers.save(row);
  }
}
