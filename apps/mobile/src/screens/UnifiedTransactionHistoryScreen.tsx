import { Ionicons } from "@expo/vector-icons";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ActivityIndicator,
  BackHandler,
  FlatList,
  Modal,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import type { ReactNode } from "react";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import {
  TransactionStatusBadge,
  transactionStatusLabel,
} from "../components/transactions/TransactionResultComponents";
import { useAuth } from "../context/AuthContext";
import {
  fetchHistoryAssetRegistry,
  fetchUnifiedTransactionHistory,
  searchWalletTransactionHistory,
  type HistoryAssetOption,
  type HistoryProgress,
  type HistorySource,
  type HistoryTransaction,
  type WalletHistoryFilters,
} from "../services/transactionHistoryAdapter";
import {
  readTransactionHistoryCache,
  writeTransactionHistoryCache,
} from "../services/transactionHistoryCache";
import type { TransactionResult } from "../services/transactionResultMapper";
import { fonts } from "../theme/colors";

type Props = {
  fontsReady: boolean;
  initialSource?: HistorySource;
  onBack: () => void;
  onOpenTransaction: (transaction: TransactionResult) => void;
};

type DateRange = "all" | "today" | "last7" | "thisMonth" | "lastMonth" | "custom";
type HistoryFilters = {
  dateRange: DateRange;
  asset: string;
  type: string;
  status: string;
  direction: "all" | "debit" | "credit";
  network: string;
  wallet: string;
  source: string;
  minAmount: string;
  maxAmount: string;
  fromDate: string;
  toDate: string;
};

type ListItem =
  | { kind: "header"; id: string; label: string }
  | { kind: "transaction"; id: string; transaction: HistoryTransaction };

const sourceNames: Record<HistorySource, string> = {
  wallet: "Wallet",
  exchange: "Exchange",
  convert: "Convert",
  card: "ExaCard",
  staking: "Earn",
  giftcard: "Giftcards",
  p2p: "P2P",
  exapay: "ExaPay · simulation",
};

const statuses = [
  "SUCCESS",
  "PENDING",
  "PROCESSING",
  "FAILED",
  "CANCELLED",
  "OFFLINE_COMMITTED",
  "RECONCILIATION_PENDING",
  "REDEEMED",
  "RECOVERY_REQUIRED",
  "UNKNOWN",
];

function emptyFilters(source?: HistorySource): HistoryFilters {
  return {
    dateRange: "all",
    asset: "all",
    type: "all",
    status: "all",
    direction: "all",
    network: "all",
    wallet: "all",
    source: source ?? "all",
    minAmount: "",
    maxAmount: "",
    fromDate: "",
    toDate: "",
  };
}

function startOfDay(date: Date) {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
}

function dateLabel(value: string) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Date unavailable";
  return new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(date);
}

function dateGroup(value: string) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Earlier dates";
  const now = new Date();
  const today = startOfDay(now);
  const yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1).getTime();
  const day = startOfDay(date);
  if (day === today) return "Today";
  if (day === yesterday) return "Yesterday";
  return "Earlier dates";
}

function formatAmount(value: string) {
  if (!/^-?\d+(?:\.\d+)?$/.test(value)) return value || "—";
  const negative = value.startsWith("-");
  const normalized = negative ? value.slice(1) : value;
  const [whole = "0", fraction = ""] = normalized.split(".");
  const trimmedFraction = fraction.replace(/0+$/, "");
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  return `${negative ? "-" : ""}${grouped}${trimmedFraction ? `.${trimmedFraction}` : ""}`;
}

function scaledAmount(value: string) {
  if (!/^\d+(?:\.\d+)?$/.test(value.trim())) return null;
  const [whole = "0", fraction = ""] = value.trim().split(".");
  const scaledFraction = `${fraction.slice(0, 8)}${"0".repeat(8)}`.slice(0, 8);
  try {
    return BigInt(whole) * 100000000n + BigInt(scaledFraction);
  } catch {
    return null;
  }
}

function matchesDateRange(transaction: HistoryTransaction, filters: HistoryFilters) {
  const time = new Date(transaction.timestamp).getTime();
  if (Number.isNaN(time)) return false;
  const now = new Date();
  if (filters.dateRange === "today") return startOfDay(new Date(time)) === startOfDay(now);
  if (filters.dateRange === "last7") return time >= now.getTime() - 7 * 86400000;
  if (filters.dateRange === "thisMonth") {
    return new Date(time).getFullYear() === now.getFullYear() && new Date(time).getMonth() === now.getMonth();
  }
  if (filters.dateRange === "lastMonth") {
    const previous = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    const date = new Date(time);
    return date.getFullYear() === previous.getFullYear() && date.getMonth() === previous.getMonth();
  }
  if (filters.dateRange === "custom") {
    const from = filters.fromDate ? new Date(`${filters.fromDate}T00:00:00`).getTime() : null;
    const to = filters.toDate ? new Date(`${filters.toDate}T23:59:59.999`).getTime() : null;
    if (from !== null && !Number.isNaN(from) && time < from) return false;
    if (to !== null && !Number.isNaN(to) && time > to) return false;
  }
  return true;
}

function listItems(transactions: HistoryTransaction[]): ListItem[] {
  const groups = new Map<string, HistoryTransaction[]>();
  for (const transaction of transactions) {
    const label = dateGroup(transaction.timestamp);
    const group = groups.get(label) ?? [];
    group.push(transaction);
    groups.set(label, group);
  }
  const orderedLabels = ["Today", "Yesterday", "Earlier dates"];
  return orderedLabels.flatMap((label) => {
    const group = groups.get(label);
    if (!group?.length) return [];
    return [
      { kind: "header" as const, id: `header:${label}`, label },
      ...group.map((transaction) => ({ kind: "transaction" as const, id: transaction.id, transaction })),
    ];
  });
}

function HistoryRow({
  transaction,
  onPress,
}: {
  transaction: HistoryTransaction;
  onPress: () => void;
}) {
  const icon = transaction.source === "card"
    ? "card-outline"
    : transaction.source === "exchange"
      ? "swap-horizontal-outline"
      : transaction.source === "staking"
        ? "trending-up-outline"
        : transaction.source === "giftcard"
          ? "gift-outline"
          : transaction.source === "p2p"
            ? "people-outline"
            : transaction.source === "exapay"
              ? "bluetooth-outline"
              : "wallet-outline";
  const amountPrefix = transaction.direction === "debit" ? "−" : transaction.direction === "credit" ? "+" : "";
  const amountTone = transaction.direction === "credit" ? styles.credit : transaction.direction === "debit" ? styles.debit : null;

  return (
    <AnimatedPressable
      style={styles.row}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${transaction.title}, ${amountPrefix}${transaction.amount} ${transaction.asset}, ${transactionStatusLabel(transaction.detail.status, transaction.detail.simulation)}`}
    >
      <View style={styles.rowIcon}>
        <Ionicons name={icon} size={18} color={transaction.source === "exapay" ? "#8f9ff5" : "#b9c3cf"} />
      </View>
      <View style={styles.rowCopy}>
        <Text style={styles.rowTitle} numberOfLines={1}>{transaction.title}</Text>
        <Text style={styles.rowMeta} numberOfLines={1}>
          {dateLabel(transaction.timestamp)} · {transaction.sourceLabel}
        </Text>
        <View style={styles.rowStatus}>
          <TransactionStatusBadge transaction={transaction.detail} />
        </View>
      </View>
      <View style={styles.amountGroup}>
        <Text style={[styles.amount, amountTone]} numberOfLines={1}>
          {amountPrefix}{formatAmount(transaction.amount)}
        </Text>
        <Text style={styles.asset} numberOfLines={1}>{transaction.asset}</Text>
      </View>
    </AnimatedPressable>
  );
}

function FilterChip({ label, selected, onPress }: { label: string; selected: boolean; onPress: () => void }) {
  return (
    <Pressable
      onPress={onPress}
      style={[styles.filterChip, selected ? styles.filterChipSelected : null]}
      accessibilityRole="button"
      accessibilityState={{ selected }}
    >
      <Text style={[styles.filterChipText, selected ? styles.filterChipTextSelected : null]}>{label}</Text>
    </Pressable>
  );
}

export default function UnifiedTransactionHistoryScreen({
  fontsReady,
  initialSource,
  onBack,
  onOpenTransaction,
}: Props) {
  const insets = useSafeAreaInsets();
  const { request, user } = useAuth();
  const [transactions, setTransactions] = useState<HistoryTransaction[]>([]);
  const [progress, setProgress] = useState<HistoryProgress>({});
  const [assetRegistry, setAssetRegistry] = useState<HistoryAssetOption[]>([]);
  const [errors, setErrors] = useState<string[]>([]);
  const [offlineCacheUsed, setOfflineCacheUsed] = useState(false);
  const [searchError, setSearchError] = useState("");
  const [serverSearchRecords, setServerSearchRecords] = useState<HistoryTransaction[]>([]);
  const [searchRefreshToken, setSearchRefreshToken] = useState(0);
  const [search, setSearch] = useState("");
  const [filters, setFilters] = useState<HistoryFilters>(() => emptyFilters(initialSource));
  const [draftFilters, setDraftFilters] = useState<HistoryFilters>(() => emptyFilters(initialSource));
  const [filtersVisible, setFiltersVisible] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [newestFirst, setNewestFirst] = useState(true);
  const mounted = useRef(true);
  const loadingMoreRef = useRef(false);
  const searchSequence = useRef(0);
  const requestGeneration = useRef(0);

  useEffect(() => {
    setTransactions([]);
    setProgress({});
    setErrors([]);
    setServerSearchRecords([]);
    setOfflineCacheUsed(false);
  }, [user?.id]);

  const loadFirstPage = useCallback(async (isRefresh = false) => {
    const generation = ++requestGeneration.current;
    if (isRefresh) setSearchRefreshToken((value) => value + 1);
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setErrors([]);
    try {
      const assets = await fetchHistoryAssetRegistry(request).catch(() => [] as HistoryAssetOption[]);
      const result = await fetchUnifiedTransactionHistory(request, user?.id, {}, assets);
      if (!mounted.current || generation !== requestGeneration.current) return;
      setAssetRegistry(assets);
      if (result.errors.length) {
        const cached = await readTransactionHistoryCache(user?.id);
        if (!mounted.current || generation !== requestGeneration.current) return;
        const merged = new Map(result.items.map((item) => [item.id, item]));
        cached.forEach((item) => { if (!merged.has(item.id)) merged.set(item.id, item); });
        setTransactions(Array.from(merged.values()).sort((left, right) => right.timestamp.localeCompare(left.timestamp) || left.id.localeCompare(right.id)));
        setOfflineCacheUsed(cached.length > 0);
      } else {
        setTransactions(result.items);
        setOfflineCacheUsed(false);
        await writeTransactionHistoryCache(user?.id, result.items);
      }
      setProgress(result.progress);
      setErrors(result.errors);
    } catch (error) {
      if (!mounted.current || generation !== requestGeneration.current) return;
      const cached = await readTransactionHistoryCache(user?.id);
      if (!mounted.current || generation !== requestGeneration.current) return;
      setTransactions(cached);
      setOfflineCacheUsed(cached.length > 0);
      setErrors([error instanceof Error ? error.message : "Unable to load transaction history."]);
    } finally {
      if (mounted.current && generation === requestGeneration.current) {
        setLoading(false);
        setRefreshing(false);
      }
    }
  }, [request, user?.id]);

  useEffect(() => {
    mounted.current = true;
    void loadFirstPage();
    return () => {
      mounted.current = false;
    };
  }, [loadFirstPage]);

  useEffect(() => {
    const subscription = BackHandler.addEventListener("hardwareBackPress", () => {
      if (filtersVisible) {
        setFiltersVisible(false);
        return true;
      }
      onBack();
      return true;
    });
    return () => subscription.remove();
  }, [filtersVisible, onBack]);

  useEffect(() => {
    const query = search.trim();
    const sequence = ++searchSequence.current;
    const dateBoundary = (date: Date, endOfDay = false) => {
      const local = new Date(
        date.getFullYear(), date.getMonth(), date.getDate(),
        endOfDay ? 23 : 0, endOfDay ? 59 : 0, endOfDay ? 59 : 0, endOfDay ? 999 : 0,
      );
      return local.toISOString();
    };
    const now = new Date();
    let from = "";
    let to = "";
    if (filters.dateRange === "today") {
      from = dateBoundary(now);
      to = dateBoundary(now, true);
    }
    if (filters.dateRange === "last7") {
      from = dateBoundary(new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6));
      to = dateBoundary(now, true);
    }
    if (filters.dateRange === "thisMonth") {
      from = dateBoundary(new Date(now.getFullYear(), now.getMonth(), 1));
      to = dateBoundary(now, true);
    }
    if (filters.dateRange === "lastMonth") {
      from = dateBoundary(new Date(now.getFullYear(), now.getMonth() - 1, 1));
      to = dateBoundary(new Date(now.getFullYear(), now.getMonth(), 0), true);
    }
    if (filters.dateRange === "custom") {
      const parseLocalDate = (value: string) => {
        const [year, month, day] = value.split("-").map(Number);
        return new Date(year, month - 1, day);
      };
      if (filters.fromDate) from = dateBoundary(parseLocalDate(filters.fromDate));
      if (filters.toDate) to = dateBoundary(parseLocalDate(filters.toDate), true);
    }
    const walletFilters: WalletHistoryFilters = {
      asset: filters.asset,
      type: filters.type,
      status: filters.status,
      direction: filters.direction === "all" ? undefined : filters.direction,
      network: filters.network,
      wallet: filters.wallet,
      minAmount: filters.minAmount,
      maxAmount: filters.maxAmount,
      from: from || undefined,
      to: to || undefined,
    };
    const hasServerFilters = Object.values(walletFilters).some((value) => Boolean(value && value !== "all"));
    if (query.length < 2 && !hasServerFilters) {
      setServerSearchRecords([]);
      setSearchError("");
      return;
    }

    const timer = setTimeout(() => {
      void searchWalletTransactionHistory(request, query.length >= 2 ? query : "", user?.id, walletFilters, assetRegistry)
        .then((records) => {
          if (mounted.current && searchSequence.current === sequence) {
            setServerSearchRecords(records);
            setSearchError("");
          }
        })
        .catch((error: unknown) => {
          if (mounted.current && searchSequence.current === sequence) {
            setServerSearchRecords([]);
            setSearchError(error instanceof Error ? error.message : "Server search is temporarily unavailable.");
          }
        });
    }, 350);

    return () => {
      clearTimeout(timer);
    };
  }, [assetRegistry, filters, request, search, searchRefreshToken, user?.id]);

  const loadMore = useCallback(async () => {
    if (loading || loadingMoreRef.current || !Object.values(progress).some((entry) => entry?.hasMore)) return;
    loadingMoreRef.current = true;
    const generation = requestGeneration.current;
    setLoadingMore(true);
    try {
      const result = await fetchUnifiedTransactionHistory(request, user?.id, progress, assetRegistry);
      if (!mounted.current || generation !== requestGeneration.current) return;
      setTransactions((current) => {
        const merged = new Map(current.map((item) => [item.id, item]));
        result.items.forEach((item) => merged.set(item.id, item));
        return Array.from(merged.values()).sort((left, right) => right.timestamp.localeCompare(left.timestamp) || left.id.localeCompare(right.id));
      });
      setProgress(result.progress);
      setErrors(result.errors);
    } catch (error) {
      if (mounted.current && generation === requestGeneration.current) setErrors([error instanceof Error ? error.message : "Unable to load more transactions."]);
    } finally {
      loadingMoreRef.current = false;
      if (mounted.current) setLoadingMore(false);
    }
  }, [assetRegistry, loading, progress, request, user?.id]);

  const searchTransactions = useMemo(() => {
    if (!serverSearchRecords.length) return transactions;
    const merged = new Map(transactions.map((item) => [item.id, item]));
    serverSearchRecords.forEach((item) => merged.set(item.id, item));
    return Array.from(merged.values());
  }, [serverSearchRecords, transactions]);

  const assets = useMemo(
    () => Array.from(new Set(searchTransactions.map((item) => item.asset).filter((asset) => asset && asset !== "—"))).sort(),
    [searchTransactions],
  );
  const types = useMemo(() => Array.from(new Set(searchTransactions.map((item) => item.type))).sort(), [searchTransactions]);
  const networks = useMemo(
    () => Array.from(new Set(searchTransactions.map((item) => item.network).filter((network): network is string => Boolean(network)))).sort(),
    [searchTransactions],
  );
  const wallets = useMemo(
    () => Array.from(new Set(searchTransactions.map((item) => item.walletId).filter((wallet): wallet is string => Boolean(wallet)))).sort(),
    [searchTransactions],
  );
  const sources = useMemo(
    () => Array.from(new Set(searchTransactions.map((item) => item.source))).sort(),
    [searchTransactions],
  );

  const filteredTransactions = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();
    const minimum = filters.minAmount ? scaledAmount(filters.minAmount) : null;
    const maximum = filters.maxAmount ? scaledAmount(filters.maxAmount) : null;
    const filtered = searchTransactions.filter((transaction) => {
      if (normalizedSearch && !transaction.searchableText.includes(normalizedSearch)) return false;
      if (filters.source !== "all"
        && transaction.source !== filters.source
        && !(filters.source === "exchange" && transaction.source === "convert")) return false;
      if (filters.asset !== "all" && transaction.asset !== filters.asset) return false;
      if (filters.type !== "all" && transaction.type !== filters.type) return false;
      if (filters.status !== "all" && transaction.status !== filters.status) return false;
      if (filters.direction !== "all" && transaction.direction !== filters.direction) return false;
      if (filters.network !== "all" && transaction.network !== filters.network) return false;
      if (filters.wallet !== "all" && transaction.walletId !== filters.wallet) return false;
      if (!matchesDateRange(transaction, filters)) return false;
      const amount = scaledAmount(transaction.amount);
      if ((minimum !== null || maximum !== null) && amount === null) return false;
      if (minimum !== null && amount !== null && amount < minimum) return false;
      if (maximum !== null && amount !== null && amount > maximum) return false;
      return true;
    });
    return filtered.sort((left, right) => newestFirst
      ? right.timestamp.localeCompare(left.timestamp) || left.id.localeCompare(right.id)
      : left.timestamp.localeCompare(right.timestamp) || left.id.localeCompare(right.id));
  }, [filters, newestFirst, search, searchTransactions]);

  const rows = useMemo(() => listItems(filteredTransactions), [filteredTransactions]);
  const activeFilterCount = useMemo(() => {
    return Number(filters.dateRange !== "all")
      + Number(filters.asset !== "all")
      + Number(filters.type !== "all")
      + Number(filters.status !== "all")
      + Number(filters.direction !== "all")
      + Number(filters.network !== "all")
      + Number(filters.wallet !== "all")
      + Number(filters.source !== "all")
      + Number(Boolean(filters.minAmount))
      + Number(Boolean(filters.maxAmount));
  }, [filters]);
  const draftFilterError = useMemo(() => {
    const validDate = (value: string) => {
      if (!value) return true;
      if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
      const [year, month, day] = value.split("-").map(Number);
      const date = new Date(year, month - 1, day);
      return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
    };
    if (draftFilters.dateRange === "custom" && (!validDate(draftFilters.fromDate) || !validDate(draftFilters.toDate))) {
      return "Enter dates in YYYY-MM-DD format.";
    }
    if (draftFilters.fromDate && draftFilters.toDate && draftFilters.fromDate > draftFilters.toDate) {
      return "The start date must be on or before the end date.";
    }
    if ((draftFilters.minAmount || draftFilters.maxAmount) && draftFilters.asset === "all") {
      return "Choose an asset before filtering by amount.";
    }
    const minimum = draftFilters.minAmount ? scaledAmount(draftFilters.minAmount) : null;
    const maximum = draftFilters.maxAmount ? scaledAmount(draftFilters.maxAmount) : null;
    if (draftFilters.minAmount && minimum === null || draftFilters.maxAmount && maximum === null) {
      return "Enter a valid amount.";
    }
    if (minimum !== null && maximum !== null && minimum > maximum) return "Minimum amount must not exceed maximum amount.";
    return "";
  }, [draftFilters]);

  function clearFilter(key: keyof HistoryFilters) {
    setFilters((current) => {
      const next = { ...current };
      if (key === "dateRange") {
        next.dateRange = "all";
        next.fromDate = "";
        next.toDate = "";
      } else if (key === "minAmount" || key === "maxAmount" || key === "fromDate" || key === "toDate") {
        next[key] = "";
      } else if (key === "direction") next.direction = "all";
      else next[key] = "all";
      return next;
    });
  }

  function filterChips() {
    const chips: Array<{ key: keyof HistoryFilters; label: string }> = [];
    if (filters.dateRange !== "all") chips.push({ key: "dateRange", label: filters.dateRange === "custom" ? "Custom dates" : typeLabel(filters.dateRange) });
    if (filters.asset !== "all") chips.push({ key: "asset", label: filters.asset });
    if (filters.type !== "all") chips.push({ key: "type", label: filters.type });
    if (filters.status !== "all") chips.push({ key: "status", label: transactionStatusLabel(filters.status as HistoryTransaction["status"]) });
    if (filters.direction !== "all") chips.push({ key: "direction", label: typeLabel(filters.direction) });
    if (filters.network !== "all") chips.push({ key: "network", label: filters.network });
    if (filters.wallet !== "all") chips.push({ key: "wallet", label: `Wallet ${filters.wallet}` });
    if (filters.source !== "all") chips.push({ key: "source", label: sourceNames[filters.source as HistorySource] });
    if (filters.minAmount) chips.push({ key: "minAmount", label: `Min ${filters.minAmount}` });
    if (filters.maxAmount) chips.push({ key: "maxAmount", label: `Max ${filters.maxAmount}` });
    return chips;
  }

  const applyDraftFilters = () => {
    if (draftFilterError) return;
    setFilters(draftFilters);
    setFiltersVisible(false);
  };

  const resetDraftFilters = () => {
    setDraftFilters(emptyFilters(initialSource));
  };

  const isOfflineOnly = transactions.some((transaction) => transaction.source === "exapay");
  const allSourcesFailed = !loading && !transactions.length && errors.length > 0;
  const renderItem = ({ item }: { item: ListItem }) => item.kind === "header"
    ? <Text style={styles.dateHeader}>{item.label}</Text>
    : <HistoryRow transaction={item.transaction} onPress={() => onOpenTransaction(item.transaction.detail)} />;

  return (
    <View style={styles.fill}>
      <View style={[styles.header, { paddingTop: insets.top + 8 }]}>
        <AnimatedPressable onPress={onBack} style={styles.headerButton} accessibilityRole="button" accessibilityLabel="Go back">
          <Ionicons name="arrow-back" size={20} color="#f0f3f6" />
        </AnimatedPressable>
        <Text style={[styles.title, !fontsReady ? styles.systemFont : null]} accessibilityRole="header">Transaction History</Text>
        <AnimatedPressable
          onPress={() => setNewestFirst((current) => !current)}
          style={styles.headerButton}
          accessibilityRole="button"
          accessibilityLabel={newestFirst ? "Sort oldest first" : "Sort newest first"}
        >
          <Ionicons name="ellipsis-horizontal" size={20} color="#c2cad4" />
        </AnimatedPressable>
      </View>

      <View style={styles.searchRow}>
        <View style={styles.searchField}>
          <Ionicons name="search-outline" size={18} color="#8f9aa8" />
          <TextInput
            value={search}
            onChangeText={setSearch}
            placeholder="Search reference, recipient, asset..."
            placeholderTextColor="#717c89"
            style={styles.searchInput}
            returnKeyType="search"
            accessibilityLabel="Search transactions"
            maxLength={120}
          />
          {search ? (
            <Pressable onPress={() => setSearch("")} style={styles.clearSearch} accessibilityRole="button" accessibilityLabel="Clear search">
              <Ionicons name="close-circle" size={17} color="#8792a0" />
            </Pressable>
          ) : null}
          {searchError ? (
            <View style={styles.errorNotice} accessibilityRole="alert">
              <Ionicons name="search-outline" size={15} color="#e8b866" />
              <Text style={styles.errorNoticeText}>Wallet server search unavailable: {searchError}</Text>
            </View>
          ) : null}
        </View>
        <AnimatedPressable
          onPress={() => {
            setDraftFilters(filters);
            setFiltersVisible(true);
          }}
          style={styles.filterButton}
          accessibilityRole="button"
          accessibilityLabel={`Open filters${activeFilterCount ? `, ${activeFilterCount} active` : ""}`}
        >
          <Ionicons name="options-outline" size={19} color="#dce4ec" />
          {activeFilterCount > 0 ? <View style={styles.filterCount}><Text style={styles.filterCountText}>{activeFilterCount}</Text></View> : null}
        </AnimatedPressable>
      </View>

      {filterChips().length > 0 ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.activeFilters}>
          {filterChips().map((chip) => (
            <Pressable key={chip.key} onPress={() => clearFilter(chip.key)} style={styles.activeChip}>
              <Text style={styles.activeChipText}>{chip.label}</Text>
              <Ionicons name="close" size={13} color="#dce4ec" />
            </Pressable>
          ))}
          <Pressable onPress={() => setFilters(emptyFilters(initialSource))} style={styles.clearAllButton}>
            <Text style={styles.clearAllText}>Clear all</Text>
          </Pressable>
        </ScrollView>
      ) : null}

      {offlineCacheUsed ? (
        <View style={styles.localNotice} accessibilityRole="alert">
          <Ionicons name="cloud-offline-outline" size={15} color="#b5a2ed" />
          <Text style={styles.localNoticeText}>Secure on-device copy for this signed-in account. Status may be stale; reconnect to verify settlement.</Text>
        </View>
      ) : null}
      {isOfflineOnly ? (
        <View style={styles.localNotice}>
          <Ionicons name="phone-portrait-outline" size={15} color="#b5a2ed" />
          <Text style={styles.localNoticeText}>Offline entries are in-memory simulations for this app session, not online-settled funds.</Text>
        </View>
      ) : null}
      {initialSource === "exapay" && !isOfflineOnly ? (
        <View style={styles.localNotice}>
          <Ionicons name="information-circle-outline" size={15} color="#b5a2ed" />
          <Text style={styles.localNoticeText}>No ExaPay online history API is available. Only local offline simulations from this app session can appear here.</Text>
        </View>
      ) : null}
      {errors.length > 0 ? (
        <View style={styles.errorNotice} accessibilityRole="alert">
          <Ionicons name="warning-outline" size={15} color="#e8b866" />
          <Text style={styles.errorNoticeText} numberOfLines={4}>
            Some history sources are unavailable: {errors.join(" · ")}
            {offlineCacheUsed ? " Showing recent encrypted on-device history; verify online before relying on current status." : transactions.length ? " Cached status may be out of date; reconnect to verify settlement." : ""}
          </Text>
        </View>
      ) : null}

      {loading ? (
        <View style={styles.loadingState}>
          <ActivityIndicator size="small" color="#dce4ec" />
          <Text style={styles.loadingText}>Loading transaction history…</Text>
          <View style={styles.skeletonRow} />
          <View style={styles.skeletonRow} />
          <View style={styles.skeletonRow} />
        </View>
      ) : allSourcesFailed ? (
        <View style={styles.emptyState}>
          <Ionicons name="cloud-offline-outline" size={30} color="#8f9aa8" />
          <Text style={styles.emptyTitle}>History couldn’t load</Text>
          <Text style={styles.emptyText}>Check your connection and try again. No transaction status has been inferred.</Text>
          <AnimatedPressable onPress={() => void loadFirstPage()} style={styles.retryButton}>
            <Text style={styles.retryText}>Try again</Text>
          </AnimatedPressable>
        </View>
      ) : filteredTransactions.length === 0 ? (
        <View style={styles.emptyState}>
          <Ionicons name={search || activeFilterCount ? "search-outline" : "receipt-outline"} size={30} color="#8f9aa8" />
          <Text style={styles.emptyTitle}>{search || activeFilterCount ? "No matching transactions" : "No transactions yet"}</Text>
          <Text style={styles.emptyText}>{search || activeFilterCount ? "Adjust your search or clear some filters." : "Your confirmed account activity will appear here when available."}</Text>
          {errors.length > 0 ? (
            <AnimatedPressable onPress={() => void loadFirstPage(true)} style={styles.retryButton} accessibilityRole="button">
              <Text style={styles.retryText}>Refresh history</Text>
            </AnimatedPressable>
          ) : null}
        </View>
      ) : (
        <FlatList
          data={rows}
          keyExtractor={(item) => item.id}
          renderItem={renderItem}
          contentContainerStyle={styles.listContent}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void loadFirstPage(true)} tintColor="#dce4ec" />}
          onEndReached={() => void loadMore()}
          onEndReachedThreshold={0.45}
          showsVerticalScrollIndicator={false}
          ListFooterComponent={loadingMore ? <ActivityIndicator style={styles.footerLoader} size="small" color="#dce4ec" /> : null}
          ItemSeparatorComponent={({ leadingItem }) => leadingItem?.kind === "transaction" ? <View style={styles.separator} /> : null}
        />
      )}

      <Modal visible={filtersVisible} transparent animationType="slide" onRequestClose={() => setFiltersVisible(false)}>
        <View style={styles.modalBackdrop}>
          <Pressable style={styles.backdropDismiss} onPress={() => setFiltersVisible(false)} accessibilityLabel="Close filters" />
          <View style={[styles.filterSheet, { paddingBottom: Math.max(insets.bottom, 14) }]}>
            <View style={styles.sheetHandle} />
            <View style={styles.sheetHeader}>
              <View>
                <Text style={styles.sheetTitle}>Filter transactions</Text>
                <Text style={styles.sheetSubtitle}>{activeFilterCount} active</Text>
              </View>
              <Pressable onPress={() => setFiltersVisible(false)} style={styles.closeButton} accessibilityRole="button" accessibilityLabel="Close filters">
                <Ionicons name="close" size={19} color="#dce4ec" />
              </Pressable>
            </View>
            <ScrollView style={styles.filterScroll} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
              <FilterSection title="Date">
                <FilterChip label="Any time" selected={draftFilters.dateRange === "all"} onPress={() => setDraftFilters((value) => ({ ...value, dateRange: "all" }))} />
                <FilterChip label="Today" selected={draftFilters.dateRange === "today"} onPress={() => setDraftFilters((value) => ({ ...value, dateRange: "today" }))} />
                <FilterChip label="Last 7 days" selected={draftFilters.dateRange === "last7"} onPress={() => setDraftFilters((value) => ({ ...value, dateRange: "last7" }))} />
                <FilterChip label="This month" selected={draftFilters.dateRange === "thisMonth"} onPress={() => setDraftFilters((value) => ({ ...value, dateRange: "thisMonth" }))} />
                <FilterChip label="Last month" selected={draftFilters.dateRange === "lastMonth"} onPress={() => setDraftFilters((value) => ({ ...value, dateRange: "lastMonth" }))} />
                <FilterChip label="Custom range" selected={draftFilters.dateRange === "custom"} onPress={() => setDraftFilters((value) => ({ ...value, dateRange: "custom" }))} />
              </FilterSection>
              {draftFilters.dateRange === "custom" ? (
                <View style={styles.dateInputs}>
                  <TextInput
                    value={draftFilters.fromDate}
                    onChangeText={(fromDate) => setDraftFilters((value) => ({ ...value, fromDate }))}
                    placeholder="From · YYYY-MM-DD"
                    placeholderTextColor="#717c89"
                    style={styles.filterInput}
                    autoCapitalize="none"
                    keyboardType="numbers-and-punctuation"
                    accessibilityLabel="Start date"
                  />
                  <TextInput
                    value={draftFilters.toDate}
                    onChangeText={(toDate) => setDraftFilters((value) => ({ ...value, toDate }))}
                    placeholder="To · YYYY-MM-DD"
                    placeholderTextColor="#717c89"
                    style={styles.filterInput}
                    autoCapitalize="none"
                    keyboardType="numbers-and-punctuation"
                    accessibilityLabel="End date"
                  />
                </View>
              ) : null}
              <FilterSection title="Asset / currency">
                <FilterChip label="All assets" selected={draftFilters.asset === "all"} onPress={() => setDraftFilters((value) => ({ ...value, asset: "all" }))} />
                {Array.from(new Set([...assetRegistry.map((entry) => entry.currency), ...assets])).map((asset) => <FilterChip key={asset} label={asset} selected={draftFilters.asset === asset} onPress={() => setDraftFilters((value) => ({ ...value, asset }))} />)}
              </FilterSection>
              <FilterSection title="Transaction type">
                <FilterChip label="All types" selected={draftFilters.type === "all"} onPress={() => setDraftFilters((value) => ({ ...value, type: "all" }))} />
                {types.map((type) => <FilterChip key={type} label={type} selected={draftFilters.type === type} onPress={() => setDraftFilters((value) => ({ ...value, type }))} />)}
              </FilterSection>
              <FilterSection title="Status">
                <FilterChip label="All statuses" selected={draftFilters.status === "all"} onPress={() => setDraftFilters((value) => ({ ...value, status: "all" }))} />
                {statuses.map((status) => <FilterChip key={status} label={transactionStatusLabel(status as HistoryTransaction["status"])} selected={draftFilters.status === status} onPress={() => setDraftFilters((value) => ({ ...value, status }))} />)}
              </FilterSection>
              <FilterSection title="Direction">
                <FilterChip label="All" selected={draftFilters.direction === "all"} onPress={() => setDraftFilters((value) => ({ ...value, direction: "all" }))} />
                <FilterChip label="Sent / debit" selected={draftFilters.direction === "debit"} onPress={() => setDraftFilters((value) => ({ ...value, direction: "debit" }))} />
                <FilterChip label="Received / credit" selected={draftFilters.direction === "credit"} onPress={() => setDraftFilters((value) => ({ ...value, direction: "credit" }))} />
              </FilterSection>
              <FilterSection title="Network">
                <FilterChip label="All networks" selected={draftFilters.network === "all"} onPress={() => setDraftFilters((value) => ({ ...value, network: "all" }))} />
                {networks.map((network) => <FilterChip key={network} label={network} selected={draftFilters.network === network} onPress={() => setDraftFilters((value) => ({ ...value, network }))} />)}
              </FilterSection>
              {wallets.length ? (
                <FilterSection title="Wallet">
                  <FilterChip label="All wallets" selected={draftFilters.wallet === "all"} onPress={() => setDraftFilters((value) => ({ ...value, wallet: "all" }))} />
                  {wallets.map((wallet) => <FilterChip key={wallet} label={wallet} selected={draftFilters.wallet === wallet} onPress={() => setDraftFilters((value) => ({ ...value, wallet }))} />)}
                </FilterSection>
              ) : null}
              <FilterSection title="Source">
                <FilterChip label="All sources" selected={draftFilters.source === "all"} onPress={() => setDraftFilters((value) => ({ ...value, source: "all" }))} />
                {sources.map((source) => <FilterChip key={source} label={sourceNames[source]} selected={draftFilters.source === source} onPress={() => setDraftFilters((value) => ({ ...value, source }))} />)}
              </FilterSection>
              <FilterSection title="Amount range">
                <TextInput
                  value={draftFilters.minAmount}
                  onChangeText={(minAmount) => setDraftFilters((value) => ({ ...value, minAmount: minAmount.replace(/[^\d.]/g, "") }))}
                  placeholder="Minimum amount"
                  placeholderTextColor="#717c89"
                  keyboardType="decimal-pad"
                  style={styles.filterInput}
                  accessibilityLabel="Minimum amount"
                />
                <TextInput
                  value={draftFilters.maxAmount}
                  onChangeText={(maxAmount) => setDraftFilters((value) => ({ ...value, maxAmount: maxAmount.replace(/[^\d.]/g, "") }))}
                  placeholder="Maximum amount"
                  placeholderTextColor="#717c89"
                  keyboardType="decimal-pad"
                  style={styles.filterInput}
                  accessibilityLabel="Maximum amount"
                />
              </FilterSection>
              {draftFilterError ? <Text style={styles.filterError}>{draftFilterError}</Text> : null}
            </ScrollView>
            <View style={styles.sheetActions}>
              <Pressable onPress={resetDraftFilters} style={styles.resetButton} accessibilityRole="button">
                <Text style={styles.resetText}>Reset filters</Text>
              </Pressable>
              <Pressable onPress={applyDraftFilters} style={[styles.applyButton, draftFilterError ? styles.disabledButton : null]} accessibilityRole="button" accessibilityState={{ disabled: Boolean(draftFilterError) }}>
                <Text style={styles.applyText}>Apply filters</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

function typeLabel(value: string) {
  return value.replace(/([A-Z])/g, " $1").replace(/[_-]+/g, " ").trim().replace(/(^|\s)\S/g, (letter) => letter.toUpperCase());
}

function FilterSection({ title, children }: { title: string; children: ReactNode }) {
  return (
    <View style={styles.filterSection}>
      <Text style={styles.filterSectionTitle}>{title}</Text>
      <View style={styles.chips}>{children}</View>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: "#090b12" },
  header: { minHeight: 62, flexDirection: "row", alignItems: "center", justifyContent: "space-between", paddingHorizontal: 18, borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.06)" },
  headerButton: { width: 42, height: 42, alignItems: "center", justifyContent: "center", borderRadius: 13 },
  title: { flex: 1, color: "#f2f5f8", fontFamily: fonts.display, fontSize: 18, textAlign: "center" },
  systemFont: { fontFamily: fonts.semibold },
  searchRow: { flexDirection: "row", alignItems: "center", gap: 10, paddingHorizontal: 18, paddingTop: 16, paddingBottom: 10 },
  searchField: { minHeight: 48, flex: 1, flexDirection: "row", alignItems: "center", gap: 9, paddingHorizontal: 13, borderRadius: 14, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "#111720" },
  searchInput: { flex: 1, minWidth: 0, color: "#edf1f5", fontFamily: fonts.body, fontSize: 12, paddingVertical: 0 },
  clearSearch: { width: 28, height: 36, alignItems: "center", justifyContent: "center" },
  filterButton: { width: 48, height: 48, alignItems: "center", justifyContent: "center", borderRadius: 14, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "#111720" },
  filterCount: { position: "absolute", top: -4, right: -4, minWidth: 17, height: 17, paddingHorizontal: 4, alignItems: "center", justifyContent: "center", borderRadius: 9, backgroundColor: "#6dc99a" },
  filterCountText: { color: "#0b1510", fontFamily: fonts.semibold, fontSize: 9 },
  activeFilters: { alignItems: "center", gap: 7, paddingHorizontal: 18, paddingBottom: 9 },
  activeChip: { minHeight: 29, flexDirection: "row", alignItems: "center", gap: 5, paddingHorizontal: 9, borderRadius: 9, backgroundColor: "#202934" },
  activeChipText: { color: "#dce4ec", fontFamily: fonts.medium, fontSize: 10 },
  clearAllButton: { minHeight: 30, justifyContent: "center", paddingHorizontal: 6 },
  clearAllText: { color: "#a6afbc", fontFamily: fonts.medium, fontSize: 10 },
  localNotice: { flexDirection: "row", alignItems: "center", gap: 8, marginHorizontal: 18, marginBottom: 8, padding: 10, borderRadius: 11, backgroundColor: "rgba(143,159,245,0.09)" },
  localNoticeText: { flex: 1, color: "#b9c3cf", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  errorNotice: { flexDirection: "row", alignItems: "flex-start", gap: 8, marginHorizontal: 18, marginBottom: 8, padding: 10, borderRadius: 11, backgroundColor: "rgba(232,184,102,0.08)" },
  errorNoticeText: { flex: 1, color: "#e6c58e", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  listContent: { paddingHorizontal: 18, paddingTop: 6, paddingBottom: 30 },
  dateHeader: { paddingTop: 15, paddingBottom: 7, color: "#9da8b7", fontFamily: fonts.semibold, fontSize: 11, letterSpacing: 0.3 },
  row: { minHeight: 80, flexDirection: "row", alignItems: "center", gap: 11, paddingVertical: 11 },
  rowIcon: { width: 40, height: 40, alignItems: "center", justifyContent: "center", borderRadius: 13, borderWidth: 1, borderColor: "rgba(255,255,255,0.06)", backgroundColor: "#141b24" },
  rowCopy: { flex: 1, minWidth: 0, gap: 4 },
  rowTitle: { color: "#eef1f4", fontFamily: fonts.semibold, fontSize: 12 },
  rowMeta: { color: "#8995a5", fontFamily: fonts.body, fontSize: 9 },
  rowStatus: { alignSelf: "flex-start", transform: [{ scale: 0.85 }] },
  amountGroup: { maxWidth: "34%", alignItems: "flex-end", gap: 5 },
  amount: { color: "#e7ebef", fontFamily: fonts.semibold, fontSize: 12, textAlign: "right" },
  credit: { color: "#72d3a0" },
  debit: { color: "#e7ebef" },
  asset: { color: "#8995a5", fontFamily: fonts.medium, fontSize: 9, textAlign: "right" },
  separator: { height: 1, marginLeft: 51, backgroundColor: "rgba(255,255,255,0.055)" },
  loadingState: { flex: 1, alignItems: "center", justifyContent: "center", gap: 12, paddingHorizontal: 20 },
  loadingText: { color: "#9da8b7", fontFamily: fonts.body, fontSize: 11 },
  skeletonRow: { width: "100%", height: 61, marginTop: 5, borderRadius: 13, backgroundColor: "#111720" },
  emptyState: { flex: 1, alignItems: "center", justifyContent: "center", paddingHorizontal: 36 },
  emptyTitle: { marginTop: 13, color: "#edf1f5", fontFamily: fonts.semibold, fontSize: 15, textAlign: "center" },
  emptyText: { marginTop: 7, color: "#8995a5", fontFamily: fonts.body, fontSize: 11, lineHeight: 17, textAlign: "center" },
  retryButton: { minHeight: 42, justifyContent: "center", marginTop: 16, paddingHorizontal: 20, borderRadius: 12, backgroundColor: "#e6edf3" },
  retryText: { color: "#101820", fontFamily: fonts.semibold, fontSize: 11 },
  footerLoader: { paddingVertical: 18 },
  modalBackdrop: { flex: 1, justifyContent: "flex-end", backgroundColor: "rgba(0,0,0,0.68)" },
  backdropDismiss: { ...StyleSheet.absoluteFillObject },
  filterSheet: { maxHeight: "89%", paddingHorizontal: 18, paddingTop: 9, borderTopLeftRadius: 22, borderTopRightRadius: 22, borderWidth: 1, borderColor: "rgba(255,255,255,0.1)", backgroundColor: "#0d1219" },
  sheetHandle: { width: 36, height: 4, alignSelf: "center", borderRadius: 2, backgroundColor: "#4b5663" },
  sheetHeader: { minHeight: 64, flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  sheetTitle: { color: "#f2f5f8", fontFamily: fonts.display, fontSize: 17 },
  sheetSubtitle: { marginTop: 3, color: "#8995a5", fontFamily: fonts.body, fontSize: 10 },
  closeButton: { width: 38, height: 38, alignItems: "center", justifyContent: "center", borderRadius: 12, backgroundColor: "#171e28" },
  filterScroll: { flexShrink: 1 },
  filterSection: { paddingTop: 13, paddingBottom: 3, borderTopWidth: 1, borderTopColor: "rgba(255,255,255,0.055)" },
  filterSectionTitle: { marginBottom: 9, color: "#bdc6d0", fontFamily: fonts.semibold, fontSize: 11 },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: 7 },
  filterChip: { minHeight: 34, justifyContent: "center", paddingHorizontal: 11, borderRadius: 10, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "#121923" },
  filterChipSelected: { borderColor: "rgba(114,211,160,0.42)", backgroundColor: "rgba(114,211,160,0.12)" },
  filterChipText: { color: "#a6afbc", fontFamily: fonts.medium, fontSize: 10 },
  filterChipTextSelected: { color: "#9be0b8" },
  dateInputs: { flexDirection: "row", gap: 8, paddingTop: 9 },
  filterInput: { minHeight: 42, flex: 1, marginBottom: 7, paddingHorizontal: 11, borderRadius: 10, borderWidth: 1, borderColor: "rgba(255,255,255,0.09)", backgroundColor: "#111720", color: "#e7ebef", fontFamily: fonts.body, fontSize: 11 },
  filterError: { marginTop: 4, marginBottom: 8, color: "#f08c8c", fontFamily: fonts.body, fontSize: 10 },
  sheetActions: { flexDirection: "row", gap: 10, paddingTop: 11, borderTopWidth: 1, borderTopColor: "rgba(255,255,255,0.06)" },
  resetButton: { minHeight: 47, flex: 1, alignItems: "center", justifyContent: "center", borderRadius: 12, borderWidth: 1, borderColor: "rgba(255,255,255,0.1)" },
  resetText: { color: "#b9c3cf", fontFamily: fonts.semibold, fontSize: 11 },
  applyButton: { minHeight: 47, flex: 1.4, alignItems: "center", justifyContent: "center", borderRadius: 12, backgroundColor: "#e6edf3" },
  disabledButton: { opacity: 0.45 },
  applyText: { color: "#101820", fontFamily: fonts.semibold, fontSize: 11 },
});
