import { ConflictException, Inject, Injectable, Logger, ServiceUnavailableException, UnprocessableEntityException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { EntityManager, Repository } from 'typeorm';
import { GiftCardTransactionEntity } from '../entities/gift-card-transaction.entity';
import { PurchaseGiftCardDto } from '../dto/purchase-gift-card.dto';
import { SellGiftCardDto } from '../dto/sell-gift-card.dto';
import { GIFT_CARD_PROVIDERS } from '../providers/provider.tokens';
import { GiftCardOperation, GiftCardProvider, ProviderRate } from '../interfaces/gift-card-provider.interface';
import { GiftCardPriceEngine, ProviderQuote } from './gift-card-price-engine.service';
import { ProviderAnalyticsService } from './provider-analytics.service';
import { RateCacheService } from './rate-cache.service';
import { WalletTreasuryLedgerService } from './wallet-treasury-ledger.service';
import { assertPositiveAmount, compare } from './money';

@Injectable()
export class GiftCardRouterService {
  private readonly logger = new Logger(GiftCardRouterService.name);

  constructor(
    @InjectRepository(GiftCardTransactionEntity) private readonly transactions: Repository<GiftCardTransactionEntity>,
    @Inject(GIFT_CARD_PROVIDERS) private readonly providers: GiftCardProvider[],
    private readonly priceEngine: GiftCardPriceEngine,
    private readonly analytics: ProviderAnalyticsService,
    private readonly rateCache: RateCacheService,
    private readonly ledger: WalletTreasuryLedgerService,
  ) {}

  async purchase(dto: PurchaseGiftCardDto): Promise<GiftCardTransactionEntity> {
    assertPositiveAmount(dto.amount);
    const existing = await this.transactions.findOne({ where: { idempotencyKey: dto.idempotencyKey } });
    if (existing) {
      if (existing.operation !== 'purchase') {
        throw new ConflictException('Idempotency key was already used for a different operation.');
      }
      return existing;
    }

    const quotes = await this.quoteProviders('purchase', dto.brand, dto.amount, dto.currency);
    if (quotes.length === 0) {
      throw new ServiceUnavailableException('No gift card provider is available for this purchase.');
    }

    const sorted = this.priceEngine.sort(quotes);
    const tx = await this.createTransaction(dto, 'purchase', sorted[0]);

    await this.ledger.reserveForPurchase({
      userId: dto.userId,
      transactionId: tx.id,
      idempotencyKey: dto.idempotencyKey,
      amount: sorted[0].userCharge,
      currency: dto.currency,
      provider: sorted[0].provider,
    });
    await this.markStatus(tx.id, 'funds_reserved');

    for (const quote of sorted) {
      const provider = this.providerByName(quote.provider);
      const started = Date.now();
      try {
        await this.markProviderAttempt(tx.id, quote);
        const result = await provider.purchase({
          transactionId: tx.id,
          idempotencyKey: dto.idempotencyKey,
          userId: dto.userId,
          brand: dto.brand,
          amount: dto.amount,
          currency: dto.currency,
          recipientEmail: dto.recipientEmail,
          recipientPhone: dto.recipientPhone,
          metadata: { quote },
        });
        const responseTimeMs = Date.now() - started;
        await this.ledger.settlePurchase({
          userId: dto.userId,
          transactionId: tx.id,
          idempotencyKey: dto.idempotencyKey,
          provider: quote.provider,
          userCharge: quote.userCharge,
          providerCost: quote.providerCost,
          profit: quote.profit,
          currency: dto.currency,
          providerReference: result.providerReference,
        });
        await this.analytics.log({ provider: quote.provider, transactionId: tx.id, operation: 'purchase', success: true, responseTimeMs, providerCost: quote.providerCost, profit: quote.profit });
        await this.analytics.updateProviderHealth(quote.provider, true, responseTimeMs);
        return this.completeTransaction(tx.id, quote, result.providerReference, { delivery: result });
      } catch (error) {
        const responseTimeMs = Date.now() - started;
        const message = error instanceof Error ? error.message : 'Unknown provider failure';
        await this.analytics.log({ provider: quote.provider, transactionId: tx.id, operation: 'purchase', success: false, responseTimeMs, providerCost: quote.providerCost, profit: quote.profit, error: message });
        await this.analytics.updateProviderHealth(quote.provider, false, responseTimeMs);
        this.logger.warn(`Gift card provider ${quote.provider} purchase failed`, { transactionId: tx.id, error: message });
      }
    }

    await this.ledger.refundPurchase({
      userId: dto.userId,
      transactionId: tx.id,
      idempotencyKey: dto.idempotencyKey,
      amount: sorted[0].userCharge,
      currency: dto.currency,
      reason: 'all_providers_failed',
    });
    await this.failTransaction(tx.id, 'All providers failed; user funds refunded.', 'refunded');
    throw new ServiceUnavailableException('All gift card providers failed. Funds have been refunded.');
  }

  async sell(dto: SellGiftCardDto): Promise<GiftCardTransactionEntity> {
    assertPositiveAmount(dto.amount);
    const existing = await this.transactions.findOne({ where: { idempotencyKey: dto.idempotencyKey } });
    if (existing) {
      if (existing.operation !== 'sell') {
        throw new ConflictException('Idempotency key was already used for a different operation.');
      }
      return existing;
    }

    const quotes = await this.quoteProviders('sell', dto.brand, dto.amount, dto.currency);
    if (quotes.length === 0) {
      throw new ServiceUnavailableException('No gift card provider is available for this sell request.');
    }

    const sorted = this.priceEngine.sort(quotes).filter((quote) => compare(quote.userPayout, '0') > 0);
    if (sorted.length === 0) {
      throw new UnprocessableEntityException('No provider returned an acceptable payout.');
    }

    const tx = await this.createTransaction(dto, 'sell', sorted[0]);
    for (const quote of sorted) {
      const provider = this.providerByName(quote.provider);
      const started = Date.now();
      try {
        await this.markProviderAttempt(tx.id, quote);
        const result = await provider.validateCard({
          transactionId: tx.id,
          idempotencyKey: dto.idempotencyKey,
          userId: dto.userId,
          brand: dto.brand,
          amount: dto.amount,
          currency: dto.currency,
          cardNumber: dto.cardNumber,
          pin: dto.pin,
          metadata: { quote },
        });
        if (result.status === 'invalid') {
          throw new Error('Provider rejected gift card as invalid.');
        }
        const responseTimeMs = Date.now() - started;
        await this.ledger.settleSell({
          userId: dto.userId,
          transactionId: tx.id,
          idempotencyKey: dto.idempotencyKey,
          provider: quote.provider,
          userPayout: quote.userPayout,
          platformProfit: quote.profit,
          currency: dto.currency,
          providerReference: result.providerReference,
        });
        await this.analytics.log({ provider: quote.provider, transactionId: tx.id, operation: 'sell', success: true, responseTimeMs, providerCost: '0', profit: quote.profit });
        await this.analytics.updateProviderHealth(quote.provider, true, responseTimeMs);
        return this.completeTransaction(tx.id, quote, result.providerReference, { validation: result });
      } catch (error) {
        const responseTimeMs = Date.now() - started;
        const message = error instanceof Error ? error.message : 'Unknown provider failure';
        await this.analytics.log({ provider: quote.provider, transactionId: tx.id, operation: 'sell', success: false, responseTimeMs, error: message });
        await this.analytics.updateProviderHealth(quote.provider, false, responseTimeMs);
      }
    }

    await this.ledger.recordFailure({ userId: dto.userId, transactionId: tx.id, idempotencyKey: dto.idempotencyKey, operation: 'sell', reason: 'all_providers_failed' });
    await this.failTransaction(tx.id, 'All providers failed validation.', 'failed');
    throw new ServiceUnavailableException('All gift card providers failed validation.');
  }

  private async quoteProviders(operation: GiftCardOperation, brand: string, amount: string, currency: string): Promise<ProviderQuote[]> {
    const active = await this.analytics.activeProviders(operation);
    const rows = await Promise.allSettled(active.map(async (providerConfig) => {
      const adapter = this.providerByName(providerConfig.name);
      const cached = await this.rateCache.get(providerConfig.name, operation, brand, amount, currency);
      const rate: ProviderRate = cached ?? await adapter.getRate(brand, amount, currency, operation);
      if (!cached) {
        await this.rateCache.put(operation, rate);
      }
      if (rate.responseTimeMs > providerConfig.maxLatencyMs) {
        throw new Error(`${providerConfig.name} exceeded latency budget.`);
      }
      return this.priceEngine.buildQuote(rate, providerConfig, operation);
    }));

    return rows.flatMap((row) => row.status === 'fulfilled' ? [row.value] : []);
  }

  private providerByName(name: string): GiftCardProvider {
    const provider = this.providers.find((candidate) => candidate.name === name);
    if (!provider) {
      throw new Error(`Provider adapter ${name} is not registered.`);
    }
    return provider;
  }

  private async createTransaction(dto: PurchaseGiftCardDto | SellGiftCardDto, operation: GiftCardOperation, quote: ProviderQuote): Promise<GiftCardTransactionEntity> {
    return this.transactions.manager.transaction(async (manager: EntityManager) => {
      const repo = manager.getRepository(GiftCardTransactionEntity);
      const existing = await repo.findOne({ where: { idempotencyKey: dto.idempotencyKey }, lock: { mode: 'pessimistic_write' } });
      if (existing) {
        return existing;
      }

      return repo.save(repo.create({
        idempotencyKey: dto.idempotencyKey,
        userId: dto.userId,
        operation,
        brand: dto.brand,
        currency: dto.currency.toUpperCase(),
        cardValue: dto.amount,
        userCharge: quote.userCharge,
        providerCost: quote.providerCost,
        userPayout: quote.userPayout,
        profit: quote.profit,
        provider: quote.provider,
        status: 'pending',
        metadata: { selectedQuote: quote },
      }));
    });
  }

  private async markStatus(transactionId: string, status: GiftCardTransactionEntity['status']): Promise<void> {
    await this.transactions.update(transactionId, { status });
  }

  private async markProviderAttempt(transactionId: string, quote: ProviderQuote): Promise<void> {
    await this.transactions.update(transactionId, {
      provider: quote.provider,
      providerCost: quote.providerCost,
      userCharge: quote.userCharge,
      userPayout: quote.userPayout,
      profit: quote.profit,
      status: 'provider_processing',
      metadata: { selectedQuote: quote },
    });
  }

  private async completeTransaction(transactionId: string, quote: ProviderQuote, providerReference: string, metadata: Record<string, unknown>): Promise<GiftCardTransactionEntity> {
    await this.transactions.update(transactionId, {
      provider: quote.provider,
      providerReference,
      userCharge: quote.userCharge,
      providerCost: quote.providerCost,
      userPayout: quote.userPayout,
      profit: quote.profit,
      status: 'completed',
      metadata: { selectedQuote: quote, ...metadata },
    });
    return this.transactions.findOneByOrFail({ id: transactionId });
  }

  private async failTransaction(transactionId: string, reason: string, status: 'failed' | 'refunded'): Promise<void> {
    await this.transactions.update(transactionId, { status, failureReason: reason });
  }
}
