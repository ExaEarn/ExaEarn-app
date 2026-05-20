import { Inject, Injectable } from '@nestjs/common';
import { GIFT_CARD_PROVIDERS } from '../providers/provider.tokens';
import { GiftCard, GiftCardProvider } from '../interfaces/gift-card-provider.interface';

@Injectable()
export class GiftCardCatalogService {
  private cache: { expiresAt: number; items: GiftCard[] } | null = null;

  constructor(@Inject(GIFT_CARD_PROVIDERS) private readonly providers: GiftCardProvider[]) {}

  async getCatalog(query: { countryCode?: string; brand?: string }): Promise<GiftCard[]> {
    const now = Date.now();
    if (!this.cache || this.cache.expiresAt <= now) {
      const catalogs = await Promise.allSettled(this.providers.map((provider) => provider.getCatalog()));
      const items = catalogs.flatMap((result) => result.status === 'fulfilled' ? result.value : []);
      this.cache = { expiresAt: now + 10 * 60 * 1000, items };
    }

    return this.cache.items.filter((item) => {
      if (query.countryCode && item.countryCode !== query.countryCode.toUpperCase()) {
        return false;
      }
      if (query.brand && !item.brand.toLowerCase().includes(query.brand.toLowerCase())) {
        return false;
      }
      return true;
    });
  }
}
