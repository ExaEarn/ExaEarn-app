import { Body, Controller, Get, Post, Query } from '@nestjs/common';
import { CatalogQueryDto } from './dto/catalog-query.dto';
import { PurchaseGiftCardDto } from './dto/purchase-gift-card.dto';
import { SellGiftCardDto } from './dto/sell-gift-card.dto';
import { GiftCardCatalogService } from './services/gift-card-catalog.service';
import { GiftCardRouterService } from './services/gift-card-router.service';

@Controller('giftcard')
export class GiftCardController {
  constructor(
    private readonly catalog: GiftCardCatalogService,
    private readonly router: GiftCardRouterService,
  ) {}

  @Get('catalog')
  catalogIndex(@Query() query: CatalogQueryDto) {
    return this.catalog.getCatalog(query);
  }

  @Post('purchase')
  purchase(@Body() payload: PurchaseGiftCardDto) {
    return this.router.purchase(payload);
  }

  @Post('sell')
  sell(@Body() payload: SellGiftCardDto) {
    return this.router.sell(payload);
  }
}
