import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import Decimal from "decimal.js";
import {
  AlertTriangle,
  ArrowLeft,
  BarChart3,
  CheckCircle2,
  ChevronRight,
  Clock3,
  Eye,
  EyeOff,
  Info,
  RefreshCw,
  Search,
  ShieldCheck,
  Sparkles,
  X,
} from "lucide-react";
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
import { useAuth } from "../../context/AuthContext";
import { stakingApi } from "./stakingApi";
import type { ExaTokenCampaign, PortfolioRow, StakingApyHistory, StakingAsset, StakingPosition, StakingProduct, StakingReward } from "./types";
import { boolish, canToggleAutoCompound, canUnstakePosition, claimableExaRewards, claimableNativeRewards, compareDecimal, decimal, estimateReward, formatAssetAmount, formatDateTime, formatDuration, formatFiat, formatPercent, isProductOperational, mapApiError, nextPositionMilestone, normalizeStatus, percentageAmount, portfolioTotals, positionStatusLabel } from "./stakingUtils";
import { useStakingData } from "./useStakingData";

type StakingDashboardProps = {
  onBack?: () => void;
};

type WalletBalance = {
  currency?: string;
  asset?: string;
  available?: string | number;
  balance?: string | number;
};

type ViewState =
  | { name: "dashboard" }
  | { name: "products"; slug: string }
  | { name: "positions"; publicId?: string };

const statusTone: Record<string, string> = {
  active: "border-emerald-300/30 bg-emerald-400/10 text-emerald-100",
  operational: "border-emerald-300/30 bg-emerald-400/10 text-emerald-100",
  online: "border-emerald-300/30 bg-emerald-400/10 text-emerald-100",
  production: "border-emerald-300/30 bg-emerald-400/10 text-emerald-100",
  testnet: "border-sky-300/30 bg-sky-400/10 text-sky-100",
  pending: "border-amber-300/30 bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]",
  batching: "border-amber-300/30 bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]",
  awaiting_signature: "border-amber-300/30 bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]",
  delegation_submitted: "border-sky-300/30 bg-sky-400/10 text-sky-100",
  awaiting_activation: "border-sky-300/30 bg-sky-400/10 text-sky-100",
  unbonding: "border-cyan-300/30 bg-cyan-400/10 text-cyan-100",
  unstaking: "border-cyan-300/30 bg-cyan-400/10 text-cyan-100",
  withdrawable: "border-emerald-300/30 bg-emerald-400/10 text-emerald-100",
  completed: "border-violet-200/25 bg-violet-400/10 text-[var(--exa-text-secondary)]",
  paused: "border-orange-300/30 bg-orange-400/10 text-orange-100",
  failed: "border-red-300/30 bg-red-400/10 text-red-100",
  slashed: "border-red-300/30 bg-red-400/10 text-red-100",
  configuration_required: "border-orange-300/30 bg-orange-400/10 text-orange-100",
};

function safeArray<T>(value: unknown): T[] {
  if (Array.isArray(value)) return value as T[];
  const nested = (value as { data?: unknown })?.data;
  return Array.isArray(nested) ? (nested as T[]) : [];
}

function riskLabel(product: StakingProduct, asset?: StakingAsset): string {
  const metadataRisk = (product.metadata as { risk_level?: string } | null)?.risk_level
    || (asset?.metadata as { risk_level?: string } | null)?.risk_level;
  if (metadataRisk) return metadataRisk;
  if ((product.duration_days ?? 0) >= 180) return "Elevated";
  if ((product.unbonding_period_seconds ?? asset?.unbonding_period_seconds ?? 0) >= 14 * 86400) return "Moderate";
  return "Managed";
}

function pathToView(): ViewState {
  const segments = window.location.pathname.split("/").filter(Boolean);
  const stakingIndex = segments.indexOf("staking");
  const rest = stakingIndex >= 0 ? segments.slice(stakingIndex + 1) : [];
  if (rest[0] === "products" && rest[1]) return { name: "products", slug: decodeURIComponent(rest[1]) };
  if (rest[0] === "positions") return { name: "positions", publicId: rest[1] ? decodeURIComponent(rest[1]) : undefined };
  return { name: "dashboard" };
}

function StakingDashboard({ onBack }: StakingDashboardProps) {
  const auth = useAuth() as { request: <T = unknown>(path: string, options?: RequestInit) => Promise<T>; user?: { email?: string; unique_user_id?: string } | null };
  const data = useStakingData(auth.request);
  const [view, setView] = useState<ViewState>(() => pathToView());
  const [selectedProduct, setSelectedProduct] = useState<StakingProduct | null>(null);
  const [stakeSeedAmount, setStakeSeedAmount] = useState("");
  const [selectedUnstake, setSelectedUnstake] = useState<StakingPosition | null>(null);
  const [selectedClaim, setSelectedClaim] = useState<{ position: StakingPosition; type: "native" | "exatoken" } | null>(null);
  const [autoCompound, setAutoCompound] = useState<StakingPosition | null>(null);
  const [walletBalances, setWalletBalances] = useState<WalletBalance[]>([]);
  const [notice, setNotice] = useState("");

  useEffect(() => {
    const handlePop = () => setView(pathToView());
    window.addEventListener("popstate", handlePop);
    return () => window.removeEventListener("popstate", handlePop);
  }, []);

  useEffect(() => {
    let mounted = true;
    void auth.request<unknown>("/api/wallet/balances", { method: "GET" })
      .then((payload) => {
        if (mounted) setWalletBalances(safeArray<WalletBalance>((payload as { data?: unknown })?.data ?? payload));
      })
      .catch(() => {
        if (mounted) setWalletBalances([]);
      });
    return () => {
      mounted = false;
    };
  }, [auth]);

  const openView = useCallback((next: ViewState) => {
    setView(next);
    const path = next.name === "products" ? `/staking/products/${next.slug}` : next.name === "positions" && next.publicId ? `/staking/positions/${next.publicId}` : next.name === "positions" ? "/staking/positions" : "/staking";
    window.history.pushState({ stakingView: next }, "", path);
    window.scrollTo({ top: 0, behavior: "smooth" });
  }, []);

  const assetById = useMemo(() => new Map(data.assets.map((asset) => [asset.id, asset])), [data.assets]);
  const balanceBySymbol = useMemo(() => {
    const map = new Map<string, string>();
    for (const balance of walletBalances) {
      const symbol = String(balance.currency ?? balance.asset ?? "").toUpperCase();
      if (symbol) map.set(symbol, String(balance.available ?? balance.balance ?? "0"));
    }
    return map;
  }, [walletBalances]);
  const healthProblem = data.networkStatuses.find((status) => !["online", "operational", "healthy"].includes(String(status.status).toLowerCase()));
  const openStakeModal = useCallback((product: StakingProduct, amount = "") => {
    setStakeSeedAmount(amount);
    setSelectedProduct(product);
  }, []);

  if (view.name === "products") {
    const product = data.products.find((item) => item.slug === view.slug);
    return (
      <StakingShell onBack={() => openView({ name: "dashboard" })} backLabel="Staking" notice={notice}>
        <ProductDetails product={product} asset={product ? assetById.get(product.staking_asset_id) : undefined} apyHistory={data.apyHistory} availableBalance={product ? balanceBySymbol.get(product.symbol) ?? "0" : "0"} onStake={(item) => openStakeModal(item)} loading={data.loading} />
        {selectedProduct ? <StakeModal product={selectedProduct} productOptions={data.products.filter((item) => item.symbol === selectedProduct.symbol)} asset={assetById.get(selectedProduct.staking_asset_id)} availableBalance={balanceBySymbol.get(selectedProduct.symbol) ?? "0"} initialAmount={stakeSeedAmount} request={auth.request} onClose={() => { setSelectedProduct(null); setStakeSeedAmount(""); }} onDone={async (message) => { setNotice(message); setSelectedProduct(null); setStakeSeedAmount(""); await data.refresh(); }} /> : null}
      </StakingShell>
    );
  }

  if (view.name === "positions") {
    const position = view.publicId ? data.positions.find((item) => item.public_id === view.publicId) : null;
    return (
      <StakingShell onBack={() => (view.publicId ? openView({ name: "positions" }) : openView({ name: "dashboard" }))} backLabel={view.publicId ? "Positions" : "Staking"} notice={notice}>
        {view.publicId ? (
          <PositionDetails position={position} rewards={data.rewards} transactions={data.transactions} onUnstake={(item) => setSelectedUnstake(item)} onClaim={(item, type) => setSelectedClaim({ position: item, type })} onAutoCompound={setAutoCompound} loading={data.loading} />
        ) : (
          <PositionsPage positions={data.positions} rewards={data.rewards} onOpen={(publicId) => openView({ name: "positions", publicId })} onUnstake={(item) => setSelectedUnstake(item)} onClaim={(item, type) => setSelectedClaim({ position: item, type })} />
        )}
        {selectedUnstake ? <UnstakeModal position={selectedUnstake} request={auth.request} onClose={() => setSelectedUnstake(null)} onDone={async (message) => { setNotice(message); setSelectedUnstake(null); await data.refresh(); }} /> : null}
        {selectedClaim ? <ClaimRewardModal {...selectedClaim} request={auth.request} onClose={() => setSelectedClaim(null)} onDone={async (message) => { setNotice(message); setSelectedClaim(null); await data.refresh(); }} /> : null}
        {autoCompound ? <AutoCompoundModal position={autoCompound} request={auth.request} onClose={() => setAutoCompound(null)} onDone={async (message) => { setNotice(message); setAutoCompound(null); await data.refresh(); }} /> : null}
      </StakingShell>
    );
  }

  return (
    <StakingShell onBack={onBack} backLabel="Home" notice={notice}>
      <StakingHero portfolio={data.portfolio} positions={data.positions} apyHistory={data.apyHistory} onExplore={() => document.getElementById("staking-products")?.scrollIntoView({ behavior: "smooth" })} onPositions={() => openView({ name: "positions" })} onLearn={() => document.getElementById("staking-learn")?.scrollIntoView({ behavior: "smooth" })} networkStatus={healthProblem?.status ?? "Operational"} />
      {data.error ? <ErrorState message={data.error} onRetry={data.refresh} /> : null}
      <NetworkBanner statuses={data.networkStatuses} />
      <StakingQuickNav onEarn={() => document.getElementById("staking-products")?.scrollIntoView({ behavior: "smooth" })} onPositions={() => document.getElementById("my-positions")?.scrollIntoView({ behavior: "smooth" })} onRewards={() => document.getElementById("staking-rewards")?.scrollIntoView({ behavior: "smooth" })} onCampaigns={() => document.getElementById("staking-campaigns")?.scrollIntoView({ behavior: "smooth" })} />
      <AvailableStakingAssets loading={data.loading} error={data.error} products={data.products} assets={assetById} balances={balanceBySymbol} positions={data.positions} onInspect={(slug) => openView({ name: "products", slug })} onStake={openStakeModal} />
      <PositionPreview positions={data.positions} onOpen={() => openView({ name: "positions" })} onUnstake={setSelectedUnstake} />
      <RewardHistory rewards={data.rewards} />
      <ExaTokenBonusSection campaigns={data.campaigns} />
      <RiskAndFaq terms={data.terms} />
      {selectedProduct ? <StakeModal product={selectedProduct} productOptions={data.products.filter((item) => item.symbol === selectedProduct.symbol)} asset={assetById.get(selectedProduct.staking_asset_id)} availableBalance={balanceBySymbol.get(selectedProduct.symbol) ?? "0"} initialAmount={stakeSeedAmount} request={auth.request} onClose={() => { setSelectedProduct(null); setStakeSeedAmount(""); }} onDone={async (message) => { setNotice(message); setSelectedProduct(null); setStakeSeedAmount(""); await data.refresh(); }} /> : null}
      {selectedUnstake ? <UnstakeModal position={selectedUnstake} request={auth.request} onClose={() => setSelectedUnstake(null)} onDone={async (message) => { setNotice(message); setSelectedUnstake(null); await data.refresh(); }} /> : null}
    </StakingShell>
  );
}

function StakingShell({ children, onBack, backLabel, notice }: { children: ReactNode; onBack?: () => void; backLabel: string; notice?: string }) {
  return (
    <main className="min-h-screen overflow-x-hidden bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)] exa-bg">
      <div className="relative z-[2] mx-auto w-full max-w-[1480px] px-3 pb-24 pt-[max(12px,env(safe-area-inset-top))] sm:px-5 lg:px-6">
        <div className="rounded-[30px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-3 shadow-[var(--exa-shadow-panel)] backdrop-blur">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
          {onBack ? (
            <button type="button" onClick={onBack} className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 text-sm text-[var(--exa-text-secondary)] transition hover:bg-[var(--exa-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]">
              <ArrowLeft className="h-4 w-4" aria-hidden="true" />
              {backLabel}
            </button>
          ) : <span />}
          <div className="inline-flex items-center gap-2 rounded-lg border border-emerald-300/20 bg-emerald-400/10 px-3 py-2 text-xs text-emerald-100">
            <ShieldCheck className="h-4 w-4" aria-hidden="true" />
            Verified rewards only
          </div>
        </div>
        {notice ? <div role="status" className="mb-3 rounded-2xl border border-emerald-300/25 bg-emerald-400/10 p-3 text-sm text-emerald-100">{notice}</div> : null}
        {children}
        </div>
      </div>
    </main>
  );
}

function StakingHero({ portfolio, positions, apyHistory, onExplore, onPositions, onLearn, networkStatus }: { portfolio: PortfolioRow[]; positions: StakingPosition[]; apyHistory: StakingApyHistory[]; onExplore: () => void; onPositions: () => void; onLearn: () => void; networkStatus: string }) {
  const totals = portfolioTotals(portfolio, positions);
  const averageApy = apyHistory[0]?.amount ?? null;
  return (
    <section className="relative overflow-hidden rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)] lg:p-5">
      <div className="grid gap-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(340px,0.95fr)] lg:items-start">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--exa-gold-light)]">ExaEarn Staking</p>
          <h1 className="mt-2 font-['Sora'] text-[30px] font-semibold leading-[1.1] tracking-normal text-white sm:text-[34px]">Earn rewards with your crypto</h1>
          <p className="mt-3 max-w-3xl text-sm leading-6 text-[var(--exa-text-secondary)]">
            Stake eligible assets from your ExaEarn balance and receive verified network rewards.
          </p>
          <div className="mt-4 flex flex-col gap-3 sm:flex-row">
            <button type="button" onClick={onExplore} className="inline-flex min-h-11 items-center justify-center rounded-lg exa-button-primary px-5 text-sm font-semibold text-[var(--exa-gold-contrast)] transition hover:bg-[var(--exa-gold-light)] focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]">
              Stake now
            </button>
            <button type="button" onClick={onPositions} className="inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-5 text-sm font-semibold text-[var(--exa-text-primary)] transition hover:bg-[var(--exa-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]">
              My positions
            </button>
            <button type="button" onClick={onLearn} className="inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--exa-border)] bg-transparent px-4 text-sm font-semibold text-[var(--exa-text-secondary)] transition hover:bg-[var(--exa-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]">
              Understand staking
            </button>
          </div>
          <div className="mt-4 rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] p-3 text-xs leading-5 text-[var(--exa-gold-light)]">
            APY is variable and not guaranteed. Locked products, validator performance, network fees, unbonding, and slashing can affect outcomes.
          </div>
        </div>
        <div className="rounded-[18px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="text-sm font-semibold text-white">Portfolio snapshot</p>
              <p className="mt-1 text-xs text-[var(--exa-text-muted)]">Compact balances for the staking journey</p>
            </div>
            <StatusBadge status={networkStatus} />
          </div>
          <div className="mt-4 grid gap-3 sm:grid-cols-2">
            <Metric label="Portfolio value" value={formatFiat(totals.activePrincipal)} />
            <Metric label="Active principal" value={formatAssetAmount(totals.activePrincipal)} />
            <Metric label="Rewards earned" value={formatAssetAmount(totals.nativeRewards)} />
            <Metric label="Estimated portfolio APY" value={averageApy ? formatPercent(averageApy) : "Not published"} />
          </div>
        </div>
      </div>
    </section>
  );
}

function StakingQuickNav({ onEarn, onPositions, onRewards, onCampaigns }: { onEarn: () => void; onPositions: () => void; onRewards: () => void; onCampaigns: () => void }) {
  const items = [
    ["Earn", onEarn],
    ["My Positions", onPositions],
    ["Rewards", onRewards],
    ["Campaigns", onCampaigns],
  ] as const;

  return (
    <section className="mt-2 flex flex-wrap gap-2">
      {items.map(([label, handler]) => (
        <button key={label} type="button" onClick={handler} className="min-h-10 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-4 text-sm font-semibold text-[var(--exa-text-primary)] transition hover:bg-[var(--exa-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]">
          {label}
        </button>
      ))}
    </section>
  );
}

function PortfolioSummary({ portfolio, positions, apyHistory, loading, refreshing, lastUpdated, onRefresh }: { portfolio: PortfolioRow[]; positions: StakingPosition[]; apyHistory: StakingApyHistory[]; loading: boolean; refreshing: boolean; lastUpdated: Date | null; onRefresh: () => Promise<void> }) {
  const [hidden, setHidden] = useState(false);
  const [currency, setCurrency] = useState("USD");
  const totals = useMemo(() => portfolioTotals(portfolio, positions), [portfolio, positions]);
  const latestApy = apyHistory[0]?.amount ?? null;
  const chartData = apyHistory.slice(0, 20).reverse().map((row, index) => ({ name: row.recorded_at ? new Date(row.recorded_at).toLocaleDateString() : `#${index + 1}`, apy: decimal(row.amount).toNumber() }));
  const metrics = [
    ["Active principal", formatAssetAmount(totals.activePrincipal)],
    ["Pending stake", formatAssetAmount(totals.pendingStake)],
    ["Pending unstake", formatAssetAmount(totals.pendingUnstake)],
    ["Native rewards earned", formatAssetAmount(totals.nativeRewards)],
    ["Claimable native rewards", formatAssetAmount(totals.claimableNative)],
    ["ExaToken bonuses", formatAssetAmount(totals.exaBonus, "EXA")],
    ["Claimable ExaToken", formatAssetAmount(totals.claimableExa, "EXA")],
    ["Active positions", String(totals.activeCount)],
  ];

  return (
    <section className="mt-2 rounded-[20px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-3 shadow-[var(--exa-shadow-panel)]">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="font-['Sora'] text-xl font-semibold text-white">Portfolio Summary</h2>
          <p className="mt-1 text-sm text-[var(--exa-text-muted)]">Principal, rewards, and claimable balances stay separated.</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <select value={currency} onChange={(event) => setCurrency(event.target.value)} className="min-h-10 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm text-[var(--exa-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]" aria-label="Portfolio currency">
            {["USD", "USDT", "EUR", "NGN"].map((item) => <option key={item}>{item}</option>)}
          </select>
          <button type="button" onClick={() => setHidden((value) => !value)} className="grid h-10 w-10 place-items-center rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]" aria-label={hidden ? "Show portfolio values" : "Hide portfolio values"}>
            {hidden ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
          </button>
          <button type="button" onClick={() => void onRefresh()} className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]">
            <RefreshCw className={`h-4 w-4 ${refreshing ? "animate-spin" : ""}`} />
            Refresh
          </button>
        </div>
      </div>
      {loading ? <SkeletonGrid /> : (
        <>
          <div className="mt-3 grid gap-2 sm:grid-cols-2">
            <Metric label="Total staking portfolio value" value={formatFiat(totals.activePrincipal, currency, hidden)} />
            <Metric label="Weighted estimated portfolio APY" value={latestApy ? formatPercent(latestApy) : "Not published"} />
            {metrics.map(([label, value]) => <Metric key={label} label={label} value={hidden ? "â€¢â€¢â€¢â€¢â€¢â€¢" : value} />)}
          </div>
          <div className="mt-3 min-h-[180px] rounded-[16px] border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-3">
            {chartData.length ? (
              <ResponsiveContainer width="100%" height={180}>
                <AreaChart data={chartData}>
                  <CartesianGrid stroke="rgba(255,255,255,0.08)" vertical={false} />
                  <XAxis dataKey="name" tick={{ fill: "rgba(245,240,255,0.55)", fontSize: 11 }} />
                  <YAxis tick={{ fill: "rgba(245,240,255,0.55)", fontSize: 11 }} />
                  <Tooltip contentStyle={{ background: "#111827", border: "1px solid rgba(255,255,255,0.12)", borderRadius: 8 }} />
                  <Area type="monotone" dataKey="apy" stroke="#f9e2ad" fill="rgba(249,226,173,0.16)" />
                </AreaChart>
              </ResponsiveContainer>
            ) : <EmptyState title="No APY history yet" body="Historical APY appears after backend reconciliation records are available." />}
          </div>
          <p className="mt-3 text-xs text-[var(--exa-text-muted)]">Last updated {lastUpdated ? lastUpdated.toLocaleTimeString() : "not yet"}</p>
        </>
      )}
    </section>
  );
}

function FeaturedProducts({ products, assets, balances, onStake, onOpen }: { products: StakingProduct[]; assets: Map<number, StakingAsset>; balances: Map<string, string>; onStake: (product: StakingProduct) => void; onOpen: (slug: string) => void }) {
  const featured = products.slice(0, 6);
  return (
    <section id="staking-products" className="mt-5">
      <div className="mb-3 flex items-center justify-between gap-3">
        <h2 className="font-['Sora'] text-xl font-semibold">Featured Staking Products</h2>
        <span className="text-xs text-[var(--exa-text-muted)]">Native rewards and ExaToken bonuses are separate.</span>
      </div>
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        {featured.length ? featured.map((product) => <ProductCard key={product.id} product={product} asset={assets.get(product.staking_asset_id)} availableBalance={balances.get(product.symbol) ?? "0"} onStake={onStake} onOpen={onOpen} featured />) : <EmptyState title="No featured products" body="Products will appear here after the staking backend enables them." />}
      </div>
    </section>
  );
}

function AvailableStakingAssets({ loading, error, products, assets, balances, positions, onInspect, onStake }: { loading: boolean; error: string; products: StakingProduct[]; assets: Map<number, StakingAsset>; balances: Map<string, string>; positions: StakingPosition[]; onInspect: (slug: string) => void; onStake: (product: StakingProduct, amount?: string) => void }) {
  const [query, setQuery] = useState("");
  const [filter, setFilter] = useState("all");
  const [network, setNetwork] = useState("all");
  const [sort, setSort] = useState("apy");
  const [focusedProductId, setFocusedProductId] = useState<number | null>(products[0]?.id ?? null);
  const [calculatorAmount, setCalculatorAmount] = useState("");

  const networks = useMemo(() => Array.from(new Set(products.map((product) => product.network))).sort(), [products]);

  const rows = useMemo(() => {
    return products
      .filter((product) => {
        const available = balances.get(product.symbol) ?? "0";
        const haystack = `${product.symbol} ${product.network} ${product.name}`.toLowerCase();
        if (query && !haystack.includes(query.toLowerCase())) return false;
        if (network !== "all" && product.network !== network) return false;
        if (filter === "flexible" && product.duration_days) return false;
        if (filter === "fixed" && !product.duration_days) return false;
        if (filter === "available" && compareDecimal(available, "0") <= 0) return false;
        return true;
      })
      .sort((a, b) => {
        if (sort === "duration") return Number(a.duration_days ?? 0) - Number(b.duration_days ?? 0);
        if (sort === "balance") return decimal(balances.get(b.symbol)).cmp(decimal(balances.get(a.symbol)));
        if (sort === "shortest-unbonding") return Number(a.unbonding_period_seconds ?? 0) - Number(b.unbonding_period_seconds ?? 0);
        return decimal(b.displayed_apy).cmp(decimal(a.displayed_apy));
      });
  }, [balances, filter, network, products, query, sort]);

  const focusedProduct = useMemo(() => rows.find((product) => product.id === focusedProductId) ?? rows[0] ?? null, [focusedProductId, rows]);
  const focusedAsset = focusedProduct ? assets.get(focusedProduct.staking_asset_id) : undefined;
  const focusedBalance = focusedProduct ? balances.get(focusedProduct.symbol) ?? "0" : "0";
  const focusedOperational = focusedProduct ? isProductOperational(focusedProduct, focusedAsset) : { ok: false, reason: "Unavailable" };
  const focusedFamily = useMemo(() => {
    if (!focusedProduct) return [];
    return products
      .filter((product) => product.symbol === focusedProduct.symbol && product.network === focusedProduct.network)
      .sort((left, right) => Number(left.duration_days ?? 0) - Number(right.duration_days ?? 0));
  }, [focusedProduct, products]);
  const focusedAmount = calculatorAmount || percentageAmount(focusedBalance, 25, focusedAsset?.amount_precision ?? 8);
  const focusedDaily = focusedProduct ? estimateReward(focusedAmount, focusedProduct.displayed_apy, 1) : "0";
  const focusedMonthly = focusedProduct ? estimateReward(focusedAmount, focusedProduct.displayed_apy, 30) : "0";
  const focusedTotal = focusedProduct ? estimateReward(focusedAmount, focusedProduct.displayed_apy, focusedProduct.duration_days ?? 365) : "0";
  const userHasEligibleBalance = useMemo(() => Array.from(balances.values()).some((value) => compareDecimal(value, "0") > 0), [balances]);
  const focusedAmountError = focusedProduct ? validateStakeAmount(calculatorAmount, focusedProduct, focusedBalance) : "";
  const focusedMaturityDate = useMemo(() => {
    if (!focusedProduct?.duration_days) return "Flexible";
    const end = new Date();
    end.setDate(end.getDate() + focusedProduct.duration_days);
    return formatDateTime(end.toISOString());
  }, [focusedProduct]);
  const focusedEstimatedStart = focusedAsset?.expected_activation_seconds
    ? formatDateTime(new Date(Date.now() + focusedAsset.expected_activation_seconds * 1000).toISOString())
    : "After network confirmation";
  const focusedUserActive = focusedProduct
    ? positions
      .filter((position) => position.symbol === focusedProduct.symbol && position.network === focusedProduct.network)
      .reduce((sum, position) => sum.plus(decimal(position.active_principal_amount)), decimal(0))
      .toFixed()
    : "0";

  useEffect(() => {
    if (!rows.length) {
      setFocusedProductId(null);
      return;
    }
    if (!rows.some((product) => product.id === focusedProductId)) setFocusedProductId(rows[0].id);
  }, [focusedProductId, rows]);

  useEffect(() => {
    if (focusedProduct) {
      setCalculatorAmount((current) => current || percentageAmount(focusedBalance, 25, focusedAsset?.amount_precision ?? 8));
    }
  }, [focusedAsset?.amount_precision, focusedBalance, focusedProduct]);

  return (
    <section id="staking-products" className="mt-3 rounded-[24px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)] lg:p-5">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h2 className="font-['Sora'] text-2xl font-semibold text-white">Available staking assets</h2>
          <p className="mt-1 text-sm text-[var(--exa-text-muted)]">Choose an asset, compare the plan, enter an amount, and review estimated rewards before you confirm.</p>
        </div>
        <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-[minmax(260px,1fr)_180px_180px]">
          <label className="relative block">
            <Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-[var(--exa-text-disabled)]" />
            <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search asset or symbol" className="min-h-10 w-full rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]" />
          </label>
          <select value={filter} onChange={(event) => setFilter(event.target.value)} className="min-h-10 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]" aria-label="Filter staking products">
            <option value="all">All products</option>
            <option value="flexible">Flexible</option>
            <option value="fixed">Fixed</option>
            <option value="available">Available balance</option>
          </select>
          <select value={sort} onChange={(event) => setSort(event.target.value)} className="min-h-10 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]" aria-label="Sort staking products">
            <option value="apy">Highest APY</option>
            <option value="duration">Shortest lock</option>
            <option value="shortest-unbonding">Shortest unbonding</option>
            <option value="balance">Available balance</option>
          </select>
        </div>
      </div>

      {loading ? <SkeletonGrid /> : null}
      {!loading && error ? <div className="mt-4"><EmptyState title="Staking products are temporarily unavailable" body="We could not load the current staking catalog. Refresh again when the backend is reachable." /></div> : null}

      {!loading ? (
        <>
          <div className="mt-3 flex flex-wrap gap-2">
            <Badge label={`${rows.length} products`} />
            <Badge label={`${networks.length || 0} networks`} />
            <Badge label={`${positions.filter((position) => position.status === "active").length} active positions`} />
            <button type="button" onClick={() => setNetwork("all")} className={`min-h-9 rounded-lg border px-3 text-sm ${network === "all" ? "border-[var(--exa-border-active)] exa-button-primary text-[var(--exa-gold-contrast)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)]"}`}>
              All networks
            </button>
            {networks.map((item) => (
              <button key={item} type="button" onClick={() => setNetwork(item)} className={`min-h-9 rounded-lg border px-3 text-sm ${network === item ? "border-[var(--exa-border-active)] exa-button-primary text-[var(--exa-gold-contrast)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)]"}`}>
                {item}
              </button>
            ))}
          </div>

          <div className="mt-4 grid gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(380px,0.9fr)]">
            <div className="overflow-hidden rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)]">
              <div className="hidden xl:block overflow-x-auto">
                <table className="w-full min-w-[1280px] text-left text-sm">
                  <thead className="bg-[var(--exa-surface-elevated)] text-xs uppercase tracking-wide text-[var(--exa-text-disabled)]">
                    <tr>
                      <th className="px-4 py-3">Asset</th>
                      <th className="px-4 py-3">Estimated APY</th>
                      <th className="px-4 py-3">Type</th>
                      <th className="px-4 py-3">Available</th>
                      <th className="px-4 py-3">Minimum</th>
                      <th className="px-4 py-3">Payout</th>
                      <th className="px-4 py-3">Lock</th>
                      <th className="px-4 py-3">Unbonding</th>
                      <th className="px-4 py-3">Risk</th>
                      <th className="px-4 py-3">Status</th>
                      <th className="px-4 py-3">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((product) => {
                      const asset = assets.get(product.staking_asset_id);
                      const available = balances.get(product.symbol) ?? "0";
                      const operational = isProductOperational(product, asset);
                      const selected = focusedProductId === product.id;
                      return (
                        <tr key={product.id} className={`border-t border-[var(--exa-border)] transition ${selected ? "bg-[var(--exa-gold-surface)]" : "hover:bg-[var(--exa-surface-elevated)]"}`}>
                          <td className="px-4 py-4">
                            <button type="button" onClick={() => { setFocusedProductId(product.id); setCalculatorAmount(""); }} className="text-left">
                              <AssetPill symbol={product.symbol} network={product.network} />
                              <div className="mt-2 text-xs text-[var(--exa-text-disabled)]">{product.name}</div>
                            </button>
                          </td>
                          <td className="px-4 py-4">{formatPercent(product.displayed_apy)}</td>
                          <td className="px-4 py-4">{product.duration_days ? `Fixed ${product.duration_days}d` : "Flexible"}</td>
                          <td className="px-4 py-4">{formatAssetAmount(available, product.symbol)}</td>
                          <td className="px-4 py-4">{formatAssetAmount(product.minimum_amount, product.symbol)}</td>
                          <td className="px-4 py-4">{product.reward_schedule || "Verified settlements"}</td>
                          <td className="px-4 py-4">{product.duration_days ? `${product.duration_days} days` : "No fixed lock"}</td>
                          <td className="px-4 py-4">{formatDuration(product.unbonding_period_seconds)}</td>
                          <td className="px-4 py-4">{riskLabel(product, asset)}</td>
                          <td className="px-4 py-4"><StatusBadge status={operational.ok ? product.status : operational.reason} /></td>
                          <td className="px-4 py-4">
                            <ActionButton disabled={!operational.ok} label="Stake now" onClick={() => onStake(product)} reason={operational.reason} />
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>

              <div className="grid gap-3 p-3 xl:hidden">
                {rows.map((product) => {
                  const asset = assets.get(product.staking_asset_id);
                  const available = balances.get(product.symbol) ?? "0";
                  const operational = isProductOperational(product, asset);
                  return (
                    <article key={product.id} className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                      <div className="flex items-start justify-between gap-3">
                        <button type="button" onClick={() => { setFocusedProductId(product.id); setCalculatorAmount(""); }} className="text-left">
                          <AssetPill symbol={product.symbol} network={product.network} />
                          <p className="mt-3 font-semibold text-white">{product.name}</p>
                        </button>
                        <StatusBadge status={operational.ok ? product.status : operational.reason} />
                      </div>
                      <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <MiniMetric label="APY" value={formatPercent(product.displayed_apy)} />
                        <MiniMetric label="Reward asset" value={product.symbol} />
                        <MiniMetric label="Available" value={formatAssetAmount(available, product.symbol)} />
                        <MiniMetric label="Minimum" value={formatAssetAmount(product.minimum_amount, product.symbol)} />
                        <MiniMetric label="Product type" value={product.duration_days ? "Fixed" : "Flexible"} />
                        <MiniMetric label="Payout" value={product.reward_schedule || "Verified settlements"} />
                        <MiniMetric label="Risk" value={riskLabel(product, asset)} />
                        <MiniMetric label="Unbonding" value={formatDuration(product.unbonding_period_seconds)} />
                      </div>
                      <div className="mt-4 flex gap-2">
                        <button type="button" onClick={() => onInspect(product.slug)} className="min-h-10 flex-1 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 text-sm font-semibold text-[var(--exa-text-primary)]">
                          View details
                        </button>
                        <ActionButton disabled={!operational.ok} label="Stake now" onClick={() => onStake(product)} reason={operational.reason} />
                      </div>
                    </article>
                  );
                })}
              </div>

              {!rows.length ? (
                <div className="p-4">
                  <EmptyState
                    title={
                      !products.length
                        ? "No staking products enabled"
                        : !userHasEligibleBalance && filter === "available"
                          ? "You donâ€™t have an eligible balance yet"
                          : "No staking products are currently available"
                    }
                    body={
                      !products.length
                        ? "ExaEarn has not enabled any staking products yet. Please check again later."
                        : !userHasEligibleBalance && filter === "available"
                          ? "Deposit or buy an eligible Proof-of-Stake asset to begin staking from your ExaEarn balance."
                          : "Try a different search or filter, or check again when product status changes."
                    }
                  />
                </div>
              ) : null}
            </div>

            <aside className="h-fit rounded-[22px] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 shadow-[var(--exa-shadow-panel)] xl:sticky xl:top-4">
              {focusedProduct ? (
                <>
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="text-xs uppercase tracking-[0.24em] text-[var(--exa-text-disabled)]">Staking workspace</p>
                      <AssetPill symbol={focusedProduct.symbol} network={focusedProduct.network} />
                      <button type="button" onClick={() => onInspect(focusedProduct.slug)} className="mt-3 text-left font-['Sora'] text-xl font-semibold text-white hover:text-[var(--exa-gold-light)]">
                        {focusedProduct.name}
                      </button>
                      <p className="mt-1 text-sm text-[var(--exa-text-muted)]">Choose the plan, enter the amount, and confirm from your ExaEarn wallet balance.</p>
                    </div>
                    <StatusBadge status={focusedOperational.ok ? focusedProduct.status : focusedOperational.reason} />
                  </div>

                  <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    <Metric label="Estimated APY" value={formatPercent(focusedProduct.displayed_apy)} />
                    <Metric label="Reward asset" value={focusedProduct.symbol} />
                    <Metric label="Available balance" value={formatAssetAmount(focusedBalance, focusedProduct.symbol)} />
                    <Metric label="Minimum amount" value={formatAssetAmount(focusedProduct.minimum_amount, focusedProduct.symbol)} />
                    <Metric label="Already active by you" value={formatAssetAmount(focusedUserActive, focusedProduct.symbol)} />
                    <Metric label="Expected activation" value={focusedEstimatedStart} />
                  </div>

                  {focusedFamily.length > 1 ? (
                    <div className="mt-4 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4">
                      <div className="flex items-center justify-between gap-2">
                        <h3 className="font-semibold text-white">Select staking plan</h3>
                        <span className="text-xs text-[var(--exa-text-muted)]">Flexible and fixed options</span>
                      </div>
                      <div className="mt-3 grid gap-2">
                        {focusedFamily.map((plan) => (
                          <button
                            key={plan.id}
                            type="button"
                            onClick={() => {
                              setFocusedProductId(plan.id);
                              setCalculatorAmount("");
                            }}
                            className={`rounded-xl border p-3 text-left transition ${
                              plan.id === focusedProduct.id
                                ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]"
                                : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] hover:bg-[var(--exa-surface-hover)]"
                            }`}
                          >
                            <div className="flex items-center justify-between gap-3">
                              <div>
                                <p className="text-sm font-semibold text-white">{plan.duration_days ? `Fixed - ${plan.duration_days} days` : "Flexible"}</p>
                                <p className="mt-1 text-xs text-[var(--exa-text-muted)]">
                                  {plan.reward_schedule || "Verified settlements"} | {formatDuration(plan.unbonding_period_seconds)} unbonding
                                </p>
                              </div>
                              <div className="text-right">
                                <p className="text-xs text-[var(--exa-text-disabled)]">Estimated APY</p>
                                <p className="mt-1 font-semibold text-[var(--exa-gold-light)]">{formatPercent(plan.displayed_apy)}</p>
                              </div>
                            </div>
                          </button>
                        ))}
                      </div>
                    </div>
                  ) : null}

                  <div className="mt-4 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4">
                    <div className="flex items-center justify-between gap-2">
                      <h3 className="font-['Sora'] text-lg font-semibold text-white">Enter stake amount</h3>
                      <button type="button" onClick={() => setCalculatorAmount(percentageAmount(focusedBalance, 100, focusedAsset?.amount_precision ?? 8))} className="text-sm font-semibold text-[var(--exa-gold-light)]">
                        Max
                      </button>
                    </div>
                    <div className="mt-3">
                      <AmountInput symbol={focusedProduct.symbol} value={calculatorAmount} onChange={setCalculatorAmount} balance={focusedBalance} precision={focusedAsset?.amount_precision ?? 8} />
                    </div>
                    {focusedAmountError ? <p className="mt-3 rounded-lg border border-red-300/25 bg-red-400/10 px-3 py-2 text-sm text-red-100">{focusedAmountError}</p> : null}
                    <div className="mt-4 grid grid-cols-2 gap-3">
                      <MiniMetric label="Daily estimate" value={formatAssetAmount(focusedDaily, focusedProduct.symbol)} />
                      <MiniMetric label="Monthly estimate" value={formatAssetAmount(focusedMonthly, focusedProduct.symbol)} />
                      <MiniMetric label="Reward for selected period" value={formatAssetAmount(focusedTotal, focusedProduct.symbol)} />
                      <MiniMetric label="Maturity value" value={formatAssetAmount(decimal(focusedAmount).plus(decimal(focusedTotal)).toFixed(), focusedProduct.symbol)} />
                      <MiniMetric label="Lock duration" value={focusedProduct.duration_days ? `${focusedProduct.duration_days} days` : "Flexible"} />
                      <MiniMetric label="Unbonding" value={formatDuration(focusedProduct.unbonding_period_seconds)} />
                    </div>
                    <div className="mt-4 rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] p-3 text-xs leading-5 text-[var(--exa-gold-light)]">
                      Estimates use backend-published APY and are not guaranteed. Actual rewards depend on verified network performance, validator commission, fees, and slashing outcomes.
                    </div>
                  </div>

                  <div className="mt-4 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4">
                    <h3 className="font-semibold text-white">Product conditions</h3>
                    <div className="mt-3 grid grid-cols-2 gap-3 text-sm">
                      <MiniMetric label="Product type" value={focusedProduct.duration_days ? "Fixed" : "Flexible"} />
                      <MiniMetric label="Risk level" value={riskLabel(focusedProduct, focusedAsset)} />
                      <MiniMetric label="Reward schedule" value={focusedProduct.reward_schedule || "Verified settlements"} />
                      <MiniMetric label="Commission" value={formatPercent(focusedProduct.platform_commission_rate)} />
                      <MiniMetric label="Early redemption" value={boolish(focusedProduct.early_redemption_allowed) ? "Allowed" : "Not supported"} />
                      <MiniMetric label="Campaign bonus" value="Separate if active" />
                    </div>
                  </div>

                  <div className="mt-4 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4">
                    <div className="flex items-center justify-between gap-3">
                      <h3 className="font-semibold text-white">Review before staking</h3>
                      <span className="text-xs text-[var(--exa-text-muted)]">Estimates only</span>
                    </div>
                    <div className="mt-3 space-y-2 text-sm">
                      <div className="flex items-center justify-between gap-3 border-b border-[var(--exa-border)] pb-2">
                        <span className="text-[var(--exa-text-muted)]">Asset</span>
                        <span className="font-semibold text-white">{focusedProduct.symbol}</span>
                      </div>
                      <div className="flex items-center justify-between gap-3 border-b border-[var(--exa-border)] pb-2">
                        <span className="text-[var(--exa-text-muted)]">Amount to stake</span>
                        <span className="font-semibold text-white">{formatAssetAmount(focusedAmount, focusedProduct.symbol)}</span>
                      </div>
                      <div className="flex items-center justify-between gap-3 border-b border-[var(--exa-border)] pb-2">
                        <span className="text-[var(--exa-text-muted)]">Staking plan</span>
                        <span className="font-semibold text-white">{focusedProduct.duration_days ? `Fixed - ${focusedProduct.duration_days} days` : "Flexible"}</span>
                      </div>
                      <div className="flex items-center justify-between gap-3 border-b border-[var(--exa-border)] pb-2">
                        <span className="text-[var(--exa-text-muted)]">Estimated APY</span>
                        <span className="font-semibold text-white">{formatPercent(focusedProduct.displayed_apy)}</span>
                      </div>
                      <div className="flex items-center justify-between gap-3 border-b border-[var(--exa-border)] pb-2">
                        <span className="text-[var(--exa-text-muted)]">Estimated reward</span>
                        <span className="font-semibold text-white">{formatAssetAmount(focusedTotal, focusedProduct.symbol)}</span>
                      </div>
                      <div className="flex items-center justify-between gap-3 border-b border-[var(--exa-border)] pb-2">
                        <span className="text-[var(--exa-text-muted)]">Expected activation</span>
                        <span className="font-semibold text-white">{focusedEstimatedStart}</span>
                      </div>
                      <div className="flex items-center justify-between gap-3 border-b border-[var(--exa-border)] pb-2">
                        <span className="text-[var(--exa-text-muted)]">Estimated maturity</span>
                        <span className="font-semibold text-white">{focusedMaturityDate}</span>
                      </div>
                      <div className="flex items-center justify-between gap-3 border-b border-[var(--exa-border)] pb-2">
                        <span className="text-[var(--exa-text-muted)]">Unbonding period</span>
                        <span className="font-semibold text-white">{formatDuration(focusedProduct.unbonding_period_seconds)}</span>
                      </div>
                      <div className="flex items-center justify-between gap-3">
                        <span className="text-[var(--exa-text-muted)]">Source balance</span>
                        <span className="font-semibold text-white">ExaEarn wallet</span>
                      </div>
                    </div>
                  </div>

                  <div className="mt-4 flex flex-col gap-2">
                    <button type="button" disabled={!focusedOperational.ok || !!focusedAmountError || compareDecimal(calculatorAmount || "0", "0") <= 0} onClick={() => onStake(focusedProduct, calculatorAmount)} className="min-h-11 rounded-lg exa-button-primary px-4 text-sm font-semibold text-[var(--exa-gold-contrast)] disabled:cursor-not-allowed disabled:bg-[var(--exa-surface-hover)] disabled:text-[var(--exa-text-disabled)]">
                      Confirm plan and stake
                    </button>
                    <button type="button" onClick={() => onInspect(focusedProduct.slug)} className="min-h-11 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-4 text-sm font-semibold text-[var(--exa-text-primary)]">
                      Review product details
                    </button>
                  </div>
                </>
              ) : (
                <EmptyState title="Choose an asset to start staking" body="Select an enabled staking product to review APY, lock terms, unbonding, and estimated rewards before you confirm." />
              )}
            </aside>
          </div>
        </>
      ) : null}
    </section>
  );
}

function StakingMarketplace({ products, assets, balances, positions, onStake, onOpen }: { products: StakingProduct[]; assets: Map<number, StakingAsset>; balances: Map<string, string>; positions: StakingPosition[]; onStake: (product: StakingProduct) => void; onOpen: (slug: string) => void }) {
  const [query, setQuery] = useState("");
  const [filter, setFilter] = useState("all");
  const [sort, setSort] = useState("apy");
  const rows = useMemo(() => {
    return products
      .filter((product) => {
        const asset = assets.get(product.staking_asset_id);
        const haystack = `${product.symbol} ${product.network} ${product.name}`.toLowerCase();
        if (query && !haystack.includes(query.toLowerCase())) return false;
        if (filter === "available" && !isProductOperational(product, asset).ok) return false;
        if (filter === "flexible" && product.duration_days) return false;
        if (filter === "locked" && !product.duration_days) return false;
        if (filter === "auto" && !boolish(product.auto_compound_supported)) return false;
        if (filter === "bonus") return false;
        return true;
      })
      .sort((a, b) => {
        if (sort === "balance") return decimal(balances.get(b.symbol)).cmp(decimal(balances.get(a.symbol)));
        if (sort === "minimum") return decimal(a.minimum_amount).cmp(decimal(b.minimum_amount));
        if (sort === "duration") return Number(a.duration_days ?? 0) - Number(b.duration_days ?? 0);
        return decimal(b.displayed_apy).cmp(decimal(a.displayed_apy));
      });
  }, [assets, balances, filter, products, query, sort]);

  return (
    <section className="mt-6 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)] sm:p-5">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="font-['Sora'] text-xl font-semibold">All Staking Assets</h2>
          <p className="mt-1 text-sm text-[var(--exa-text-muted)]">Unavailable products stay visible with their reason.</p>
        </div>
        <div className="grid gap-2 sm:grid-cols-[1fr_auto_auto]">
          <label className="relative block">
            <Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-[var(--exa-text-disabled)]" />
            <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search asset, network, or product" className="min-h-10 w-full rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]" />
          </label>
          <select value={filter} onChange={(event) => setFilter(event.target.value)} className="min-h-10 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]" aria-label="Filter staking products">
            <option value="all">All products</option>
            <option value="flexible">Flexible</option>
            <option value="locked">Locked</option>
            <option value="auto">Auto-compound</option>
            <option value="available">Available to stake</option>
          </select>
          <select value={sort} onChange={(event) => setSort(event.target.value)} className="min-h-10 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]" aria-label="Sort staking products">
            <option value="apy">Highest APY</option>
            <option value="duration">Shortest duration</option>
            <option value="minimum">Minimum stake</option>
            <option value="balance">Available balance</option>
          </select>
        </div>
      </div>
      <div className="mt-4 hidden overflow-x-auto lg:block">
        <table className="w-full min-w-[980px] border-separate border-spacing-y-2 text-left text-sm">
          <thead className="text-xs uppercase tracking-wide text-[var(--exa-text-disabled)]">
            <tr><th>Asset</th><th>Product</th><th>Estimated APY</th><th>Commission</th><th>Duration</th><th>Unbonding</th><th>Available</th><th>Status</th><th>Action</th></tr>
          </thead>
          <tbody>
            {rows.map((product) => {
              const asset = assets.get(product.staking_asset_id);
              const operational = isProductOperational(product, asset);
              const totalStaked = positions.filter((position) => position.symbol === product.symbol).reduce((sum, position) => sum.plus(decimal(position.active_principal_amount)), decimal(0));
              return (
                <tr key={product.id} className="rounded-lg bg-white/[0.035]">
                  <td className="rounded-l-lg p-3"><AssetPill symbol={product.symbol} network={product.network} /></td>
                  <td className="p-3"><button type="button" onClick={() => onOpen(product.slug)} className="text-left font-semibold text-white hover:text-[var(--exa-gold-light)]">{product.name}</button><p className="text-xs text-[var(--exa-text-disabled)]">Staked by you: {formatAssetAmount(totalStaked.toFixed(), product.symbol)}</p></td>
                  <td className="p-3">{formatPercent(product.displayed_apy)}</td>
                  <td className="p-3">{formatPercent(product.platform_commission_rate)}</td>
                  <td className="p-3">{formatDuration(undefined, product.duration_days)}</td>
                  <td className="p-3">{formatDuration(product.unbonding_period_seconds)}</td>
                  <td className="p-3">{formatAssetAmount(balances.get(product.symbol) ?? "0", product.symbol)}</td>
                  <td className="p-3"><StatusBadge status={operational.ok ? product.status : operational.reason} /></td>
                  <td className="rounded-r-lg p-3"><ActionButton disabled={!operational.ok} label="Stake" onClick={() => onStake(product)} reason={operational.reason} /></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <div className="mt-4 grid gap-3 lg:hidden">
        {rows.map((product) => <ProductCard key={product.id} product={product} asset={assets.get(product.staking_asset_id)} availableBalance={balances.get(product.symbol) ?? "0"} onStake={onStake} onOpen={onOpen} />)}
      </div>
      {!rows.length ? <EmptyState title="No staking products found" body="Adjust the search or clear filters to view unavailable products too." /> : null}
    </section>
  );
}

function ProductCard({ product, asset, availableBalance, onStake, onOpen, featured = false }: { product: StakingProduct; asset?: StakingAsset; availableBalance: string; onStake: (product: StakingProduct) => void; onOpen: (slug: string) => void; featured?: boolean }) {
  const operational = isProductOperational(product, asset);
              const capacity = product.capacity ? decimal(product.total_subscribed).div(Decimal.max(decimal(product.capacity), decimal(1))).times(100).toDecimalPlaces(0).toNumber() : null;
  return (
    <article className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 shadow-[var(--exa-shadow-panel)]">
      <div className="flex items-start justify-between gap-3">
        <AssetPill symbol={product.symbol} network={product.network} />
        <div className="flex flex-wrap justify-end gap-2">
          {featured ? <Badge label="Popular" /> : null}
          {product.network_environment === "testnet" ? <Badge label="Testnet" /> : null}
          {boolish(product.auto_compound_supported) ? <Badge label="Auto" /> : null}
        </div>
      </div>
      <button type="button" onClick={() => onOpen(product.slug)} className="mt-4 block text-left font-['Sora'] text-lg font-semibold text-white hover:text-[var(--exa-gold-light)]">{product.name}</button>
      <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
        <MiniMetric label="Estimated APY" value={formatPercent(product.displayed_apy)} />
        <MiniMetric label="Native reward" value={product.symbol} />
        <MiniMetric label="Minimum" value={formatAssetAmount(product.minimum_amount, product.symbol)} />
        <MiniMetric label="Available" value={formatAssetAmount(availableBalance, product.symbol)} />
        <MiniMetric label="Lock" value={formatDuration(undefined, product.duration_days)} />
        <MiniMetric label="Unbonding" value={formatDuration(product.unbonding_period_seconds)} />
      </div>
      {capacity !== null ? <div className="mt-4"><div className="mb-1 flex justify-between text-xs text-[var(--exa-text-muted)]"><span>Capacity</span><span>{capacity}%</span></div><div className="h-2 rounded-full bg-[var(--exa-surface-hover)]"><span className="block h-2 rounded-full bg-[var(--exa-gold-light)]" style={{ width: `${Math.min(100, capacity)}%` }} /></div></div> : null}
      <div className="mt-4 flex items-center justify-between gap-3">
        <StatusBadge status={operational.ok ? product.status : operational.reason} />
        <ActionButton disabled={!operational.ok} label="Stake" onClick={() => onStake(product)} reason={operational.reason} />
      </div>
    </article>
  );
}

function ProductDetails({ product, asset, apyHistory, availableBalance, onStake, loading }: { product?: StakingProduct; asset?: StakingAsset; apyHistory: StakingApyHistory[]; availableBalance: string; onStake: (product: StakingProduct) => void; loading: boolean }) {
  if (loading) return <SkeletonGrid />;
  if (!product) return <EmptyState title="Product not found" body="This staking product is no longer available." />;
  const operational = isProductOperational(product, asset);
  const rows = apyHistory.filter((row) => row.symbol === product.symbol).slice(0, 24).reverse().map((row, index) => ({ name: row.recorded_at ? new Date(row.recorded_at).toLocaleDateString() : `#${index + 1}`, apy: decimal(row.amount).toNumber() }));
  return (
    <div className="grid gap-5 lg:grid-cols-[1fr_360px]">
      <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-5 shadow-[var(--exa-shadow-panel)]">
        <AssetPill symbol={product.symbol} network={product.network} />
        <h1 className="mt-4 font-['Sora'] text-3xl font-semibold">{product.name}</h1>
        <p className="mt-2 text-sm leading-6 text-[var(--exa-text-muted)]">Earn network-generated {product.symbol} rewards from verified staking settlements. ExaToken bonuses, when available, are funded separately.</p>
        <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <Metric label="Estimated net APY" value={formatPercent(product.displayed_apy)} />
          <Metric label="ExaEarn commission" value={formatPercent(product.platform_commission_rate)} />
          <Metric label="Activation estimate" value={formatDuration(asset?.expected_activation_seconds)} />
          <Metric label="Unbonding period" value={formatDuration(product.unbonding_period_seconds ?? asset?.unbonding_period_seconds)} />
          <Metric label="Minimum stake" value={formatAssetAmount(product.minimum_amount, product.symbol)} />
          <Metric label="Maximum stake" value={product.maximum_amount ? formatAssetAmount(product.maximum_amount, product.symbol) : "No backend cap"} />
          <Metric label="Reward schedule" value={product.reward_schedule || "Verified settlements"} />
          <Metric label="Terms" value={product.terms_version} />
        </div>
        <div className="mt-5 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4">
          <h2 className="font-['Sora'] text-lg font-semibold">Historical APY</h2>
          <div className="mt-3 min-h-[220px]">
            {rows.length ? <ResponsiveContainer width="100%" height={220}><AreaChart data={rows}><CartesianGrid stroke="rgba(255,255,255,0.08)" vertical={false} /><XAxis dataKey="name" tick={{ fill: "rgba(245,240,255,0.55)", fontSize: 11 }} /><YAxis tick={{ fill: "rgba(245,240,255,0.55)", fontSize: 11 }} /><Tooltip contentStyle={{ background: "#111827", border: "1px solid rgba(255,255,255,0.12)", borderRadius: 8 }} /><Area type="monotone" dataKey="apy" stroke="#f9e2ad" fill="rgba(249,226,173,0.16)" /></AreaChart></ResponsiveContainer> : <EmptyState title="No APY chart yet" body="The backend has not published APY history for this product." />}
          </div>
        </div>
        <RewardExplanation product={product} />
      </section>
      <aside className="h-fit rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 shadow-[var(--exa-shadow-panel)] lg:sticky lg:top-4">
        <Metric label="Available balance" value={formatAssetAmount(availableBalance, product.symbol)} />
        <div className="mt-4 space-y-2 text-sm text-[var(--exa-text-muted)]">
          <p>Network status: <StatusBadge status={operational.ok ? product.status : operational.reason} /></p>
          <p>Auto-compound: {boolish(product.auto_compound_supported) ? "Supported" : "Not supported"}</p>
          <p>Redemption: {product.redemption_type || "Network unbonding"}</p>
          <p>Early redemption: {boolish(product.early_redemption_allowed) ? "Allowed by product rules" : "Unavailable"}</p>
        </div>
        <button type="button" disabled={!operational.ok} onClick={() => onStake(product)} className="mt-5 min-h-11 w-full rounded-lg exa-button-primary px-4 font-semibold text-[var(--exa-gold-contrast)] disabled:cursor-not-allowed disabled:bg-[var(--exa-surface-hover)] disabled:text-[var(--exa-text-disabled)]">Stake {product.symbol}</button>
        <p className="mt-3 text-xs text-[var(--exa-text-muted)]">Rewards are subject to validator and network performance, lock-up, unbonding, and slashing risks.</p>
      </aside>
    </div>
  );
}

function StakeModal({ product, productOptions = [], asset, availableBalance, initialAmount = "", request, onClose, onDone }: { product: StakingProduct; productOptions?: StakingProduct[]; asset?: StakingAsset; availableBalance: string; initialAmount?: string; request: <T = unknown>(path: string, options?: RequestInit) => Promise<T>; onClose: () => void; onDone: (message: string) => Promise<void> }) {
  const siblingProducts = useMemo(() => {
    const items = productOptions.length ? productOptions : [product];
    return [...items].sort((left, right) => Number(left.duration_days ?? 0) - Number(right.duration_days ?? 0));
  }, [product, productOptions]);
  const [selectedProductId, setSelectedProductId] = useState(product.id);
  const [step, setStep] = useState(1);
  const [amount, setAmount] = useState(initialAmount);
  const [autoCompound, setAutoCompound] = useState(false);
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [riskAccepted, setRiskAccepted] = useState(false);
  const [pin, setPin] = useState("");
  const [twoFactor, setTwoFactor] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [successPayload, setSuccessPayload] = useState<{ publicId?: string; activation?: string; firstReward?: string } | null>(null);
  const selectedProduct = siblingProducts.find((item) => item.id === selectedProductId) ?? product;
  const idempotency = useRef(`stake-web-${selectedProduct.id}-${crypto.randomUUID?.() ?? Date.now()}`);
  const operational = isProductOperational(selectedProduct, asset);
  const validation = validateStakeAmount(amount, selectedProduct, availableBalance);
  const days = selectedProduct.duration_days ?? 365;
  const annual = estimateReward(amount, selectedProduct.displayed_apy, 365);
  const monthly = estimateReward(amount, selectedProduct.displayed_apy, 30);
  const daily = estimateReward(amount, selectedProduct.displayed_apy, 1);

  useEffect(() => {
    setSelectedProductId(product.id);
  }, [product.id]);

  useEffect(() => {
    setAmount(initialAmount);
  }, [initialAmount]);

  useLeaveWarning(submitting);

  const submit = async () => {
    if (submitting || validation || !termsAccepted || !riskAccepted || !operational.ok) return;
    setSubmitting(true);
    setError("");
    try {
      await stakingApi.acceptTerms(request, selectedProduct.terms_version);
      const position = await stakingApi.createPosition(request, {
        staking_product_id: selectedProduct.id,
        amount,
        auto_compound: autoCompound,
        terms_version: selectedProduct.terms_version,
        transaction_pin: pin || undefined,
        two_factor_code: twoFactor || undefined,
        idempotency_key: idempotency.current,
      });
      setPin("");
      setTwoFactor("");
      setSuccessPayload({
        publicId: position.public_id,
        activation: asset?.expected_activation_seconds ? new Date(Date.now() + Number(asset.expected_activation_seconds) * 1000).toISOString() : undefined,
        firstReward: asset?.expected_activation_seconds ? new Date(Date.now() + Number(asset.expected_activation_seconds) * 1000 + 86400000).toISOString() : undefined,
      });
      setStep(5);
      await onDone(`Stake request submitted for ${formatAssetAmount(amount, selectedProduct.symbol)}. Position ${position.public_id || "created"} is awaiting delegation.`);
    } catch (err) {
      setError(mapApiError(err));
      setPin("");
      setTwoFactor("");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Modal title={`Stake ${selectedProduct.symbol}`} onClose={submitting ? undefined : onClose}>
      {step === 1 ? (
        <div className="space-y-4">
          <ProductActionSummary product={selectedProduct} availableBalance={availableBalance} />
          {siblingProducts.length > 1 ? (
            <div className="rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-3">
              <p className="text-sm font-semibold text-white">Choose duration</p>
              <div className="mt-3 grid gap-2 sm:grid-cols-2">
                {siblingProducts.map((item) => (
                  <button key={item.id} type="button" onClick={() => setSelectedProductId(item.id)} className={`rounded-lg border px-3 py-3 text-left ${item.id === selectedProduct.id ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)]"}`}>
                    <div className="flex items-center justify-between gap-2">
                      <span className="text-sm font-semibold text-white">{item.duration_days ? `${item.duration_days} days` : "Flexible"}</span>
                      <span className="text-sm text-[var(--exa-gold-light)]">{formatPercent(item.displayed_apy)}</span>
                    </div>
                    <p className="mt-1 text-xs text-[var(--exa-text-muted)]">{formatDuration(item.unbonding_period_seconds)} unbonding</p>
                  </button>
                ))}
              </div>
            </div>
          ) : null}
          <AmountInput symbol={selectedProduct.symbol} value={amount} onChange={setAmount} balance={availableBalance} precision={asset?.amount_precision ?? 8} />
          {validation ? <InlineError message={validation} /> : null}
          <RewardEstimator amount={amount} product={selectedProduct} days={days} daily={daily} monthly={monthly} annual={annual} />
          <ModalActions primary="Continue" onPrimary={() => setStep(2)} primaryDisabled={Boolean(validation) || !amount || !operational.ok} secondary="Cancel" onSecondary={onClose} />
        </div>
      ) : null}
      {step === 2 ? (
        <div className="space-y-4">
          <Toggle label="Auto-compound" description={boolish(selectedProduct.auto_compound_supported) ? "Compound when the network and product allow it." : "This product does not support auto-compounding."} checked={autoCompound} disabled={!boolish(selectedProduct.auto_compound_supported)} onChange={setAutoCompound} />
          <div className="rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-3 text-sm text-[var(--exa-text-muted)]">Reward destination is managed by ExaEarn ledger accounts for this product. Native rewards and ExaToken bonuses remain separated.</div>
          <ModalActions primary="Review" onPrimary={() => setStep(3)} secondary="Back" onSecondary={() => setStep(1)} />
        </div>
      ) : null}
      {step === 3 ? (
        <div className="space-y-4">
          <ReviewRows rows={[
            ["Asset", selectedProduct.symbol],
            ["Network", selectedProduct.network],
            ["Amount", formatAssetAmount(amount, selectedProduct.symbol)],
            ["Staking product", selectedProduct.name],
            ["Estimated APY", formatPercent(selectedProduct.displayed_apy)],
            ["Estimated daily reward", formatAssetAmount(daily, selectedProduct.symbol)],
            ["Estimated monthly reward", formatAssetAmount(monthly, selectedProduct.symbol)],
            ["Estimated annual reward", formatAssetAmount(annual, selectedProduct.symbol)],
            ["Reward frequency", selectedProduct.reward_schedule || "Verified settlements"],
            ["ExaEarn commission", formatPercent(selectedProduct.platform_commission_rate)],
            ["Lock", formatDuration(undefined, selectedProduct.duration_days)],
            ["Unbonding", formatDuration(selectedProduct.unbonding_period_seconds)],
            ["Auto-compound", autoCompound ? "Enabled" : "Disabled"],
            ["Source wallet", "ExaEarn balance"],
            ["Terms", selectedProduct.terms_version],
          ]} />
          <CheckRow checked={termsAccepted} onChange={setTermsAccepted} label="I accept the staking product terms." />
          <CheckRow checked={riskAccepted} onChange={setRiskAccepted} label="I understand APY is estimated and staking includes lock-up, unbonding, validator, and slashing risks." />
          <input value={pin} onChange={(event) => setPin(event.target.value)} placeholder="Transaction PIN" type="password" autoComplete="one-time-code" className="min-h-11 w-full rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]" />
          <input value={twoFactor} onChange={(event) => setTwoFactor(event.target.value)} placeholder="Two-factor code if required" inputMode="numeric" autoComplete="one-time-code" className="min-h-11 w-full rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]" />
          {error ? <InlineError message={error} /> : null}
          <ModalActions primary={submitting ? "Submitting..." : "Confirm and stake"} onPrimary={() => void submit()} primaryDisabled={submitting || !termsAccepted || !riskAccepted} secondary="Back" onSecondary={() => setStep(2)} />
        </div>
      ) : null}
      {step === 5 ? (
        <SuccessState
          title="Stake request submitted"
          body="Your request is awaiting delegation and network activation. Principal is not active until the backend confirms blockchain activation."
          details={[
            ["Amount staked", formatAssetAmount(amount, selectedProduct.symbol)],
            ["Product type", selectedProduct.duration_days ? `Fixed - ${selectedProduct.duration_days} days` : "Flexible"],
            ["Position reference", successPayload?.publicId || "Pending assignment"],
            ["Expected activation", successPayload?.activation ? formatDateTime(successPayload.activation) : "Awaiting backend estimate"],
            ["Expected first reward", successPayload?.firstReward ? formatDateTime(successPayload.firstReward) : "After activation and settlement"],
          ]}
          onClose={onClose}
        />
      ) : null}
    </Modal>
  );
}

function UnstakeModal({ position, request, onClose, onDone }: { position: StakingPosition; request: <T = unknown>(path: string, options?: RequestInit) => Promise<T>; onClose: () => void; onDone: (message: string) => Promise<void> }) {
  const [amount, setAmount] = useState(position.active_principal_amount || position.principal_amount);
  const [pin, setPin] = useState("");
  const [twoFactor, setTwoFactor] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const idempotency = useRef(`unstake-web-${position.public_id}-${crypto.randomUUID?.() ?? Date.now()}`);
  const invalid = compareDecimal(amount, "0") <= 0 || compareDecimal(amount, position.active_principal_amount) > 0;
  useLeaveWarning(busy);

  const submit = async () => {
    if (busy || invalid) return;
    setBusy(true);
    setError("");
    try {
      await stakingApi.unstake(request, position.public_id, { amount, transaction_pin: pin || undefined, two_factor_code: twoFactor || undefined, idempotency_key: idempotency.current });
      setPin("");
      setTwoFactor("");
      await onDone("Unstake request submitted. Principal is not available until network confirmation and release.");
    } catch (err) {
      setError(mapApiError(err));
      setPin("");
      setTwoFactor("");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal title={`Unstake ${position.symbol}`} onClose={busy ? undefined : onClose}>
      <div className="space-y-4">
        <Metric label="Active principal" value={formatAssetAmount(position.active_principal_amount, position.symbol)} />
        <AmountInput symbol={position.symbol} value={amount} onChange={setAmount} balance={position.active_principal_amount || "0"} precision={8} />
        {invalid ? <InlineError message="Enter an amount above zero and no greater than active principal." /> : null}
        <div className="rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] p-3 text-sm text-[var(--exa-gold-light)]">Unstaking starts a network-specific unbonding process. Funds are not available until the backend verifies withdrawable principal.</div>
        <input value={pin} onChange={(event) => setPin(event.target.value)} placeholder="Transaction PIN" type="password" className="min-h-11 w-full rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]" />
        <input value={twoFactor} onChange={(event) => setTwoFactor(event.target.value)} placeholder="Two-factor code if required" inputMode="numeric" className="min-h-11 w-full rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--exa-gold)]" />
        {error ? <InlineError message={error} /> : null}
        <ModalActions primary={busy ? "Submitting..." : "Submit unstake request"} onPrimary={() => void submit()} primaryDisabled={busy || invalid} secondary="Cancel" onSecondary={onClose} />
      </div>
    </Modal>
  );
}

function ClaimRewardModal({ position, type, request, onClose, onDone }: { position: StakingPosition; type: "native" | "exatoken"; request: <T = unknown>(path: string, options?: RequestInit) => Promise<T>; onClose: () => void; onDone: (message: string) => Promise<void> }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const submit = async () => {
    if (busy) return;
    setBusy(true);
    setError("");
    try {
      if (type === "native") await stakingApi.claimNative(request, position.public_id);
      else await stakingApi.claimExaToken(request, position.public_id);
      await onDone(`${type === "native" ? "Native" : "ExaToken"} reward claim submitted.`);
    } catch (err) {
      setError(mapApiError(err));
    } finally {
      setBusy(false);
    }
  };
  return (
    <Modal title={type === "native" ? "Claim native rewards" : "Claim ExaToken rewards"} onClose={busy ? undefined : onClose}>
      <ReviewRows rows={[
        ["Position", position.public_id],
        ["Reward asset", type === "native" ? position.symbol : "EXA"],
        ["Destination", type === "native" ? `${position.symbol} reward payable` : "ExaToken reward payable"],
        ["Status", "Requires backend verified allocation"],
      ]} />
      {error ? <InlineError message={error} /> : null}
      <ModalActions primary={busy ? "Submitting..." : "Submit claim"} onPrimary={() => void submit()} secondary="Cancel" onSecondary={onClose} primaryDisabled={busy} />
    </Modal>
  );
}

function AutoCompoundModal({ position, request, onClose, onDone }: { position: StakingPosition; request: <T = unknown>(path: string, options?: RequestInit) => Promise<T>; onClose: () => void; onDone: (message: string) => Promise<void> }) {
  const [next, setNext] = useState(!boolish(position.auto_compound_enabled));
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const submit = async () => {
    setBusy(true);
    setError("");
    try {
      await stakingApi.autoCompound(request, position.public_id, next);
      await onDone(`Auto-compound ${next ? "enabled" : "disabled"} for ${position.symbol}.`);
    } catch (err) {
      setError(mapApiError(err));
    } finally {
      setBusy(false);
    }
  };
  return (
    <Modal title="Auto-compound settings" onClose={busy ? undefined : onClose}>
      <Toggle label="Auto-compound" description="The change applies only after backend confirmation." checked={next} onChange={setNext} />
      {error ? <InlineError message={error} /> : null}
      <ModalActions primary={busy ? "Saving..." : "Confirm change"} onPrimary={() => void submit()} secondary="Cancel" onSecondary={onClose} primaryDisabled={busy} />
    </Modal>
  );
}

function PositionsPage({ positions, rewards, onOpen, onUnstake, onClaim }: { positions: StakingPosition[]; rewards: StakingReward[]; onOpen: (publicId: string) => void; onUnstake: (position: StakingPosition) => void; onClaim: (position: StakingPosition, type: "native" | "exatoken") => void }) {
  const [tab, setTab] = useState("all");
  const visible = tab === "all" ? positions : positions.filter((position) => position.status === tab);
  return (
    <section id="my-positions" className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)]">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="font-['Sora'] text-2xl font-semibold">My Staking Positions</h1>
          <p className="mt-1 text-sm text-[var(--exa-text-muted)]">Monitor activation, lock expiry, rewards, and eligible actions from one place.</p>
        </div>
        <div className="grid gap-2 sm:grid-cols-3">
          <Metric label="Total positions" value={String(positions.length)} />
          <Metric label="Active positions" value={String(positions.filter((position) => position.status === "active").length)} />
          <Metric label="Claimable records" value={String(rewards.filter((reward) => reward.status !== "distributed").length)} />
        </div>
      </div>
      <div className="mt-4 flex gap-2 overflow-x-auto pb-2">
        {["all", "pending", "active", "unstaking", "unbonding", "withdrawable", "completed", "failed"].map((item) => <button key={item} type="button" onClick={() => setTab(item)} className={`whitespace-nowrap rounded-lg border px-3 py-2 text-sm ${tab === item ? "border-[var(--exa-border-active)] exa-button-primary text-[var(--exa-gold-contrast)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)]"}`}>{positionStatusLabel(item)}</button>)}
      </div>
      <div className="mt-4 hidden overflow-x-auto xl:block">
        <table className="w-full min-w-[1180px] text-left text-sm">
          <thead className="text-xs uppercase tracking-wide text-[var(--exa-text-disabled)]">
            <tr>
              <th className="py-3 pr-4">Asset</th>
              <th className="py-3 pr-4">Principal</th>
              <th className="py-3 pr-4">Active</th>
              <th className="py-3 pr-4">Rewards</th>
              <th className="py-3 pr-4">Opened</th>
              <th className="py-3 pr-4">Maturity</th>
              <th className="py-3 pr-4">Status</th>
              <th className="py-3 pr-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            {visible.map((position) => (
              <tr key={position.public_id} className="border-t border-[var(--exa-border)]">
                <td className="py-4 pr-4">
                  <button type="button" onClick={() => onOpen(position.public_id)} className="text-left">
                    <AssetPill symbol={position.symbol} network={position.network} />
                    <div className="mt-2 text-xs text-[var(--exa-text-disabled)]">{position.product_name}</div>
                  </button>
                </td>
                <td className="py-4 pr-4">{formatAssetAmount(position.principal_amount, position.symbol)}</td>
                <td className="py-4 pr-4">{formatAssetAmount(position.active_principal_amount, position.symbol)}</td>
                <td className="py-4 pr-4">{formatAssetAmount(position.total_native_net_rewards, position.symbol)}</td>
                <td className="py-4 pr-4">{formatDateTime(position.opened_at)}</td>
                <td className="py-4 pr-4">{position.lock_ends_at ? formatDateTime(position.lock_ends_at) : "Flexible"}</td>
                <td className="py-4 pr-4"><StatusBadge status={position.status} /></td>
                <td className="py-4 pr-4">
                  <div className="flex flex-wrap gap-2">
                    <button type="button" onClick={() => onOpen(position.public_id)} className="min-h-9 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 text-xs font-semibold text-[var(--exa-text-primary)]">View</button>
                    <button type="button" disabled={position.status !== "active"} onClick={() => onUnstake(position)} className="min-h-9 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 text-xs font-semibold text-[var(--exa-text-primary)] disabled:opacity-45">Unstake</button>
                    <button type="button" onClick={() => onClaim(position, "native")} className="min-h-9 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 text-xs font-semibold text-[var(--exa-text-primary)]">Claim</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="mt-4 grid gap-3 xl:hidden">
        {visible.map((position) => <PositionCard key={position.public_id} position={position} rewardCount={rewards.filter((reward) => reward.staking_position_id === position.id).length} onOpen={onOpen} onUnstake={onUnstake} onClaim={onClaim} />)}
      </div>
      {!visible.length ? <EmptyState title="No positions in this status" body="Your staking positions will appear here after requests are submitted." /> : null}
    </section>
  );
}

function PositionPreview({ positions, onOpen, onUnstake }: { positions: StakingPosition[]; onOpen: () => void; onUnstake: (position: StakingPosition) => void }) {
  const active = positions.filter((position) => ["active", "pending", "unbonding", "unstaking", "awaiting_activation", "delegation_submitted"].includes(position.status)).slice(0, 4);
  return (
    <section id="my-positions" className="mt-2 rounded-[20px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-3 shadow-[var(--exa-shadow-panel)]">
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-['Sora'] text-xl font-semibold">My Active and Pending Positions</h2>
        <button type="button" onClick={onOpen} className="text-sm text-[var(--exa-gold-light)]">View all</button>
      </div>
      <div className="mt-4 grid gap-3 md:grid-cols-2">
        {active.map((position) => <PositionCard key={position.public_id} position={position} compact onOpen={() => undefined} onUnstake={onUnstake} onClaim={() => undefined} />)}
      </div>
      {!active.length ? <EmptyState title="No active staking yet" body="Explore enabled products when the backend marks a network operational." /> : null}
    </section>
  );
}

function PositionDetails({ position, rewards, transactions, onUnstake, onClaim, onAutoCompound, loading }: { position?: StakingPosition | null; rewards: StakingReward[]; transactions: { staking_position_id?: number | null; transaction_type: string; amount: string; status: string; created_at?: string }[]; onUnstake: (position: StakingPosition) => void; onClaim: (position: StakingPosition, type: "native" | "exatoken") => void; onAutoCompound: (position: StakingPosition) => void; loading: boolean }) {
  if (loading) return <SkeletonGrid />;
  if (!position) return <EmptyState title="Position not found" body="This staking position could not be loaded." />;
  const relatedRewards = rewards.filter((reward) => reward.staking_position_id === position.id);
  const relatedTransactions = transactions.filter((transaction) => transaction.staking_position_id === position.id);
  const claimableNative = claimableNativeRewards(position);
  const claimableExa = claimableExaRewards(position);
  const canUnstake = canUnstakePosition(position);
  const canAutoCompound = canToggleAutoCompound(position);
  return (
    <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)] sm:p-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div><AssetPill symbol={position.symbol} network={position.network} /><h1 className="mt-3 font-['Sora'] text-2xl font-semibold">{position.product_name}</h1><p className="mt-1 text-sm text-[var(--exa-text-muted)]">Position {position.public_id}</p></div>
        <StatusBadge status={position.status} />
      </div>
      <div className="mt-5 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.24em] text-[var(--exa-text-disabled)]">Position control</p>
            <h2 className="mt-2 font-['Sora'] text-lg font-semibold text-white">What you can do right now</h2>
            <p className="mt-1 max-w-2xl text-sm text-[var(--exa-text-muted)]">{nextPositionMilestone(position)}</p>
          </div>
          <div className="grid gap-2 sm:grid-cols-2 lg:min-w-[340px]">
            <MiniMetric label="Claimable native" value={formatAssetAmount(claimableNative, position.symbol)} />
            <MiniMetric label="Claimable EXA" value={formatAssetAmount(claimableExa, "EXA")} />
            <MiniMetric label="Lock ends" value={formatDateTime(position.lock_ends_at)} />
            <MiniMetric label="Withdrawable" value={formatDateTime(position.withdrawable_at)} />
          </div>
        </div>
      </div>
      <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Metric label="Principal" value={formatAssetAmount(position.principal_amount, position.symbol)} />
        <Metric label="Active principal" value={formatAssetAmount(position.active_principal_amount, position.symbol)} />
        <Metric label="Pending stake" value={formatAssetAmount(position.pending_stake_amount, position.symbol)} />
        <Metric label="Pending unstake" value={formatAssetAmount(position.pending_unstake_amount, position.symbol)} />
        <Metric label="Native rewards" value={formatAssetAmount(position.total_native_net_rewards, position.symbol)} />
        <Metric label="ExaToken bonuses" value={formatAssetAmount(position.total_exatoken_bonus_rewards, "EXA")} />
        <Metric label="Claimable native" value={formatAssetAmount(claimableNative, position.symbol)} />
        <Metric label="Claimable EXA" value={formatAssetAmount(claimableExa, "EXA")} />
        <Metric label="Auto-compound" value={boolish(position.auto_compound_enabled) ? "Enabled" : "Disabled"} />
        <Metric label="Activation" value={formatDateTime(position.activation_at)} />
      </div>
      <PositionTimeline position={position} />
      <div className="mt-5 grid gap-2 lg:grid-cols-4">
        <button type="button" disabled={!canUnstake} onClick={() => onUnstake(position)} className="min-h-11 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-4 text-sm font-semibold disabled:opacity-45">Unstake principal</button>
        <button type="button" disabled={compareDecimal(claimableNative, "0") <= 0} onClick={() => onClaim(position, "native")} className="min-h-11 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-4 text-sm font-semibold disabled:opacity-45">Claim native rewards</button>
        <button type="button" disabled={compareDecimal(claimableExa, "0") <= 0} onClick={() => onClaim(position, "exatoken")} className="min-h-11 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-4 text-sm font-semibold disabled:opacity-45">Claim ExaToken bonus</button>
        <button type="button" disabled={!canAutoCompound} onClick={() => onAutoCompound(position)} className="min-h-11 rounded-lg exa-button-primary px-4 text-sm font-semibold text-[var(--exa-gold-contrast)] disabled:bg-[var(--exa-surface-hover)] disabled:text-[var(--exa-text-disabled)]">{boolish(position.auto_compound_enabled) ? "Manage auto-compound" : "Enable auto-compound"}</button>
      </div>
      <RewardHistory rewards={relatedRewards} compact />
      <div className="mt-5 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4"><h2 className="font-['Sora'] text-lg font-semibold">Transaction History</h2>{relatedTransactions.length ? relatedTransactions.map((tx) => <div key={`${tx.transaction_type}-${tx.created_at}`} className="mt-3 flex items-center justify-between gap-3 border-t border-[var(--exa-border)] pt-3 text-sm"><span>{tx.transaction_type.replaceAll("_", " ")}</span><span>{formatAssetAmount(tx.amount, position.symbol)}</span><StatusBadge status={tx.status} /></div>) : <EmptyState title="No transactions yet" body="Position-specific transactions appear after backend records are available." />}</div>
    </section>
  );
}

function PositionCard({ position, rewardCount = 0, compact = false, onOpen, onUnstake, onClaim }: { position: StakingPosition; rewardCount?: number; compact?: boolean; onOpen: (publicId: string) => void; onUnstake: (position: StakingPosition) => void; onClaim: (position: StakingPosition, type: "native" | "exatoken") => void }) {
  const claimableNative = claimableNativeRewards(position);
  const claimableExa = claimableExaRewards(position);
  const canUnstake = canUnstakePosition(position);
  const hasClaimableNative = compareDecimal(claimableNative, "0") > 0;
  return (
    <article className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
      <div className="flex items-start justify-between gap-3">
        <AssetPill symbol={position.symbol} network={position.network} />
        <StatusBadge status={position.status} />
      </div>
      <button type="button" onClick={() => onOpen(position.public_id)} className="mt-3 text-left font-semibold text-white hover:text-[var(--exa-gold-light)]">{position.product_name}</button>
      <div className="mt-3 grid grid-cols-2 gap-3 text-sm">
        <MiniMetric label="Principal" value={formatAssetAmount(position.principal_amount, position.symbol)} />
        <MiniMetric label="Active" value={formatAssetAmount(position.active_principal_amount, position.symbol)} />
        <MiniMetric label="Pending unstake" value={formatAssetAmount(position.pending_unstake_amount, position.symbol)} />
        <MiniMetric label="Rewards" value={formatAssetAmount(position.total_native_net_rewards, position.symbol)} />
        <MiniMetric label="Claimable now" value={formatAssetAmount(claimableNative, position.symbol)} />
        <MiniMetric label="EXA bonus" value={formatAssetAmount(claimableExa, "EXA")} />
      </div>
      {!compact ? <p className="mt-3 text-xs text-[var(--exa-text-muted)]">{rewardCount} reward records. {nextPositionMilestone(position)} Opened {formatDateTime(position.opened_at)}</p> : null}
      <div className="mt-4 flex flex-wrap gap-2">
        <button type="button" onClick={() => onOpen(position.public_id)} className="min-h-10 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 text-sm">Details</button>
        <button type="button" disabled={!canUnstake} onClick={() => onUnstake(position)} className="min-h-10 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 text-sm disabled:opacity-45">Unstake</button>
        <button type="button" disabled={!hasClaimableNative} onClick={() => onClaim(position, "native")} className="min-h-10 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 text-sm disabled:opacity-45">Claim</button>
      </div>
    </article>
  );
}

function RewardHistory({ rewards, compact = false }: { rewards: StakingReward[]; compact?: boolean }) {
  const [asset, setAsset] = useState("all");
  const filtered = asset === "all" ? rewards : rewards.filter((reward) => reward.symbol === asset);
  const symbols = Array.from(new Set(rewards.map((reward) => reward.symbol)));
  const totals = useMemo(() => ({
    net: filtered.reduce((sum, reward) => sum.plus(decimal(reward.net_native_reward)), decimal(0)).toFixed(),
    claimableExa: filtered.reduce((sum, reward) => sum.plus(decimal(reward.exatoken_bonus_amount)), decimal(0)).toFixed(),
    claimed: filtered.filter((reward) => reward.status === "distributed").length,
    pending: filtered.filter((reward) => reward.status !== "distributed").length,
  }), [filtered]);
  return (
    <section id="staking-rewards" className={`${compact ? "mt-2" : "mt-2"} rounded-[20px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-3 shadow-[var(--exa-shadow-panel)]`}>
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="font-['Sora'] text-xl font-semibold">Reward History</h2>
          <p className="mt-1 text-sm text-[var(--exa-text-muted)]">Native rewards and EXA bonuses stay separated for audit clarity.</p>
        </div>
        <select value={asset} onChange={(event) => setAsset(event.target.value)} className="min-h-10 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm">
          <option value="all">All assets</option>
          {symbols.map((symbol) => <option key={symbol}>{symbol}</option>)}
        </select>
      </div>
      <div className="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
        <Metric label="Total rewards earned" value={formatAssetAmount(totals.net)} />
        <Metric label="Claimable EXA bonuses" value={formatAssetAmount(totals.claimableExa, "EXA")} />
        <Metric label="Distributed reward entries" value={String(totals.claimed)} />
        <Metric label="Pending reward entries" value={String(totals.pending)} />
      </div>
      <div className="mt-4 overflow-x-auto">
        {filtered.length ? (
          <table className="w-full min-w-[980px] text-left text-sm">
            <thead className="text-xs uppercase tracking-wide text-[var(--exa-text-disabled)]"><tr><th>Date</th><th>Asset</th><th>Reward type</th><th>Gross</th><th>Validator fee</th><th>Network cost</th><th>Commission</th><th>Net</th><th>ExaToken</th><th>Status</th></tr></thead>
            <tbody>{filtered.map((reward) => <tr key={reward.id} className="border-t border-[var(--exa-border)]"><td className="py-3">{formatDateTime(reward.distributed_at || reward.period_end)}</td><td>{reward.symbol}</td><td>{compareDecimal(reward.exatoken_bonus_amount || "0", "0") > 0 ? "Native + EXA" : "Native reward"}</td><td>{formatAssetAmount(reward.gross_native_reward, reward.symbol)}</td><td>{formatAssetAmount(reward.validator_fee_share, reward.symbol)}</td><td>{formatAssetAmount(reward.network_fee_share, reward.symbol)}</td><td>{formatAssetAmount(reward.platform_fee, reward.symbol)}</td><td>{formatAssetAmount(reward.net_native_reward, reward.symbol)}</td><td>{formatAssetAmount(reward.exatoken_bonus_amount, "EXA")}</td><td><StatusBadge status={reward.status} /></td></tr>)}</tbody>
          </table>
        ) : <EmptyState title="No rewards yet" body="Rewards appear only after verified blockchain or provider settlements are reconciled." />}
      </div>
    </section>
  );
}

function ExaTokenBonusSection({ campaigns }: { campaigns: ExaTokenCampaign[] }) {
  return (
    <section id="staking-campaigns" className="mt-2 rounded-[20px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-3 shadow-[var(--exa-shadow-panel)]">
      <div className="flex items-center gap-2"><Sparkles className="h-5 w-5 text-[var(--exa-gold-light)]" /><h2 className="font-['Sora'] text-xl font-semibold">Earn More with ExaToken</h2></div>
      <p className="mt-2 text-sm text-[var(--exa-text-muted)]">ExaToken bonuses are promotional rewards funded separately from native blockchain rewards.</p>
      <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        {campaigns.length ? campaigns.map((campaign) => {
          const remaining = decimal(campaign.budget_amount).minus(decimal(campaign.reserved_amount)).minus(decimal(campaign.distributed_amount));
          return <article key={campaign.id} className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4"><StatusBadge status={campaign.status} /><h3 className="mt-3 font-semibold text-white">{campaign.name}</h3><div className="mt-3 grid grid-cols-2 gap-2 text-sm"><MiniMetric label="Budget" value={formatAssetAmount(campaign.budget_amount, "EXA")} /><MiniMetric label="Remaining" value={formatAssetAmount(remaining.toFixed(), "EXA")} /><MiniMetric label="Per-user cap" value={campaign.per_user_cap ? formatAssetAmount(campaign.per_user_cap, "EXA") : "Not published"} /><MiniMetric label="Ends" value={formatDateTime(campaign.ends_at)} /></div></article>;
        }) : <div className="rounded-xl border border-dashed border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 text-sm text-[var(--exa-text-muted)]">No ExaToken bonus campaign is currently active. Standard native staking rewards remain available.</div>}
      </div>
    </section>
  );
}

function NetworkBanner({ statuses }: { statuses: { symbol: string; status: string; network: string }[] }) {
  const affected = statuses.filter((item) => !["online", "operational", "healthy"].includes(String(item.status).toLowerCase()));
  if (!affected.length) return null;
  return <div className="mt-5 rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] p-4 text-sm text-[var(--exa-gold-light)]"><div className="flex gap-2"><AlertTriangle className="mt-0.5 h-4 w-4" /><p>{affected.length} staking network status requires attention. Affected actions are disabled while historical positions remain available.</p></div></div>;
}

function RiskAndFaq({ terms }: { terms: { terms_version?: string; native_rewards_source?: string } | null }) {
  const faqs = [
    ["What is ExaEarn Staking?", "A Native PoS product area where eligible wallet assets can be delegated or bonded through backend-approved network integrations."],
    ["Is APY guaranteed?", "No. Estimated APY is variable and actual rewards depend on validator, provider, and network performance."],
    ["Can I trade staked assets?", "No. Principal moved to pending or active staking is not available for trading, transfer, conversion, or withdrawal."],
    ["What is unbonding?", "A network waiting period after unstaking before principal can be released back to available balance."],
    ["What are ExaToken bonuses?", "Separate promotional rewards paid from a funded ExaToken reserve, not from native chain rewards."],
    ["What happens if a validator is slashed?", "A verified slashing event can reduce principal or rewards, and ExaEarn must reconcile the effect before user balances change."],
  ];
  return (
    <section id="staking-learn" className="mt-2 grid gap-2">
      <div className="rounded-[20px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-3 shadow-[var(--exa-shadow-panel)]">
        <h2 className="font-['Sora'] text-xl font-semibold">Understand Staking</h2>
        <div className="mt-3 space-y-2">
          {["Rewards come from verified blockchain or approved provider settlements.", "APY changes as network conditions, validator commission, and fees change.", "Unbonding means principal may be temporarily unavailable after unstaking.", "Native rewards and ExaToken bonuses are calculated, funded, displayed, and audited separately."].map((text) => <details key={text} className="rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-3"><summary className="cursor-pointer text-sm font-semibold text-white">{text}</summary><p className="mt-2 text-sm text-[var(--exa-text-muted)]">Current terms version: {terms?.terms_version || "staking-v1"}. Source: {terms?.native_rewards_source || "verified settlements only"}.</p></details>)}
        </div>
      </div>
      <div className="rounded-[20px] border border-[var(--exa-border)] bg-[var(--exa-surface)] p-3 shadow-[var(--exa-shadow-panel)]">
        <h2 className="font-['Sora'] text-xl font-semibold">Frequently Asked Questions</h2>
        <div className="mt-3 space-y-2">{faqs.map(([q, a]) => <details key={q} className="rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-3"><summary className="cursor-pointer text-sm font-semibold text-white">{q}</summary><p className="mt-2 text-sm text-[var(--exa-text-muted)]">{a}</p></details>)}</div>
      </div>
    </section>
  );
}

function RewardExplanation({ product }: { product: StakingProduct }) {
  return (
    <div className="mt-5 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4">
      <h2 className="font-['Sora'] text-lg font-semibold">How rewards are calculated</h2>
      <ol className="mt-3 space-y-2 text-sm text-[var(--exa-text-muted)]">
        <li>1. Blockchain or approved provider produces verified {product.symbol} rewards.</li>
        <li>2. Network, validator, and provider costs are deducted when applicable.</li>
        <li>3. ExaEarn takes its disclosed commission: {formatPercent(product.platform_commission_rate)}.</li>
        <li>4. Net native rewards are distributed proportionally to eligible active stakers.</li>
        <li>5. ExaToken bonuses are paid separately from a funded reserve.</li>
      </ol>
    </div>
  );
}

function PositionTimeline({ position }: { position: StakingPosition }) {
  const steps = [
    ["Stake requested", position.opened_at],
    ["Balance reserved", position.opened_at],
    ["Delegation submitted", position.delegation_submitted_at],
    ["Network activation", position.activation_at],
    ["Rewards generated", position.total_native_net_rewards && compareDecimal(position.total_native_net_rewards, "0") > 0 ? position.activation_at : null],
    ["Unstake requested", position.status === "unstaking" || position.status === "unbonding" || position.status === "withdrawable" || position.status === "completed" ? position.unbonding_ends_at || position.opened_at : null],
    ["Unbonding", position.unbonding_ends_at],
    ["Principal released", position.completed_at],
  ];
  return <div className="mt-5 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4"><h2 className="font-['Sora'] text-lg font-semibold">Status Timeline</h2><div className="mt-3 grid gap-2">{steps.map(([label, date]) => <div key={label} className="flex items-center gap-3 text-sm"><span className={`grid h-7 w-7 place-items-center rounded-full border ${date ? "border-emerald-300/30 bg-emerald-400/10 text-emerald-100" : "border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-disabled)]"}`}>{date ? <CheckCircle2 className="h-4 w-4" /> : <Clock3 className="h-4 w-4" />}</span><span className="flex-1">{label}</span><span className="text-[var(--exa-text-muted)]">{date ? formatDateTime(String(date)) : "Pending"}</span></div>)}</div></div>;
}

function AmountInput({ symbol, value, onChange, balance, precision }: { symbol: string; value: string; onChange: (value: string) => void; balance: string; precision: number }) {
  const setPercent = (percent: number) => onChange(percentageAmount(balance, percent, precision));
  return (
    <div>
      <label className="text-sm font-semibold text-[var(--exa-text-secondary)]">Amount</label>
      <div className="mt-2 flex min-h-12 overflow-hidden rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] focus-within:ring-2 focus-within:ring-auric-300">
        <input value={value} onChange={(event) => onChange(event.target.value.replace(/[^0-9.]/g, ""))} inputMode="decimal" aria-label={`Stake amount in ${symbol}`} className="min-w-0 flex-1 bg-transparent px-3 text-base outline-none" placeholder="0.00" />
        <button type="button" onClick={() => onChange(percentageAmount(balance, 100, precision))} className="px-3 text-sm font-semibold text-[var(--exa-gold-light)]">MAX</button>
        <span className="grid place-items-center border-l border-[var(--exa-border)] px-3 text-sm text-[var(--exa-text-muted)]">{symbol}</span>
      </div>
      <div className="mt-2 grid grid-cols-4 gap-2">{[25, 50, 75, 100].map((percent) => <button key={percent} type="button" onClick={() => setPercent(percent)} className="min-h-9 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-sm">{percent}%</button>)}</div>
    </div>
  );
}

function validateStakeAmount(amount: string, product: StakingProduct, available: string): string {
  if (!amount) return "";
  if (compareDecimal(amount, "0") <= 0) return "Enter an amount greater than zero.";
  if (compareDecimal(amount, product.minimum_amount) < 0) return `Minimum stake is ${formatAssetAmount(product.minimum_amount, product.symbol)}.`;
  if (product.maximum_amount && compareDecimal(amount, product.maximum_amount) > 0) return `Maximum stake is ${formatAssetAmount(product.maximum_amount, product.symbol)}.`;
  if (compareDecimal(amount, available) > 0) return "Amount exceeds your available balance.";
  if (product.capacity) {
    const remaining = decimal(product.capacity).minus(decimal(product.total_subscribed));
    if (decimal(amount).gt(remaining)) return "Amount exceeds remaining product capacity.";
  }
  return "";
}

function ProductActionSummary({ product, availableBalance }: { product: StakingProduct; availableBalance: string }) {
  return (
    <ReviewRows
      rows={[
        ["Product", product.name],
        ["Network", product.network],
        ["Available", formatAssetAmount(availableBalance, product.symbol)],
        ["Estimated APY", formatPercent(product.displayed_apy)],
        ["Minimum", formatAssetAmount(product.minimum_amount, product.symbol)],
        ["Maximum", product.maximum_amount ? formatAssetAmount(product.maximum_amount, product.symbol) : "No backend cap"],
        ["Reward frequency", product.reward_schedule || "Verified settlements"],
        ["Lock", formatDuration(undefined, product.duration_days)],
        ["Unbonding", formatDuration(product.unbonding_period_seconds)],
        ["Native reward asset", product.symbol],
        ["ExaToken bonus", "Shown separately when campaign eligible"],
      ]}
    />
  );
}

function RewardEstimator({ amount, product, daily, monthly, annual }: { amount: string; product: StakingProduct; days: number; daily: string; monthly: string; annual: string }) {
  const maturity = amount ? decimal(amount).plus(decimal(annual || "0")).toFixed() : "0";
  return <div className="rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-3"><div className="flex items-center gap-2 text-sm font-semibold"><BarChart3 className="h-4 w-4 text-[var(--exa-gold-light)]" />Estimated reward calculator</div><div className="mt-3 grid grid-cols-2 gap-3 text-sm"><MiniMetric label="Daily" value={formatAssetAmount(daily, product.symbol)} /><MiniMetric label="Monthly" value={formatAssetAmount(monthly, product.symbol)} /><MiniMetric label="Annual" value={formatAssetAmount(annual, product.symbol)} /><MiniMetric label="Reward asset" value={product.symbol} /><MiniMetric label="Estimated maturity value" value={formatAssetAmount(maturity, product.symbol)} /><MiniMetric label="Commission" value={formatPercent(product.platform_commission_rate)} /></div><p className="mt-3 text-xs text-[var(--exa-text-muted)]">Estimates use the current displayed APY and never credit rewards. Actual rewards may be higher or lower.</p></div>;
}

function Modal({ title, children, onClose }: { title: string; children: ReactNode; onClose?: () => void }) {
  useEffect(() => {
    const handleKey = (event: KeyboardEvent) => {
      if (event.key === "Escape" && onClose) onClose();
    };
    window.addEventListener("keydown", handleKey);
    return () => window.removeEventListener("keydown", handleKey);
  }, [onClose]);
  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="staking-modal-title">
      <div className="max-h-[92dvh] w-full overflow-y-auto rounded-t-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-2xl sm:max-w-xl sm:rounded-2xl sm:p-5">
        <div className="mb-4 flex items-center justify-between gap-3"><h2 id="staking-modal-title" className="font-['Sora'] text-xl font-semibold">{title}</h2>{onClose ? <button type="button" onClick={onClose} className="grid h-10 w-10 place-items-center rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)]" aria-label="Close"><X className="h-4 w-4" /></button> : null}</div>
        {children}
      </div>
    </div>
  );
}

function useLeaveWarning(enabled: boolean) {
  useEffect(() => {
    const handler = (event: BeforeUnloadEvent) => {
      if (!enabled) return;
      event.preventDefault();
      event.returnValue = "";
    };
    window.addEventListener("beforeunload", handler);
    return () => window.removeEventListener("beforeunload", handler);
  }, [enabled]);
}

function ModalActions({ primary, secondary, onPrimary, onSecondary, primaryDisabled = false }: { primary: string; secondary: string; onPrimary: () => void; onSecondary: () => void; primaryDisabled?: boolean }) {
  return <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" onClick={onSecondary} className="min-h-11 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-4 text-sm font-semibold">{secondary}</button><button type="button" disabled={primaryDisabled} onClick={onPrimary} className="min-h-11 rounded-lg exa-button-primary px-4 text-sm font-semibold text-[var(--exa-gold-contrast)] disabled:cursor-not-allowed disabled:bg-[var(--exa-surface-hover)] disabled:text-[var(--exa-text-disabled)]">{primary}</button></div>;
}

function Toggle({ label, description, checked, disabled = false, onChange }: { label: string; description: string; checked: boolean; disabled?: boolean; onChange: (value: boolean) => void }) {
  return <button type="button" disabled={disabled} onClick={() => onChange(!checked)} className="flex w-full items-center justify-between gap-3 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-3 text-left disabled:opacity-50"><span><span className="block text-sm font-semibold">{label}</span><span className="mt-1 block text-xs text-[var(--exa-text-muted)]">{description}</span></span><span className={`h-6 w-11 rounded-full p-1 transition ${checked ? "exa-button-primary" : "bg-[var(--exa-surface-hover)]"}`}><span className={`block h-4 w-4 rounded-full bg-white transition ${checked ? "translate-x-5" : ""}`} /></span></button>;
}

function CheckRow({ checked, onChange, label }: { checked: boolean; onChange: (value: boolean) => void; label: string }) {
  return <label className="flex items-start gap-3 rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-3 text-sm text-[var(--exa-text-secondary)]"><input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="mt-1 h-4 w-4 accent-[var(--exa-gold)]" /><span>{label}</span></label>;
}

function ReviewRows({ rows }: { rows: [string, string][] }) {
  return <div className="rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-3">{rows.map(([label, value]) => <div key={label} className="flex items-start justify-between gap-4 border-b border-[var(--exa-border)] py-2 last:border-b-0"><span className="text-sm text-[var(--exa-text-muted)]">{label}</span><span className="max-w-[58%] text-right text-sm font-semibold text-white break-words">{value}</span></div>)}</div>;
}

function Metric({ label, value }: { label: string; value: string }) {
  return <div className="rounded-lg border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-3"><p className="text-xs text-[var(--exa-text-muted)]">{label}</p><p className="mt-2 break-words font-['Sora'] text-lg font-semibold text-white">{value}</p></div>;
}

function MiniMetric({ label, value }: { label: string; value: string }) {
  return <div><p className="text-xs text-[var(--exa-text-disabled)]">{label}</p><p className="mt-1 break-words font-semibold text-[var(--exa-text-primary)]">{value}</p></div>;
}

function AssetPill({ symbol, network }: { symbol: string; network: string }) {
  return <div className="flex items-center gap-2"><span className="grid h-10 w-10 place-items-center rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] font-['Sora'] text-sm font-semibold text-[var(--exa-gold-light)]">{symbol.slice(0, 3)}</span><span><span className="block font-semibold text-white">{symbol}</span><span className="block text-xs text-[var(--exa-text-muted)]">{network}</span></span></div>;
}

function Badge({ label }: { label: string }) {
  return <span className="rounded-md border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-2 py-1 text-xs text-[var(--exa-gold-light)]">{label}</span>;
}

function StatusBadge({ status }: { status: string }) {
  const key = String(status || "unknown").toLowerCase().replaceAll(" ", "_");
  return <span className={`inline-flex items-center rounded-md border px-2 py-1 text-xs capitalize ${statusTone[key] || "border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-muted)]"}`}>{normalizeStatus(status)}</span>;
}

function ActionButton({ disabled, label, reason, onClick }: { disabled: boolean; label: string; reason: string; onClick: () => void }) {
  return <button type="button" disabled={disabled} title={disabled ? reason : label} onClick={onClick} className="inline-flex min-h-10 items-center gap-1 rounded-lg exa-button-primary px-3 text-sm font-semibold text-[var(--exa-gold-contrast)] disabled:cursor-not-allowed disabled:bg-[var(--exa-surface-hover)] disabled:text-[var(--exa-text-disabled)]">{label}<ChevronRight className="h-4 w-4" /></button>;
}

function InlineError({ message }: { message: string }) {
  return <div role="alert" className="rounded-lg border border-red-300/25 bg-red-400/10 p-3 text-sm text-red-100">{message}</div>;
}

function ErrorState({ message, onRetry }: { message: string; onRetry: () => Promise<void> }) {
  return <div className="mt-5 rounded-xl border border-red-300/25 bg-red-400/10 p-4 text-sm text-red-100"><div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><span>{message}</span><button type="button" onClick={() => void onRetry()} className="min-h-10 rounded-lg border border-red-200/25 px-3">Retry</button></div></div>;
}

function EmptyState({ title, body }: { title: string; body: string }) {
  return <div className="rounded-xl border border-dashed border-[var(--exa-border)] bg-[var(--exa-surface)] p-5 text-center"><Info className="mx-auto h-5 w-5 text-[var(--exa-text-disabled)]" /><h3 className="mt-2 font-semibold text-white">{title}</h3><p className="mt-1 text-sm text-[var(--exa-text-muted)]">{body}</p></div>;
}

function SuccessState({ title, body, details = [], onClose }: { title: string; body: string; details?: [string, string][]; onClose: () => void }) {
  return <div className="text-center"><CheckCircle2 className="mx-auto h-10 w-10 text-emerald-300" /><h3 className="mt-3 font-['Sora'] text-xl font-semibold">{title}</h3><p className="mt-2 text-sm text-[var(--exa-text-muted)]">{body}</p>{details.length ? <div className="mt-5 text-left"><ReviewRows rows={details} /></div> : null}<button type="button" onClick={onClose} className="mt-5 min-h-11 rounded-lg exa-button-primary px-4 font-semibold text-[var(--exa-gold-contrast)]">Return to Staking</button></div>;
}

function SkeletonGrid() {
  return <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Loading staking data">{Array.from({ length: 8 }, (_, index) => <div key={index} className="h-24 animate-pulse rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-hover)]" />)}</div>;
}

export default StakingDashboard;

