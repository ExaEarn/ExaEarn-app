import { Column, CreateDateColumn, Entity, Index, PrimaryGeneratedColumn } from 'typeorm';

@Entity({ name: 'provider_logs' })
export class ProviderLogEntity {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Index()
  @Column({ length: 40 })
  provider: string;

  @Index()
  @Column({ name: 'transaction_id', nullable: true })
  transactionId?: string;

  @Column({ length: 32 })
  operation: string;

  @Column({ default: false })
  success: boolean;

  @Column({ name: 'response_time_ms', type: 'int' })
  responseTimeMs: number;

  @Column({ name: 'provider_cost', type: 'decimal', precision: 36, scale: 18, default: 0 })
  providerCost: string;

  @Column({ type: 'decimal', precision: 36, scale: 18, default: 0 })
  profit: string;

  @Column({ type: 'text', nullable: true })
  error?: string;

  @Column({ type: 'jsonb', nullable: true })
  metadata?: Record<string, unknown>;

  @CreateDateColumn({ name: 'created_at' })
  createdAt: Date;
}
