import Decimal from "decimal.js";
import type { PortfolioRow, StakingAsset, StakingPosition, StakingProduct } from "./types";

export const NATIVE_POS_SYMBOLS = ["SOL", "ETH", "ADA", "BNB", "AVAX", "SUI", "DOT", "ATOM", "NEAR", "XTZ", "POL"];
export const EXCLUDED_NATIVE_STAKING_SYMBOLS = ["XRP", "BTC", "USDT", "USDC", "PI"];

export function decimal(value: string | number | null | undefined): Decimal {
  try {
    return new Decimal(value ?? 0);
  } catch {
    return new Decimal(0);
  }
}

export function addDecimal(values: Array<string | number | null | undefined>): string {
  return values.reduce((sum, value) => sum.plus(decimal(value)), new Decimal(0)).toFixed();
}

export function compareDecimal(a: string | number | null | undefined, b: string | number | null | undefined): number {
  return decimal(a).cmp(decimal(b));
}

export function formatAssetAmount(value: string | number | null | undefined, symbol = "", precision = 6): string {
  const amount = decimal(value);
  const fixed = amount.abs().gte(1000) ? amount.toDecimalPlaces(2) : amount.toDecimalPlaces(precision);
  return `${fixed.toNumber().toLocaleString(undefined, { maximumFractionDigits: precision })}${symbol ? ` ${symbol}` : ""}`;
}

export function formatFiat(value: string | number | null | undefined, currency = "USD", hidden = false): string {
  if (hidden) return "••••••";
  return new Intl.NumberFormat(undefined, { style: "currency", currency, maximumFractionDigits: 2 }).format(decimal(value).toNumber());
}

export function formatPercent(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === "") return "Not published";
  const rate = decimal(value);
  const asPercent = rate.lte(1) ? rate.times(100) : rate;
  return `${asPercent.toDecimalPlaces(2).toFixed()}%`;
}

export function formatDuration(seconds?: number | null, days?: number | null): string {
  if (days && days > 0) return `${days} days`;
  if (!seconds || seconds <= 0) return "Flexible";
  const d = Math.floor(seconds / 86400);
  if (d >= 1) return `${d} days`;
  const h = Math.floor(seconds / 3600);
  return h > 0 ? `${h} hours` : `${Math.max(1, Math.floor(seconds / 60))} min`;
}

export function formatDateTime(value?: string | null): string {
  if (!value) return "Not confirmed";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not confirmed";
  return new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(date);
}

export function boolish(value: boolean | number | string | null | undefined): boolean {
  return value === true || value === 1 || value === "1";
}

export function normalizeStatus(status?: string): string {
  return String(status || "unknown").replaceAll("_", " ");
}

export function positionStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    pending: "Pending",
    reserved: "Reserved",
    batching: "Awaiting Delegation",
    awaiting_signature: "Awaiting Signature",
    delegation_submitted: "Delegation Submitted",
    awaiting_activation: "Awaiting Activation",
    active: "Active",
    partial_unstake_pending: "Partial Unstake Pending",
    unstaking: "Unstaking",
    unbonding: "Unbonding",
    withdrawable: "Withdrawable",
    releasing: "Releasing",
    completed: "Completed",
    cancelled: "Cancelled",
    failed: "Failed",
    suspended: "Paused",
    slashed: "Slashed",
  };
  return labels[status] ?? normalizeStatus(status);
}

export function isProductOperational(product: StakingProduct, asset?: StakingAsset): { ok: boolean; reason: string } {
  if (EXCLUDED_NATIVE_STAKING_SYMBOLS.includes(product.symbol?.toUpperCase())) return { ok: false, reason: "Not available for Native PoS Staking" };
  if (product.status !== "active") return { ok: false, reason: "Product unavailable" };
  if (asset) {
    if (boolish(asset.emergency_paused)) return { ok: false, reason: "Staking paused" };
    if (!boolish(asset.native_staking_enabled)) return { ok: false, reason: "Asset disabled" };
    if (!boolish(asset.new_positions_enabled)) return { ok: false, reason: "New positions disabled" };
    if (asset.provider_health && asset.provider_health.ready !== true) return { ok: false, reason: asset.provider_health.status || "Provider unavailable" };
    if (!["testnet", "integration_testing", "internal_testing", "limited_release", "production"].includes(asset.readiness_status)) {
      return { ok: false, reason: "Readiness checks pending" };
    }
  }
  if (product.capacity && compareDecimal(product.total_subscribed, product.capacity) >= 0) return { ok: false, reason: "Capacity reached" };
  return { ok: true, reason: "Available" };
}

export function estimateReward(principal: string, apy: string | null | undefined, days: number): string {
  if (!principal || compareDecimal(principal, "0") <= 0 || !apy) return "0";
  const rate = decimal(apy).lte(1) ? decimal(apy) : decimal(apy).div(100);
  return decimal(principal).times(rate).times(new Decimal(days).div(365)).toFixed();
}

export function percentageAmount(balance: string | null | undefined, percent: number, precision = 8): string {
  return decimal(balance).times(percent).div(100).toDecimalPlaces(precision, Decimal.ROUND_DOWN).toFixed();
}

export function portfolioTotals(rows: PortfolioRow[], positions: StakingPosition[]) {
  const activePrincipal = addDecimal(rows.map((row) => row.active_stake));
  const pendingStake = addDecimal(rows.map((row) => row.pending_stake));
  const pendingUnstake = addDecimal(rows.map((row) => row.pending_unstake));
  const nativeRewards = addDecimal(rows.map((row) => row.native_gross_rewards));
  const claimableNative = addDecimal(rows.map((row) => row.claimable_native_rewards));
  const claimableExa = addDecimal(rows.map((row) => row.claimable_exatoken));
  const exaBonus = addDecimal(positions.map((position) => position.total_exatoken_bonus_rewards));
  return {
    activePrincipal,
    pendingStake,
    pendingUnstake,
    nativeRewards,
    claimableNative,
    exaBonus,
    claimableExa,
    activeCount: positions.filter((position) => position.status === "active").length,
  };
}

export function claimableNativeRewards(position: StakingPosition): string {
  return Decimal.max(decimal(position.total_native_net_rewards).minus(decimal(position.claimed_native_rewards)), new Decimal(0)).toFixed();
}

export function claimableExaRewards(position: StakingPosition): string {
  return Decimal.max(decimal(position.total_exatoken_bonus_rewards).minus(decimal(position.claimed_exatoken_rewards)), new Decimal(0)).toFixed();
}

export function canUnstakePosition(position: StakingPosition): boolean {
  return ["active", "withdrawable"].includes(position.status) && compareDecimal(position.active_principal_amount, "0") > 0;
}

export function canToggleAutoCompound(position: StakingPosition): boolean {
  return position.status === "active";
}

export function nextPositionMilestone(position: StakingPosition): string {
  if (position.status === "withdrawable") return "Principal can be released after backend confirmation.";
  if (position.status === "unbonding") return position.unbonding_ends_at ? `Expected completion ${formatDateTime(position.unbonding_ends_at)}.` : "Network unbonding is in progress.";
  if (position.status === "unstaking" || position.status === "partial_unstake_pending") return "Unstake request is queued for network submission.";
  if (position.status === "awaiting_activation" || position.status === "delegation_submitted" || position.status === "batching") return "Waiting for delegation and network activation.";
  if (position.status === "active") return "Position is earning network rewards when settlements are verified.";
  if (position.status === "completed") return "Principal has returned to available balance.";
  if (position.status === "failed") return "This request did not complete and should be reviewed.";
  if (position.status === "slashed") return "A validated slashing event affected this position.";
  return "Position updates appear here as blockchain and ledger events are confirmed.";
}

export function mapApiError(error: unknown): string {
  const candidate = error as { code?: string; message?: string; status?: number };
  const mapped: Record<string, string> = {
    STAKING_ASSET_DISABLED: "This asset is not enabled for staking.",
    STAKING_PRODUCT_UNAVAILABLE: "This staking product is not accepting positions.",
    INSUFFICIENT_AVAILABLE_BALANCE: "Your available balance is not enough for this stake.",
    BELOW_MINIMUM_STAKE: "The amount is below the product minimum.",
    ABOVE_MAXIMUM_STAKE: "The amount is above the product maximum.",
    PRODUCT_CAPACITY_REACHED: "This product has reached capacity.",
    POSITION_NOT_ACTIVE: "This position is not active yet.",
    LOCK_PERIOD_ACTIVE: "This position is still inside its lock period.",
    UNSTAKING_ALREADY_REQUESTED: "An unstake request is already in progress.",
    NETWORK_UNAVAILABLE: "The staking network is currently unavailable.",
    PROVIDER_UNAVAILABLE: "The staking provider is currently unavailable.",
    TERMS_NOT_ACCEPTED: "Please accept the staking terms before continuing.",
    DUPLICATE_REQUEST: "This request was already submitted.",
    STAKING_EMERGENCY_PAUSED: "Staking is paused for this network.",
    TWO_FACTOR_REQUIRED: "Enter your two-factor code to continue.",
    INVALID_TRANSACTION_PIN: "The transaction PIN was not accepted.",
    EXATOKEN_CAMPAIGN_UNAVAILABLE: "This ExaToken campaign is not available.",
    EXATOKEN_RESERVE_INSUFFICIENT: "The ExaToken reward reserve is insufficient for new bonuses.",
  };
  if (candidate.code && mapped[candidate.code]) return mapped[candidate.code];
  const message = candidate.message || "The request could not be completed.";
  if (/stack|sql|database|rpc url|private|secret|signer/i.test(message)) return "The request could not be completed safely. Please try again later.";
  return message;
}
