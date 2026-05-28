import { Column, CreateDateColumn, Entity, Index, PrimaryGeneratedColumn, UpdateDateColumn } from 'typeorm';

@Entity({ name: 'rates_cache' })
export class RateCacheEntity {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Index({ unique: true })
  @Column({ name: 'cache_key', length: 180 })
  cacheKey: string;

  @Column({ length: 40 })
  provider: string;

  @Column({ length: 80 })
  brand: string;

  @Column({ length: 8 })
  currency: string;

  @Column({ type: 'decimal', precision: 36, scale: 18 })
  amount: string;

  @Column({ name: 'provider_cost', type: 'decimal', precision: 36, scale: 18 })
  providerCost: string;

  @Column({ name: 'provider_payout', type: 'decimal', precision: 36, scale: 18, nullable: true })
  providerPayout?: string;

  @Column({ type: 'jsonb', nullable: true })
  payload?: Record<string, unknown>;

  @Index()
  @Column({ name: 'expires_at' })
  expiresAt: Date;

  @CreateDateColumn({ name: 'created_at' })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at' })
  updatedAt: Date;
}
