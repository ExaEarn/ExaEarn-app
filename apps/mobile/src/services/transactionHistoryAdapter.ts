import {
  decimalToMinorUnits,
  mapBackendTransactionResult,
  type TransactionResult,
  type TransactionResultStatus,
} from "./transactionResultMapper";
import { getCommittedOfflineSimulationTransactions } from "./exaPayOfflineSimulator";

type RequestOptions = RequestInit & { headers?: Record<string, string> };
export type HistoryRequest = <T>(path: string, options?: RequestOptions) => Promise<T>;

export type HistorySource =
  | "wallet"
  | "exchange"
  | "convert"
  | "card"
  | "staking"
  | "giftcard"
  | "p2p"
  | "exapay";

export type HistorySourceProgress = {
  page: number;
  hasMore: boolean;
  cursor?: string;
};

export type HistoryProgress = Partial<Record<HistorySource, HistorySourceProgress>>;

export type HistoryTransaction = {
  id: string;
  source: HistorySource;
  sourceLabel: string;
  type: string;
  title: string;
  description: string;
  amount: string;
  asset: string;
  direction: "debit" | "credit" | "neutral";
  status: TransactionResultStatus;
  timestamp: string;
  reference: string;
  network?: string;
  walletId?: string;
  searchableText: string;
  detail: TransactionResult;
};

export type HistoryLoadResult = {
  items: HistoryTransaction[];
  progress: HistoryProgress;
  errors: string[];
};

export type WalletHistoryFilters = {
  asset?: string;
  type?: string;
  status?: string;
  direction?: "debit" | "credit";
  network?: string;
  wallet?: string;
  minAmount?: string;
  maxAmount?: string;
  from?: string;
  to?: string;
};

export type HistoryAssetOption = {
  currency: string;
  decimals: number;
  network?: string;
};

const pageSize = 30;

type PageResult = {
  items: Record<string, unknown>[];
  hasMore: boolean;
  cursor?: string;
};

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value) && typeof value === "object" && !Array.isArray(value);
}

function recordArray(value: unknown): Record<string, unknown>[] {
  return Array.isArray(value) ? value.filter(isRecord) : [];
}

function pageResult(payload: unknown, requestedPage: number, requestedPageSize = pageSize): PageResult {
  const outer = isRecord(payload) ? payload : {};
  const data = isRecord(outer.data) || Array.isArray(outer.data) ? outer.data : outer;
  if (Array.isArray(data)) {
    const meta = isRecord(outer.meta) ? outer.meta : {};
    return {
      items: recordArray(data),
      hasMore: meta.has_more === true,
      cursor: typeof meta.next_cursor === "string" ? meta.next_cursor : undefined,
    };
  }

  const page = isRecord(data) ? data : {};
  const items = recordArray(page.data ?? page.items);
  const currentPage = Number(page.current_page ?? requestedPage);
  const lastPage = Number(page.last_page);
  const hasMore = Number.isFinite(lastPage)
    ? currentPage < lastPage
    : Boolean(page.next_page_url) || items.length >= requestedPageSize;

  return {
    items,
    hasMore,
    cursor: typeof page.next_cursor === "string" ? page.next_cursor : undefined,
  };
}

function stringValue(record: Record<string, unknown>, ...keys: string[]): string {
  for (const key of keys) {
    const value = record[key];
    if (typeof value === "string" || typeof value === "number") {
      const text = String(value).trim();
      if (text) return text;
    }
  }
  return "";
}

function amountValue(record: Record<string, unknown>, ...keys: string[]): string {
  const value = stringValue(record, ...keys);
  return /^-?\d+(?:\.\d+)?$/.test(value) ? value : "";
}

function dateValue(record: Record<string, unknown>, ...keys: string[]): string {
  const value = stringValue(record, ...keys);
  const date = value ? new Date(value) : null;
  return date && !Number.isNaN(date.getTime()) ? date.toISOString() : "";
}

function normalizedStatus(record: Record<string, unknown>, source: HistorySource): TransactionResultStatus {
  const status = stringValue(record, "status", "transaction_status", "payment_status", "settlement_status", "state")
    .toLowerCase()
    .replace(/[\s-]+/g, "_");
  const known: Record<string, TransactionResultStatus> = {
    completed: "SUCCESS",
    complete: "SUCCESS",
    success: "SUCCESS",
    succeeded: "SUCCESS",
    settled: "SUCCESS",
    confirmed: "SUCCESS",
    credited: "SUCCESS",
    executed: source === "exchange" ? "PROCESSING" : "SUCCESS",
    filled: source === "exchange" ? "PROCESSING" : "SUCCESS",
    pending: "PENDING",
    queued: "PENDING",
    processing: "PROCESSING",
    in_progress: "PROCESSING",
    failed: "FAILED",
    rejected: "FAILED",
    cancelled: "CANCELLED",
    canceled: "CANCELLED",
    offline_committed: "OFFLINE_COMMITTED",
    reconciliation_pending: "RECONCILIATION_PENDING",
    pending_reconciliation: "RECONCILIATION_PENDING",
    recovery_required: "RECOVERY_REQUIRED",
    redeemed: "REDEEMED",
    unknown: "UNKNOWN",
  };
  return known[status] ?? "UNKNOWN";
}

function typeLabel(value: string) {
  return value
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/(^|\s)\S/g, (letter) => letter.toUpperCase());
}

function historyType(source: HistorySource, record: Record<string, unknown>, userId?: string | number) {
  const rawType = stringValue(record, "type", "transaction_type", "action", "side");
  if (source === "wallet") return typeLabel(rawType || "Wallet transaction");
  if (source === "exchange") {
    const pair = stringValue(record, "pair", "symbol");
    const buyOrder = isRecord(record.buy_order) ? record.buy_order : {};
    const sellOrder = isRecord(record.sell_order) ? record.sell_order : {};
    const isBuyer = userId !== undefined && String(buyOrder.user_id ?? "") === String(userId);
    const isSeller = userId !== undefined && String(sellOrder.user_id ?? "") === String(userId);
    const side = isBuyer ? "Buy" : isSeller ? "Sell" : rawType ? typeLabel(rawType) : "Execution";
    return `Spot trading · ${side}${pair ? ` ${pair}` : ""}`;
  }
  if (source === "convert") return "Convert";
  if (source === "card") return typeLabel(rawType || "Card payment");
  if (source === "staking") return typeLabel(rawType || "Staking activity");
  if (source === "giftcard") return typeLabel(rawType || "Giftcard order");
  if (source === "p2p") return typeLabel(rawType || "P2P trade");
  return "ExaPay offline payment";
}

function directionFor(source: HistorySource, record: Record<string, unknown>, type: string): HistoryTransaction["direction"] {
  const explicit = stringValue(record, "direction").toLowerCase();
  if (["debit", "outgoing", "sent"].includes(explicit)) return "debit";
  if (["credit", "incoming", "received"].includes(explicit)) return "credit";

  const normalizedType = type.toLowerCase().replace(/[\s-]+/g, "_");
  if (source === "wallet") {
    if (/deposit|receive|credit|refund|staking_unlock|staking_reward|nft_sale|lottery_reward|referral_reward|platform_reward|sports_payout|agri_payout|game_reward|edtech_reward|giftcard_sell|giftcard_refund|p2p_escrow_release|p2p_escrow_return/.test(normalizedType)) return "credit";
    if (/withdraw|send|debit|internal_transfer|staking_lock|nft_purchase|lottery_bet|subscription_payment|giftcard_buy|p2p_escrow_lock/.test(normalizedType)) return "debit";
    return "neutral";
  }
  if (source === "exchange") {
    if (/\bbuy\b/i.test(type)) return "credit";
    if (/\bsell\b/i.test(type)) return "debit";
    return "neutral";
  }
  if (source === "card") return /refund|reversal|credit/.test(normalizedType) ? "credit" : "debit";
  if (source === "staking") return /reward|claim|unstake|withdraw|release/.test(normalizedType) ? "credit" : "debit";
  if (source === "giftcard") return /sell|payout/.test(normalizedType) ? "credit" : "debit";
  if (source === "exapay") return "debit";
  if (source === "convert") return "neutral";
  return "neutral";
}

function inferPrecision(value: string) {
  const fraction = value.split(".")[1] ?? "";
  const trimmedFraction = fraction.replace(/0+$/, "");
  return Math.min(8, Math.max(2, trimmedFraction.length));
}

function sourceLabel(source: HistorySource) {
  return {
    wallet: "Wallet",
    exchange: "Exchange",
    convert: "Convert",
    card: "ExaCard",
    staking: "Earn",
    giftcard: "Giftcards",
    p2p: "P2P",
    exapay: "ExaPay · simulation",
  }[source];
}

function normalizeRecord(
  source: HistorySource,
  record: Record<string, unknown>,
  userId?: string | number,
  assetRegistry: HistoryAssetOption[] = [],
): HistoryTransaction | null {
  const type = historyType(source, record, userId);
  const amount = amountValue(
    record,
    ...(source === "card"
      ? ["billing_amount", "transaction_amount", "amount"]
      : source === "exchange"
        ? ["quantity", "amount", "filled_quantity"]
        : source === "staking"
          ? ["amount", "principal_amount", "reward_amount", "net_amount"]
          : source === "p2p"
            ? ["crypto_amount", "amount", "value"]
          : source === "giftcard"
            ? ["amount", "card_value", "total"]
            : ["amount", "amount_sent", "value"]),
  );
  const asset = stringValue(
    record,
    ...(source === "card"
      ? ["billing_currency", "transaction_currency", "currency"]
      : source === "exchange"
        ? ["base_asset", "asset", "currency"]
        : source === "staking"
          ? ["symbol", "asset", "currency"]
          : source === "giftcard"
            ? ["currency", "asset"]
            : ["currency", "asset", "from_currency", "symbol"]),
  );
  const normalizedAsset = source === "exchange" && !asset
    ? stringValue(record, "pair", "symbol").split(/[/-]/)[0] || asset
    : asset;
  const reference = stringValue(
    record,
    "reference",
    "provider_reference",
    "transaction_id",
    "public_id",
    "transaction_uuid",
    "swap_id",
    "trade_uuid",
    "order_uuid",
    "id",
  );
  const timestamp = dateValue(
    record,
    "created_at",
    "executed_at",
    "provider_created_at",
    "timestamp",
    "updated_at",
  );
  if (!reference || !timestamp) return null;

  const status = normalizedStatus(record, source);
  const direction = directionFor(source, record, type);
  const network = stringValue(record, "network", "chain");
  const walletId = stringValue(record, "wallet_id", "wallet_uuid")
    || (isRecord(record.metadata) ? stringValue(record.metadata, "wallet_id", "wallet_uuid") : "");
  const merchant = stringValue(record, "merchant", "merchant_name", "brand", "recipient_name", "counterparty");
  const pair = stringValue(record, "pair", "symbol");
  const toCurrency = stringValue(record, "to_currency", "target_currency");
  const title = merchant || pair || (source === "convert" && asset && toCurrency ? `${asset} → ${toCurrency}` : "") || type;
  const description = stringValue(record, "description", "note", "memo") || `${sourceLabel(source)} · ${type}`;
  const suppliedPrecision = Number(record.precision ?? record.decimals ?? (isRecord(record.metadata) ? record.metadata.precision ?? record.metadata.decimals : undefined));
  const registryPrecision = assetRegistry.find((entry) => entry.currency.toUpperCase() === normalizedAsset.toUpperCase())?.decimals;
  const precision = Number.isInteger(suppliedPrecision) && suppliedPrecision >= 0 && suppliedPrecision <= 18
    ? suppliedPrecision
    : Number.isInteger(registryPrecision) && (registryPrecision as number) >= 0 && (registryPrecision as number) <= 18
      ? registryPrecision as number
      : inferPrecision(amount || "0");
  const minorAmount = amount ? decimalToMinorUnits(amount.replace(/^-/, ""), precision) : undefined;
  const detailRecord = {
    ...record,
    reference,
    status: stringValue(record, "status", "transaction_status", "payment_status", "settlement_status", "state"),
    created_at: timestamp,
    amount,
    currency: normalizedAsset,
    transaction_hash: record.transaction_hash ?? record.tx_hash,
    fee_minor: record.fee_minor,
  };
  const detail = mapBackendTransactionResult(
    { data: detailRecord },
    {
      type,
      fallbackReference: reference,
      amount: amount.replace(/^-/, ""),
      amountMinor: minorAmount,
      asset: normalizedAsset,
      precision,
      source: stringValue(record, "sender", "from_address"),
      destination: stringValue(
        record,
        ...(source === "card"
          ? ["destination", "recipient", "to_address", "merchant"]
          : ["destination", "recipient", "to_address"]),
      ),
      timestampLabel: "Date and time",
    },
  );
  if (amount && normalizedAsset) detail.amountDisplay = `${amount.replace(/^-/, "")} ${normalizedAsset}`;
  if (source === "exchange" && ["filled", "executed"].includes(stringValue(record, "status", "state").toLowerCase())) {
    detail.confirmationStatus = "Trade execution record; wallet settlement is shown in the ledger.";
  }

  if (!record.fee_minor && amount) {
    const fee = amountValue(record, "fee");
    if (fee) {
      detail.feeDisplay = `${fee} ${stringValue(record, "fee_currency", "fee_asset") || normalizedAsset}`;
      const feePrecision = precision;
      const feeMinor = decimalToMinorUnits(fee, feePrecision);
      if (feeMinor !== undefined) {
        detail.feeMinor = feeMinor;
        detail.feePrecision = feePrecision;
        detail.feeAsset = normalizedAsset;
      }
      const settlementStatus = stringValue(record, "settlement_status", "reconciliation_status");
      if (settlementStatus) detail.confirmationStatus = typeLabel(settlementStatus);
    }
  }

  return {
    id: `${source}:${reference}`,
    source,
    sourceLabel: sourceLabel(source),
    type,
    title,
    description,
    amount: amount.replace(/^-/, ""),
    asset: normalizedAsset || "—",
    direction,
    status,
    timestamp,
    reference,
    network: network || undefined,
    walletId: walletId || undefined,
    searchableText: [
      title,
      description,
      type,
      normalizedAsset,
      reference,
      merchant,
      stringValue(record, "sender", "sender_name", "source", "from_address"),
      stringValue(record, "recipient", "recipient_name", "destination", "to_address"),
      toCurrency,
      network,
      walletId,
    ].join(" ").toLowerCase(),
    detail: {
      ...detail,
      status,
      sourceOfTruth: "backend",
      simulation: false,
    },
  };
}

function normalizeLocalSimulation(record: {
  reference: string;
  recipientLabel: string;
  amountMinor: number;
  method: string;
  createdAt: string;
}): HistoryTransaction {
  const date = new Date(record.createdAt);
  const timestamp = Number.isNaN(date.getTime()) ? new Date().toISOString() : date.toISOString();
  const amount = (record.amountMinor / 100).toFixed(2);
  const detail: TransactionResult = {
    reference: record.reference,
    referenceLabel: "Local simulation reference",
    timestamp,
    type: `ExaPay offline payment · ${record.method}`,
    status: "OFFLINE_COMMITTED",
    amountMinor: record.amountMinor,
    amountLabel: "Simulated amount",
    asset: "simUSDT",
    precision: 2,
    destination: record.recipientLabel,
    confirmationStatus: "Local simulation only · not settled online",
    sourceOfTruth: "local",
    simulation: true,
    message: "This record exists only in this app session and is not an online payment or ledger transaction.",
  };
  return {
    id: `exapay-simulation:${record.reference}`,
    source: "exapay",
    sourceLabel: sourceLabel("exapay"),
    type: detail.type,
    title: record.recipientLabel || "Offline payment",
    description: "Local simulation only · not settled online",
    amount,
    asset: "simUSDT",
    direction: "debit",
    status: "OFFLINE_COMMITTED",
    timestamp,
    reference: record.reference,
    searchableText: `${record.reference} ${record.recipientLabel} simusdt exapay offline simulation`.toLowerCase(),
    detail,
  };
}

function unwrapArray(payload: unknown): Record<string, unknown>[] {
  if (!isRecord(payload)) return [];
  const data = payload.data;
  if (Array.isArray(data)) return recordArray(data);
  if (isRecord(data)) {
    if (Array.isArray(data.data)) return recordArray(data.data);
    if (Array.isArray(data.items)) return recordArray(data.items);
  }
  return [];
}

function queryPage(path: string, page: number, cursor?: string, cursorMode = false) {
  const separator = path.includes("?") ? "&" : "?";
  const query = new URLSearchParams({ per_page: String(pageSize) });
  if (cursorMode) query.set("cursor_mode", "1");
  else query.set("page", String(page));
  if (cursor) query.set("cursor", cursor);
  return `${path}${separator}${query.toString()}`;
}

function dedupeAndSort(items: HistoryTransaction[]) {
  const seen = new Set<string>();
  return items
    .filter((item) => {
      const key = item.id.toLowerCase();
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    })
    .sort((left, right) => right.timestamp.localeCompare(left.timestamp) || left.id.localeCompare(right.id));
}

export async function fetchUnifiedTransactionHistory(
  request: HistoryRequest,
  userId?: string | number,
  previousProgress: HistoryProgress = {},
  assetRegistry: HistoryAssetOption[] = [],
): Promise<HistoryLoadResult> {
  const errors: string[] = [];
  const items: HistoryTransaction[] = [];
  const progress: HistoryProgress = { ...previousProgress };
  const pageFor = (source: HistorySource) => previousProgress[source]?.page ?? 0;
  const paginatedSources: Array<{
    source: Extract<HistorySource, "wallet" | "exchange" | "convert" | "giftcard" | "p2p" | "card" | "staking">;
    path: string;
  }> = [
    { source: "wallet", path: "/api/transactions/mine" },
    { source: "exchange", path: "/api/trade/history" },
    { source: "convert", path: "/api/swap/history" },
    { source: "giftcard", path: "/api/giftcard/orders/mine" },
    { source: "p2p", path: "/api/p2p/trades/mine" },
    { source: "card", path: "/api/cards/transactions/mine" },
    { source: "staking", path: "/api/v1/staking/transactions" },
  ];

  const sourcesToLoad = paginatedSources
    .filter(({ source }) => !Object.prototype.hasOwnProperty.call(previousProgress, source) || previousProgress[source]?.hasMore)
    .map(({ source, path }) => {
      const page = pageFor(source) + 1;
      return {
        source,
        promise: request<unknown>(queryPage(path, page, previousProgress[source]?.cursor, ["wallet", "card", "staking", "p2p"].includes(source)), { method: "GET" }).then((response) => ({
          source,
          page,
          result: pageResult(response, page),
        })),
      };
    });

  const settledPages = await Promise.allSettled(sourcesToLoad.map(({ promise }) => promise));
  for (const [index, result] of settledPages.entries()) {
    if (result.status === "rejected") {
      const source = sourcesToLoad[index]?.source;
      errors.push(`${source ? sourceLabel(source) : "Activity"}: ${result.reason instanceof Error ? result.reason.message : "Unable to load records."}`);
      if (source) {
        progress[source] = {
          page: pageFor(source),
          hasMore: false,
        };
      }
      continue;
    }
    const { source, page, result: pageData } = result.value;
    progress[source] = { page, hasMore: pageData.hasMore, cursor: pageData.cursor };
    items.push(...pageData.items.map((record) => normalizeRecord(source, record, userId, assetRegistry)).filter((item): item is HistoryTransaction => Boolean(item)));
  }

  const firstLoad = !Object.keys(previousProgress).length;
  if (firstLoad || !progress.exapay) {
    const localRecords = getCommittedOfflineSimulationTransactions().map(normalizeLocalSimulation);
    items.push(...localRecords);
    progress.exapay = { page: 1, hasMore: false };
  }

  return {
    items: dedupeAndSort(items),
    progress,
    errors,
  };
}

export async function searchWalletTransactionHistory(
  request: HistoryRequest,
  search: string,
  userId?: string | number,
  filters: WalletHistoryFilters = {},
  assetRegistry: HistoryAssetOption[] = [],
): Promise<HistoryTransaction[]> {
  const query = new URLSearchParams({
    per_page: "100",
    cursor_mode: "1",
  });
  if (search.trim()) query.set("search", search.trim());
  if (filters.asset && filters.asset !== "all") query.set("currency", filters.asset.toUpperCase());
  if (filters.type && filters.type !== "all") query.set("type", filters.type.toLowerCase().replace(/[\s-]+/g, "_"));
  if (filters.status && filters.status !== "all") {
    const statusMap: Record<string, string> = {
      SUCCESS: "completed",
      PENDING: "pending",
      PROCESSING: "processing",
      FAILED: "failed",
      CANCELLED: "cancelled",
    };
    if (statusMap[filters.status]) query.set("status", statusMap[filters.status]);
  }
  if (filters.direction) query.set("direction", filters.direction);
  if (filters.network && filters.network !== "all") query.set("network", filters.network);
  if (filters.wallet && filters.wallet !== "all") query.set("wallet_id", filters.wallet);
  if (filters.minAmount) query.set("min_amount", filters.minAmount);
  if (filters.maxAmount) query.set("max_amount", filters.maxAmount);
  if (filters.from) query.set("from", filters.from);
  if (filters.to) query.set("to", filters.to);
  const response = await request<unknown>(`/api/transactions/mine?${query.toString()}`, { method: "GET" });
  return pageResult(response, 1, 100).items
    .map((record) => normalizeRecord("wallet", record, userId, assetRegistry))
    .filter((item): item is HistoryTransaction => Boolean(item));
}

export async function fetchHistoryAssetRegistry(request: HistoryRequest): Promise<HistoryAssetOption[]> {
  const payload = await request<unknown>("/api/wallet/balances", { method: "GET" });
  return unwrapArray(payload).flatMap((record) => {
    const currency = stringValue(record, "currency", "asset").toUpperCase();
    const decimals = Number(record.decimals);
    if (!currency || !Number.isInteger(decimals) || decimals < 0 || decimals > 18) return [];
    return [{ currency, decimals, network: stringValue(record, "network") || undefined }];
  });
}
