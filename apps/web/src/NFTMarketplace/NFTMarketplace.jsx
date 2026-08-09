import { useCallback, useEffect, useMemo, useState } from "react";
import { ArrowLeft, BarChart3, CreditCard, LandPlot, ShieldCheck, Sparkles, TrendingUp, WalletCards } from "lucide-react";
import { useAuth } from "../context/AuthContext";
import { buyNftListing, createNftAuction, createNftListing, fetchMyNfts, fetchNftCollections, fetchNftDashboard, fetchNftMarketplace, mintFinancialNft, subscribeToFinancialNft, upgradeFinancialNft } from "../services/nftApi";

const utilityCatalog = [
  { id: "staking", label: "Staking NFT", phase: "Phase 1", revenue: "Entry fee + staking commission" },
  { id: "boost", label: "Boost NFT", phase: "Phase 1", revenue: "Tier sales + upgrades" },
  { id: "fee", label: "Fee NFT", phase: "Phase 1", revenue: "Paid fee reduction at volume" },
  { id: "fiat_bridge", label: "Fiat Bridge NFT", phase: "Phase 2", revenue: "Withdrawal fees + spread" },
  { id: "yield_passport", label: "Yield Passport NFT", phase: "Phase 2", revenue: "Mint + subscription + upgrades" },
  { id: "access", label: "Access NFT", phase: "Phase 2", revenue: "Recurring premium access" },
  { id: "agrishare", label: "AgriShare NFT", phase: "Phase 3", revenue: "Profit share + resale fees" },
  { id: "credit_line", label: "Credit Line NFT", phase: "Phase 3", revenue: "Interest + penalties" },
  { id: "ai_portfolio", label: "AI Portfolio NFT", phase: "Phase 3", revenue: "Subscription + analytics upgrades" },
];

const fmt = (value, digits = 2) => Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: digits });

function Section({ title, subtitle, children, action }) {
  return (
    <section className="rounded-[1.75rem] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-5 backdrop-blur sm:p-6">
      <div className="mb-4 flex items-start justify-between gap-4">
        <div>
          <h2 className="font-['Sora'] text-xl font-semibold text-[var(--exa-text-primary)]">{title}</h2>
          {subtitle ? <p className="mt-1 text-sm text-[var(--exa-text-secondary)]">{subtitle}</p> : null}
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}

export default function NFTMarketplace({ onBack }) {
  const { token } = useAuth();
  const apiBaseUrl = import.meta.env.VITE_API_URL?.trim() || "";
  const [walletAddress, setWalletAddress] = useState("");
  const [mintForm, setMintForm] = useState({ utility_type: "staking", name: "", tier: "standard" });
  const [marketFilter, setMarketFilter] = useState({ utility_type: "all", phase: "all" });
  const [dashboard, setDashboard] = useState(null);
  const [marketplace, setMarketplace] = useState([]);
  const [myAssets, setMyAssets] = useState([]);
  const [collections, setCollections] = useState([]);
  const [state, setState] = useState({ loading: true, busy: false, message: "", error: "" });

  const setNotice = (message, error = "") => setState((current) => ({ ...current, message: error ? "" : message, error }));

  const loadData = useCallback(async (filters = marketFilter) => {
    setState((current) => ({ ...current, loading: true }));
    try {
      const [dash, market, mine, cols] = await Promise.all([
        fetchNftDashboard({ apiBaseUrl, token }),
        fetchNftMarketplace({ apiBaseUrl, token, params: filters }),
        fetchMyNfts({ apiBaseUrl, token }),
        fetchNftCollections({ apiBaseUrl, token }),
      ]);
      setDashboard(dash.data || null);
      setMarketplace(market.data || []);
      setMyAssets(mine.data || []);
      setCollections(cols.data || []);
      setNotice("");
    } catch (error) {
      setNotice("", error.message || "Unable to load NFT data.");
    } finally {
      setState((current) => ({ ...current, loading: false }));
    }
  }, [apiBaseUrl, marketFilter, token]);

  useEffect(() => { loadData(); }, [loadData]);

  const utilities = useMemo(() => collections.length ? collections.map((item) => ({ id: item.utility_type, label: item.name, phase: item.metadata?.phase || "Live", revenue: `${item.royalty_percentage / 100}% royalty` })) : utilityCatalog, [collections]);
  const summary = dashboard?.summary || {};
  const prompts = dashboard?.upgrade_prompts || [];

  const requireWallet = () => {
    if (!walletAddress) {
      setNotice("", "Enter a wallet address before running NFT financial actions.");
      return false;
    }
    return true;
  };

  const runAction = async (action, successMessage) => {
    setState((current) => ({ ...current, busy: true }));
    try {
      await action();
      setNotice(successMessage);
      await loadData();
    } catch (error) {
      setNotice("", error.message || "NFT action failed.");
    } finally {
      setState((current) => ({ ...current, busy: false }));
    }
  };

  const onMint = async (event) => {
    event.preventDefault();
    if (!requireWallet()) return;
    await runAction(() => mintFinancialNft({ apiBaseUrl, token, payload: { utility_type: mintForm.utility_type, name: mintForm.name, wallet_address: walletAddress, tier: mintForm.tier } }), "Financial NFT minted.");
    setMintForm((current) => ({ ...current, name: "" }));
  };

  const onUpgrade = async (asset) => {
    if (!requireWallet()) return;
    const nextTier = asset.tier === "standard" ? "pro" : "institutional";
    await runAction(() => upgradeFinancialNft({ apiBaseUrl, token, nftId: asset.id, payload: { wallet_address: walletAddress, target_tier: nextTier, target_level: Number(asset.level || 1) + 1 } }), `${asset.name} upgraded.`);
  };

  const onSubscribe = async (asset) => {
    if (!requireWallet()) return;
    await runAction(() => subscribeToFinancialNft({ apiBaseUrl, token, nftId: asset.id, payload: { wallet_address: walletAddress, plan: asset.tier === "institutional" ? "institutional" : "pro", duration_days: 30 } }), `${asset.name} subscription activated.`);
  };

  const onList = async (asset) => {
    if (!requireWallet()) return;
    const price = window.prompt(`Listing price in EXA for ${asset.name}`, asset.current_value_exa || "100");
    if (!price) return;
    await runAction(() => createNftListing({ apiBaseUrl, token, nftId: asset.id, payload: { wallet_address: walletAddress, price_exa: price } }), `${asset.name} listed.`);
  };

  const onAuction = async (asset) => {
    if (!requireWallet()) return;
    const startingPrice = window.prompt(`Auction start price in EXA for ${asset.name}`, asset.current_value_exa || "100");
    if (!startingPrice) return;
    await runAction(() => createNftAuction({ apiBaseUrl, token, nftId: asset.id, payload: { wallet_address: walletAddress, starting_price_exa: startingPrice, reserve_price_exa: startingPrice, ends_at: new Date(Date.now() + 86400000).toISOString() } }), `${asset.name} auction created.`);
  };

  const onBuy = async (asset) => {
    if (!requireWallet()) return;
    if (!asset.listing?.id) return setNotice("", "This NFT does not have an active listing.");
    await runAction(() => buyNftListing({ apiBaseUrl, token, listingId: asset.listing.id, payload: { wallet_address: walletAddress, buyer_wallet: walletAddress } }), `${asset.name} purchased.`);
  };

  return (
    <main className="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_24%),radial-gradient(circle_at_top_right,_rgba(245,158,11,0.16),_transparent_24%),linear-gradient(180deg,#06040a_0%,#100916_45%,#08050d_100%)] px-4 py-8 text-[var(--exa-text-primary)] sm:px-6 lg:px-8">
      <div className="mx-auto max-w-7xl space-y-6">
        <header className="rounded-[2rem] border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-6 sm:p-8">
          <div className="flex flex-wrap items-start justify-between gap-5">
            <div className="max-w-3xl">
              {onBack ? <button type="button" onClick={onBack} className="mb-4 inline-flex items-center gap-2 rounded-full border border-[var(--exa-border)] px-4 py-2 text-sm text-[var(--exa-text-secondary)]"><ArrowLeft className="h-4 w-4" />Back</button> : null}
              <p className="text-xs uppercase tracking-[0.35em] text-emerald-300">ExaEarn Financial NFT Engine</p>
              <h1 className="mt-3 font-['Sora'] text-4xl font-semibold tracking-tight text-[var(--exa-text-primary)] sm:text-5xl">High-utility NFTs designed to generate revenue, access, and token demand.</h1>
              <p className="mt-4 max-w-2xl text-sm leading-7 text-[var(--exa-text-muted)]">Minting, upgrades, subscriptions, secondary trading, fiat access, staking positions, agricultural exposure, and credit logic all flow through NFTs that function like financial products, not collectibles.</p>
            </div>
            <div className="w-full max-w-md rounded-3xl border border-emerald-300/20 bg-emerald-400/8 p-4">
              <label className="block text-xs font-semibold uppercase tracking-[0.28em] text-emerald-200/80">Wallet Address</label>
              <input value={walletAddress} onChange={(event) => setWalletAddress(event.target.value)} placeholder="0x..." className="mt-3 w-full rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm text-[var(--exa-text-primary)] outline-none placeholder:text-[var(--exa-text-muted)]" />
              <p className="mt-2 text-xs text-[var(--exa-text-secondary)]">Required for minting, upgrades, subscriptions, listings, and purchases.</p>
            </div>
          </div>
          {state.message ? <div className="mt-5 rounded-2xl border border-emerald-400/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{state.message}</div> : null}
          {state.error ? <div className="mt-5 rounded-2xl border border-rose-400/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">{state.error}</div> : null}
        </header>

        <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
          {[
            { label: "Assets", value: `${fmt(summary.total_assets_exa)} EXA`, tone: "bg-emerald-400/15 text-emerald-200", icon: WalletCards },
            { label: "Earnings", value: `${fmt(summary.earnings_generated_exa)} EXA`, tone: "bg-sky-400/15 text-sky-200", icon: TrendingUp },
            { label: "Fees Captured", value: `${fmt(summary.platform_fees_paid_exa)} EXA`, tone: "bg-amber-400/15 text-amber-200", icon: BarChart3 },
            { label: "Positions", value: String(summary.active_positions || 0), tone: "bg-fuchsia-400/15 text-fuchsia-200", icon: Sparkles },
            { label: "Listings", value: String(summary.active_listings || 0), tone: "bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]", icon: ShieldCheck },
          ].map((metric) => { const IconComponent = metric.icon; return (
            <div key={metric.label} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
              <div className="flex items-center gap-3">
                <div className={`flex h-11 w-11 items-center justify-center rounded-2xl ${metric.tone}`}><IconComponent className="h-5 w-5" /></div>
                <div>
                  <p className="text-xs uppercase tracking-[0.24em] text-[var(--exa-text-secondary)]">{metric.label}</p>
                  <p className="mt-1 text-xl font-semibold text-[var(--exa-text-primary)]">{metric.value}</p>
                </div>
              </div>
            </div>
          ); })}
        </section>

        <div className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
          <Section title="Revenue Utilities" subtitle="Each NFT answers the same question: how does it generate revenue?">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              {utilities.map((item) => <div key={item.id} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4"><p className="text-xs uppercase tracking-[0.24em] text-emerald-300">{item.phase}</p><h3 className="mt-2 text-lg font-semibold text-[var(--exa-text-primary)]">{item.label}</h3><p className="mt-3 text-sm text-[var(--exa-text-muted)]">Revenue engine: {item.revenue}</p></div>)}
            </div>
          </Section>

          <Section title="Mint Revenue NFT" subtitle="Use paid mints to start the utility and fee loop immediately">
            <form className="space-y-4" onSubmit={onMint}>
              <select value={mintForm.utility_type} onChange={(event) => setMintForm((current) => ({ ...current, utility_type: event.target.value }))} className="w-full rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm text-[var(--exa-text-primary)] outline-none">{utilityCatalog.map((item) => <option key={item.id} value={item.id} className="bg-[var(--exa-surface)]">{item.label}</option>)}</select>
              <input value={mintForm.name} onChange={(event) => setMintForm((current) => ({ ...current, name: event.target.value }))} placeholder="Exa Pro Yield Passport" className="w-full rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm text-[var(--exa-text-primary)] outline-none placeholder:text-[var(--exa-text-muted)]" />
              <select value={mintForm.tier} onChange={(event) => setMintForm((current) => ({ ...current, tier: event.target.value }))} className="w-full rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm text-[var(--exa-text-primary)] outline-none"><option value="standard" className="bg-[var(--exa-surface)]">Standard</option><option value="pro" className="bg-[var(--exa-surface)]">Pro</option><option value="institutional" className="bg-[var(--exa-surface)]">Institutional</option></select>
              <button type="submit" disabled={state.busy || state.loading} className="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-400 px-4 py-3 font-semibold text-slate-950 disabled:opacity-60"><Sparkles className="h-4 w-4" />{state.busy ? "Processing..." : "Mint financial NFT"}</button>
            </form>
          </Section>
        </div>

        <div className="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
          <Section title="Upgrade Prompts" subtitle="Built to nudge users toward higher-value tiers and subscriptions">
            <div className="space-y-3">
              {prompts.length ? prompts.map((prompt) => <div key={prompt} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 text-sm text-[var(--exa-text-secondary)]">{prompt}</div>) : <p className="text-sm text-[var(--exa-text-muted)]">Your current NFT stack already covers the highest-value prompts.</p>}
            </div>
          </Section>

          <Section title="Earn, Fiat, RWA, Credit" subtitle="Utility sections exposed through financial NFT ownership">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4"><div className="mb-3 flex items-center gap-2 text-emerald-200"><TrendingUp className="h-4 w-4" />Earn</div>{(dashboard?.earn?.active_positions || []).slice(0, 3).map((item) => <p key={item.nft_id} className="text-sm text-[var(--exa-text-secondary)]">NFT #{item.nft_id}: {fmt(item.staked_amount_exa)} EXA staked</p>)}{!(dashboard?.earn?.active_positions || []).length ? <p className="text-sm text-[var(--exa-text-muted)]">No active staking positions yet.</p> : null}</div>
              <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4"><div className="mb-3 flex items-center gap-2 text-sky-200"><CreditCard className="h-4 w-4" />Fiat Bridge</div>{(dashboard?.fiat_bridge?.profiles || []).slice(0, 3).map((item) => <p key={item.nft_id} className="text-sm text-[var(--exa-text-secondary)]">NFT #{item.nft_id}: ${fmt(item.daily_limit_usd)} daily limit</p>)}{!(dashboard?.fiat_bridge?.profiles || []).length ? <p className="text-sm text-[var(--exa-text-muted)]">No fiat bridge access yet.</p> : null}</div>
              <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4"><div className="mb-3 flex items-center gap-2 text-amber-200"><LandPlot className="h-4 w-4" />RWA</div>{(dashboard?.rwa_panel?.assets || []).slice(0, 3).map((item) => <p key={item.id} className="text-sm text-[var(--exa-text-secondary)]">{item.name}: {fmt(item.current_value_exa)} EXA</p>)}{!(dashboard?.rwa_panel?.assets || []).length ? <p className="text-sm text-[var(--exa-text-muted)]">No AgriShare positions yet.</p> : null}</div>
              <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4"><div className="mb-3 flex items-center gap-2 text-fuchsia-200"><ShieldCheck className="h-4 w-4" />AI + Credit</div><p className="text-sm text-[var(--exa-text-secondary)]">Premium AI access: {dashboard?.ai_insights?.premium_access ? "Enabled" : "Locked"}</p><p className="text-sm text-[var(--exa-text-secondary)]">Reports available: {dashboard?.ai_insights?.reports_available || 0}</p>{(dashboard?.credit_panel?.credit_lines || []).slice(0, 2).map((item) => <p key={item.nft_id} className="text-sm text-[var(--exa-text-secondary)]">NFT #{item.nft_id}: {fmt(item.available_credit_exa)} EXA credit</p>)}</div>
            </div>
          </Section>
        </div>

        <Section title="My Financial NFTs" subtitle="Upgrade, subscribe, list, or auction owned utility positions" action={<span className="rounded-full border border-[var(--exa-border)] px-3 py-1 text-xs text-[var(--exa-text-muted)]">{myAssets.length} owned</span>}>
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {myAssets.map((asset) => (
              <div key={asset.id} className="rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-5">
                <p className="text-xs uppercase tracking-[0.24em] text-emerald-300">{asset.utility_type.replaceAll("_", " ")}</p>
                <h3 className="mt-2 text-lg font-semibold text-[var(--exa-text-primary)]">{asset.name}</h3>
                <div className="mt-4 space-y-2 text-sm text-[var(--exa-text-secondary)]">
                  <p>Tier: <span className="text-[var(--exa-text-primary)]">{asset.tier}</span> - Level {asset.level}</p>
                  <p>Value: <span className="text-[var(--exa-text-primary)]">{fmt(asset.current_value_exa)} EXA</span></p>
                  <p>Generated: <span className="text-[var(--exa-text-primary)]">{fmt(asset.earnings_generated_exa)} EXA</span></p>
                </div>
                <div className="mt-5 flex flex-wrap gap-2">
                  <button type="button" onClick={() => onUpgrade(asset)} className="rounded-full border border-[var(--exa-border)] px-3 py-2 text-xs text-[var(--exa-text-secondary)]">Upgrade</button>
                  <button type="button" onClick={() => onSubscribe(asset)} className="rounded-full border border-emerald-300/25 px-3 py-2 text-xs text-emerald-100">Subscribe</button>
                  <button type="button" onClick={() => onList(asset)} className="rounded-full border border-amber-300/25 px-3 py-2 text-xs text-amber-100">List</button>
                  <button type="button" onClick={() => onAuction(asset)} className="rounded-full border border-sky-300/25 px-3 py-2 text-xs text-sky-100">Auction</button>
                </div>
              </div>
            ))}
            {!myAssets.length ? <p className="text-sm text-[var(--exa-text-muted)]">No financial NFTs minted yet. Start with Staking, Boost, or Fee NFTs for the fastest revenue path.</p> : null}
          </div>
        </Section>

        <Section title="Marketplace" subtitle="Secondary trading captures fees, upgrades demand, and royalty-style value" action={<div className="flex gap-2"><select value={marketFilter.utility_type} onChange={(event) => { const next = { ...marketFilter, utility_type: event.target.value }; setMarketFilter(next); loadData(next); }} className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-xs text-[var(--exa-text-secondary)]"><option value="all">All utilities</option>{utilityCatalog.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}</select><select value={marketFilter.phase} onChange={(event) => { const next = { ...marketFilter, phase: event.target.value }; setMarketFilter(next); loadData(next); }} className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-xs text-[var(--exa-text-secondary)]"><option value="all">All phases</option><option value="phase_1">Phase 1</option><option value="phase_2">Phase 2</option><option value="phase_3">Phase 3</option></select></div>}>
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {marketplace.map((asset) => (
              <div key={asset.id} className="rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-5">
                <p className="text-xs uppercase tracking-[0.24em] text-amber-300">{asset.utility_type.replaceAll("_", " ")}</p>
                <h3 className="mt-2 text-lg font-semibold text-[var(--exa-text-primary)]">{asset.name}</h3>
                <div className="mt-4 space-y-2 text-sm text-[var(--exa-text-secondary)]">
                  <p>Utility: {(asset.benefits || []).slice(0, 2).join(" - ") || "Revenue-position NFT"}</p>
                  <p>Value: <span className="text-[var(--exa-text-primary)]">{fmt(asset.current_value_exa)} EXA</span></p>
                  <p>Generated: <span className="text-[var(--exa-text-primary)]">{fmt(asset.earnings_generated_exa)} EXA</span></p>
                  <p>Listing: <span className="text-[var(--exa-text-primary)]">{asset.listing ? `${fmt(asset.listing.price_exa)} EXA` : "Not live"}</span></p>
                </div>
                <button type="button" disabled={!asset.listing?.id || state.busy} onClick={() => onBuy(asset)} className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-white px-4 py-3 font-semibold text-slate-950 disabled:opacity-50"><WalletCards className="h-4 w-4" />{asset.listing?.id ? "Buy financial NFT" : "Listing unavailable"}</button>
              </div>
            ))}
          </div>
        </Section>
      </div>
    </main>
  );
}
