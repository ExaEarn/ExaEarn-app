import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  BadgeCheck,
  Blocks,
  CalendarClock,
  Landmark,
  Link2,
  ShieldCheck,
  Sprout,
  Wallet,
} from "lucide-react";
import Image from "../../assets/Image";
import "./SubscriptionPage.css";

const plans = [
  {
    id: "starter",
    title: "Starter Plan",
    minNgn: 25000,
    minUsd: 25,
    duration: "6 Months",
    durationMonths: 6,
    roiMin: 12,
    roiMax: 15,
    payout: "Quarterly Updates",
    extra: "Community Access",
  },
  {
    id: "growth",
    title: "Growth Plan",
    minNgn: 100000,
    minUsd: 100,
    duration: "9-12 Months",
    durationMonths: 12,
    roiMin: 18,
    roiMax: 22,
    payout: "Priority Harvest Payout",
    extra: "NFT Ownership Certificate",
    popular: true,
  },
  {
    id: "premium",
    title: "Premium Farm Partner Plan",
    minNgn: 500000,
    minUsd: 500,
    duration: "12-18 Months",
    durationMonths: 18,
    roiMin: 25,
    roiMax: 30,
    payout: "Direct Farm Impact Reports",
    extra: "Governance Voting + VIP Badge",
  },
];

const transparency = [
  { icon: Blocks, title: "Blockchain-backed transactions" },
  { icon: ShieldCheck, title: "Smart contract distribution" },
  { icon: BadgeCheck, title: "Verified agricultural partners" },
  { icon: Link2, title: "Real-time farm tracking" },
];

const impact = [
  { label: "Farmers Empowered", target: 18420, suffix: "+" },
  { label: "Acres Cultivated", target: 92750, suffix: "+" },
  { label: "Foreign Investors", target: 1320, suffix: "+" },
  { label: "Jobs Created", target: 4870, suffix: "+" },
];

function formatCurrency(value) {
  return new Intl.NumberFormat("en-NG", {
    style: "currency",
    currency: "NGN",
    maximumFractionDigits: 0,
  }).format(value);
}

function Counter({ target, suffix }) {
  const [value, setValue] = useState(0);

  useEffect(() => {
    let start = 0;
    let raf;
    const duration = 1200;
    const step = (t) => {
      if (!start) start = t;
      const progress = Math.min((t - start) / duration, 1);
      setValue(Math.floor(progress * target));
      if (progress < 1) raf = requestAnimationFrame(step);
    };
    raf = requestAnimationFrame(step);
    return () => cancelAnimationFrame(raf);
  }, [target]);

  return (
    <p className="mt-2 text-3xl font-semibold text-[#D4AF37] drop-shadow-[0_0_12px_rgba(212,175,55,0.35)]">
      {value.toLocaleString()}
      {suffix}
    </p>
  );
}

function SubscriptionPage({ onBack }) {
  const [selectedPlanId, setSelectedPlanId] = useState("growth");
  const [amount, setAmount] = useState(100000);
  const [walletConnected, setWalletConnected] = useState(false);

  const selectedPlan = useMemo(
    () => plans.find((plan) => plan.id === selectedPlanId) || plans[1],
    [selectedPlanId],
  );

  const preview = useMemo(() => {
    const roiMid = (selectedPlan.roiMin + selectedPlan.roiMax) / 2;
    const earnings = amount * (roiMid / 100);
    const payoutCount = Math.max(1, Math.floor(selectedPlan.durationMonths / 3));
    const payoutEach = earnings / payoutCount;
    const maturityDate = new Date();
    maturityDate.setMonth(maturityDate.getMonth() + selectedPlan.durationMonths);

    return {
      earnings,
      totalReturn: amount + earnings,
      payoutCount,
      payoutEach,
      maturity: maturityDate.toLocaleDateString("en-NG", { day: "numeric", month: "long", year: "numeric" }),
    };
  }, [amount, selectedPlan]);

  const scrollPlans = () => document.getElementById("plans")?.scrollIntoView({ behavior: "smooth" });

  return (
    <div className="min-h-screen text-[#F8F8F8] exa-bg app-shell subscription-page">
      <div className="relative mx-auto w-full max-w-sm px-3 pb-10 pt-4 sm:max-w-lg sm:px-4 md:max-w-3xl lg:max-w-6xl">
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <span className="gold-particle p1" />
          <span className="gold-particle p2" />
          <span className="gold-particle p3" />
          <div className="chain-grid" />
        </div>

        <div className="relative rounded-3xl border border-[#D4AF37]/30 bg-[#0B0B0B]/70 p-4 backdrop-blur-xl sm:p-6">
          <div className="mb-6 flex items-center justify-between">
            <p className="text-xs uppercase tracking-[0.22em] text-[#D4AF37]/85">ExaEarn Agri Subscription</p>
            {onBack ? (
              <button
                type="button"
                onClick={onBack}
                className="inline-flex items-center gap-2 rounded-xl border border-[#D4AF37]/40 bg-[#0B0B0B]/70 px-3 py-2 text-xs hover:border-[#D4AF37]"
              >
                <ArrowLeft className="h-4 w-4" />
                Back
              </button>
            ) : null}
          </div>

          <section className="relative overflow-hidden rounded-3xl border border-[#D4AF37]/35">
            <img src={Image.agriculture} alt="Agriculture landscape" className="h-[330px] w-full object-cover opacity-45 sm:h-[380px]" />
            <div className="absolute inset-0 bg-[linear-gradient(125deg,rgba(11,11,11,0.42),rgba(11,11,11,0.9)),radial-gradient(circle_at_20%_20%,rgba(212,175,55,0.24),transparent_45%)]" />
            <div className="absolute inset-0 p-5 sm:p-7">
              <span className="inline-flex items-center gap-2 rounded-full border border-[#D4AF37]/50 bg-[#0B0B0B]/55 px-3 py-1 text-xs text-[#D4AF37]">
                <Landmark className="h-4 w-4" />
                Blockchain-backed ownership
              </span>
              <h1 className="mt-4 max-w-3xl font-['Sora'] text-3xl font-semibold leading-tight sm:text-5xl">
                <span className="text-[#D4AF37]">Subscribe to Agriculture.</span> Earn While the Land Grows.
              </h1>
              <p className="mt-4 max-w-2xl text-sm text-[#F8F8F8]/85 sm:text-base">
                Join verified agricultural projects. Earn structured returns. Empower farmers. Agriculture meets Web3 finance.
              </p>
              <div className="mt-6 flex flex-wrap gap-3">
                <button
                  type="button"
                  onClick={scrollPlans}
                  className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#bc8e1f] px-5 py-3 text-sm font-semibold text-[#0B0B0B]"
                >
                  Subscribe Now
                </button>
                <button
                  type="button"
                  onClick={scrollPlans}
                  className="rounded-xl border border-[#D4AF37]/70 bg-transparent px-5 py-3 text-sm font-semibold text-[#D4AF37]"
                >
                  Explore Active Farms
                </button>
              </div>
            </div>
          </section>

          <section id="plans" className="mt-8">
            <h2 className="font-['Sora'] text-2xl font-semibold">Subscription Plans</h2>
            <div className="mt-4 grid gap-4 lg:grid-cols-3">
              {plans.map((plan) => (
                <article
                  key={plan.id}
                  className={`rounded-2xl border bg-[#0B0B0B]/70 p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_16px_30px_rgba(0,0,0,0.38)] ${
                    plan.popular ? "border-[#D4AF37]/85 shadow-[0_0_0_1px_rgba(212,175,55,0.5),0_0_28px_rgba(212,175,55,0.25)]" : "border-[#D4AF37]/25"
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <h3 className="font-['Sora'] text-lg">{plan.title}</h3>
                    {plan.popular ? <span className="rounded-full border border-[#D4AF37]/75 px-2 py-1 text-[10px] text-[#D4AF37]">Most Popular</span> : null}
                  </div>
                  <p className="mt-2 text-sm text-[#F8F8F8]/75">Minimum: {formatCurrency(plan.minNgn)} / ${plan.minUsd}</p>
                  <p className="mt-1 text-sm text-[#F8F8F8]/75">Duration: {plan.duration}</p>
                  <p className="mt-2 text-xl font-semibold text-[#D4AF37]">ROI {plan.roiMin}% - {plan.roiMax}%</p>
                  <p className="mt-3 text-sm text-[#F8F8F8]/75">{plan.payout}</p>
                  <p className="mt-1 text-sm text-[#F8F8F8]/75">{plan.extra}</p>
                  <button
                    type="button"
                    onClick={() => setSelectedPlanId(plan.id)}
                    className="mt-4 w-full rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#bc8e1f] px-4 py-2 text-sm font-semibold text-[#0B0B0B]"
                  >
                    Subscribe Now
                  </button>
                </article>
              ))}
            </div>
          </section>

          <section className="mt-8 rounded-2xl border border-[#D4AF37]/30 bg-[#F8F8F8]/[0.03] p-5">
            <h2 className="inline-flex items-center gap-2 font-['Sora'] text-xl font-semibold text-[#D4AF37]">
              <CalendarClock className="h-5 w-5" />
              Smart ROI Preview Calculator
            </h2>
            <div className="mt-4 grid gap-4 lg:grid-cols-2">
              <div className="space-y-4 rounded-xl border border-[#F8F8F8]/15 bg-[#0B0B0B]/65 p-4">
                <label className="block text-sm">
                  Investment Amount (NGN)
                  <input
                    type="number"
                    min={selectedPlan.minNgn}
                    value={amount}
                    onChange={(event) => setAmount(Math.max(selectedPlan.minNgn, Number(event.target.value) || selectedPlan.minNgn))}
                    className="mt-2 w-full rounded-lg border border-[#D4AF37]/35 bg-[#0B0B0B] px-3 py-2 outline-none focus:border-[#D4AF37]"
                  />
                </label>
                <label className="block text-sm">
                  Subscription Plan
                  <select
                    value={selectedPlanId}
                    onChange={(event) => setSelectedPlanId(event.target.value)}
                    className="mt-2 w-full rounded-lg border border-[#D4AF37]/35 bg-[#0B0B0B] px-3 py-2 outline-none focus:border-[#D4AF37]"
                  >
                    {plans.map((plan) => (
                      <option key={plan.id} value={plan.id}>{plan.title}</option>
                    ))}
                  </select>
                </label>
              </div>
              <div className="grid grid-cols-2 gap-3 rounded-xl border border-[#D4AF37]/30 bg-[#0B0B0B]/65 p-4">
                <div className="rounded-lg border border-[#F8F8F8]/10 p-3">
                  <p className="text-xs text-[#F8F8F8]/70">Projected Earnings</p>
                  <p className="mt-2 font-semibold text-[#D4AF37]">{formatCurrency(preview.earnings)}</p>
                </div>
                <div className="rounded-lg border border-[#F8F8F8]/10 p-3">
                  <p className="text-xs text-[#F8F8F8]/70">Total Return</p>
                  <p className="mt-2 font-semibold text-[#D4AF37]">{formatCurrency(preview.totalReturn)}</p>
                </div>
                <div className="rounded-lg border border-[#F8F8F8]/10 p-3">
                  <p className="text-xs text-[#F8F8F8]/70">Payout Schedule</p>
                  <p className="mt-2 text-sm">{preview.payoutCount} payments of {formatCurrency(preview.payoutEach)}</p>
                </div>
                <div className="rounded-lg border border-[#F8F8F8]/10 p-3">
                  <p className="text-xs text-[#F8F8F8]/70">Maturity Date</p>
                  <p className="mt-2 text-sm">{preview.maturity}</p>
                </div>
              </div>
            </div>
          </section>

          <section className="mt-8">
            <h2 className="font-['Sora'] text-2xl font-semibold">Secure & Transparent Investment</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {transparency.map((item) => (
                <article key={item.title} className="rounded-xl border border-[#D4AF37]/25 bg-[#F8F8F8]/[0.03] p-4">
                  <item.icon className="h-5 w-5 text-[#D4AF37]" />
                  <p className="mt-3 text-sm">{item.title}</p>
                </article>
              ))}
            </div>
          </section>

          <section className="mt-8 rounded-2xl border border-[#D4AF37]/25 bg-[#0B0B0B]/70 p-5">
            <h2 className="font-['Sora'] text-2xl font-semibold">Community Impact Dashboard</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {impact.map((item) => (
                <article key={item.label} className="rounded-xl border border-[#F8F8F8]/10 bg-[#F8F8F8]/[0.03] p-4">
                  <p className="text-xs uppercase tracking-[0.12em] text-[#F8F8F8]/70">{item.label}</p>
                  <Counter target={item.target} suffix={item.suffix} />
                </article>
              ))}
            </div>
          </section>

          <section className="mt-8 rounded-2xl border border-[#D4AF37]/30 bg-[#F8F8F8]/[0.03] p-5">
            <h2 className="font-['Sora'] text-2xl font-semibold">Web3 Payment Integration</h2>
            <div className="mt-4 grid gap-4 lg:grid-cols-2">
              <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#0B0B0B]/65 p-4">
                <div className="grid gap-3 sm:grid-cols-2">
                  <button
                    type="button"
                    onClick={() => setWalletConnected((v) => !v)}
                    className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] to-[#be9020] px-4 py-2 text-sm font-semibold text-[#0B0B0B]"
                  >
                    <span className="inline-flex items-center gap-2"><Wallet className="h-4 w-4" /> {walletConnected ? "Wallet Connected" : "Connect Wallet"}</span>
                  </button>
                  <button type="button" className="rounded-xl border border-[#D4AF37]/70 px-4 py-2 text-sm text-[#D4AF37]">Pay with Crypto</button>
                  <button type="button" className="rounded-xl border border-[#D4AF37]/70 px-4 py-2 text-sm text-[#D4AF37]">Pay with Local Currency</button>
                  <button type="button" className="rounded-xl border border-[#D4AF37]/70 px-4 py-2 text-sm text-[#D4AF37]">Installment Option</button>
                </div>
              </div>
              <div className="eth-motion rounded-xl border border-[#D4AF37]/30 bg-[#0B0B0B]/65 p-4">
                <span className="node n1" />
                <span className="node n2" />
                <span className="node n3" />
                <span className="node n4" />
                <span className="link l1" />
                <span className="link l2" />
                <span className="link l3" />
              </div>
            </div>
          </section>

          <section className="mt-8">
            <h2 className="font-['Sora'] text-2xl font-semibold">How Subscription Works</h2>
            <div className="mt-4 grid gap-3 md:grid-cols-5">
              {["Create Account", "Choose Plan", "Subscribe", "Track Farm Growth", "Receive Harvest Returns"].map((step, index, arr) => (
                <article key={step} className="relative rounded-xl border border-[#D4AF37]/25 bg-[#F8F8F8]/[0.03] p-4">
                  <p className="text-xs text-[#D4AF37]">{index + 1}</p>
                  <p className="mt-2 text-sm font-semibold">{step}</p>
                  {index < arr.length - 1 ? <span className="timeline-link" /> : null}
                </article>
              ))}
            </div>
          </section>

          <section className="mt-8 rounded-2xl border border-[#D4AF37]/40 bg-gradient-to-r from-[#D4AF37]/15 via-[#D4AF37]/5 to-transparent p-6 text-center">
            <span className="inline-flex items-center gap-2 rounded-full border border-[#D4AF37]/55 bg-[#0B0B0B]/65 px-3 py-1 text-xs text-[#D4AF37]">
              <Sprout className="h-4 w-4" />
              Wealth + Sustainability
            </span>
            <h2 className="mt-4 font-['Sora'] text-3xl font-semibold sm:text-4xl">Grow Your Wealth While Growing Communities.</h2>
            <button
              type="button"
              onClick={scrollPlans}
              className="mt-5 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#bc8e1f] px-6 py-3 text-sm font-semibold text-[#0B0B0B]"
            >
              Subscribe Today
            </button>
          </section>
        </div>
      </div>
    </div>
  );
}

export default SubscriptionPage;
