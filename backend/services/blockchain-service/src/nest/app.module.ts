import { Module } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { TypeOrmModule } from '@nestjs/typeorm';
import { GiftCardModule } from './gift-card/gift-card.module';
import { GiftCardProviderEntity } from './gift-card/entities/gift-card-provider.entity';
import { GiftCardTransactionEntity } from './gift-card/entities/gift-card-transaction.entity';
import { ProviderLogEntity } from './gift-card/entities/provider-log.entity';
import { RateCacheEntity } from './gift-card/entities/rate-cache.entity';

@Module({
  imports: [
    ConfigModule.forRoot({ isGlobal: true }),
    TypeOrmModule.forRootAsync({
      inject: [ConfigService],
      useFactory: (config: ConfigService) => ({
        type: 'postgres',
        url: config.get<string>('GIFT_CARD_DATABASE_URL') ?? config.get<string>('DATABASE_URL'),
        host: config.get<string>('GIFT_CARD_DB_HOST'),
        port: Number(config.get<string>('GIFT_CARD_DB_PORT') ?? 5432),
        username: config.get<string>('GIFT_CARD_DB_USER'),
        password: config.get<string>('GIFT_CARD_DB_PASSWORD'),
        database: config.get<string>('GIFT_CARD_DB_NAME'),
        entities: [GiftCardProviderEntity, GiftCardTransactionEntity, ProviderLogEntity, RateCacheEntity],
        synchronize: false,
        ssl: config.get<string>('GIFT_CARD_DB_SSL') === 'true' ? { rejectUnauthorized: false } : false,
      }),
    }),
    GiftCardModule,
  ],
})
export class AppModule {}
