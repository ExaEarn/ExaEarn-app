export const TRANSACTION_STATUSES = [
  "SUCCESS",
  "PROCESSING",
  "PENDING",
  "FAILED",
  "CANCELLED",
  "OFFLINE_COMMITTED",
  "RECONCILIATION_PENDING",
  "RECOVERY_REQUIRED",
  "REDEEMED",
];

function normalizeStatus(value) {
  return String(value || "").trim().toLowerCase().replace(/[\s-]+/g, "_");
}

export function mapBackendTransactionResult(response, context = {}) {
  const record = response?.data?.transaction
    ?? response?.transaction
    ?? response?.data?.order
    ?? response?.data?.position
    ?? response?.data
    ?? response
    ?? {};
  const statusValue = normalizeStatus(
    record.transaction_status
      ?? record.payment_status
      ?? record.status
      ?? record.state,
  );
  const reference = record.transaction_reference
    ?? record.reference
    ?? record.public_id
    ?? record.transaction_hash
    ?? context.fallbackReference
    ?? "";
  let status = "PROCESSING";
  if (["confirmed", "settled", "completed", "succeeded", "success"].includes(statusValue)) {
    status = reference ? "SUCCESS" : "PROCESSING";
  } else if (statusValue === "redeemed") {
    status = reference ? "REDEEMED" : "PROCESSING";
  } else if (["pending", "queued", "awaiting_confirmation"].includes(statusValue)) {
    status = "PENDING";
  } else if (["broadcast", "submitted", "accepted", "created", "processing", "in_progress"].includes(statusValue)) {
    status = "PROCESSING";
  } else if (["failed", "rejected", "error"].includes(statusValue)) {
    status = "FAILED";
  } else if (["cancelled", "canceled"].includes(statusValue)) {
    status = "CANCELLED";
  } else if (statusValue === "offline_committed") {
    status = "OFFLINE_COMMITTED";
  } else if (["reconciliation_pending", "pending_reconciliation"].includes(statusValue)) {
    status = "RECONCILIATION_PENDING";
  } else if (["recovery_required", "recovery_needed"].includes(statusValue)) {
    status = "RECOVERY_REQUIRED";
  }

  return {
    reference,
    referenceLabel: record.transaction_reference || record.reference || record.public_id ? "Transaction reference" : "Request reference",
    timestamp: record.created_at || record.timestamp || context.timestamp || new Date().toISOString(),
    type: context.type || "Transaction",
    status,
    amountMinor: context.amountMinor,
    asset: record.asset || record.currency || context.asset,
    precision: Number.isInteger(record.precision) ? record.precision : context.precision,
    feeMinor: Number.isSafeInteger(record.fee_minor) ? record.fee_minor : context.feeMinor,
    feeAsset: record.fee_asset || record.asset || context.asset,
    totalMinor: Number.isSafeInteger(record.total_minor) ? record.total_minor : context.totalMinor,
    source: record.source || context.source,
    destination: record.destination || context.destination,
    network: record.network || context.network,
    transactionHash: record.transaction_hash || "",
    confirmationStatus: record.confirmation_status || status.replace(/_/g, " ").toLowerCase(),
    explorerUrl: getTrustedExplorerUrl(record),
    sourceOfTruth: "backend",
    simulation: false,
  };
}

export function mapExaPayPaymentResult(draft, result) {
  let status = "FAILED";
  if (result.outcome === "offline-committed-simulation") status = "OFFLINE_COMMITTED";
  if (result.outcome === "recovery-required") status = "RECOVERY_REQUIRED";
  if (result.outcome === "cancelled") status = "CANCELLED";
  const totalMinor = draft.feeMinor === null ? undefined : draft.amountMinor + draft.feeMinor;
  return {
    reference: result.reference,
    referenceLabel: "Payment reference",
    timestamp: new Date().toISOString(),
    type: draft.channel === "bluetooth"
      ? "ExaPay Bluetooth transfer"
      : draft.channel === "qr"
        ? "ExaPay QR transfer"
        : "ExaPay online transfer",
    status,
    amountMinor: result.amountMinor,
    asset: draft.asset.symbol,
    precision: draft.asset.precision,
    feeMinor: draft.feeMinor ?? undefined,
    feeAsset: draft.asset.symbol,
    totalMinor: Number.isSafeInteger(totalMinor) ? totalMinor : undefined,
    source: draft.sourceAccount.label,
    destination: draft.recipient.username ? `@${draft.recipient.username}` : draft.recipient.displayName,
    network: draft.asset.network,
    confirmationStatus: status === "OFFLINE_COMMITTED"
      ? "Pending reconciliation · simulation only"
      : "Not settled · no payment service connected",
    sourceOfTruth: "simulation",
    simulation: true,
    message: result.message,
  };
}

export function mapExaPayRedemptionResult(transaction, outcome) {
  return {
    reference: transaction.reference,
    referenceLabel: "Simulation reference",
    timestamp: new Date().toISOString(),
    type: "ExaPay Sync & Redeem",
    status: outcome === "redeemed-simulation"
      ? "REDEEMED"
      : outcome === "recovery-required"
        ? "RECOVERY_REQUIRED"
        : "FAILED",
    amountMinor: transaction.amountMinor,
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
  };
}

function getTrustedExplorerUrl(record) {
  if (typeof record.explorer_url !== "string") return "";
  const network = String(record.network || "").toLowerCase();
  try {
    const url = new URL(record.explorer_url);
    const hosts = network.includes("bitcoin")
      ? ["mempool.space", "blockstream.info"]
      : network.includes("ethereum")
        ? ["etherscan.io"]
        : network.includes("solana")
          ? ["solscan.io"]
          : [];
    if (url.protocol !== "https:" || !hosts.some((host) => url.hostname === host || url.hostname.endsWith(`.${host}`))) return "";
    return url.toString();
  } catch {
    return "";
  }
}
