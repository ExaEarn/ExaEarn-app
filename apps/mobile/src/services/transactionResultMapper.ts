import type {
  ExaPayPaymentDraft,
  ExaPayPaymentResult,
} from "./exaPayPaymentReviewModel";
import type {
  RedemptionOutcome,
  ReconciliationTransaction,
} from "./exaPaySyncRedeemSimulator";

export type TransactionResultStatus =
  | "SUCCESS"
  | "PROCESSING"
  | "PENDING"
  | "FAILED"
  | "CANCELLED"
  | "OFFLINE_COMMITTED"
  | "RECONCILIATION_PENDING"
  | "RECOVERY_REQUIRED"
  | "REDEEMED"
  | "UNKNOWN";

export type TransactionResult = {
  reference: string;
  referenceLabel?: string;
  timestamp: string;
  timestampLabel?: string;
  type: string;
  status: TransactionResultStatus;
  amountMinor?: number;
  amountLabel?: string;
  amountDisplay?: string;
  asset?: string;
  precision?: number;
  feeMinor?: number;
  feeDisplay?: string;
  feeAsset?: string;
  feePrecision?: number;
  totalMinor?: number;
  totalLabel?: string;
  source?: string;
  destination?: string;
  network?: string;
  transactionHash?: string;
  confirmationStatus?: string;
  explorerUrl?: string;
  sourceOfTruth: "backend" | "simulation" | "local";
  simulation?: boolean;
  message?: string;
};

export type BackendTransactionContext = {
  type: string;
  fallbackReference: string;
  amount?: string;
  amountMinor?: number;
  amountLabel?: string;
  asset?: string;
  precision?: number;
  source?: string;
  destination?: string;
  timestampLabel?: string;
};

const knownExplorerHosts: Record<string, string[]> = {
  bitcoin: ["mempool.space", "blockstream.info"],
  ethereum: ["etherscan.io"],
  solana: ["solscan.io"],
};

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value) && typeof value === "object" && !Array.isArray(value);
}

function transactionRecord(response: unknown): Record<string, unknown> {
  if (!isRecord(response)) return {};
  const data = isRecord(response.data) ? response.data : null;
  const nested = isRecord(response.transaction)
    ? response.transaction
    : data && isRecord(data.transaction)
      ? data.transaction
      : data && isRecord(data.order)
        ? data.order
        : data && isRecord(data.position)
          ? data.position
          : null;
  if (nested) return nested;
  return data ?? response;
}

function validDate(value: unknown, fallback: string) {
  if (typeof value !== "string" && typeof value !== "number") return fallback;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? fallback : date.toISOString();
}

export function decimalToMinorUnits(value: string, precision = 2): number | undefined {
  if (!Number.isInteger(precision) || precision < 0 || precision > 8) return undefined;
  const trimmed = value.trim();
  if (!/^(?:\d+)(?:\.\d+)?$/.test(trimmed)) return undefined;
  const [whole = "0", rawFraction = ""] = trimmed.split(".");
  const fraction = rawFraction.replace(/0+$/, "");
  if (fraction.length > precision) return undefined;
  const scale = 10 ** precision;
  const wholeValue = Number(whole);
  if (!Number.isSafeInteger(wholeValue)) return undefined;
  const minor = wholeValue * scale + Number(fraction.padEnd(precision, "0") || "0");
  return Number.isSafeInteger(minor) ? minor : undefined;
}

function backendStatus(record: Record<string, unknown>, hasBackendIdentity: boolean): TransactionResultStatus {
  const status = String(
    record.transaction_status
      ?? record.payment_status
      ?? record.state
      ?? record.status
      ?? "",
  ).trim().toLowerCase().replace(/[\s-]+/g, "_");

  if (["confirmed", "settled", "completed", "succeeded", "success"].includes(status)) {
    return hasBackendIdentity ? "SUCCESS" : "PROCESSING";
  }
  if (status === "redeemed") return hasBackendIdentity ? "REDEEMED" : "PROCESSING";
  if (["broadcast", "submitted", "accepted", "created", "processing", "in_progress"].includes(status)) return "PROCESSING";
  if (["pending", "queued", "awaiting_confirmation"].includes(status)) return "PENDING";
  if (["failed", "rejected", "error"].includes(status)) return "FAILED";
  if (["cancelled", "canceled"].includes(status)) return "CANCELLED";
  if (status === "offline_committed") return "OFFLINE_COMMITTED";
  if (["reconciliation_pending", "pending_reconciliation"].includes(status)) return "RECONCILIATION_PENDING";
  if (["recovery_required", "recovery_needed"].includes(status)) return "RECOVERY_REQUIRED";
  return "UNKNOWN";
}

function trustedExplorerUrl(record: Record<string, unknown>) {
  const urlValue = record.explorer_url;
  const network = String(record.network ?? "").toLowerCase();
  if (typeof urlValue !== "string" || !network) return undefined;
  try {
    const url = new URL(urlValue);
    const hosts = Object.entries(knownExplorerHosts).find(([name]) => network.includes(name))?.[1] ?? [];
    if (url.protocol !== "https:" || !hosts.some((host) => url.hostname === host || url.hostname.endsWith(`.${host}`))) return undefined;
    return url.toString();
  } catch {
    return undefined;
  }
}

export function mapBackendTransactionResult(
  response: unknown,
  context: BackendTransactionContext,
): TransactionResult {
  const record = transactionRecord(response);
  const now = new Date().toISOString();
  const backendReference = record.transaction_reference
    ?? record.reference
    ?? record.public_id
    ?? record.transaction_hash;
  const status = backendStatus(record, Boolean(backendReference));
  const amountMinor = typeof context.amountMinor === "number" && Number.isSafeInteger(context.amountMinor)
    ? context.amountMinor
    : context.amount
      ? decimalToMinorUnits(context.amount, context.precision ?? 2)
      : undefined;
  const responseAmountMinor = record.amount_minor;
  const safeResponseAmount = typeof responseAmountMinor === "number" && Number.isSafeInteger(responseAmountMinor)
    ? responseAmountMinor
    : undefined;
  const feeMinor = typeof record.fee_minor === "number" && Number.isSafeInteger(record.fee_minor) && record.fee_minor >= 0
    ? record.fee_minor
    : undefined;
  const totalMinor = typeof record.total_minor === "number" && Number.isSafeInteger(record.total_minor) && record.total_minor >= 0
    ? record.total_minor
    : undefined;

  return {
    reference: String(backendReference ?? context.fallbackReference),
    referenceLabel: backendReference ? "Transaction reference" : context.fallbackReference ? "Request reference" : "Reference unavailable",
    timestamp: validDate(record.created_at ?? record.timestamp, now),
    timestampLabel: context.timestampLabel ?? "Submitted at",
    type: context.type,
    status,
    amountMinor: safeResponseAmount ?? amountMinor,
    amountLabel: context.amountLabel,
    asset: String(record.asset ?? record.currency ?? context.asset ?? "") || undefined,
    precision: typeof record.precision === "number" && Number.isInteger(record.precision)
      ? record.precision
      : context.precision,
    feeMinor,
    feeAsset: String(record.fee_asset ?? record.asset ?? context.asset ?? "") || undefined,
    totalMinor,
    source: String(record.source ?? context.source ?? "") || undefined,
    destination: String(record.destination ?? context.destination ?? "") || undefined,
    network: String(record.network ?? "") || undefined,
    transactionHash: String(record.transaction_hash ?? "") || undefined,
    confirmationStatus: String(record.confirmation_status ?? "") || undefined,
    explorerUrl: trustedExplorerUrl(record),
    sourceOfTruth: "backend",
    message: status === "FAILED"
      ? String(record.message ?? "The service did not complete this transaction. Check its status before trying again.")
      : undefined,
  };
}

export function mapBlockchainTransactionResult(
  response: unknown,
  context: BackendTransactionContext,
): TransactionResult {
  return mapBackendTransactionResult(response, context);
}

export function mapExaPayPaymentResult(
  draft: ExaPayPaymentDraft,
  result: ExaPayPaymentResult,
): TransactionResult {
  const isOfflineCommit = result.outcome === "offline-committed-simulation";
  const status: TransactionResultStatus = isOfflineCommit
    ? "OFFLINE_COMMITTED"
    : result.outcome === "recovery-required"
      ? "RECOVERY_REQUIRED"
      : result.outcome === "cancelled"
        ? "CANCELLED"
        : "FAILED";
  const channelLabel = result.channel === "bluetooth"
    ? "ExaPay Bluetooth transfer"
    : result.channel === "qr"
      ? "ExaPay QR transfer"
      : "ExaPay online transfer";
  const totalMinor = draft.feeMinor === null ? undefined : draft.amountMinor + draft.feeMinor;

  return {
    reference: result.reference,
    referenceLabel: "Payment reference",
    timestamp: new Date().toISOString(),
    type: channelLabel,
    status,
    amountMinor: result.amountMinor,
    amountLabel: "Amount",
    asset: draft.asset.symbol,
    precision: draft.asset.precision,
    feeMinor: draft.feeMinor ?? undefined,
    feeAsset: draft.asset.symbol,
    totalMinor: Number.isSafeInteger(totalMinor) ? totalMinor : undefined,
    source: draft.sourceAccount.label,
    destination: draft.recipient.username ? `@${draft.recipient.username}` : draft.recipient.displayName,
    network: draft.asset.network,
    confirmationStatus: isOfflineCommit
      ? "Pending reconciliation · simulation only"
      : "Not settled · no payment service connected",
    sourceOfTruth: "simulation",
    simulation: true,
    message: result.message,
  };
}

export function mapExaPayRedemptionResult(
  transaction: ReconciliationTransaction,
  outcome: RedemptionOutcome,
): TransactionResult {
  const status: TransactionResultStatus = outcome === "redeemed-simulation"
    ? "REDEEMED"
    : outcome === "recovery-required"
      ? "RECOVERY_REQUIRED"
      : outcome === "ineligible"
        ? "FAILED"
        : "FAILED";

  return {
    reference: transaction.reference,
    referenceLabel: "Simulation reference",
    timestamp: new Date().toISOString(),
    type: "ExaPay Sync & Redeem",
    status,
    amountMinor: transaction.amountMinor,
    amountLabel: "Simulated redeemed value",
    asset: "simUSDT",
    precision: 2,
    source: transaction.sender,
    destination: "Simulated ExaEarn balance",
    confirmationStatus: outcome === "redeemed-simulation"
      ? "Redeemed in simulation · no real ledger updated"
      : outcome === "recovery-required"
        ? "Recovery required · do not retry"
        : "Not redeemed",
    sourceOfTruth: "simulation",
    simulation: true,
    message: outcome === "redeemed-simulation"
      ? "The simulator confirmed this redemption. It is not a real-money settlement."
      : outcome === "recovery-required"
        ? "The redemption state is uncertain. Reconcile it before retrying."
        : "The simulated redemption was not completed.",
  };
}
