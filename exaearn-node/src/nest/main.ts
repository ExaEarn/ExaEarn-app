import 'reflect-metadata';
import { Logger, ValidationPipe } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { NestFactory } from '@nestjs/core';
import helmet from 'helmet';
import { AppModule } from './app.module';

async function bootstrap(): Promise<void> {
  const app = await NestFactory.create(AppModule, { bufferLogs: true });
  const config = app.get(ConfigService);
  const logger = new Logger('GiftCardRouter');

  app.use(helmet());
  app.enableCors({
    origin: (config.get<string>('NODE_CORS_ORIGINS') ?? '*').split(',').map((origin) => origin.trim()),
    credentials: true,
  });
  app.useGlobalPipes(new ValidationPipe({ whitelist: true, forbidNonWhitelisted: true, transform: true }));
  app.setGlobalPrefix('api');

  const port = Number(config.get<string>('GIFT_CARD_ROUTER_PORT') ?? config.get<string>('PORT') ?? 4100);
  await app.listen(port);
  logger.log(`Gift card router listening on ${port}`);
}

void bootstrap();
