import { Column, CreateDateColumn, Entity, Index, PrimaryGeneratedColumn, UpdateDateColumn } from 'typeorm';

@Entity({ name: 'giftcard_providers' })
export class GiftCardProviderEntity {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Index({ unique: true })
  @Column({ length: 40 })
  name: string;

  @Column({ default: true })
  enabled: boolean;

  @Column({ type: 'int', default: 100 })
  priority: number;

  @Column({ name: 'success_rate_bps', type: 'int', default: 9800 })
  successRateBps: number;

  @Column({ name: 'avg_response_time_ms', type: 'int', default: 1000 })
  avgResponseTimeMs: number;

  @Column({ name: 'max_latency_ms', type: 'int', default: 3000 })
  maxLatencyMs: number;

  @Column({ name: 'supports_purchase', default: true })
  supportsPurchase: boolean;

  @Column({ name: 'supports_sell', default: true })
  supportsSell: boolean;

  @Column({ type: 'jsonb', nullable: true })
  metadata?: Record<string, unknown>;

  @CreateDateColumn({ name: 'created_at' })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at' })
  updatedAt: Date;
}
