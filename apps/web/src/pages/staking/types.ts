export type DecimalString = string;

export type ReadinessStatus =
  | "development"
  | "testnet"
  | "integration_testing"
  | "internal_testing"
  | "limited_release"
  | "production"
  | "paused"
  | "deprecated";

export type StakingPositionStatus =
  | "pending"
  | "reserved"
  | "batching"
  | "awaiting_signature"
  | "delegation_submitted"
  | "awaiting_activation"
  | "active"
  | "partial_unstake_pending"
  | "unstaking"
  | "unbonding"
  | "withdrawable"
  | "releasing"
  | "completed"
  | "cancelled"
  | "failed"
  | "suspended"
  | "slashed";

export type ProviderHealth = {
  ready?: boolean;
  status?: string;
  message?: string;
  rpc_endpoints_configured?: number;
  capabilities?: string[];
};

export type StakingAsset = {
  id: number;
  asset_id?: number;
  symbol: string;
  network: string;
  provider?: string;
  staking_type?: string;
  readiness_status: ReadinessStatus;
  native_staking_enabled?: boolean | number;
  mainnet_enabled?: boolean | number;
  testnet_enabled?: boolean | number;
  new_positions_enabled?: boolean | number;
  unstaking_enabled?: boolean | number;
  emergency_paused?: boolean | number;
  minimum_stake?: DecimalString;
  maximum_stake?: DecimalString | null;
  delegation_minimum?: DecimalString;
  amount_precision?: number;
  reward_precision?: number;
  platform_commission_rate?: DecimalString;
  displayed_apy?: DecimalString | null;
  reward_distribution_frequency?: string;
  expected_activation_seconds?: number | null;
  unbonding_period_seconds?: number | null;
  supports_flexible_staking?: boolean | number;
  supports_locked_staking?: boolean | number;
  supports_partial_unstaking?: boolean | number;
  supports_reward_claiming?: boolean | number;
  auto_compound_supported?: boolean | number;
  validator_selection_strategy?: string;
  confirmation_requirement?: number;
  metadata?: unknown;
  provider_health?: ProviderHealth;
};

export type StakingProduct = {
  id: number;
  staking_asset_id: number;
  symbol: string;
  network: string;
  name: string;
  slug: string;
  product_type: string;
  status: string;
  network_environment: string;
  duration_days?: number | null;
  minimum_amount: DecimalString;
  maximum_amount?: DecimalString | null;
  displayed_apy?: DecimalString | null;
  platform_commission_rate?: DecimalString;
  reward_schedule?: string;
  redemption_type?: string;
  unbonding_period_seconds?: number | null;
  early_redemption_allowed?: boolean | number;
  early_redemption_penalty_rate?: DecimalString;
  auto_compound_supported?: boolean | number;
  capacity?: DecimalString | null;
  total_subscribed?: DecimalString;
  starts_at?: string | null;
  ends_at?: string | null;
  terms_version: string;
  metadata?: unknown;
};

export type PortfolioRow = {
  symbol: string;
  principal?: DecimalString;
  pending_stake?: DecimalString;
  active_stake?: DecimalString;
  pending_unstake?: DecimalString;
  native_gross_rewards?: DecimalString;
  validator_fees?: DecimalString;
  network_fees?: DecimalString;
  platform_commission?: DecimalString;
  claimable_native_rewards?: DecimalString;
  claimable_exatoken?: DecimalString;
};

export type StakingPosition = {
  id?: number;
  public_id: string;
  staking_product_id: number;
  staking_asset_id: number;
  symbol: string;
  network: string;
  product_name: string;
  principal_amount: DecimalString;
  active_principal_amount?: DecimalString;
  pending_stake_amount?: DecimalString;
  pending_unstake_amount?: DecimalString;
  total_native_gross_rewards?: DecimalString;
  total_native_net_rewards?: DecimalString;
  total_exatoken_bonus_rewards?: DecimalString;
  claimed_native_rewards?: DecimalString;
  claimed_exatoken_rewards?: DecimalString;
  status: StakingPositionStatus;
  auto_compound_enabled?: boolean | number;
  opened_at?: string;
  delegation_submitted_at?: string | null;
  activation_at?: string | null;
  lock_ends_at?: string | null;
  unbonding_ends_at?: string | null;
  withdrawable_at?: string | null;
  completed_at?: string | null;
  terms_version?: string;
  metadata?: unknown;
};

export type StakingReward = {
  id: number;
  staking_position_id: number;
  user_id: number;
  symbol: string;
  eligible_principal?: DecimalString;
  gross_native_reward?: DecimalString;
  validator_fee_share?: DecimalString;
  network_fee_share?: DecimalString;
  platform_fee?: DecimalString;
  net_native_reward?: DecimalString;
  exatoken_bonus_amount?: DecimalString;
  status: string;
  period_start?: string;
  period_end?: string;
  distributed_at?: string | null;
};

export type StakingTransaction = {
  id: number;
  public_id: string;
  staking_position_id?: number | null;
  symbol: string;
  transaction_type: string;
  amount: DecimalString;
  fee_amount?: DecimalString;
  net_amount?: DecimalString;
  blockchain_transaction_hash?: string | null;
  status: string;
  processed_at?: string | null;
  created_at?: string;
};

export type StakingNetworkStatus = {
  id?: number;
  staking_asset_id?: number;
  symbol: string;
  network: string;
  status: string;
  metadata?: unknown;
  updated_at?: string;
  created_at?: string;
};

export type StakingApyHistory = {
  id?: number;
  symbol: string;
  amount?: DecimalString;
  status?: string;
  recorded_at?: string;
  created_at?: string;
  metadata?: unknown;
};

export type StakingTerms = {
  terms_version: string;
  native_rewards_source?: string;
  excluded_native_pos_assets?: string[];
  mainnet_activation?: string;
};

export type ExaTokenCampaign = {
  id: number;
  name: string;
  slug: string;
  status: string;
  budget_amount: DecimalString;
  reserved_amount?: DecimalString;
  distributed_amount?: DecimalString;
  per_user_cap?: DecimalString | null;
  starts_at?: string | null;
  ends_at?: string | null;
  eligibility_rules?: unknown;
  metadata?: unknown;
};

export type StakeRequest = {
  staking_product_id: number;
  amount: DecimalString;
  auto_compound: boolean;
  terms_version: string;
  transaction_pin?: string;
  two_factor_code?: string;
  idempotency_key: string;
};

export type UnstakeRequest = {
  amount?: DecimalString;
  transaction_pin?: string;
  two_factor_code?: string;
  idempotency_key: string;
};

export type ApiEnvelope<T> = {
  data: T;
  status?: string;
  message?: string;
};
