import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  Activity, AlertCircle, ArrowDownLeft, ArrowLeft, ArrowUpDown, ArrowUpRight,
  CalendarDays, Check, ChevronDown, CreditCard, ExternalLink, Filter,
  Gift, LoaderCircle, RefreshCw, RotateCcw, Search, Shuffle, SlidersHorizontal,
  Users, Wallet, X,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import { useLanguage } from "../../context/LanguageContext.jsx";
import TransactionResult from "../../components/TransactionResult/TransactionResult";
import { fetchUserTransactions, fetchWalletAssetRegistry } from "../../services/transactionApi";

const PAGE_SIZE = 30;
const FILTERS = [
  ["type", "Type"], ["status", "Status"], ["currency", "Asset"],
  ["network", "Network"], ["direction", "Direction"], ["wallet_id", "Wallet"],
  ["min_amount", "Minimum amount"], ["max_amount", "Maximum amount"],
  ["from", "From date"], ["to", "To date"],
];
const TYPE_OPTIONS = [
  ["deposit", "Deposit"], ["withdrawal", "Withdrawal"], ["transfer", "Transfer"], ["internal_transfer", "Internal transfer"],
  ["trade", "Trade"], ["buy", "Buy"], ["sell", "Sell"], ["convert", "Convert"],
  ["card_payment", "Card payment"], ["p2p_trade", "P2P trade"], ["staking_lock", "Earn"],
  ["staking", "All Earn activity"], ["staking_reward", "Earn reward"], ["giftcard", "All gift-card activity"],
  ["giftcard_buy", "Gift card"], ["giftcard_sell", "Gift card sale"],
  ["nft_purchase", "NFT"], ["referral_reward", "Referral reward"],
];
const STATUS_OPTIONS = [
  ["completed", "Completed"], ["pending", "Pending"], ["processing", "Processing"],
  ["failed", "Failed"], ["cancelled", "Cancelled"], ["offline_committed_simulation", "Offline committed · simulation"],
  ["reconciliation_pending", "Reconciliation pending"], ["recovery_required", "Recovery required"],
];

function safeString(value, fallback = "") {
  return value === null || value === undefined ? fallback : String(value);
}

function transactionType(type) {
  const normalized = safeString(type).toLowerCase();
  const types = {
    deposit: "Deposit", withdrawal: "Withdrawal", withdraw: "Withdrawal", send: "Transfer",
    internal_transfer: "Transfer", transfer: "Transfer", trade: "Trade", staking_lock: "Earn",
    staking_reward: "Earn reward", referral_reward: "Reward", platform_reward: "Reward",
    lottery_reward: "Reward", nft_purchase: "NFT purchase", lottery_bet: "Lottery",
    giftcard_buy: "Gift card", p2p_escrow_lock: "P2P escrow", p2p_escrow_release: "P2P release",
    p2p_escrow_return: "P2P return", giftcard_sell: "Gift card sale", giftcard_refund: "Gift card refund",
    staking_unlock: "Earn unlock",
  };
  return types[normalized] || normalized.replace(/[_-]+/g, " ").replace(/\b\w/g, (letter) => letter.toUpperCase()) || "Activity";
}

function transactionStatus(value) {
  const normalized = safeString(value).toLowerCase().replace(/[ -]+/g, "_");
  if (["completed", "complete", "success", "succeeded", "settled", "confirmed", "credited"].includes(normalized)) return "Completed";
  if (["failed", "rejected"].includes(normalized)) return "Failed";
  if (["cancelled", "canceled"].includes(normalized)) return "Cancelled";
  if (["processing", "in_progress", "executed", "filled"].includes(normalized)) return "Processing";
  if (["reconciliation_pending", "pending_reconciliation"].includes(normalized)) return "Reconciliation pending";
  if (normalized === "offline_committed_simulation") return "Offline committed · simulation";
  if (normalized === "recovery_required") return "Recovery required";
  if (normalized === "offline_committed") return "Offline committed";
  if (normalized === "redeemed") return "Redeemed";
  if (normalized === "pending" || normalized === "queued") return "Pending";
  return normalized ? transactionType(normalized) : "Unknown";
}

function directionFor(record) {
  const type = safeString(record.type).toLowerCase();
  const source = safeString(record.__history_source, "wallet");
  if (source === "exchange") {
    const side = safeString(record.side || record.type).toLowerCase();
    if (side === "buy") return "credit";
    if (side === "sell") return "debit";
    return "neutral";
  }
  if (source === "convert" || source === "p2p") return "neutral";
  if (source === "exapay") return "debit";
  if (source === "card") return /refund|reversal|credit/.test(type) ? "credit" : "debit";
  if (source === "staking") return /reward|claim|unstake|withdraw|release/.test(type) ? "credit" : "debit";
  if (source === "giftcard") return /sell|payout/.test(type) ? "credit" : "debit";
  if (source === "p2p") return safeString(record.side || record.direction).toLowerCase() === "sell" ? "credit" : "debit";
  if (/deposit|receive|credit|refund|staking_unlock|staking_reward|nft_sale|lottery_reward|referral_reward|platform_reward|giftcard_sell|giftcard_refund|p2p_escrow_release|p2p_escrow_return/.test(type)) return "credit";
  if (/withdraw|send|debit|internal_transfer|transfer|staking_lock|nft_purchase|lottery_bet|giftcard_buy|p2p_escrow_lock/.test(type)) return "debit";
  return Number.parseFloat(record.amount) < 0 ? "debit" : "neutral";
}

function formatDecimal(value, precision) {
  const raw = safeString(value, "0").trim();
  const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(raw);
  if (!match) return raw;
  const [, sign, whole, originalFraction = ""] = match;
  const maxDecimals = Number.isInteger(Number(precision)) && Number(precision) >= 0
    ? Math.min(Number(precision), 18)
    : Math.min(originalFraction.length, 8);
  const fraction = originalFraction.slice(0, maxDecimals).replace(/0+$/, "");
  const groupedWhole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  return `${sign}${groupedWhole}${fraction ? `.${fraction}` : ""}`;
}

function normalizeTransaction(record, assetOptions) {
  if (!record || typeof record !== "object") return null;
  const source = safeString(record.__history_source, "wallet");
  const sourceIds = {
    wallet: ["transaction_id", "reference", "id"],
    exchange: ["trade_uuid", "order_uuid", "order_id", "id"],
    convert: ["swap_id", "reference", "id"],
    giftcard: ["order_uuid", "reference", "id"],
    p2p: ["trade_uuid", "provider_reference", "public_id", "reference", "id"],
    card: ["transaction_uuid", "provider_reference", "id"],
    staking: ["transaction_uuid", "reference", "id"],
    exapay: ["reference", "id"],
  };
  const rawId = safeString(...sourceIds[source].map((key) => record[key]).filter((value) => value !== null && value !== undefined));
  const id = `${source}:${rawId}`;
  const timestamp = safeString(record.created_at || record.executed_at || record.timestamp || record.updated_at);
  const date = new Date(timestamp);
  if (!rawId || Number.isNaN(date.getTime())) return null;
  const amountKeys = {
    card: ["billing_amount", "transaction_amount", "amount"],
    exchange: ["quantity", "amount", "filled_quantity"],
    staking: ["amount", "principal_amount", "reward_amount", "net_amount"],
    p2p: ["crypto_amount", "amount", "value"],
    giftcard: ["amount", "card_value", "total"],
    convert: ["amount", "from_amount", "value"],
  };
  const currencyKeys = {
    card: ["billing_currency", "transaction_currency", "currency"],
    exchange: ["base_asset", "asset", "currency"],
    staking: ["symbol", "asset", "currency"],
    p2p: ["currency", "asset", "crypto_currency"],
    giftcard: ["currency", "asset"],
    convert: ["from_currency", "currency", "asset", "symbol"],
  };
  const amountValue = (amountKeys[source] || ["amount", "value"]).map((key) => record[key]).find((value) => value !== null && value !== undefined);
  const currencyValue = (currencyKeys[source] || ["currency", "asset"]).map((key) => record[key]).find((value) => value !== null && value !== undefined);
  const currency = safeString(currencyValue, "—").toUpperCase();
  const asset = assetOptions.find((item) => item.currency === currency);
  const direction = directionFor(record);
  const rawAmount = safeString(amountValue, "0");
  const absoluteAmount = rawAmount.startsWith("-") ? rawAmount.slice(1) : rawAmount;
  const amount = formatDecimal(absoluteAmount, record.precision ?? record.decimals ?? asset?.decimals);
  const signedAmount = direction === "credit" ? `+${amount}` : direction === "debit" ? `−${amount}` : amount;
  const rawStatus = safeString(record.status || record.state || record.payment_status);
  const exchangeExecution = source === "exchange" && ["completed", "complete", "success", "succeeded", "filled", "executed"].includes(rawStatus.toLowerCase());
  const statusForUi = exchangeExecution ? "processing" : rawStatus;
  const feeValue = record.fee ?? record.transaction_fee ?? record.network_fee;
  const fee = feeValue === null || feeValue === undefined
    ? null
    : `${formatDecimal(feeValue, record.precision ?? record.decimals ?? asset?.decimals)} ${currency}`;
  const merchant = safeString(record.merchant_name || record.merchant || record.counterparty || record.sender_name || record.sender || record.recipient_name || record.recipient || record.description || record.pair || record.symbol);
  const searchText = [record.merchant, record.merchant_name, record.sender, record.sender_name, record.recipient, record.recipient_name, record.counterparty, record.description, record.pair, record.symbol].map((value) => safeString(value)).join(" ");
  const ref = safeString(record.reference || record.provider_reference || record.public_id || record.transaction_id || record.swap_id || record.trade_uuid || record.order_uuid || rawId, "—");
  return {
    id,
    source,
    sourceLabel: ({ wallet: "Wallet", exchange: "Exchange", convert: "Convert", giftcard: "Gift card", p2p: "P2P", card: "ExaCard", staking: "Earn", exapay: "ExaPay · simulation" })[source] || "Activity",
    timestamp: date,
    type: transactionType(record.type || (source === "convert" ? "convert" : source === "exchange" ? "trade" : source === "card" ? "card_payment" : `${source}_activity`)),
    rawType: safeString(record.type),
    rawStatus,
    status: exchangeExecution ? "Execution recorded" : transactionStatus(statusForUi),
    currency,
    amount: signedAmount,
    absoluteAmount: amount,
    rawAmount: absoluteAmount,
    direction,
    reference: ref,
    txHash: safeString(record.tx_hash),
    fee,
    network: safeString(record.network || record.chain || record.network_name),
    walletId: safeString(record.wallet_id || record.wallet_uuid),
    party: merchant,
    searchText,
    settlement: safeString(record.settlement_status || record.reconciliation_status),
    raw: record,
  };
}

function localDateKey(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

function groupTransactions(transactions) {
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);
  const todayKey = localDateKey(today);
  const yesterdayKey = localDateKey(yesterday);
  const groups = new Map();
  for (const transaction of transactions) {
    const key = localDateKey(transaction.timestamp);
    const title = key === todayKey ? "Today" : key === yesterdayKey ? "Yesterday" : transaction.timestamp.toLocaleDateString(undefined, { weekday: "long", month: "long", day: "numeric", year: "numeric" });
    if (!groups.has(title)) groups.set(title, []);
    groups.get(title).push(transaction);
  }
  return [...groups.entries()];
}

function sortTransactions(transactions) {
  return [...transactions].sort((left, right) => right.timestamp - left.timestamp || left.id.localeCompare(right.id));
}

function compareDecimal(left, right) {
  const clean = (value) => {
    const [whole = "0", fraction = ""] = safeString(value, "0").replace(/^[+-]/, "").split(".");
    return { whole: whole.replace(/^0+(?=\d)/, ""), fraction: fraction.replace(/0+$/, "") };
  };
  const a = clean(left);
  const b = clean(right);
  if (a.whole.length !== b.whole.length) return a.whole.length < b.whole.length ? -1 : 1;
  const integerCompare = a.whole.localeCompare(b.whole);
  if (integerCompare !== 0) return integerCompare;
  const scale = Math.max(a.fraction.length, b.fraction.length);
  return a.fraction.padEnd(scale, "0").localeCompare(b.fraction.padEnd(scale, "0"));
}

function matchesFilters(transaction, filters, query) {
  if (filters.type) {
    const type = filters.type.toLowerCase();
    const sourceAlias = (type === "trade" && transaction.source === "exchange")
      || (type === "transfer" && ["transfer", "internal_transfer"].includes(transaction.rawType.toLowerCase()))
      || (type === "internal_transfer" && transaction.source === "p2p")
      || (type === "card_payment" && transaction.source === "card")
      || (type === "staking" && transaction.source === "staking")
      || (type === "giftcard" && transaction.source === "giftcard")
      || (type === "convert" && transaction.source === "convert")
      || (type === "p2p_trade" && transaction.source === "p2p");
    if (!sourceAlias && transaction.rawType.toLowerCase() !== type) return false;
  }
  const statusFilters = {
    completed: ["Completed", "Redeemed"], pending: ["Pending"], processing: ["Processing", "Execution recorded"],
    failed: ["Failed"], cancelled: ["Cancelled"], offline_committed_simulation: ["Offline committed · simulation"],
    reconciliation_pending: ["Reconciliation pending"], recovery_required: ["Recovery required"],
  };
  if (filters.status && !(statusFilters[filters.status.toLowerCase()] || []).includes(transaction.status)) return false;
  if (filters.currency && transaction.currency !== filters.currency.toUpperCase()) return false;
  if (filters.network && transaction.network.toLowerCase() !== filters.network.toLowerCase()) return false;
  if (filters.direction && transaction.direction !== filters.direction) return false;
  if (filters.wallet_id && transaction.walletId !== filters.wallet_id) return false;
  if (filters.min_amount && compareDecimal(transaction.rawAmount, filters.min_amount) < 0) return false;
  if (filters.max_amount && compareDecimal(transaction.rawAmount, filters.max_amount) > 0) return false;
  if (filters.from && transaction.timestamp < new Date(filters.from)) return false;
  if (filters.to && transaction.timestamp > new Date(filters.to)) return false;
  const needle = query.trim().toLowerCase();
  if (needle) {
    const searchable = [transaction.id, transaction.reference, transaction.txHash, transaction.party, transaction.searchText, transaction.currency, transaction.rawType, transaction.network, transaction.walletId]
      .join(" ").toLowerCase();
    if (!searchable.includes(needle)) return false;
  }
  return true;
}

function iconFor(type) {
  if (/deposit/i.test(type)) return ArrowDownLeft;
  if (/withdraw/i.test(type)) return ArrowUpRight;
  if (/reward|earn/i.test(type)) return Gift;
  if (/p2p|transfer/i.test(type)) return Users;
  if (/card|gift/i.test(type)) return CreditCard;
  if (/trade|exchange|convert/i.test(type)) return ArrowUpDown;
  if (/wallet/i.test(type)) return Wallet;
  return Activity;
}

function statusStyle(status) {
  if (status === "Completed" || status === "Redeemed") return "border-emerald-400/25 bg-emerald-400/10 text-emerald-300";
  if (status === "Failed" || status === "Cancelled" || status === "Recovery required") return "border-rose-400/25 bg-rose-400/10 text-rose-300";
  if (status === "Processing" || status === "Execution recorded" || status === "Reconciliation pending" || status.startsWith("Offline committed")) return "border-amber-300/25 bg-amber-300/10 text-amber-200";
  return "border-slate-400/20 bg-slate-400/10 text-slate-300";
}

function formatTime(date) {
  return date.toLocaleTimeString(undefined, { hour: "numeric", minute: "2-digit" });
}

function cacheKey(userId, filters) {
  return `exaearn:history:v1:${userId}:${encodeURIComponent(JSON.stringify(filters))}`;
}

function readSessionCache(userId, filters) {
  try {
    const value = sessionStorage.getItem(cacheKey(userId, filters));
    const cached = value ? JSON.parse(value) : null;
    return cached?.expiresAt > Date.now() && Array.isArray(cached.items) ? cached : null;
  } catch {
    return null;
  }
}

function writeSessionCache(userId, filters, items, meta) {
  try {
    sessionStorage.setItem(cacheKey(userId, filters), JSON.stringify({
      items: items.slice(0, PAGE_SIZE), meta, expiresAt: Date.now() + 6 * 60 * 60 * 1000,
    }));
  } catch {
    // Private browsing and full session storage must not break transaction history.
  }
}

function dateBoundary(value, endOfDay = false) {
  if (!value) return "";
  const [year, month, day] = value.split("-").map(Number);
  const date = new Date(year, month - 1, day, endOfDay ? 23 : 0, endOfDay ? 59 : 0, endOfDay ? 59 : 0, endOfDay ? 999 : 0);
  return date.toISOString();
}

function localDateInput(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

function initialDraft(filters) {
  return { from: "", to: "", ...filters };
}

function Transactions({ onBack, initialFilters = {} }) {
  const { apiBaseUrl, token, user } = useAuth();
  const { t: translate } = useLanguage();
  const userId = safeString(user?.id ?? user?.user_id);
  const [filters, setFilters] = useState(() => ({ ...initialFilters }));
  const [draft, setDraft] = useState(() => initialDraft(initialFilters));
  const [queryInput, setQueryInput] = useState("");
  const [filterOpen, setFilterOpen] = useState(false);
  const [assetOptions, setAssetOptions] = useState([]);
  const [transactions, setTransactions] = useState([]);
  const [selectedId, setSelectedId] = useState("");
  const [progress, setProgress] = useState({});
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState("");
  const [sourceErrors, setSourceErrors] = useState([]);
  const [stale, setStale] = useState(false);
  const [refreshKey, setRefreshKey] = useState(0);
  const [copyMessage, setCopyMessage] = useState("");
  const [receiptOpen, setReceiptOpen] = useState(false);
  const [query, setQuery] = useState("");
  const priorUser = useRef(userId);
  const assetOptionsRef = useRef(assetOptions);
  const filterDialogRef = useRef(null);

  useEffect(() => {
    assetOptionsRef.current = assetOptions;
    if (assetOptions.length) {
      setTransactions((current) => current.map((item) => normalizeTransaction(item.raw, assetOptions) || item));
    }
  }, [assetOptions]);

  useEffect(() => {
    const timeout = window.setTimeout(() => setQuery(queryInput.trim()), 300);
    return () => window.clearTimeout(timeout);
  }, [queryInput]);

  useEffect(() => {
    if (priorUser.current && priorUser.current !== userId) {
      try {
        for (let index = sessionStorage.length - 1; index >= 0; index -= 1) {
          const key = sessionStorage.key(index);
          if (key?.startsWith(`exaearn:history:v1:${priorUser.current}:`)) sessionStorage.removeItem(key);
        }
      } catch { /* session storage can be unavailable */ }
    }
    priorUser.current = userId;
  }, [userId]);

  useEffect(() => {
    if (!apiBaseUrl || !token || !userId) {
      setAssetOptions([]);
      return undefined;
    }
    const controller = new AbortController();
    fetchWalletAssetRegistry({ apiBaseUrl, token, signal: controller.signal })
      .then(setAssetOptions)
      .catch((registryError) => {
        if (registryError?.name !== "AbortError") setAssetOptions([]);
      });
    return () => controller.abort();
  }, [apiBaseUrl, token, userId]);

  const requestFilters = useMemo(() => ({ ...filters, ...(query ? { search: query } : {}) }), [filters, query]);
  const filterSignature = useMemo(() => JSON.stringify(requestFilters), [requestFilters]);

  useEffect(() => {
    const controller = new AbortController();
    let active = true;
    setLoading(true);
    setError("");
    setStale(false);
    setTransactions([]);
    setSelectedId("");
    setProgress({});
    setHasMore(false);
    setSourceErrors([]);
    if (!apiBaseUrl || !token || !userId) {
      setLoading(false);
      setError("Sign in to an ExaEarn account to view its transaction history.");
      return () => controller.abort();
    }

    fetchUserTransactions({ apiBaseUrl, token, userId, filters: requestFilters, perPage: PAGE_SIZE, signal: controller.signal })
      .then((payload) => {
        if (!active) return;
        const rows = Array.isArray(payload?.data) ? payload.data : [];
        const mapped = rows.map((row) => normalizeTransaction(row, assetOptionsRef.current)).filter(Boolean);
        const meta = payload?.meta || {};
        setTransactions(sortTransactions(mapped));
        setSelectedId(mapped[0]?.id || "");
        setProgress(meta.progress || {});
        setHasMore(Boolean(meta.has_more));
        setSourceErrors(Array.isArray(meta.errors) ? meta.errors : []);
        setStale(false);
        writeSessionCache(userId, requestFilters, rows, meta);
      })
      .catch((requestError) => {
        if (!active || requestError?.name === "AbortError") return;
        const cached = readSessionCache(userId, requestFilters);
        if (cached) {
          const mapped = cached.items.map((row) => normalizeTransaction(row, assetOptionsRef.current)).filter(Boolean);
          setTransactions(sortTransactions(mapped));
          setSelectedId(mapped[0]?.id || "");
          setProgress(cached.meta?.progress || {});
          setHasMore(Boolean(cached.meta?.has_more));
          setSourceErrors(Array.isArray(cached.meta?.errors) ? cached.meta.errors : []);
          setStale(true);
          setError("You are offline. Showing this account’s saved history from this browser session.");
        } else {
          setError(requestError?.message || translate("transactions.unableToLoad"));
        }
      })
      .finally(() => {
        if (active) {
          setLoading(false);
          setRefreshing(false);
        }
      });

    return () => {
      active = false;
      controller.abort();
    };
  }, [apiBaseUrl, token, userId, filterSignature, refreshKey, translate, requestFilters]);

  const loadMore = useCallback(async () => {
    if (!hasMore || loadingMore || stale || !apiBaseUrl || !token) return;
    setLoadingMore(true);
    setError("");
    try {
      const payload = await fetchUserTransactions({ apiBaseUrl, token, userId, filters: requestFilters, progress, perPage: PAGE_SIZE });
      const rows = Array.isArray(payload?.data) ? payload.data : [];
      const mapped = rows.map((row) => normalizeTransaction(row, assetOptions)).filter(Boolean);
      setTransactions((previous) => {
        const seen = new Set(previous.map((item) => item.id));
        return sortTransactions([...previous, ...mapped.filter((item) => !seen.has(item.id))]);
      });
      setProgress(payload?.meta?.progress || progress);
      setHasMore(Boolean(payload?.meta?.has_more));
      setSourceErrors(Array.isArray(payload?.meta?.errors) ? payload.meta.errors : []);
    } catch (requestError) {
      setError(requestError?.message || "Could not load the next page.");
    } finally {
      setLoadingMore(false);
    }
  }, [hasMore, progress, loadingMore, stale, apiBaseUrl, token, userId, requestFilters, assetOptions]);

  const matchingTransactions = useMemo(
    () => transactions.filter((item) => matchesFilters(item, filters, query)),
    [transactions, filters, query],
  );
  const selected = matchingTransactions.find((item) => item.id === selectedId) || matchingTransactions[0] || null;
  const receipt = selected ? {
    reference: selected.reference,
    timestamp: selected.timestamp.toISOString(),
    type: `${selected.sourceLabel} · ${selected.type}`,
    status: selected.status === "Completed" || selected.status === "Redeemed" ? "SUCCESS"
      : selected.status === "Failed" ? "FAILED"
        : selected.status === "Cancelled" ? "CANCELLED"
          : selected.status === "Recovery required" ? "RECOVERY_REQUIRED"
            : selected.status === "Reconciliation pending" ? "RECONCILIATION_PENDING"
              : selected.status.startsWith("Offline committed") ? "OFFLINE_COMMITTED"
                : selected.status === "Processing" || selected.status === "Execution recorded" ? "PROCESSING" : "PENDING",
    amountDisplay: `${selected.amount} ${selected.currency}`,
    feeDisplay: selected.fee || undefined,
    asset: selected.currency,
    source: safeString(selected.raw.sender || selected.raw.from_address || (selected.source === "exapay" ? "Simulation" : "")),
    destination: safeString(selected.raw.recipient || selected.raw.to_address || selected.party),
    network: selected.network || undefined,
    transactionHash: selected.txHash || undefined,
    confirmationStatus: selected.settlement || (selected.source === "exapay" ? "Simulation only · not settled online" : "Status shown above; verify settlement in the wallet ledger."),
    sourceOfTruth: selected.source === "exapay" ? "simulation" : "backend",
    simulation: selected.source === "exapay",
    message: selected.source === "exchange" && ["completed", "complete", "success", "succeeded", "filled", "executed"].includes(selected.rawStatus.toLowerCase())
      ? "Trade execution record. Wallet settlement is shown separately in the ledger."
      : selected.source === "exapay" ? "This offline payment is a local simulation and did not move real funds." : undefined,
  } : null;
  const grouped = useMemo(() => groupTransactions(matchingTransactions), [matchingTransactions]);
  const networkOptions = useMemo(() => [...new Set([
    ...assetOptions.map((item) => item.network).filter(Boolean),
    ...transactions.map((item) => item.network).filter(Boolean),
  ])].sort((left, right) => left.localeCompare(right)), [assetOptions, transactions]);
  const walletOptions = useMemo(() => [...new Set(transactions.map((item) => item.walletId).filter(Boolean))].sort(), [transactions]);
  const availableAssets = useMemo(() => [...new Set([
    ...assetOptions.map((item) => item.currency),
    ...transactions.map((item) => item.currency).filter((item) => item && item !== "—"),
  ])].sort(), [assetOptions, transactions]);
  const activeFilterCount = Object.entries(filters).filter(([key, value]) => key !== "search" && String(value || "").trim()).length;

  const openFilters = () => {
    const from = localDateInput(filters.from);
    const to = localDateInput(filters.to);
    setDraft({ ...filters, from, to });
    setFilterOpen(true);
  };

  const updateDraft = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
  const applyFilters = (event) => {
    event.preventDefault();
    if ((draft.min_amount || draft.max_amount) && !draft.currency) {
      setError("Choose an asset before filtering by amount.");
      return;
    }
    const next = {};
    for (const key of ["type", "status", "currency", "network", "direction", "wallet_id", "min_amount", "max_amount"]) {
      if (String(draft[key] || "").trim()) next[key] = String(draft[key]).trim();
    }
    if (draft.from) next.from = dateBoundary(draft.from);
    if (draft.to) next.to = dateBoundary(draft.to, true);
    if (next.from && next.to && new Date(next.from) > new Date(next.to)) {
      setError("The start date must be before the end date.");
      return;
    }
    setError("");
    setFilters(next);
    setFilterOpen(false);
  };

  const resetFilters = () => {
    setDraft({ from: "", to: "" });
    setFilters({});
    setError("");
  };

  const setDatePreset = (days) => {
    if (days === null) {
      updateDraft("from", "");
      updateDraft("to", "");
      return;
    }
    const end = new Date();
    const start = new Date(end);
    start.setDate(start.getDate() - days + 1);
    const localInput = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
    updateDraft("from", localInput(start));
    updateDraft("to", localInput(end));
  };

  const removeFilter = (key) => {
    const next = { ...filters };
    delete next[key];
    setFilters(next);
  };

  const copyReference = async (value) => {
    try {
      await navigator.clipboard.writeText(value);
      setCopyMessage("Copied");
      window.setTimeout(() => setCopyMessage(""), 1800);
    } catch {
      setCopyMessage("Copy unavailable");
      window.setTimeout(() => setCopyMessage(""), 1800);
    }
  };

  const title = translate("transactions.title");
  const dateRangeFrom = draft.from || "";
  const dateRangeTo = draft.to || "";

  useEffect(() => {
    if (!receiptOpen) return undefined;
    const closeOnEscape = (event) => {
      if (event.key === "Escape") setReceiptOpen(false);
    };
    window.addEventListener("keydown", closeOnEscape);
    return () => window.removeEventListener("keydown", closeOnEscape);
  }, [receiptOpen]);

  useEffect(() => {
    if (!filterOpen) return undefined;
    const previousFocus = document.activeElement;
    const dialog = filterDialogRef.current;
    const focusable = () => [...(dialog?.querySelectorAll("button, input, select, [href], [tabindex]:not([tabindex='-1'])") || [])]
      .filter((element) => !element.disabled && element.getAttribute("aria-hidden") !== "true");
    focusable()[0]?.focus();
    const handleDialogKeys = (event) => {
      if (event.key === "Escape") {
        setFilterOpen(false);
        return;
      }
      if (event.key !== "Tab") return;
      const elements = focusable();
      if (!elements.length) return;
      if (event.shiftKey && document.activeElement === elements[0]) {
        event.preventDefault();
        elements.at(-1).focus();
      } else if (!event.shiftKey && document.activeElement === elements.at(-1)) {
        event.preventDefault();
        elements[0].focus();
      }
    };
    window.addEventListener("keydown", handleDialogKeys);
    return () => {
      window.removeEventListener("keydown", handleDialogKeys);
      if (previousFocus instanceof HTMLElement) previousFocus.focus();
    };
  }, [filterOpen]);

  return (
    <main className="min-h-screen bg-[var(--exa-bg-primary)] px-3 py-4 text-[var(--exa-text-primary)] sm:px-6 sm:py-8">
      <div className="mx-auto max-w-[1440px]">
        <header className="relative mb-5 grid min-h-14 grid-cols-[1fr_auto_1fr] items-center gap-2 border-b border-[var(--exa-border)] pb-4">
          <div className="flex justify-start">{onBack ? <button type="button" onClick={onBack} aria-label="Go back" className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)] hover:text-[var(--exa-text-primary)]"><ArrowLeft size={18} /></button> : null}</div>
          <div className="min-w-0 text-center">
            <h1 className="font-['Sora'] text-lg font-semibold tracking-tight sm:text-2xl">{title}</h1>
            <p className="mt-0.5 hidden text-xs text-[var(--exa-text-muted)] sm:block">Your account activity, from the ExaEarn ledger</p>
          </div>
          <div className="flex shrink-0 justify-end gap-2">
            <button type="button" onClick={() => { setRefreshing(true); setRefreshKey((value) => value + 1); }} disabled={loading || refreshing} className="inline-flex h-10 items-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 text-sm text-[var(--exa-text-secondary)] hover:text-[var(--exa-text-primary)] disabled:opacity-50"><RefreshCw size={15} className={refreshing ? "animate-spin" : ""} /><span className="hidden sm:inline">Refresh</span></button>
            <button type="button" onClick={openFilters} aria-haspopup="dialog" aria-expanded={filterOpen} className="relative inline-flex h-10 items-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 text-sm font-medium text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)] hover:text-[var(--exa-text-primary)]"><SlidersHorizontal size={16} /> <span className="hidden sm:inline">Filters</span>{activeFilterCount > 0 ? <span className="inline-flex min-w-5 items-center justify-center rounded-full bg-[var(--exa-gold-surface)] px-1.5 text-[11px] font-semibold text-[var(--exa-gold-light)]">{activeFilterCount}</span> : null}</button>
          </div>
        </header>

        <section className="mb-4 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-3 sm:p-4" aria-label="Search and transaction filters">
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--exa-text-muted)]" size={17} />
            <input type="search" value={queryInput} onChange={(event) => setQueryInput(event.target.value)} placeholder="Search reference, sender, recipient, merchant or asset" aria-label="Search transactions" className="h-11 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] pl-10 pr-10 text-sm outline-none placeholder:text-[var(--exa-text-muted)] focus:border-[var(--exa-border-active)]" />
            {queryInput ? <button type="button" onClick={() => setQueryInput("")} aria-label="Clear search" className="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--exa-text-muted)] hover:text-white"><X size={16} /></button> : null}
          </div>
          {activeFilterCount > 0 ? <div className="mt-3 flex flex-wrap gap-2">
            {FILTERS.filter(([key]) => filters[key]).map(([key, label]) => <button key={key} type="button" onClick={() => removeFilter(key)} className="inline-flex items-center gap-1.5 rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-2.5 py-1 text-xs text-[var(--exa-gold-light)]">{label}: {key === "currency" ? String(filters[key]).toUpperCase() : filters[key]} <X size={12} /></button>)}
            <button type="button" onClick={resetFilters} className="px-2 text-xs text-[var(--exa-text-muted)] hover:text-white">Clear all</button>
          </div> : null}
        </section>

        {error ? <div role="status" className={`mb-4 flex items-start gap-2 rounded-xl border px-3 py-2.5 text-sm ${stale ? "border-amber-300/25 bg-amber-300/10 text-amber-100" : "border-rose-300/25 bg-rose-400/10 text-rose-200"}`}><AlertCircle size={17} className="mt-0.5 shrink-0" /><span>{error}</span>{!stale ? <button type="button" onClick={() => setRefreshKey((value) => value + 1)} className="ml-auto shrink-0 underline">Retry</button> : null}</div> : null}
        {sourceErrors.length > 0 && !error ? <div role="status" className="mb-4 flex items-start gap-2 rounded-xl border border-amber-300/25 bg-amber-300/10 px-3 py-2.5 text-xs text-amber-100"><AlertCircle size={15} className="mt-0.5 shrink-0" /><span>Some activity sources could not be reached. Available sources are shown; use “Load more activity” to retry unavailable sources.</span></div> : null}

        <div className="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,0.65fr)]">
          <section className="min-w-0" aria-label="Transaction list">
            {loading ? <div className="space-y-3" aria-label="Loading transactions" aria-busy="true">{[0, 1, 2, 3, 4].map((item) => <div key={item} className="animate-pulse rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4"><div className="flex gap-3"><div className="h-10 w-10 rounded-xl bg-white/5" /><div className="flex-1 space-y-2 py-1"><div className="h-3 w-32 rounded bg-white/5" /><div className="h-2.5 w-48 max-w-full rounded bg-white/5" /></div><div className="space-y-2 py-1"><div className="h-3 w-24 rounded bg-white/5" /><div className="h-2.5 w-16 rounded bg-white/5" /></div></div></div>)}</div> : transactions.length ? <>
              <div className="mb-3 flex items-center justify-between px-1 text-xs text-[var(--exa-text-muted)]"><span>{matchingTransactions.length} matching in {transactions.length} loaded {transactions.length === 1 ? "record" : "records"}{stale ? " · saved session data" : ""}</span>{activeFilterCount || query ? <span>Filtered activity</span> : <span>Newest first</span>}</div>
              <div className="space-y-6">{grouped.map(([group, items]) => <section key={group} aria-label={group}>
                <h2 className="mb-2 flex items-center gap-2 px-1 text-xs font-semibold uppercase tracking-[0.12em] text-[var(--exa-text-muted)]"><CalendarDays size={14} />{group}<span className="h-px flex-1 bg-[var(--exa-border)]" /></h2>
                <div className="space-y-2">{items.map((transaction) => {
                  const Icon = iconFor(transaction.type);
                  const active = transaction.id === selected?.id;
                  return <button key={transaction.id} type="button" aria-pressed={active} onClick={() => setSelectedId(transaction.id)} className={`group w-full rounded-2xl border p-3.5 text-left transition sm:p-4 ${active ? "border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)]" : "border-[var(--exa-border)] bg-[var(--exa-surface)] hover:border-white/15"}`}>
                    <span className="flex items-start justify-between gap-3">
                      <span className="flex min-w-0 items-start gap-3">
                        <span className={`mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border ${transaction.direction === "credit" ? "border-emerald-400/20 bg-emerald-400/10 text-emerald-300" : transaction.direction === "debit" ? "border-rose-400/20 bg-rose-400/10 text-rose-300" : "border-slate-400/20 bg-slate-400/10 text-slate-300"}`}><Icon size={18} /></span>
                        <span className="min-w-0">
                          <span className="block truncate text-sm font-semibold text-[var(--exa-text-primary)]">{transaction.party || transaction.type}</span>
                          <span className="mt-1 block truncate text-xs text-[var(--exa-text-secondary)]">{transaction.sourceLabel} <span className="px-1 text-[var(--exa-text-muted)]">·</span> {transaction.type} <span className="px-1 text-[var(--exa-text-muted)]">·</span> {transaction.reference}</span>
                          <span className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-[var(--exa-text-muted)]"><span>{formatTime(transaction.timestamp)}</span>{transaction.network ? <><span>·</span><span>{transaction.network}</span></> : null}</span>
                        </span>
                      </span>
                      <span className="shrink-0 text-right">
                        <span className={`block text-sm font-semibold tabular-nums ${transaction.direction === "credit" ? "text-emerald-300" : transaction.direction === "debit" ? "text-[var(--exa-text-primary)]" : "text-[var(--exa-text-secondary)]"}`}>{transaction.amount} <span className="text-xs font-medium">{transaction.currency}</span></span>
                        <span className={`mt-1.5 inline-flex rounded-full border px-2 py-0.5 text-[10px] font-medium ${statusStyle(transaction.status)}`}>{transaction.status}</span>
                      </span>
                    </span>
                  </button>;
                })}</div>
              </section>)}</div>
              {hasMore ? <button type="button" onClick={loadMore} disabled={loadingMore || stale} className="mt-4 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] text-sm font-medium text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)] hover:text-white disabled:cursor-not-allowed disabled:opacity-50">{loadingMore ? <><LoaderCircle size={16} className="animate-spin" />Loading…</> : "Load more activity"}</button> : <p className="mt-4 py-3 text-center text-xs text-[var(--exa-text-muted)]">You’re all caught up.</p>}
            </> : <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-5 py-14 text-center"><span className="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-muted)]"><Shuffle size={21} /></span><h2 className="mt-4 text-base font-semibold">{activeFilterCount || query ? "No matching transactions" : "No transactions yet"}</h2><p className="mx-auto mt-1 max-w-sm text-sm text-[var(--exa-text-muted)]">{activeFilterCount || query ? "Try changing your search or filters." : "Settled and pending account activity will appear here when available."}</p>{activeFilterCount || query ? <button type="button" onClick={() => { resetFilters(); setQueryInput(""); }} className="mt-4 text-sm font-medium text-[var(--exa-gold-light)] hover:text-white">Reset search and filters</button> : null}{hasMore ? <button type="button" onClick={loadMore} disabled={loadingMore || stale} className="mt-4 block w-full text-sm font-medium text-[var(--exa-gold-light)] hover:text-white disabled:opacity-50">{loadingMore ? "Loading…" : "Load more activity"}</button> : null}</div>}
          </section>

          <aside className="h-fit rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:p-5 xl:sticky xl:top-4" aria-label="Transaction details">
            <div className="mb-4 flex items-center justify-between gap-3"><div><p className="text-[11px] font-semibold uppercase tracking-[0.13em] text-[var(--exa-text-muted)]">Transaction receipt</p><h2 className="mt-1 font-['Sora'] text-lg font-semibold">Details</h2></div>{selected ? <span className={`rounded-full border px-2.5 py-1 text-[11px] font-medium ${statusStyle(selected.status)}`}>{selected.status}</span> : null}</div>
            {!selected ? <div className="rounded-xl border border-dashed border-[var(--exa-border)] p-8 text-center text-sm text-[var(--exa-text-muted)]">Select a transaction to view its receipt details.</div> : <>
              <div className="mb-4 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4"><div className="flex items-center justify-between gap-3"><span className="text-xs text-[var(--exa-text-muted)]">{selected.sourceLabel} · {selected.type}</span><span className="text-xs text-[var(--exa-text-muted)]">{selected.timestamp.toLocaleDateString()}</span></div><p className={`mt-3 text-2xl font-semibold tabular-nums ${selected.direction === "credit" ? "text-emerald-300" : "text-[var(--exa-text-primary)]"}`}>{selected.amount} <span className="text-sm">{selected.currency}</span></p><p className="mt-1 text-xs text-[var(--exa-text-muted)]">Source record; settlement status is shown separately.</p></div>
              <dl className="divide-y divide-[var(--exa-border)] text-sm">
                {[["Reference", selected.reference], ["Fee", selected.fee || "Not provided"], ["Counterparty", selected.party || "Not provided"], ["Network", selected.network || "Not applicable"], ["Wallet", selected.walletId || "Not provided"], ["Date and time", selected.timestamp.toLocaleString()], ["Transaction hash", selected.txHash || "Not available"], ["Reconciliation", selected.settlement || "Not provided"]].map(([label, value]) => <div key={label} className="flex items-start justify-between gap-4 py-3"><dt className="shrink-0 text-xs text-[var(--exa-text-muted)]">{label}</dt><dd className="max-w-[65%] break-all text-right text-xs font-medium text-[var(--exa-text-primary)]">{value}</dd></div>)}
              </dl>
              {(selected.reference !== "—" || selected.txHash) ? <button type="button" onClick={() => copyReference(selected.txHash || selected.reference)} className="mt-3 inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-xs font-medium text-[var(--exa-text-secondary)] hover:text-white"><Check size={14} />{copyMessage || "Copy reference"}</button> : null}
              <button type="button" onClick={() => setReceiptOpen(true)} className="mt-2 inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-[var(--exa-gold)] text-xs font-semibold text-black hover:brightness-110">Open universal receipt <ExternalLink size={14} /></button>
              {selected.txHash ? <p className="mt-3 flex items-start gap-2 text-[11px] leading-relaxed text-[var(--exa-text-muted)]"><ExternalLink size={13} className="mt-0.5 shrink-0" />Hash copied on request; explorer links are not generated unless a trusted network mapping is available.</p> : null}
            </>}
          </aside>
        </div>
      </div>

      {filterOpen ? <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-0 backdrop-blur-sm sm:items-center sm:p-5" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setFilterOpen(false); }}>
        <section ref={filterDialogRef} role="dialog" aria-modal="true" aria-labelledby="transaction-filter-title" className="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-3xl border border-[var(--exa-border)] bg-[var(--exa-bg-primary)] p-4 shadow-2xl sm:rounded-3xl sm:p-6">
          <form onSubmit={applyFilters}>
            <header className="mb-5 flex items-center justify-between gap-4"><div><div className="flex items-center gap-2 text-[var(--exa-gold-light)]"><Filter size={16} /><span className="text-[11px] font-semibold uppercase tracking-[0.14em]">Refine activity</span></div><h2 id="transaction-filter-title" className="mt-1 font-['Sora'] text-xl font-semibold">Advanced filters</h2></div><button type="button" onClick={() => setFilterOpen(false)} aria-label="Close filters" className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[var(--exa-border)] text-[var(--exa-text-secondary)] hover:text-white"><X size={17} /></button></header>
            <div className="mb-5"><p className="mb-2 text-xs font-medium text-[var(--exa-text-secondary)]">Date range</p><div className="flex flex-wrap gap-2">{[[0, "Today"], [1, "Yesterday"], [7, "7 days"], [30, "30 days"], [null, "Any time"]].map(([days, label]) => <button key={label} type="button" onClick={() => days === 1 ? (() => { const y = new Date(); y.setDate(y.getDate() - 1); const value = `${y.getFullYear()}-${String(y.getMonth() + 1).padStart(2, "0")}-${String(y.getDate()).padStart(2, "0")}`; updateDraft("from", value); updateDraft("to", value); })() : setDatePreset(days)} className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 py-1.5 text-xs text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]">{label}</button>)}</div><div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="text-xs text-[var(--exa-text-muted)]">From<input type="date" value={dateRangeFrom} onChange={(event) => updateDraft("from", event.target.value)} className="mt-1.5 h-10 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 text-sm text-white outline-none focus:border-[var(--exa-border-active)]" /></label><label className="text-xs text-[var(--exa-text-muted)]">To<input type="date" value={dateRangeTo} onChange={(event) => updateDraft("to", event.target.value)} className="mt-1.5 h-10 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 text-sm text-white outline-none focus:border-[var(--exa-border-active)]" /></label></div></div>
            <div className="grid gap-3 sm:grid-cols-2">
              <FilterSelect label="Asset / currency" value={draft.currency || ""} onChange={(value) => updateDraft("currency", value)} options={availableAssets.map((value) => [value, value])} placeholder="All assets" />
              <FilterSelect label="Transaction type" value={draft.type || ""} onChange={(value) => updateDraft("type", value)} options={TYPE_OPTIONS} placeholder="All types" />
              <FilterSelect label="Status" value={draft.status || ""} onChange={(value) => updateDraft("status", value)} options={STATUS_OPTIONS} placeholder="All statuses" />
              <FilterSelect label="Network" value={draft.network || ""} onChange={(value) => updateDraft("network", value)} options={networkOptions.map((value) => [value, value])} placeholder="All networks" />
              <FilterSelect label="Direction" value={draft.direction || ""} onChange={(value) => updateDraft("direction", value)} options={[["credit", "Credit"], ["debit", "Debit"]]} placeholder="All directions" />
              <FilterSelect label="Wallet" value={draft.wallet_id || ""} onChange={(value) => updateDraft("wallet_id", value)} options={walletOptions.map((value) => [value, value])} placeholder={walletOptions.length ? "All wallets" : "Wallets in loaded history"} />
              <label className="text-xs text-[var(--exa-text-muted)]">Minimum amount<input inputMode="decimal" value={draft.min_amount || ""} onChange={(event) => updateDraft("min_amount", event.target.value)} placeholder="0.00" className="mt-1.5 h-10 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 text-sm text-white outline-none placeholder:text-slate-600 focus:border-[var(--exa-border-active)]" /></label>
              <label className="text-xs text-[var(--exa-text-muted)]">Maximum amount<input inputMode="decimal" value={draft.max_amount || ""} onChange={(event) => updateDraft("max_amount", event.target.value)} placeholder="0.00" className="mt-1.5 h-10 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 text-sm text-white outline-none placeholder:text-slate-600 focus:border-[var(--exa-border-active)]" /></label>
            </div>
            <p className="mt-3 text-[11px] leading-relaxed text-[var(--exa-text-muted)]">Amount limits apply only to the selected asset. Filters run against your authenticated account history.</p>
            <footer className="mt-6 flex flex-col-reverse gap-2 border-t border-[var(--exa-border)] pt-4 sm:flex-row sm:justify-between"><button type="button" onClick={resetFilters} className="inline-flex h-11 items-center justify-center gap-2 rounded-xl px-3 text-sm text-[var(--exa-text-secondary)] hover:text-white"><RotateCcw size={15} />Reset</button><div className="flex gap-2"><button type="button" onClick={() => setFilterOpen(false)} className="h-11 flex-1 rounded-xl border border-[var(--exa-border)] px-4 text-sm text-[var(--exa-text-secondary)] hover:text-white sm:flex-none">Cancel</button><button type="submit" className="h-11 flex-1 rounded-xl bg-[var(--exa-gold)] px-5 text-sm font-semibold text-black transition hover:brightness-110 sm:flex-none">Apply filters</button></div></footer>
          </form>
        </section>
      </div> : null}
      {receiptOpen && receipt ? <div className="fixed inset-0 z-[60] overflow-y-auto bg-black/75 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Transaction receipt"><TransactionResult transaction={receipt} returnLabel="Close receipt" onReturn={() => setReceiptOpen(false)} /></div> : null}
    </main>
  );
}

function FilterSelect({ label, value, onChange, options, placeholder }) {
  return <label className="text-xs text-[var(--exa-text-muted)]">{label}<span className="relative mt-1.5 block"><select value={value} onChange={(event) => onChange(event.target.value)} className="h-10 w-full appearance-none rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 pr-9 text-sm text-[var(--exa-text-primary)] outline-none focus:border-[var(--exa-border-active)]"><option value="">{placeholder}</option>{options.map(([optionValue, optionLabel]) => <option key={optionValue} value={optionValue}>{optionLabel}</option>)}</select><ChevronDown size={15} className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2" /></span></label>;
}

export default Transactions;
