import { Column, CreateDateColumn, Entity, Index, PrimaryGeneratedColumn, UpdateDateColumn } from 'typeorm';

export type GiftCardTransactionStatus =
  | 'pending'
  | 'funds_reserved'
  | 'provider_processing'
  | 'completed'
  | 'failed'
  | 'refunded';

@Entity({ name: 'giftcard_transactions' })
export class GiftCardTransactionEntity {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Index({ unique: true })
  @Column({ name: 'idempotency_key', length: 80 })
  idempotencyKey: string;

  @Index()
  @Column({ name: 'user_id', length: 80 })
  userId: string;

  @Column({ type: 'varchar', length: 16 })
  operation: 'purchase' | 'sell';

  @Column({ length: 80 })
  brand: string;

  @Column({ length: 8 })
  currency: string;

  @Column({ name: 'card_value', type: 'decimal', precision: 36, scale: 18 })
  cardValue: string;

  @Column({ name: 'user_charge', type: 'decimal', precision: 36, scale: 18, default: 0 })
  userCharge: string;

  @Column({ name: 'provider_cost', type: 'decimal', precision: 36, scale: 18, default: 0 })
  providerCost: string;

  @Column({ name: 'user_payout', type: 'decimal', precision: 36, scale: 18, default: 0 })
  userPayout: string;

  @Column({ type: 'decimal', precision: 36, scale: 18, default: 0 })
  profit: string;

  @Index()
  @Column({ nullable: true, length: 40 })
  provider?: string;

  @Column({ name: 'provider_reference', nullable: true, length: 120 })
  providerReference?: string;

  @Index()
  @Column({ length: 32, default: 'pending' })
  status: GiftCardTransactionStatus;

  @Column({ name: 'failure_reason', type: 'text', nullable: true })
  failureReason?: string;

  @Column({ type: 'jsonb', nullable: true })
  metadata?: Record<string, unknown>;

  @CreateDateColumn({ name: 'created_at' })
  createdAt: Date;

  @UpdateDateColumn({ name: 'updated_at' })
  updatedAt: Date;
}
