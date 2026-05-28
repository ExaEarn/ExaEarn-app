import { IsOptional, IsString, Length } from 'class-validator';

export class CatalogQueryDto {
  @IsOptional()
  @IsString()
  @Length(2, 2)
  countryCode?: string;

  @IsOptional()
  @IsString()
  brand?: string;
}
