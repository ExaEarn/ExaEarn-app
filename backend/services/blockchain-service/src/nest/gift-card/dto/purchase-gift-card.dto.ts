import { IsEmail, IsNotEmpty, IsNumberString, IsOptional, IsString, IsUUID, Length } from 'class-validator';

export class PurchaseGiftCardDto {
  @IsUUID()
  idempotencyKey: string;

  @IsString()
  @IsNotEmpty()
  userId: string;

  @IsString()
  @Length(2, 80)
  brand: string;

  @IsNumberString()
  amount: string;

  @IsString()
  @Length(3, 8)
  currency: string;

  @IsOptional()
  @IsEmail()
  recipientEmail?: string;

  @IsOptional()
  @IsString()
  recipientPhone?: string;
}
