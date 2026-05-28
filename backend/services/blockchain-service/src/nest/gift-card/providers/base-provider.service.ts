import { Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import axios, { AxiosInstance } from 'axios';
import {
  GiftCard,
  GiftCardOperation,
  GiftCardProvider,
  ProviderRate,
  PurchasePayload,
  PurchaseResponse,
  ValidationPayload,
  ValidationResponse,
} from '../interfaces/gift-card-provider.interface';

export abstract class BaseGiftCardProvider implements GiftCardProvider {
  protected readonly logger = new Logger(this.constructor.name);
  protected readonly client: AxiosInstance;

  abstract readonly name: string;

  protected constructor(protected readonly config: ConfigService, protected readonly configPrefix: string) {
    this.client = axios.create({
      baseURL: this.config.get<string>(`${this.configPrefix}_BASE_URL`),
      timeout: Number(this.config.get<string>(`${this.configPrefix}_TIMEOUT_MS`) ?? 5000),
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });
  }

  abstract getCatalog(): Promise<GiftCard[]>;
  abstract getRate(brand: string, amount: string, currency: string, operation: GiftCardOperation): Promise<ProviderRate>;
  abstract purchase(payload: PurchasePayload): Promise<PurchaseResponse>;
  abstract validateCard(payload: ValidationPayload): Promise<ValidationResponse>;

  protected bearerHeaders(): Record<string, string> {
    const token = this.config.get<string>(`${this.configPrefix}_API_KEY`);
    if (!token) {
      throw new Error(`${this.name} API key is not configured.`);
    }

    return { Authorization: `Bearer ${token}` };
  }

  protected hmacHeaders(idempotencyKey: string): Record<string, string> {
    const apiKey = this.config.get<string>(`${this.configPrefix}_API_KEY`);
    const secret = this.config.get<string>(`${this.configPrefix}_API_SECRET`);
    if (!apiKey || !secret) {
      throw new Error(`${this.name} API credentials are not configured.`);
    }

    return {
      'X-API-Key': apiKey,
      'X-API-Secret': secret,
      'Idempotency-Key': idempotencyKey,
    };
  }

  protected ttlDate(ttlSeconds: number): Date {
    return new Date(Date.now() + ttlSeconds * 1000);
  }

  protected assertProviderReference(reference: unknown): string {
    if (typeof reference !== 'string' || reference.length < 3) {
      throw new Error(`${this.name} response did not include a valid provider reference.`);
    }

    return reference;
  }

  protected numeric(value: unknown, field: string): string {
    const asString = String(value ?? '');
    if (!/^\d+(\.\d+)?$/.test(asString)) {
      throw new Error(`${this.name} response field ${field} is not numeric.`);
    }

    return asString;
  }
}
