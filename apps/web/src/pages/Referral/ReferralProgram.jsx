import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  CheckCircle2,
  Copy,
  ExternalLink,
  Gift,
  Link as LinkIcon,
  MousePointerClick,
  QrCode,
  Share2,
  ShieldCheck,
  Sparkles,
  Users,
  Wallet,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import { affiliateApi } from "../../services/affiliateApi";

const periods = [
  { value: "today", label: "Today" },
  { value: "7d", label: "7D" },
  { value: "30d", label: "30D" },
  { value: "90d", label: "90D" },
  { value: "all", label: "All Time" },
];

const tabs = ["Overview", "Referrals", "Earnings", "Referral Tools", "Payouts", "Program / Tiers"];

export default function ReferralProgram({ onBack }) {
  const { request, user } = useAuth();
  const [activeTab, setActiveTab] = useState("Overview");
  const [period, setPeriod] = useState("30d");
  const [overview, setOverview] = useState(null);
  const [referrals, setReferrals] = useState([]);
  const [earnings, setEarnings] = useState([]);
  const [tools, setTools] = useState(null);
  const [payouts, setPayouts] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState("");
  const [copyState, setCopyState] = useState(false);
  const [payoutState, setPayoutState] = useState({ busy: false, message: "", error: "" });

  const loadAffiliate = async ({ silent = false } = {}) => {
    if (!request) return;
    if (silent) setRefreshing(true); else setLoading(true);
    setError("");

    try {
      const [overviewData, referralData, earningsData, toolData, payoutData] = await Promise.all([
        affiliateApi.overview(request, period),
        affiliateApi.referrals(request, { per_page: 12 }),
        affiliateApi.earnings(request, { per_page: 12 }),
        affiliateApi.tools(request),
        affiliateApi.payouts(request),
      ]);

      setOverview(overviewData);
      setReferrals(referralData?.data ?? []);
      setEarnings(earningsData?.data ?? []);
      setTools(toolData);
      setPayouts(payoutData);
    } catch (loadError) {
      setError(loadError?.message || "Unable to load the Affiliate Program right now.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    loadAffiliate();
  }, [period]);

  const stats = overview?.stats ?? {};
  const funnel = overview?.funnel ?? {};
  const profile = overview?.profile ?? {};
  const referralLink = tools?.referral_link || profile?.referral_link || "";
  const payoutAsset = payouts?.summary?.asset || profile?.payout_asset || "EXA";

  const metricCards = useMemo(
    () => [
      { label: "Total Clicks", value: stats.total_clicks ?? "--", icon: MousePointerClick },
      { label: "Total Signups", value: stats.total_signups ?? "--", icon: Users },
      { label: "Active Subscribers", value: stats.active_subscribers ?? "--", icon: CheckCircle2 },
      { label: "Lifetime Earnings", value: formatAsset(stats.lifetime_earnings, payoutAsset), icon: Gift, accent: true },
      { label: "Withdrawable", value: formatAsset(stats.withdrawable_earnings, payoutAsset), icon: Wallet, accent: true },
      { label: "Conversion Rate", value: formatPercent(stats.conversion_rate), icon: Sparkles },
    ],
    [stats, payoutAsset]
  );

  const copyLink = async () => {
    if (!referralLink) return;
    try {
      await navigator.clipboard.writeText(referralLink);
      setCopyState(true);
      setTimeout(() => setCopyState(false), 1800);
    } catch {
      setCopyState(false);
    }
  };

  const requestPayout = async () => {
    const amount = payouts?.summary?.withdrawable;
    if (!amount || Number(amount) <= 0) return;
    setPayoutState({ busy: true, message: "", error: "" });
    try {
      const result = await affiliateApi.requestPayout(request, { amount, asset: payoutAsset });
      setPayoutState({ busy: false, message: `Payout ${result.request_uuid} completed.`, error: "" });
      await loadAffiliate({ silent: true });
    } catch (requestError) {
      setPayoutState({ busy: false, message: "", error: requestError?.message || "Unable to request payout." });
    }
  };

  return (
    <main className="min-h-screen bg-[#070B14] text-white">
      <section className="border-b border-white/10 bg-[radial-gradient(circle_at_top_right,_rgba(212,175,55,0.16),_transparent_35%),linear-gradient(180deg,#101827_0%,#0A0F1D_100%)] px-4 py-5 sm:px-6">
        <div className="mx-auto w-full max-w-7xl">
          <button type="button" onClick={onBack} className="mb-4 inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-[#D8E0EE] hover:border-[#D4AF37]/45">
            <ArrowLeft className="h-4 w-4" /> Back
          </button>
          <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div className="max-w-3xl">
              <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-[#D4AF37]/30 bg-[#D4AF37]/10 px-3 py-1 text-xs font-medium text-[#F3D88F]">
                <ShieldCheck className="h-3.5 w-3.5" /> Affiliate Program
              </div>
              <h1 className="text-3xl font-semibold tracking-tight text-[#F8F1DE] sm:text-4xl">ExaEarn Affiliate</h1>
              <p className="mt-2 max-w-2xl text-sm text-[#AEB7C7] sm:text-base">
                Earn rewards when eligible users join through your referral and purchase qualifying ExaEarn products.
              </p>
            </div>
            <div className="grid gap-3 rounded-2xl border border-white/10 bg-[#0C1424]/80 p-4 sm:grid-cols-2">
              <InfoPill label="Affiliate Code" value={profile.affiliate_code || user?.referral_code || "--"} />
              <InfoPill label="Tier" value={profile.tier_name || "Starter"} />
              <InfoPill label="Commission Rate" value={formatBps(profile.commission_rate_bps)} />
              <InfoPill label="Payout Asset" value={payoutAsset} />
            </div>
          </div>
        </div>
      </section>

      <div className="mx-auto flex w-full max-w-7xl flex-col gap-5 px-4 py-5 sm:px-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap gap-2">
            {tabs.map((tab) => (
              <button key={tab} type="button" onClick={() => setActiveTab(tab)} className={`rounded-full px-3 py-2 text-sm ${activeTab === tab ? "bg-[#D4AF37] text-[#111827]" : "border border-white/10 bg-white/5 text-[#C9D3E3]"}`}>
                {tab}
              </button>
            ))}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {periods.map((item) => (
              <button key={item.value} type="button" onClick={() => setPeriod(item.value)} className={`rounded-lg px-3 py-2 text-xs ${period === item.value ? "bg-[#1F2A3D] text-[#F3D88F]" : "bg-white/5 text-[#9DA8BA]"}`}>
                {item.label}
              </button>
            ))}
            <button type="button" onClick={() => loadAffiliate({ silent: true })} className="rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-[#D8E0EE]">
              {refreshing ? "Refreshing..." : "Refresh"}
            </button>
          </div>
        </div>

        {error ? <ErrorState message={error} onRetry={() => loadAffiliate()} /> : null}

        {loading ? (
          <div className="grid gap-4 lg:grid-cols-3">
            {Array.from({ length: 6 }).map((_, index) => <div key={index} className="h-28 animate-pulse rounded-2xl border border-white/10 bg-white/5" />)}
          </div>
        ) : (
          <>
            <section className="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
              <div className="rounded-3xl border border-white/10 bg-[#101827]/80 p-4">
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                  {metricCards.map((item) => (
                    <MetricCard key={item.label} {...item} />
                  ))}
                </div>
              </div>
              <div className="rounded-3xl border border-white/10 bg-[#101827]/80 p-4">
                <div className="mb-3 flex items-center gap-2 text-sm font-medium text-[#F8F1DE]">
                  <Sparkles className="h-4 w-4 text-[#D4AF37]" /> Performance Funnel
                </div>
                <div className="space-y-3">
                  <FunnelRow label="Link Clicks" value={funnel.clicks} />
                  <FunnelRow label="Signups" value={funnel.signups} />
                  <FunnelRow label="Eligible Users" value={funnel.eligible_users} />
                  <FunnelRow label="Plan Purchases" value={funnel.plan_purchases} />
                  <FunnelRow label="Commission Earned" value={formatAsset(funnel.commission_earned, payoutAsset)} accent />
                </div>
              </div>
            </section>

            <section className="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
              <div className="rounded-3xl border border-white/10 bg-[#101827]/80 p-4">
                {activeTab === "Overview" ? <OverviewPanel referralLink={referralLink} tools={tools} copyLink={copyLink} copied={copyState} payoutAsset={payoutAsset} /> : null}
                {activeTab === "Referrals" ? <ReferralsTable items={referrals} /> : null}
                {activeTab === "Earnings" ? <EarningsTable items={earnings} payoutAsset={payoutAsset} /> : null}
                {activeTab === "Referral Tools" ? <ToolsPanel tools={tools} referralLink={referralLink} copyLink={copyLink} copied={copyState} /> : null}
                {activeTab === "Payouts" ? <PayoutPanel payouts={payouts} payoutAsset={payoutAsset} payoutState={payoutState} onRequest={requestPayout} /> : null}
                {activeTab === "Program / Tiers" ? <ProgramPanel profile={profile} /> : null}
              </div>

              <aside className="space-y-4">
                <div className="rounded-3xl border border-white/10 bg-[#101827]/80 p-4">
                  <div className="mb-3 flex items-center gap-2 text-sm font-medium text-[#F8F1DE]">
                    <QrCode className="h-4 w-4 text-[#D4AF37]" /> Share Tools
                  </div>
                  <PseudoQr value={referralLink || profile.affiliate_code || "EXAEARN"} />
                  <p className="mt-3 text-xs text-[#9DA8BA]">Use your affiliate link in posts, campaigns, and QR placements. Attribution remains server-verified.</p>
                </div>
                <div className="rounded-3xl border border-[#D4AF37]/20 bg-[#D4AF37]/10 p-4">
                  <div className="mb-2 flex items-center gap-2 text-sm font-medium text-[#F3D88F]"><ShieldCheck className="h-4 w-4" /> Program Guardrails</div>
                  <ul className="space-y-2 text-sm text-[#D7DDEA]">
                    <li>Only eligible product purchases create commission.</li>
                    <li>No MLM, no downlines, no deposit-based rewards.</li>
                    <li>Suspicious attribution and self-referrals can be disqualified.</li>
                    <li>Payouts remain tied to the authenticated ExaEarn account.</li>
                  </ul>
                </div>
              </aside>
            </section>
          </>
        )}
      </div>
    </main>
  );
}

function OverviewPanel({ referralLink, tools, copyLink, copied, payoutAsset }) {
  return (
    <div>
      <SectionTitle title="Overview" subtitle="Your program link, positioning, and qualifying reward flow." />
      <div className="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
        <div className="rounded-2xl border border-white/10 bg-[#0B1322] p-4">
          <p className="text-xs uppercase tracking-[0.14em] text-[#8A96AA]">Referral Link</p>
          <p className="mt-2 break-all text-sm text-[#E5EBF5]">{referralLink || "Not available yet."}</p>
          <div className="mt-4 flex flex-wrap gap-2">
            <button type="button" onClick={copyLink} className="inline-flex items-center gap-2 rounded-xl bg-[#D4AF37] px-4 py-2 text-sm font-medium text-[#111827]"><Copy className="h-4 w-4" /> {copied ? "Copied" : "Copy Link"}</button>
            {referralLink ? <a href={referralLink} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2 text-sm text-[#D8E0EE]"><ExternalLink className="h-4 w-4" /> Open Signup</a> : null}
          </div>
        </div>
        <div className="rounded-2xl border border-white/10 bg-[#0B1322] p-4">
          <p className="text-xs uppercase tracking-[0.14em] text-[#8A96AA]">Reward Basis</p>
          <p className="mt-2 text-sm text-[#E5EBF5]">Commission is created only after eligible ExaAI subscriptions are successfully purchased and verified.</p>
          <p className="mt-3 text-sm text-[#9DA8BA]">Primary payout asset: {payoutAsset}. Share copy: {tools?.share_copy || "Available after profile creation."}</p>
        </div>
      </div>
    </div>
  );
}

function ReferralsTable({ items }) {
  return (
    <div>
      <SectionTitle title="Referrals" subtitle="Privacy-safe view of referred users and their qualification state." />
      <SimpleTable
        columns={["Referral", "Joined", "Status", "Product", "Plan", "Commission"]}
        rows={items.map((item) => [item.referral, formatDate(item.joined_date), item.status, item.eligible_product, item.plan || "--", item.commission_status || "--"])}
        emptyLabel="No referrals have been attributed yet."
      />
    </div>
  );
}

function EarningsTable({ items, payoutAsset }) {
  return (
    <div>
      <SectionTitle title="Earnings" subtitle="Commission-level record of approved and paid affiliate rewards." />
      <SimpleTable
        columns={["Date", "Referral", "Product", "Plan", "Purchase", "Commission", "Status"]}
        rows={items.map((item) => [formatDate(item.date), item.referral, item.product, item.plan || "--", formatAsset(item.purchase_amount, payoutAsset), formatAsset(item.commission_amount, payoutAsset), item.status])}
        emptyLabel="No earnings have been generated yet."
      />
    </div>
  );
}

function ToolsPanel({ tools, referralLink, copyLink, copied }) {
  const shareText = encodeURIComponent(tools?.share_copy || "Join ExaEarn through my affiliate link.");
  const link = encodeURIComponent(referralLink || "");
  return (
    <div>
      <SectionTitle title="Referral Tools" subtitle="Use your link, QR, and social share actions without leaving the dashboard." />
      <div className="space-y-4 rounded-2xl border border-white/10 bg-[#0B1322] p-4">
        <p className="break-all text-sm text-[#E5EBF5]">{referralLink || "No affiliate link available."}</p>
        <div className="flex flex-wrap gap-2">
          <button type="button" onClick={copyLink} className="inline-flex items-center gap-2 rounded-xl bg-[#D4AF37] px-4 py-2 text-sm font-medium text-[#111827]"><Copy className="h-4 w-4" /> {copied ? "Copied" : "Copy"}</button>
          <a href={`https://wa.me/?text=${shareText}%20${link}`} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2 text-sm text-[#D8E0EE]"><Share2 className="h-4 w-4" /> WhatsApp</a>
          <a href={`https://t.me/share/url?url=${link}&text=${shareText}`} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2 text-sm text-[#D8E0EE]"><LinkIcon className="h-4 w-4" /> Telegram</a>
        </div>
      </div>
    </div>
  );
}

function PayoutPanel({ payouts, payoutAsset, payoutState, onRequest }) {
  const withdrawable = payouts?.summary?.withdrawable || "0";
  const paid = payouts?.summary?.paid || "0";
  const items = payouts?.items || [];
  return (
    <div>
      <SectionTitle title="Payouts" subtitle="Withdrawable affiliate rewards and payout request history." />
      <div className="grid gap-4 lg:grid-cols-2">
        <div className="rounded-2xl border border-white/10 bg-[#0B1322] p-4">
          <p className="text-xs uppercase tracking-[0.14em] text-[#8A96AA]">Withdrawable Earnings</p>
          <p className="mt-2 text-2xl font-semibold text-[#F3D88F]">{formatAsset(withdrawable, payoutAsset)}</p>
          <p className="mt-2 text-sm text-[#9DA8BA]">Already paid: {formatAsset(paid, payoutAsset)}</p>
          <button type="button" onClick={onRequest} disabled={payoutState.busy || Number(withdrawable || 0) <= 0} className="mt-4 inline-flex items-center gap-2 rounded-xl bg-[#D4AF37] px-4 py-2 text-sm font-medium text-[#111827] disabled:cursor-not-allowed disabled:opacity-50">
            {payoutState.busy ? "Processing..." : "Withdraw Available Earnings"}
          </button>
          {payoutState.message ? <p className="mt-3 text-sm text-[#86EFAC]">{payoutState.message}</p> : null}
          {payoutState.error ? <p className="mt-3 text-sm text-[#FCA5A5]">{payoutState.error}</p> : null}
        </div>
        <SimpleTable columns={["Request", "Amount", "Status", "Date"]} rows={items.map((item) => [item.request_uuid, formatAsset(item.amount, item.asset || payoutAsset), item.status, formatDate(item.requested_at)])} emptyLabel="No payout requests yet." compact />
      </div>
    </div>
  );
}

function ProgramPanel({ profile }) {
  return (
    <div>
      <SectionTitle title="Program / Tiers" subtitle="Current program rules and the tier visible on your affiliate profile." />
      <div className="grid gap-4 md:grid-cols-2">
        <div className="rounded-2xl border border-white/10 bg-[#0B1322] p-4">
          <p className="text-sm font-medium text-[#F8F1DE]">Current Tier</p>
          <p className="mt-2 text-2xl font-semibold text-[#F3D88F]">{profile.tier_name || "Starter"}</p>
          <p className="mt-2 text-sm text-[#9DA8BA]">Base commission rate: {formatBps(profile.commission_rate_bps)} on qualifying ExaAI subscriptions.</p>
        </div>
        <div className="rounded-2xl border border-white/10 bg-[#0B1322] p-4">
          <p className="text-sm font-medium text-[#F8F1DE]">Program Principles</p>
          <ul className="mt-3 space-y-2 text-sm text-[#D7DDEA]">
            <li>Commission comes from approved product purchases.</li>
            <li>Referral attribution is verified server-side.</li>
            <li>Disqualified, cancelled, or refunded activity does not earn commission.</li>
            <li>No recruitment chains or deposit-based payout mechanics.</li>
          </ul>
        </div>
      </div>
    </div>
  );
}

function SectionTitle({ title, subtitle }) {
  return (
    <div className="mb-4">
      <h2 className="text-lg font-semibold text-[#F8F1DE]">{title}</h2>
      <p className="mt-1 text-sm text-[#98A1B2]">{subtitle}</p>
    </div>
  );
}

function MetricCard({ label, value, icon: Icon, accent = false }) {
  return (
    <article className="rounded-2xl border border-white/10 bg-[#0B1322] p-4">
      <div className={`mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl ${accent ? "bg-[#D4AF37]/15 text-[#D4AF37]" : "bg-white/10 text-[#D8E0EE]"}`}><Icon className="h-4 w-4" /></div>
      <p className="text-xs uppercase tracking-[0.12em] text-[#8A96AA]">{label}</p>
      <p className={`mt-2 text-xl font-semibold ${accent ? "text-[#F3D88F]" : "text-[#F4F7FB]"}`}>{value}</p>
    </article>
  );
}

function InfoPill({ label, value }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/5 px-3 py-2">
      <p className="text-[11px] uppercase tracking-[0.12em] text-[#8A96AA]">{label}</p>
      <p className="mt-1 text-sm font-medium text-[#F4F7FB]">{value}</p>
    </div>
  );
}

function FunnelRow({ label, value, accent = false }) {
  return (
    <div className="flex items-center justify-between rounded-2xl border border-white/10 bg-[#0B1322] px-4 py-3">
      <span className="text-sm text-[#B7C2D6]">{label}</span>
      <span className={`text-sm font-semibold ${accent ? "text-[#F3D88F]" : "text-[#F4F7FB]"}`}>{value ?? "--"}</span>
    </div>
  );
}

function SimpleTable({ columns, rows, emptyLabel, compact = false }) {
  return (
    <div className={`overflow-hidden rounded-2xl border border-white/10 bg-[#0B1322] ${compact ? "p-0" : "p-0"}`}>
      <div className="overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="bg-white/5 text-[#95A3BA]">
            <tr>{columns.map((column) => <th key={column} className="px-4 py-3 font-medium">{column}</th>)}</tr>
          </thead>
          <tbody>
            {rows.length ? rows.map((row, index) => (
              <tr key={index} className="border-t border-white/10 text-[#E4EAF5]">{row.map((cell, cellIndex) => <td key={cellIndex} className="px-4 py-3 align-top">{cell}</td>)}</tr>
            )) : <tr><td colSpan={columns.length} className="px-4 py-8 text-center text-sm text-[#8D9AB0]">{emptyLabel}</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function ErrorState({ message, onRetry }) {
  return (
    <div className="rounded-2xl border border-[#FCA5A5]/30 bg-[#3A1016]/30 p-4 text-sm text-[#FBC4C4]">
      <p>{message}</p>
      <button type="button" onClick={onRetry} className="mt-3 rounded-xl border border-white/10 px-3 py-2 text-xs text-white">Retry</button>
    </div>
  );
}

function PseudoQr({ value }) {
  const seed = Array.from(value || "EXAEARN").reduce((acc, char) => acc + char.charCodeAt(0), 0);
  const cells = Array.from({ length: 121 }).map((_, index) => ((seed * (index + 17)) % 7) < 3);
  return (
    <div className="mx-auto grid w-full max-w-[220px] grid-cols-11 gap-1 rounded-2xl border border-white/10 bg-white p-3">
      {cells.map((active, index) => <div key={index} className={`aspect-square rounded-[2px] ${active ? "bg-black" : "bg-white"}`} />)}
    </div>
  );
}

function formatAsset(value, asset = "EXA") {
  if (value === undefined || value === null || value === "") return "--";
  const number = Number(value);
  if (Number.isNaN(number)) return `${value} ${asset}`;
  return `${number.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 8 })} ${asset}`;
}

function formatPercent(value) {
  if (value === undefined || value === null || value === "") return "--";
  const number = Number(value);
  return `${number.toFixed(2)}%`;
}

function formatBps(value) {
  if (value === undefined || value === null || value === "") return "--";
  return `${(Number(value) / 100).toFixed(2)}%`;
}

function formatDate(value) {
  if (!value) return "--";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "--";
  return date.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });
}
