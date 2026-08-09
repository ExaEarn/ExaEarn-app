import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ArrowDownToLine,
  ArrowLeft,
  ArrowUpFromLine,
  ChevronDown,
  CircleAlert,
  Eye,
  EyeOff,
  History,
  Repeat2,
  Search,
  Settings2,
  Wallet,
  X,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import { useLanguage } from "../../context/LanguageContext.jsx";
import { useWebSocketEvent } from "../../services/webSocketService";
import TransferModal from "../../components/TransferModal";

const DISPLAY_CURRENCIES = ["USD", "USDT", "NGN", "EUR", "GBP", "BTC"];
const ASSET_FILTERS = ["all", "crypto", "fiat"];
const SORT_OPTIONS = [
  { value: "value", label: "Value" },
  { value: "balance", label: "Balance" },
  { value: "asset", label: "Asset" },
];

const ASSET_META = {
  BTC: { name: "Bitcoin", tone: "from-amber-300 to-orange-500" },
  ETH: { name: "Ethereum", tone: "from-violet-400 to-indigo-500" },
  USDT: { name: "Tether", tone: "from-emerald-400 to-green-500" },
  USDC: { name: "USD Coin", tone: "from-sky-400 to-blue-500" },
  XRP: { name: "XRP", tone: "from-slate-300 to-slate-500" },
  EXA: { name: "ExaToken", tone: "from-cyan-400 to-blue-500" },
  NGN: { name: "Nigerian Naira", tone: "from-lime-400 to-emerald-500" },
  USD: { name: "US Dollar", tone: "from-emerald-300 to-teal-500" },
  EUR: { name: "Euro", tone: "from-blue-400 to-indigo-500" },
  BNB: { name: "BNB", tone: "from-yellow-300 to-amber-500" },
  SOL: { name: "Solana", tone: "from-fuchsia-400 to-cyan-400" },
  TRX: { name: "Tron", tone: "from-rose-400 to-red-500" },
  TON: { name: "Toncoin", tone: "from-cyan-300 to-sky-500" },
};

function formatAmount(value, maximumFractionDigits = 8) {
  const number = Number(value);
  if (!Number.isFinite(number)) return "--";
  return number.toLocaleString(undefined, { maximumFractionDigits });
}

function formatDisplayValue(value, currency, hidden = false) {
  if (hidden) return "••••••";
  const number = Number(value);
  if (!Number.isFinite(number)) return "--";

  if (["BTC", "USDT"].includes(currency)) {
    return `${formatAmount(number, currency === "BTC" ? 8 : 2)} ${currency}`;
  }

  try {
    return new Intl.NumberFormat(undefined, {
      style: "currency",
      currency,
      maximumFractionDigits: currency === "NGN" ? 2 : 2,
    }).format(number);
  } catch {
    return `${formatAmount(number, 2)} ${currency}`;
  }
}

function getAssetMeta(code, type) {
  const upper = String(code || "").toUpperCase();
  return {
    code: upper,
    name: ASSET_META[upper]?.name || upper,
    tone: ASSET_META[upper]?.tone || (type === "fiat" ? "from-emerald-400 to-teal-500" : "from-violet-400 to-indigo-500"),
  };
}

function safeValue(payload) {
  const data = payload?.data;
  if (!data || typeof data !== "object") return null;
  return data;
}

function sanitizePortfolio(portfolio) {
  if (!portfolio) return null;
  return {
    total_value: portfolio.total_value,
    currency: portfolio.currency,
    breakdown: Array.isArray(portfolio.breakdown) ? portfolio.breakdown : [],
  };
}

function mergeAssets(balances, portfolio) {
  const breakdown = new Map((portfolio?.breakdown || []).map((row) => [String(row.asset || "").toUpperCase(), row]));

  return (balances || []).map((balance) => {
    const code = String(balance.currency || "").toUpperCase();
    const meta = getAssetMeta(code, balance.type);
    const total = Number(balance.total || 0);
    const available = Number(balance.balance || 0);
    const locked = Number(balance.locked || 0);
    const row = breakdown.get(code);
    const totalValue = Number(row?.value ?? row?.value_usdt ?? NaN);
    const unitValue = Number.isFinite(totalValue) && total > 0 ? totalValue / total : null;
    const availableValue = unitValue !== null ? unitValue * available : null;
    const lockedValue = unitValue !== null ? unitValue * locked : null;

    return {
      ...balance,
      code,
      name: meta.name,
      tone: meta.tone,
      total,
      available,
      locked,
      totalValue,
      availableValue,
      lockedValue,
    };
  });
}

function buildAccountRows(accountPayload, mergedAssets) {
  const accountSource = accountPayload?.accounts;
  const assetRows = Array.isArray(accountPayload?.assets) ? accountPayload.assets : [];
  const defaults = [
    { key: "funding", label: "Funding Account", description: "Deposits, withdrawals and receive flows." },
    { key: "unified_trading", label: "Unified Trading Account", description: "Shared collateral for Spot and Futures." },
  ];

  const accountEntries = Array.isArray(accountSource)
    ? accountSource
    : accountSource && typeof accountSource === "object"
      ? Object.entries(accountSource).map(([key, values]) => ({ key, ...(values && typeof values === "object" ? values : {}) }))
      : defaults;

  const unitValues = new Map(mergedAssets.map((asset) => [asset.code, asset.total > 0 && Number.isFinite(asset.totalValue) ? asset.totalValue / asset.total : null]));

  return accountEntries.map((account) => {
    const key = String(account.key || "").toLowerCase();
    const accountAssets = assetRows
      .map((assetRow) => {
        const bucket = assetRow?.[key];
        if (!bucket || typeof bucket !== "object") return null;
        const asset = String(assetRow.asset || "").toUpperCase();
        return {
          asset,
          available: String(bucket.available ?? "0"),
          locked: String(bucket.locked ?? "0"),
          total: String(bucket.total ?? "0"),
          transferable: String(bucket.transferable ?? bucket.available ?? "0"),
          inUse: String(bucket.in_use ?? bucket.locked ?? "0"),
          futuresAvailable: String(bucket.futures_available ?? "0"),
          futuresMargin: String(bucket.futures_margin ?? "0"),
          spotAvailable: String(bucket.spot_available ?? bucket.available ?? "0"),
          spotLocked: String(bucket.spot_locked ?? bucket.locked ?? "0"),
        };
      })
      .filter(Boolean);

    let totalValue = 0;
    let availableValue = 0;
    let lockedValue = 0;
    let transferableValue = 0;

    accountAssets.forEach((bucket) => {
      const unit = unitValues.get(bucket.asset);
      if (unit === null || unit === undefined || !Number.isFinite(unit)) return;
      totalValue += Number(bucket.total || 0) * unit;
      availableValue += Number(bucket.available || 0) * unit;
      lockedValue += Number(bucket.locked || 0) * unit;
      transferableValue += Number(bucket.transferable || 0) * unit;
    });

    return {
      ...account,
      key,
      label: account.label || defaults.find((item) => item.key === key)?.label || "Account",
      description: account.description || defaults.find((item) => item.key === key)?.description || "",
      assets: accountAssets,
      assetCount: accountAssets.filter((item) => Number(item.total || 0) > 0).length,
      totalValue,
      availableValue,
      lockedValue,
      transferableValue,
    };
  });
}

function Assets({ onBack, onOpenSend, onOpenAddFunds, onOpenSwap, onOpenWithdraw, onOpenTransactions, onOpenTrade }) {
  const { request, user } = useAuth();
  const { t } = useLanguage();
  const [privacyMode, setPrivacyMode] = useState(() => localStorage.getItem("exaearn_assets_privacy") === "hidden");
  const [displayCurrency, setDisplayCurrency] = useState(() => localStorage.getItem("exaearn_assets_currency") || "USD");
  const [hideZeroBalances, setHideZeroBalances] = useState(() => localStorage.getItem("exaearn_assets_hide_zero") === "true");
  const [activeTab, setActiveTab] = useState("assets");
  const [assetFilter, setAssetFilter] = useState("all");
  const [sortBy, setSortBy] = useState("value");
  const [search, setSearch] = useState("");
  const [balances, setBalances] = useState([]);
  const [portfolio, setPortfolio] = useState(null);
  const [accounts, setAccounts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState("");
  const [selectedAsset, setSelectedAsset] = useState(null);
  const [showCurrencySelector, setShowCurrencySelector] = useState(false);
  const [showTransferModal, setShowTransferModal] = useState(false);
  const [showFilters, setShowFilters] = useState(false);

  const loadAssets = useCallback(async (mode = "full") => {
    if (mode === "full") setLoading(true);
    else setRefreshing(true);
    setError("");

    try {
      const [balancesPayload, portfolioPayload, accountsPayload] = await Promise.all([
        request("/api/wallet/balances", { method: "GET" }),
        request(`/api/portfolio?base_currency=${encodeURIComponent(displayCurrency)}`, { method: "GET" }),
        request("/api/accounts", { method: "GET" }),
      ]);

      setBalances(Array.isArray(balancesPayload?.data) ? balancesPayload.data : []);
      setPortfolio(sanitizePortfolio(safeValue(portfolioPayload)));
      setAccounts(buildAccountRows(safeValue(accountsPayload), mergeAssets(Array.isArray(balancesPayload?.data) ? balancesPayload.data : [], sanitizePortfolio(safeValue(portfolioPayload)))));
    } catch (loadError) {
      setError(loadError?.message || t("assets.unableToLoadBalances"));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [displayCurrency, request]);

  useEffect(() => {
    loadAssets("full");
  }, [loadAssets]);

  useWebSocketEvent("portfolio:update", useCallback(() => {
    loadAssets("refresh");
  }, [loadAssets]));

  useEffect(() => {
    localStorage.setItem("exaearn_assets_privacy", privacyMode ? "hidden" : "visible");
  }, [privacyMode]);

  useEffect(() => {
    localStorage.setItem("exaearn_assets_currency", displayCurrency);
  }, [displayCurrency]);

  useEffect(() => {
    localStorage.setItem("exaearn_assets_hide_zero", String(hideZeroBalances));
  }, [hideZeroBalances]);

  const mergedAssets = useMemo(() => mergeAssets(balances, portfolio), [balances, portfolio]);

  const totals = useMemo(() => {
    let available = 0;
    let locked = 0;
    mergedAssets.forEach((asset) => {
      if (Number.isFinite(asset.availableValue)) available += asset.availableValue;
      if (Number.isFinite(asset.lockedValue)) locked += asset.lockedValue;
    });
    return { available, locked };
  }, [mergedAssets]);

  const filteredAssets = useMemo(() => {
    let next = [...mergedAssets];

    if (assetFilter !== "all") next = next.filter((asset) => asset.type === assetFilter);
    if (hideZeroBalances) next = next.filter((asset) => asset.total > 0);
    if (search.trim()) {
      const needle = search.trim().toLowerCase();
      next = next.filter((asset) => asset.code.toLowerCase().includes(needle) || asset.name.toLowerCase().includes(needle));
    }

    next.sort((left, right) => {
      if (sortBy === "asset") return left.code.localeCompare(right.code);
      if (sortBy === "balance") return right.total - left.total;
      return (Number.isFinite(right.totalValue) ? right.totalValue : -1) - (Number.isFinite(left.totalValue) ? left.totalValue : -1);
    });

    return next;
  }, [assetFilter, hideZeroBalances, mergedAssets, search, sortBy]);

  const selectedAssetRow = useMemo(() => filteredAssets.find((asset) => asset.code === selectedAsset) || mergedAssets.find((asset) => asset.code === selectedAsset) || null, [filteredAssets, mergedAssets, selectedAsset]);

  const handleTransfer = async (payload) => {
    try {
      await request("/api/accounts/transfer", {
        method: "POST",
        body: JSON.stringify(payload),
      });
      setShowTransferModal(false);
      await loadAssets("refresh");
    } catch (transferError) {
      setError(transferError?.message || t("assets.unableToCompleteTransfer"));
      throw transferError;
    }
  };

  const portfolioValue = portfolio?.total_value;
  const portfolioCurrency = portfolio?.currency || displayCurrency;
  const btcEquivalent = useMemo(() => {
    if (!portfolio || portfolio.currency === "BTC") return portfolio?.total_value || null;
    const btcAsset = mergedAssets.find((asset) => asset.code === "BTC");
    if (!btcAsset || !Number.isFinite(btcAsset.totalValue) || btcAsset.totalValue <= 0) return null;
    const total = Number(portfolio.total_value);
    if (!Number.isFinite(total)) return null;
    return total / (btcAsset.totalValue / Math.max(btcAsset.total, 1e-8));
  }, [mergedAssets, portfolio]);

  return (
    <main className="min-h-[100dvh] bg-[var(--exa-bg-primary)] px-3 pb-8 pt-3 text-[var(--exa-text-primary)] sm:px-4 lg:px-6">
      <div className="mx-auto flex w-full max-w-[1380px] flex-col gap-4">
        <header className="exa-surface flex items-center justify-between gap-3 rounded-2xl px-4 py-3">
          <div className="flex min-w-0 items-center gap-3">
            {onBack ? (
              <button type="button" onClick={onBack} className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/[0.04] text-slate-100">
                <ArrowLeft className="h-4 w-4" />
              </button>
            ) : null}
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-white">{user?.name || t("assets.title")}</p>
              <p className="text-xs text-[var(--exa-text-muted)]">{t("assets.title")}</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button type="button" onClick={() => setPrivacyMode((value) => !value)} aria-label={t("assets.toggleBalancePrivacy")} className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/[0.04] text-slate-200">
              {privacyMode ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
            <button type="button" onClick={onOpenTransactions} aria-label={t("assets.openTransactionHistory")} className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/[0.04] text-slate-200">
              <History className="h-4 w-4" />
            </button>
          </div>
        </header>

        {error ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-100">
            <div className="flex items-start justify-between gap-3">
              <span>{error}</span>
              <button type="button" onClick={() => loadAssets("refresh")} className="font-semibold text-rose-100">{t("assets.retry")}</button>
            </div>
          </div>
        ) : null}

        <section className="exa-surface-elevated overflow-hidden rounded-2xl px-4 py-4">
          <div className="flex items-start justify-between gap-3">
            <div>
              <div className="flex items-center gap-2 text-xs uppercase tracking-[0.18em] text-slate-500">
                <span>{t("assets.totalAssets")}</span>
                <button type="button" onClick={() => setPrivacyMode((value) => !value)} aria-label={t("assets.toggleBalancePrivacy")} className="text-[var(--exa-text-muted)]">
                  {privacyMode ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
              {loading ? <div className="mt-2 h-10 w-52 animate-pulse rounded-xl bg-white/[0.05]" /> : <p className="exa-data-number mt-2 text-3xl font-semibold tracking-tight text-[var(--exa-text-primary)] sm:text-4xl">{formatDisplayValue(portfolioValue, portfolioCurrency, privacyMode)}</p>}
              <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-slate-400">
                <button type="button" onClick={() => setShowCurrencySelector(true)} className="inline-flex items-center gap-1 rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-1.5 text-xs font-medium text-slate-200">
                  {portfolioCurrency}
                  <ChevronDown className="h-3.5 w-3.5" />
                </button>
                {btcEquivalent ? <span>{privacyMode ? t("assets.approxHiddenBtc") : t("assets.approxBtc").replace("{{value}}", formatAmount(btcEquivalent, 5))}</span> : null}
              </div>
            </div>
            {refreshing ? <div className="text-xs text-[var(--exa-text-muted)]">{t("assets.refreshing")}</div> : null}
          </div>

          <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <SummaryMetric label={t("assets.available")} value={formatDisplayValue(totals.available, portfolioCurrency, privacyMode)} loading={loading} />
            <SummaryMetric label={t("assets.inUse")} value={formatDisplayValue(totals.locked, portfolioCurrency, privacyMode)} loading={loading} />
            <SummaryMetric label={t("assets.assets")} value={privacyMode ? "••••" : String(mergedAssets.filter((item) => item.total > 0).length)} loading={loading} />
            <SummaryMetric label={t("assets.pnl")} value="--" hint={t("assets.pnlHint")} loading={false} />
          </div>
        </section>

        <section className="exa-surface grid grid-cols-4 gap-2 rounded-2xl px-3 py-3 sm:gap-3">
          <QuickAction icon={ArrowDownToLine} label={t("assets.deposit")} onClick={onOpenAddFunds} />
          <QuickAction icon={ArrowUpFromLine} label={t("assets.withdraw")} onClick={onOpenWithdraw} />
          <QuickAction icon={Repeat2} label={t("assets.transfer")} onClick={() => setShowTransferModal(true)} />
          <QuickAction icon={Repeat2} label={t("assets.convert")} onClick={onOpenSwap} />
        </section>

        <section className="exa-surface overflow-hidden rounded-2xl">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-white/8 px-4 py-3">
            <div className="inline-flex rounded-full border border-white/10 bg-white/[0.03] p-1">
              <button type="button" onClick={() => setActiveTab("assets")} className={`rounded-full px-4 py-1.5 text-sm ${activeTab === "assets" ? "bg-[var(--exa-gold)] text-slate-950" : "text-[var(--exa-text-muted)]"}`}>
                {t("assets.asset")}
              </button>
              <button type="button" onClick={() => setActiveTab("accounts")} className={`rounded-full px-4 py-1.5 text-sm ${activeTab === "accounts" ? "bg-[var(--exa-gold)] text-slate-950" : "text-[var(--exa-text-muted)]"}`}>
                {t("assets.account")}
              </button>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <div className="flex items-center gap-2 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-sm text-slate-300">
                <Search className="h-4 w-4 text-slate-500" />
                <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder={activeTab === "assets" ? t("assets.searchAssets") : t("assets.searchAccounts")} className="w-36 bg-transparent outline-none placeholder:text-slate-500 sm:w-44" />
              </div>
              <button type="button" onClick={() => setShowFilters((value) => !value)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/[0.03] text-slate-300">
                <Settings2 className="h-4 w-4" />
              </button>
            </div>
          </div>

          {showFilters ? (
            <div className="flex flex-wrap items-center gap-2 border-b border-white/8 px-4 py-3 text-sm">
              <div className="inline-flex rounded-full border border-white/10 bg-white/[0.03] p-1">
                {ASSET_FILTERS.map((filter) => (
                  <button key={filter} type="button" onClick={() => setAssetFilter(filter)} className={`rounded-full px-3 py-1.5 ${assetFilter === filter ? "bg-[var(--exa-gold)] text-slate-950" : "text-[var(--exa-text-muted)]"}`}>
                    {filter === "all" ? t("assets.all") : filter === "crypto" ? t("assets.crypto") : t("assets.fiat")}
                  </button>
                ))}
              </div>
              <label className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-3 py-2 text-slate-300">
                <input type="checkbox" checked={hideZeroBalances} onChange={(event) => setHideZeroBalances(event.target.checked)} />
                {t("assets.hideZeroBalances")}
              </label>
              <select value={sortBy} onChange={(event) => setSortBy(event.target.value)} className="rounded-full border border-white/10 bg-white/[0.03] px-3 py-2 text-slate-200 outline-none">
                {SORT_OPTIONS.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
              </select>
            </div>
          ) : null}

          {activeTab === "assets" ? (
            <div>
              <div className="hidden grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)] gap-4 border-b border-white/8 px-4 py-3 text-xs uppercase tracking-[0.18em] text-slate-500 lg:grid">
                <span>{t("assets.asset")}</span>
                <span className="text-right">{t("assets.balance")}</span>
                <span className="text-right">{t("assets.value")}</span>
              </div>
              {loading ? (
                <div className="space-y-3 p-4">{Array.from({ length: 5 }).map((_, index) => <div key={index} className="h-16 animate-pulse rounded-xl bg-white/[0.04]" />)}</div>
              ) : filteredAssets.length ? (
                <div className="divide-y divide-white/6">
                  {filteredAssets.map((asset) => (
                    <button key={asset.code} type="button" onClick={() => setSelectedAsset(asset.code)} className="grid w-full gap-3 px-4 py-4 text-left transition hover:bg-white/[0.03] lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)] lg:items-center">
                      <div className="flex min-w-0 items-center gap-3">
                        <AssetAvatar code={asset.code} tone={asset.tone} />
                        <div className="min-w-0">
                          <p className="truncate text-sm font-semibold text-white">{asset.code}</p>
                          <p className="truncate text-xs text-slate-500">{asset.name}</p>
                        </div>
                      </div>
                      <div className="text-left lg:text-right">
                        <p className="text-sm font-medium text-white">{privacyMode ? `•••• ${asset.code}` : `${formatAmount(asset.total, asset.decimals || 8)} ${asset.code}`}</p>
                        <p className="mt-1 text-xs text-slate-500">{t("assets.available")} {privacyMode ? "••••" : formatAmount(asset.available, asset.decimals || 8)}</p>
                      </div>
                      <div className="text-left lg:text-right">
                        <p className="text-sm font-medium text-white">{formatDisplayValue(asset.totalValue, portfolioCurrency, privacyMode)}</p>
                        <p className="mt-1 text-xs text-slate-500">{t("assets.inUseSentence")} {privacyMode ? "••••" : formatAmount(asset.locked, asset.decimals || 8)}</p>
                      </div>
                    </button>
                  ))}
                </div>
              ) : (
                <EmptyState title={t("assets.noAssetsFound")} body={t("assets.noAssetsBody")} />
              )}
            </div>
          ) : (
            <div>
              <div className="hidden grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] gap-4 border-b border-white/8 px-4 py-3 text-xs uppercase tracking-[0.18em] text-slate-500 lg:grid">
                <span>{t("assets.account")}</span>
                <span className="text-right">{t("assets.transferable")}</span>
                <span className="text-right">{t("assets.inUse")}</span>
                <span className="text-right">{t("assets.equity")}</span>
              </div>
              {loading ? (
                <div className="space-y-3 p-4">{Array.from({ length: 3 }).map((_, index) => <div key={index} className="h-16 animate-pulse rounded-xl bg-white/[0.04]" />)}</div>
              ) : accounts.length ? (
                <div className="divide-y divide-white/6">
                  {accounts.map((account) => (
                    <div key={account.key} className="grid gap-3 px-4 py-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] lg:items-center">
                      <div>
                        <p className="text-sm font-semibold text-[var(--exa-text-primary)]">{account.label}</p>
                        <p className="mt-1 text-xs text-slate-500">{t("assets.realBackendAccountBalance")}</p>
                      </div>
                      <p className="text-left text-sm text-white lg:text-right">{privacyMode ? "••••" : formatAmount(account.available, 8)}</p>
                      <p className="text-left text-sm text-white lg:text-right">{privacyMode ? "••••" : formatAmount(account.locked, 8)}</p>
                      <p className="text-left text-sm text-white lg:text-right">{formatDisplayValue(account.totalValue, portfolioCurrency, privacyMode)}</p>
                    </div>
                  ))}
                </div>
              ) : (
                <EmptyState title={t("assets.accountBreakdownUnavailable")} body={t("assets.accountBreakdownBody")} />
              )}
            </div>
          )}
        </section>
      </div>

      {showCurrencySelector ? (
        <Sheet title={t("assets.displayCurrency")} onClose={() => setShowCurrencySelector(false)}>
          <div className="space-y-2">
            {DISPLAY_CURRENCIES.map((currency) => (
              <button key={currency} type="button" onClick={() => { setDisplayCurrency(currency); setShowCurrencySelector(false); }} className={`flex w-full items-center justify-between rounded-xl border px-3 py-3 text-left ${displayCurrency === currency ? "border-amber-300/50 bg-amber-300/10 text-amber-100" : "border-white/10 bg-white/[0.03] text-slate-200"}`}>
                <span>{currency}</span>
                {displayCurrency === currency ? <span className="text-xs font-semibold">{t("assets.selected")}</span> : null}
              </button>
            ))}
          </div>
        </Sheet>
      ) : null}

      {selectedAssetRow ? (
        <Sheet title={selectedAssetRow.code} onClose={() => setSelectedAsset(null)}>
          <div className="space-y-4">
            <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
              <div className="flex items-center gap-3">
                <AssetAvatar code={selectedAssetRow.code} tone={selectedAssetRow.tone} />
                <div>
                  <p className="text-lg font-semibold text-white">{selectedAssetRow.code}</p>
                  <p className="text-sm text-slate-500">{selectedAssetRow.name}</p>
                </div>
              </div>
              <dl className="mt-4 space-y-3 text-sm">
                <DetailRow label={t("assets.total")} value={privacyMode ? `•••• ${selectedAssetRow.code}` : `${formatAmount(selectedAssetRow.total, selectedAssetRow.decimals || 8)} ${selectedAssetRow.code}`} />
                <DetailRow label={t("assets.available")} value={privacyMode ? `•••• ${selectedAssetRow.code}` : `${formatAmount(selectedAssetRow.available, selectedAssetRow.decimals || 8)} ${selectedAssetRow.code}`} />
                <DetailRow label={t("assets.inUse")} value={privacyMode ? `•••• ${selectedAssetRow.code}` : `${formatAmount(selectedAssetRow.locked, selectedAssetRow.decimals || 8)} ${selectedAssetRow.code}`} />
                <DetailRow label={t("assets.value")} value={formatDisplayValue(selectedAssetRow.totalValue, portfolioCurrency, privacyMode)} />
              </dl>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <ActionPill label={t("assets.deposit")} onClick={onOpenAddFunds} />
              <ActionPill label={t("assets.withdraw")} onClick={onOpenWithdraw} />
              <ActionPill label={t("assets.transfer")} onClick={() => setShowTransferModal(true)} />
              <ActionPill label={t("assets.convert")} onClick={onOpenSwap} />
              {onOpenTrade ? <ActionPill label={t("assets.trade")} onClick={onOpenTrade} /> : null}
              {onOpenSend ? <ActionPill label={t("assets.send")} onClick={onOpenSend} /> : null}
            </div>
          </div>
        </Sheet>
      ) : null}

      <TransferModal
        isOpen={showTransferModal}
        onClose={() => setShowTransferModal(false)}
        onTransfer={handleTransfer}
        assets={Array.from(new Set([
          ...mergedAssets.map((asset) => asset.code),
          ...accounts.flatMap((account) => (account.assets || []).map((item) => item.asset)),
        ])).filter(Boolean).sort()}
        balances={accounts}
      />
    </main>
  );
}

function SummaryMetric({ label, value, hint, loading }) {
  return (
    <div className="rounded-xl border border-[var(--exa-border-subtle)] bg-white/[0.025] px-3 py-3">
      <p className="text-xs uppercase tracking-[0.16em] text-slate-500">{label}</p>
      {loading ? <div className="mt-2 h-6 w-24 animate-pulse rounded bg-white/[0.05]" /> : <p className="mt-2 text-base font-semibold text-white">{value}</p>}
      {hint ? <p className="mt-1 text-[11px] text-slate-500">{hint}</p> : null}
    </div>
  );
}

function QuickAction({ icon: Icon, label, onClick }) {
  return (
    <button type="button" onClick={onClick} className="flex flex-col items-center gap-2 rounded-xl px-2 py-2 text-xs font-semibold text-[var(--exa-text-secondary)] transition hover:bg-[var(--exa-gold-surface)] hover:text-[var(--exa-text-primary)] exa-focusable">
      <span className="inline-flex h-11 w-11 items-center justify-center rounded-full border border-[var(--exa-border-subtle)] bg-white/[0.035] text-[var(--exa-gold-light)]">
        <Icon className="h-4 w-4" />
      </span>
      <span>{label}</span>
    </button>
  );
}

function AssetAvatar({ code, tone }) {
  return <span className={`inline-flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br ${tone} text-xs font-bold text-white`}>{code.slice(0, 2)}</span>;
}

function EmptyState({ title, body }) {
  return (
    <div className="px-4 py-10 text-center">
      <CircleAlert className="mx-auto h-8 w-8 text-slate-500" />
      <p className="mt-3 text-sm font-semibold text-white">{title}</p>
      <p className="mt-1 text-sm text-slate-500">{body}</p>
    </div>
  );
}

function Sheet({ title, children, onClose }) {
  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-0 backdrop-blur-sm lg:p-4">
      <div className="max-h-[92vh] w-full overflow-hidden rounded-t-[28px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] shadow-[var(--exa-shadow-md)] lg:max-w-xl lg:rounded-[28px]">
        <div className="flex items-center justify-between border-b border-white/8 px-4 py-4">
          <h2 className="text-base font-semibold text-[var(--exa-text-primary)]">{title}</h2>
          <button type="button" onClick={onClose} className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/[0.04] text-slate-300">
            <X className="h-4 w-4" />
          </button>
        </div>
        <div className="overflow-y-auto px-4 py-4">{children}</div>
      </div>
    </div>
  );
}

function DetailRow({ label, value }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <span className="text-[var(--exa-text-muted)]">{label}</span>
      <span className="text-right font-medium text-white">{value}</span>
    </div>
  );
}

function ActionPill({ label, onClick }) {
  return <button type="button" onClick={onClick} className="rounded-xl border border-[var(--exa-border)] bg-white/[0.035] px-3 py-3 text-sm font-semibold text-[var(--exa-text-secondary)] transition hover:border-[var(--exa-border-active)] hover:bg-[var(--exa-gold-surface)] hover:text-[var(--exa-text-primary)] exa-focusable">{label}</button>;
}

export default Assets;
