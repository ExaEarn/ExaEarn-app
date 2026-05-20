import { Module } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { TypeOrmModule } from '@nestjs/typeorm';
import { GiftCardController } from './gift-card.controller';
import { GiftCardProviderEntity } from './entities/gift-card-provider.entity';
import { GiftCardTransactionEntity } from './entities/gift-card-transaction.entity';
import { ProviderLogEntity } from './entities/provider-log.entity';
import { RateCacheEntity } from './entities/rate-cache.entity';
import { GIFQService } from './providers/gifq.service';
import { ReloadlyService } from './providers/reloadly.service';
import { RunaService } from './providers/runa.service';
import { GIFT_CARD_PROVIDERS } from './providers/provider.tokens';
import { GiftCardCatalogService } from './services/gift-card-catalog.service';
import { GiftCardPriceEngine } from './services/gift-card-price-engine.service';
import { GiftCardRouterService } from './services/gift-card-router.service';
import { ProviderAnalyticsService } from './services/provider-analytics.service';
import { RateCacheService } from './services/rate-cache.service';
import { WalletTreasuryLedgerService } from './services/wallet-treasury-ledger.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      GiftCardProviderEntity,
      GiftCardTransactionEntity,
      ProviderLogEntity,
      RateCacheEntity,
    ]),
  ],
  controllers: [GiftCardController],
  providers: [
    ConfigService,
    ReloadlyService,
    GIFQService,
    RunaService,
    {
      provide: GIFT_CARD_PROVIDERS,
      inject: [ReloadlyService, GIFQService, RunaService],
      useFactory: (reloadly: ReloadlyService, gifq: GIFQService, runa: RunaService) => [reloadly, gifq, runa],
    },
    RateCacheService,
    ProviderAnalyticsService,
    GiftCardCatalogService,
    GiftCardPriceEngine,
    WalletTreasuryLedgerService,
    GiftCardRouterService,
  ],
  exports: [GiftCardRouterService],
})
export class GiftCardModule {}
