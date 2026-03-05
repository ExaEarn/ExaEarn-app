import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  BadgeCheck,
  Blocks,
  CalendarClock,
  CircleCheckBig,
  Clock3,
  Flag,
  Globe2,
  HandCoins,
  PlayCircle,
  Save,
  ShieldCheck,
  Share2,
  Sparkles,
  UserCheck,
  Wallet,
} from "lucide-react";
import Image from "../../assets/Image";
import { campaignData } from "./campaignData";
import "./ViewCampaignPage.css";

function formatNaira(amount) {
  return new Intl.NumberFormat("en-NG", {
    style: "currency",
    currency: "NGN",
    maximumFractionDigits: 0,
  }).format(amount);
}

function Counter({ value }) {
  const [count, setCount] = useState(0);
  useEffect(() => {
    let start;
    let raf;
    const duration = 1100;
    const animate = (time) => {
      if (!start) start = time;
      const progress = Math.min((time - start) / duration, 1);
      setCount(Math.floor(value * progress));
      if (progress < 1) raf = requestAnimationFrame(animate);
    };
    raf = requestAnimationFrame(animate);
    return () => cancelAnimationFrame(raf);
  }, [value]);
  return <span>{count.toLocaleString()}</span>;
}

const locationById = {
  "cmp-1": "Enugu, Nigeria",
  "cmp-2": "Kaduna, Nigeria",
  "cmp-3": "Lagos, Nigeria",
  "cmp-4": "Abuja, Nigeria",
};

function ViewCampaignPage({ onBack, onSupportCampaign, campaignId }) {
  const campaign = useMemo(
    () => campaignData.find((item) => item.id === campaignId) || campaignData[0],
    [campaignId],
  );
  const [walletConnected, setWalletConnected] = useState(false);
  const [selectedTierId, setSelectedTierId] = useState(campaign.rewardTiers[0]?.id);
  const [activeMedia, setActiveMedia] = useState(0);
  const [comment, setComment] = useState("");
  const [comments, setComments] = useState([
    "Impressive roadmap and strong execution milestones.",
    "Can you publish monthly fund utilization snapshots?",
  ]);

  useEffect(() => {
    setSelectedTierId(campaign.rewardTiers[0]?.id);
    setActiveMedia(0);
  }, [campaign]);

  const media = useMemo(
    () => [
      { id: "m1", label: "Pitch Video", image: Image.crowdfund, video: true },
      { id: "m2", label: "Operations Snapshot", image: Image.campaigns },
      { id: "m3", label: "Market Presentation", image: Image.assets },
    ],
    [],
  );

  const selectedTier = campaign.rewardTiers.find((tier) => tier.id === selectedTierId) || campaign.rewardTiers[0];
  const progress = Math.min((campaign.raised / campaign.target) * 100, 100);
  const status = campaign.daysRemaining > 0 ? (progress >= 100 ? "Funded" : "Active") : "Closed";

  const credibilityScore = useMemo(() => {
    const fundingScore = progress * 0.45;
    const backerScore = Math.min(campaign.metrics.backers / 60, 30);
    const activityScore = Math.min(campaign.activity.length * 6, 15);
    const governanceBonus = selectedTier?.benefit?.toLowerCase().includes("governance") ? 8 : 4;
    return Math.min(Math.round(fundingScore + backerScore + activityScore + governanceBonus), 98);
  }, [campaign, progress, selectedTier]);

  const globalMetrics = useMemo(() => {
    const totalRaised = campaignData.reduce((sum, item) => sum + item.raised, 0);
    const totalBackers = campaignData.reduce((sum, item) => sum + item.metrics.backers, 0);
    const totalCountries = new Set(campaignData.map((item) => locationById[item.id] || item.category)).size;
    return {
      totalRaised,
      successful: campaignData.length,
      totalBackers,
      countries: totalCountries,
    };
  }, []);

  const postComment = () => {
    const trimmed = comment.trim();
    if (!trimmed) return;
    setComments((current) => [trimmed, ...current]);
    setComment("");
  };

  const teamProfiles = [
    { name: campaign.founder, role: "Founder & CEO" },
    { name: "Amina Hassan", role: "Operations Lead" },
    { name: "Victor Udeh", role: "Finance & Compliance" },
  ];

  return (
    <main className="min-h-screen exa-bg view-campaign-page text-[#F8F8F8]">
      <div className="relative mx-auto w-full max-w-sm px-3 pb-10 pt-4 sm:max-w-lg sm:px-4 md:max-w-3xl lg:max-w-7xl">
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <span className="vc-particle vc-p1" />
          <span className="vc-particle vc-p2" />
          <span className="vc-particle vc-p3" />
          <div className="vc-grid" />
        </div>

        <section className="relative rounded-3xl border border-[#D4AF37]/30 bg-[#0B0B0B]/70 p-4 shadow-[0_24px_60px_rgba(0,0,0,0.5)] backdrop-blur-xl sm:p-6">
          <header className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/55 p-4 sm:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
              {onBack ? (
                <button
                  type="button"
                  onClick={onBack}
                  className="inline-flex items-center gap-2 rounded-xl border border-[#D4AF37]/35 bg-[#0B0B0B]/65 px-3 py-2 text-xs font-semibold text-[#F8F8F8] transition hover:border-[#D4AF37] hover:text-[#D4AF37]"
                >
                  <ArrowLeft className="h-4 w-4" />
                  Back
                </button>
              ) : null}
              <button
                type="button"
                onClick={() => setWalletConnected((v) => !v)}
                className="inline-flex items-center gap-2 rounded-xl border border-[#D4AF37] bg-[#D4AF37]/15 px-3 py-2 text-xs font-semibold text-[#D4AF37]"
              >
                <Wallet className="h-4 w-4" />
                {walletConnected ? "Wallet Connected" : "Connect Wallet"}
              </button>
            </div>

            <div className="mt-4 grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
              <div className="relative overflow-hidden rounded-2xl border border-[#D4AF37]/35">
                <img src={Image.crowdfund} alt={campaign.title} className="h-[260px] w-full object-cover opacity-45 sm:h-[340px]" />
                <div className="absolute inset-0 bg-[linear-gradient(130deg,rgba(11,11,11,0.45),rgba(11,11,11,0.9)),radial-gradient(circle_at_20%_20%,rgba(212,175,55,0.24),transparent_45%)]" />
                <div className="absolute inset-0 p-5 sm:p-7">
                  <p className="inline-flex rounded-full border border-[#D4AF37]/55 bg-[#D4AF37]/14 px-3 py-1 text-xs font-semibold text-[#D4AF37]">{campaign.category}</p>
                  <h1 className="mt-3 max-w-3xl font-['Sora'] text-2xl font-semibold sm:text-4xl">{campaign.title}</h1>
                  <p className="mt-2 inline-flex items-center gap-2 text-sm text-[#F8F8F8]/86">
                    {campaign.founder}
                    <span className="inline-flex items-center gap-1 rounded-full border border-[#D4AF37]/55 bg-[#D4AF37]/12 px-2 py-0.5 text-xs text-[#D4AF37]">
                      <BadgeCheck className="h-3.5 w-3.5" />
                      Verified
                    </span>
                  </p>
                  <p className="mt-1 inline-flex items-center gap-1 text-xs text-[#F8F8F8]/75"><Flag className="h-3.5 w-3.5 text-[#D4AF37]" />{locationById[campaign.id] || "Global"}</p>
                  <div className="mt-4 grid gap-3 sm:grid-cols-3">
                    <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                      <p className="text-xs text-[#F8F8F8]/68">Funding Goal</p>
                      <p className="mt-1 font-semibold text-[#F8F8F8]">{formatNaira(campaign.target)}</p>
                    </div>
                    <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                      <p className="text-xs text-[#F8F8F8]/68">Amount Raised</p>
                      <p className="mt-1 font-semibold text-[#D4AF37]">{formatNaira(campaign.raised)}</p>
                    </div>
                    <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                      <p className="text-xs text-[#F8F8F8]/68">Days Remaining</p>
                      <p className="mt-1 inline-flex items-center gap-1 font-semibold text-[#D4AF37]"><CalendarClock className="h-4 w-4" />{campaign.daysRemaining}</p>
                    </div>
                  </div>
                  <div className="mt-3 h-2.5 rounded-full bg-[#F8F8F8]/12">
                    <div className="h-full rounded-full bg-gradient-to-r from-[#D4AF37] via-[#f0d375] to-[#D4AF37] vc-progress-fill" style={{ width: `${progress}%` }} />
                  </div>
                  <div className="mt-4 flex flex-wrap gap-3">
                    <button
                      type="button"
                      onClick={() => onSupportCampaign?.(campaign.id)}
                      className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-2 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_22px_rgba(212,175,55,0.4)]"
                    >
                      Support Campaign
                    </button>
                    <button className="inline-flex items-center gap-2 rounded-xl border border-[#D4AF37]/65 bg-transparent px-4 py-2 text-sm font-semibold text-[#D4AF37]">
                      <Share2 className="h-4 w-4" />
                      Share Campaign
                    </button>
                    <button className="inline-flex items-center gap-2 rounded-xl border border-[#F8F8F8]/35 bg-[#F8F8F8]/8 px-4 py-2 text-sm font-semibold text-[#F8F8F8]">
                      <Save className="h-4 w-4 text-[#D4AF37]" />
                      Save to Watchlist
                    </button>
                  </div>
                </div>
              </div>

              <aside className="rounded-2xl border border-[#D4AF37]/28 bg-[#0B0B0B]/68 p-4 lg:sticky lg:top-4 lg:h-fit">
                <h3 className="font-['Sora'] text-lg font-semibold">Funding Summary</h3>
                <div className="mt-3 space-y-2 text-sm">
                  <p className="vc-stat"><span>Funding Goal</span><span>{formatNaira(campaign.target)}</span></p>
                  <p className="vc-stat"><span>Amount Raised</span><span className="text-[#D4AF37]">{formatNaira(campaign.raised)}</span></p>
                  <p className="vc-stat"><span>% Funded</span><span className="text-[#D4AF37]">{progress.toFixed(1)}%</span></p>
                  <p className="vc-stat"><span>Total Backers</span><span>{campaign.metrics.backers.toLocaleString()}</span></p>
                  <p className="vc-stat"><span>Time Remaining</span><span>{campaign.daysRemaining} days</span></p>
                  <p className="vc-stat"><span>Campaign Status</span><span>{status}</span></p>
                </div>
                <div className="mt-3 rounded-xl border border-[#D4AF37]/30 bg-[#D4AF37]/10 px-3 py-2 text-xs font-semibold text-[#D4AF37]">
                  Blockchain Verified
                </div>
                <div className="mt-4 rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                  <p className="text-xs uppercase tracking-[0.12em] text-[#D4AF37]">AI Credibility Score</p>
                  <p className="mt-1 text-3xl font-semibold text-[#D4AF37]">{credibilityScore}%</p>
                  <div className="mt-2 h-2 rounded-full bg-[#F8F8F8]/12">
                    <div className="h-full rounded-full bg-gradient-to-r from-[#D4AF37] to-[#f0d375]" style={{ width: `${credibilityScore}%` }} />
                  </div>
                  <p className="mt-2 text-xs text-[#F8F8F8]/72">Model considers progress, backer momentum, governance structure, and update consistency.</p>
                </div>
              </aside>
            </div>
          </header>

          <section className="mt-6 space-y-5">
            <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
              <h2 className="font-['Sora'] text-xl font-semibold">Campaign Media</h2>
              <div className="mt-4 grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                <div className="relative overflow-hidden rounded-xl border border-[#D4AF37]/35">
                  <img src={media[activeMedia].image} alt={media[activeMedia].label} className="h-56 w-full object-cover transition duration-300 hover:scale-[1.03]" />
                  <div className="absolute inset-0 flex items-center justify-center bg-black/35">
                    <PlayCircle className="h-14 w-14 text-[#D4AF37]" />
                  </div>
                </div>
                <div className="grid grid-cols-3 gap-3">
                  {media.map((item, index) => (
                    <button
                      key={item.id}
                      type="button"
                      onClick={() => setActiveMedia(index)}
                      className={`overflow-hidden rounded-xl border ${activeMedia === index ? "border-[#D4AF37] shadow-[0_0_16px_rgba(212,175,55,0.25)]" : "border-[#F8F8F8]/16"}`}
                    >
                      <img src={item.image} alt={item.label} className="h-24 w-full object-cover transition duration-300 hover:scale-105" />
                    </button>
                  ))}
                </div>
              </div>
            </article>

            <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
              <h2 className="font-['Sora'] text-xl font-semibold">Campaign Story</h2>
              <div className="mt-4 grid gap-3 text-sm text-[#F8F8F8]/85">
                <p><span className="font-semibold text-[#D4AF37]">Problem Statement:</span> {campaign.story.problem}</p>
                <p><span className="font-semibold text-[#D4AF37]">Proposed Solution:</span> {campaign.story.solution}</p>
                <p><span className="font-semibold text-[#D4AF37]">Market Opportunity:</span> {campaign.story.market}</p>
                <p><span className="font-semibold text-[#D4AF37]">Business Model:</span> Revenue share from project milestones, service subscriptions, and structured distribution logic.</p>
              </div>
              <div className="mt-4 rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-4">
                <p className="text-sm font-semibold text-[#D4AF37]">Roadmap Timeline</p>
                <div className="mt-3 grid gap-3 md:grid-cols-4">
                  {["Seed & Setup", "Pilot Launch", "Scale Ops", "Global Expansion"].map((item, index) => (
                    <div key={item} className="relative rounded-lg border border-[#F8F8F8]/12 bg-[#0B0B0B]/55 p-3 text-xs">
                      {item}
                      {index < 3 ? <span className="vc-road-connector" /> : null}
                    </div>
                  ))}
                </div>
              </div>
              <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-4">
                  <p className="text-sm font-semibold text-[#D4AF37]">Use of Funds</p>
                  {[
                    { label: "Product & Engineering", value: 36 },
                    { label: "Operations", value: 24 },
                    { label: "Go-to-Market", value: 22 },
                    { label: "Compliance & Security", value: 18 },
                  ].map((entry) => (
                    <div key={entry.label} className="mt-2">
                      <div className="flex items-center justify-between text-xs">
                        <span>{entry.label}</span>
                        <span>{entry.value}%</span>
                      </div>
                      <div className="mt-1 h-2 rounded-full bg-[#F8F8F8]/12">
                        <div className="h-full rounded-full bg-gradient-to-r from-[#D4AF37] to-[#f0d375]" style={{ width: `${entry.value}%` }} />
                      </div>
                    </div>
                  ))}
                </div>
                <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-4">
                  <p className="text-sm font-semibold text-[#D4AF37]">Team Profiles</p>
                  <div className="mt-3 grid gap-2">
                    {teamProfiles.map((member) => (
                      <div key={member.name} className="rounded-lg border border-[#F8F8F8]/12 bg-[#0B0B0B]/55 p-3 text-xs">
                        <p className="font-semibold">{member.name}</p>
                        <p className="text-[#F8F8F8]/72">{member.role}</p>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </article>

            <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
              <h2 className="font-['Sora'] text-xl font-semibold">Rewards / Equity Tiers</h2>
              <div className="mt-4 grid gap-3 md:grid-cols-3">
                {campaign.rewardTiers.map((tier, idx) => (
                  <div
                    key={tier.id}
                    className={`rounded-xl border p-4 ${selectedTierId === tier.id ? "border-[#D4AF37]/85 bg-[#D4AF37]/12 shadow-[0_0_18px_rgba(212,175,55,0.22)]" : "border-[#F8F8F8]/14 bg-[#F8F8F8]/[0.03]"}`}
                  >
                    <p className="text-sm font-semibold">{tier.title}</p>
                    <p className="mt-1 text-base font-semibold text-[#D4AF37]">{formatNaira(tier.amount)}</p>
                    <p className="mt-2 text-xs text-[#F8F8F8]/78">{tier.benefit}</p>
                    <p className="mt-2 text-xs text-[#F8F8F8]/70">Limited Spots: {Math.max(25 - idx * 6, 8)}</p>
                    <p className="text-xs text-[#F8F8F8]/70">Est. Delivery: {90 + idx * 30} days</p>
                    <p className="text-xs text-[#F8F8F8]/70">Equity: {idx === 2 ? "2%" : idx === 1 ? "0.5%" : "N/A"}</p>
                    <button
                      type="button"
                      onClick={() => {
                        setSelectedTierId(tier.id);
                        onSupportCampaign?.(campaign.id);
                      }}
                      className="mt-3 w-full rounded-lg border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-3 py-2 text-xs font-semibold text-[#0B0B0B]"
                    >
                      Support This Tier
                    </button>
                  </div>
                ))}
              </div>
            </article>

            <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
              <h2 className="font-['Sora'] text-xl font-semibold">Transparency & Trust</h2>
              <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <p className="vc-trust"><UserCheck className="mb-2 h-4 w-4 text-[#D4AF37]" />Verified Founder Identity</p>
                <p className="vc-trust"><Blocks className="mb-2 h-4 w-4 text-[#D4AF37]" />Blockchain Recorded Contributions</p>
                <p className="vc-trust"><ShieldCheck className="mb-2 h-4 w-4 text-[#D4AF37]" />Escrow / Smart Contract Release</p>
                <p className="vc-trust"><CircleCheckBig className="mb-2 h-4 w-4 text-[#D4AF37]" />KYC Approved</p>
                <p className="vc-trust"><Flag className="mb-2 h-4 w-4 text-[#D4AF37]" />Risk Disclosure Available</p>
              </div>
            </article>

            <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
              <h2 className="font-['Sora'] text-xl font-semibold">Community & Updates</h2>
              <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <div className="space-y-2">
                  <p className="text-sm font-semibold text-[#D4AF37]">Founder Updates Timeline</p>
                  {campaign.activity.map((line) => (
                    <p key={line} className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] px-3 py-2 text-xs">{line}</p>
                  ))}
                </div>
                <div>
                  <p className="text-sm font-semibold text-[#D4AF37]">Backer Comments / Q&A</p>
                  <textarea
                    value={comment}
                    onChange={(e) => setComment(e.target.value)}
                    className="mt-2 w-full rounded-lg border border-[#F8F8F8]/20 bg-[#F8F8F8]/[0.03] p-3 text-xs outline-none focus:border-[#D4AF37]"
                    rows={4}
                    placeholder="Post a comment or question..."
                  />
                  <button
                    type="button"
                    onClick={postComment}
                    className="mt-2 rounded-lg border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-3 py-2 text-xs font-semibold text-[#0B0B0B]"
                  >
                    Post Comment
                  </button>
                  <div className="mt-2 space-y-2">
                    {comments.map((item, idx) => (
                      <p key={`${item}-${idx}`} className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2 text-xs">{item}</p>
                    ))}
                  </div>
                </div>
              </div>
            </article>

            <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
              <h2 className="font-['Sora'] text-xl font-semibold">Impact & Metrics</h2>
              <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <p className="vc-metric">Total Funds Raised on ExaEarn <span className="block text-lg font-semibold text-[#D4AF37]">{formatNaira(globalMetrics.totalRaised)}</span></p>
                <p className="vc-metric">Successful Campaigns <span className="block text-lg font-semibold text-[#D4AF37]"><Counter value={globalMetrics.successful} /></span></p>
                <p className="vc-metric">Global Backers <span className="block text-lg font-semibold text-[#D4AF37]"><Counter value={globalMetrics.totalBackers} /></span></p>
                <p className="vc-metric">Countries Participating <span className="block text-lg font-semibold text-[#D4AF37]"><Counter value={globalMetrics.countries} /></span></p>
              </div>
            </article>

            <article className="rounded-2xl border border-[#D4AF37]/35 bg-gradient-to-r from-[#D4AF37]/14 via-[#D4AF37]/6 to-transparent p-6 text-center">
              <h2 className="font-['Sora'] text-3xl font-semibold sm:text-4xl">Back Innovation. Empower Vision. Earn With Purpose.</h2>
              <button
                type="button"
                onClick={() => onSupportCampaign?.(campaign.id)}
                className="mt-4 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-6 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.36)]"
              >
                Support This Campaign Now
              </button>
              <p className="mt-2 text-xs text-[#F8F8F8]/66">Secure. Transparent. Community-Driven.</p>
            </article>

            <article className="rounded-2xl border border-[#D4AF37]/22 bg-[#0B0B0B]/58 p-4">
              <div className="grid gap-3 sm:grid-cols-3">
                <p className="inline-flex items-center gap-2 text-xs"><Sparkles className="h-4 w-4 text-[#D4AF37]" />Luxury fintech-grade UX</p>
                <p className="inline-flex items-center gap-2 text-xs"><HandCoins className="h-4 w-4 text-[#D4AF37]" />Web3 escrow-backed funding flows</p>
                <p className="inline-flex items-center gap-2 text-xs"><Globe2 className="h-4 w-4 text-[#D4AF37]" />Global startup investment access</p>
              </div>
            </article>
          </section>
        </section>
      </div>
    </main>
  );
}

export default ViewCampaignPage;
