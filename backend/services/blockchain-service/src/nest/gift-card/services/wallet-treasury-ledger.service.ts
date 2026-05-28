import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import axios, { AxiosInstance } from 'axios';
import { GiftCardOperation } from '../interfaces/gift-card-provider.interface';

@Injectable()
export class WalletTreasuryLedgerService {
  private readonly logger = new Logger(WalletTreasuryLedgerService.name);
  private readonly client: AxiosInstance;

  constructor(private readonly config: ConfigService) {
    this.client = axios.create({
      baseURL: this.config.get<string>('EXAEARN_CORE_API_URL') ?? this.config.get<string>('LARAVEL_API_URL'),
      timeout: Number(this.config.get<string>('EXAEARN_CORE_TIMEOUT_MS') ?? 8000),
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Service-Secret': this.config.get<string>('NODE_SERVICE_SECRET') ?? this.config.get<string>('SERVICE_SECRET') ?? '',
      },
    });
  }

  async reserveForPurchase(input: {
    userId: string;
    transactionId: string;
    idempotencyKey: string;
    amount: string;
    currency: string;
    provider: string;
  }): Promise<void> {
    await this.post('/giftcard-router/reserve-purchase', input);
  }

  async settlePurchase(input: {
    userId: string;
    transactionId: string;
    idempotencyKey: string;
    provider: string;
    userCharge: string;
    providerCost: string;
    profit: string;
    currency: string;
    providerReference: string;
  }): Promise<void> {
    await this.post('/giftcard-router/settle-purchase', input);
  }

  async refundPurchase(input: {
    userId: string;
    transactionId: string;
    idempotencyKey: string;
    amount: string;
    currency: string;
    reason: string;
  }): Promise<void> {
    await this.post('/giftcard-router/refund-purchase', input);
  }

  async settleSell(input: {
    userId: string;
    transactionId: string;
    idempotencyKey: string;
    provider: string;
    userPayout: string;
    platformProfit: string;
    currency: string;
    providerReference: string;
  }): Promise<void> {
    await this.post('/giftcard-router/settle-sell', input);
  }

  async recordFailure(input: {
    userId: string;
    transactionId: string;
    idempotencyKey: string;
    operation: GiftCardOperation;
    reason: string;
  }): Promise<void> {
    try {
      await this.post('/giftcard-router/fail', input);
    } catch (error) {
      this.logger.error('Failed to record giftcard router failure in core ledger', { error });
    }
  }

  private async post(path: string, payload: Record<string, unknown>): Promise<void> {
    await this.client.post(path, payload, {
      headers: { 'Idempotency-Key': String(payload.idempotencyKey ?? '') },
    });
  }
}
