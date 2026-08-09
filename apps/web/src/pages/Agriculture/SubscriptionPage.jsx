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
import { useAuth } from "../../context/AuthContext";
import { applyAsFarmer, fetchAgriProjects } from "../../services/agriApi";
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
    <p className="mt-2 text-3xl font-semibold text-[var(--exa-gold)] ">
      {value.toLocaleString()}
      {suffix}
    </p>
  );
}

function SubscriptionPage({ onBack }) {
  const { apiBaseUrl, token, user } = useAuth();
  const [selectedPlanId, setSelectedPlanId] = useState("growth");
  const [amount, setAmount] = useState(100000);
  const [walletConnected, setWalletConnected] = useState(false);
  const [activeProjectCount, setActiveProjectCount] = useState(0);
  const [application, setApplication] = useState({
    name: "",
    location: "",
    experienceYears: 3,
    bio: "",
    hasTractor: false,
    hasIrrigation: false,
  });
  const [applicationState, setApplicationState] = useState({ submitting: false, error: "", success: "" });

  useEffect(() => {
    let active = true;

    const loadProjects = async () => {
      try {
        const payload = await fetchAgriProjects({
          apiBaseUrl,
          token,
          params: { per_page: 50 },
        });
        if (!active) {
          return;
        }

        const list = Array.isArray(payload?.data?.data) ? payload.data.data : [];
        setActiveProjectCount(list.length);
      } catch {
        if (active) {
          setActiveProjectCount(0);
        }
      }
    };

    loadProjects();

    return () => {
      active = false;
    };
  }, [apiBaseUrl, token]);

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

  const handleFarmerApplication = async () => {
    try {
      setApplicationState({ submitting: true, error: "", success: "" });
      await applyAsFarmer({
        apiBaseUrl,
        token,
        payload: {
          name: application.name || user?.name || "",
          location: application.location,
          experience_years: Number(application.experienceYears || 0),
          bio: application.bio,
          equipment_details: {
            tractor: application.hasTractor,
            irrigation: application.hasIrrigation,
          },
        },
      });

      setApplicationState({
        submitting: false,
        error: "",
        success: "Farmer onboarding request submitted for admin review.",
      });
    } catch (error) {
      setApplicationState({
        submitting: false,
        error: error.message || "Unable to submit farmer application.",
        success: "",
      });
    }
  };

  return (
    <div className="min-h-screen text-[var(--exa-text-primary)] bg-[var(--exa-bg-primary)]  subscription-page">
      <div className="relative mx-auto w-full max-w-sm px-3 pb-10 pt-4 sm:max-w-lg sm:px-4 md:max-w-3xl lg:max-w-6xl">
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <span className="gold-particle p1" />
          <span className="gold-particle p2" />
          <span className="gold-particle p3" />
          <div className="chain-grid" />
        </div>

        <div className="relative rounded-3xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] p-4 backdrop-blur-xl sm:p-6">
          <div className="mb-6 flex items-center justify-between">
            <p className="text-xs uppercase tracking-[0.22em] text-[var(--exa-gold)]">ExaEarn Agri Subscription</p>
            {onBack ? (
              <button
                type="button"
                onClick={onBack}
                className="inline-flex items-center gap-2 rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-xs hover:border-[var(--exa-border-active)]"
              >
                <ArrowLeft className="h-4 w-4" />
                Back
              </button>
            ) : null}
          </div>

          <section className="relative overflow-hidden rounded-3xl border border-[var(--exa-border-active)]">
            <img src={Image.agriculture} alt="Agriculture landscape" className="h-[330px] w-full object-cover opacity-45 sm:h-[380px]" />
            <div className="absolute inset-0 bg-[linear-gradient(125deg,rgba(11,11,11,0.42),rgba(11,11,11,0.9)),radial-gradient(circle_at_20%_20%,rgba(212,175,55,0.24),transparent_45%)]" />
            <div className="absolute inset-0 p-5 sm:p-7">
              <span className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-1 text-xs text-[var(--exa-gold)]">
                <Landmark className="h-4 w-4" />
                Blockchain-backed ownership
              </span>
              <h1 className="mt-4 max-w-3xl font-['Sora'] text-3xl font-semibold leading-tight sm:text-5xl">
                <span className="text-[var(--exa-gold)]">Subscribe to Agriculture.</span> Earn While the Land Grows.
              </h1>
              <p className="mt-4 max-w-2xl text-sm text-[var(--exa-text-secondary)] sm:text-base">
                Join verified agricultural projects. Earn structured returns. Empower farmers. Agriculture meets Web3 finance.
              </p>
              <p className="mt-3 text-xs text-[var(--exa-text-secondary)]">{activeProjectCount} live projects are currently listed in the Agri marketplace.</p>
              <div className="mt-6 flex flex-wrap gap-3">
                <button
                  type="button"
                  onClick={scrollPlans}
                  className="rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-5 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)]"
                >
                  Subscribe Now
                </button>
                <button
                  type="button"
                  onClick={scrollPlans}
                  className="rounded-xl border border-[var(--exa-border-active)] bg-transparent px-5 py-3 text-sm font-semibold text-[var(--exa-gold)]"
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
                  className={`rounded-2xl border bg-[var(--exa-surface-elevated)] p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-[var(--exa-shadow-soft)] ${
                    plan.popular ? "border-[var(--exa-border-active)] shadow-[var(--exa-shadow-gold)]" : "border-[var(--exa-border)]"
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <h3 className="font-['Sora'] text-lg">{plan.title}</h3>
                    {plan.popular ? <span className="rounded-full border border-[var(--exa-border-active)] px-2 py-1 text-[10px] text-[var(--exa-gold)]">Most Popular</span> : null}
                  </div>
                  <p className="mt-2 text-sm text-[var(--exa-text-secondary)]">Minimum: {formatCurrency(plan.minNgn)} / ${plan.minUsd}</p>
                  <p className="mt-1 text-sm text-[var(--exa-text-secondary)]">Duration: {plan.duration}</p>
                  <p className="mt-2 text-xl font-semibold text-[var(--exa-gold)]">ROI {plan.roiMin}% - {plan.roiMax}%</p>
                  <p className="mt-3 text-sm text-[var(--exa-text-secondary)]">{plan.payout}</p>
                  <p className="mt-1 text-sm text-[var(--exa-text-secondary)]">{plan.extra}</p>
                  <button
                    type="button"
                    onClick={() => setSelectedPlanId(plan.id)}
                    className="mt-4 w-full rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 py-2 text-sm font-semibold text-[var(--exa-gold-contrast)]"
                  >
                    Subscribe Now
                  </button>
                </article>
              ))}
            </div>
          </section>

          <section className="mt-8 rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] p-5">
            <h2 className="inline-flex items-center gap-2 font-['Sora'] text-xl font-semibold text-[var(--exa-gold)]">
              <CalendarClock className="h-5 w-5" />
              Smart ROI Preview Calculator
            </h2>
            <div className="mt-4 grid gap-4 lg:grid-cols-2">
              <div className="space-y-4 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                <label className="block text-sm">
                  Investment Amount (NGN)
                  <input
                    type="number"
                    min={selectedPlan.minNgn}
                    value={amount}
                    onChange={(event) => setAmount(Math.max(selectedPlan.minNgn, Number(event.target.value) || selectedPlan.minNgn))}
                    className="mt-2 w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 outline-none focus:border-[var(--exa-border-active)]"
                  />
                </label>
                <label className="block text-sm">
                  Subscription Plan
                  <select
                    value={selectedPlanId}
                    onChange={(event) => setSelectedPlanId(event.target.value)}
                    className="mt-2 w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 outline-none focus:border-[var(--exa-border-active)]"
                  >
                    {plans.map((plan) => (
                      <option key={plan.id} value={plan.id}>{plan.title}</option>
                    ))}
                  </select>
                </label>
              </div>
              <div className="grid grid-cols-2 gap-3 rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] p-4">
                <div className="rounded-lg border border-[var(--exa-border)] p-3">
                  <p className="text-xs text-[var(--exa-text-secondary)]">Projected Earnings</p>
                  <p className="mt-2 font-semibold text-[var(--exa-gold)]">{formatCurrency(preview.earnings)}</p>
                </div>
                <div className="rounded-lg border border-[var(--exa-border)] p-3">
                  <p className="text-xs text-[var(--exa-text-secondary)]">Total Return</p>
                  <p className="mt-2 font-semibold text-[var(--exa-gold)]">{formatCurrency(preview.totalReturn)}</p>
                </div>
                <div className="rounded-lg border border-[var(--exa-border)] p-3">
                  <p className="text-xs text-[var(--exa-text-secondary)]">Payout Schedule</p>
                  <p className="mt-2 text-sm">{preview.payoutCount} payments of {formatCurrency(preview.payoutEach)}</p>
                </div>
                <div className="rounded-lg border border-[var(--exa-border)] p-3">
                  <p className="text-xs text-[var(--exa-text-secondary)]">Maturity Date</p>
                  <p className="mt-2 text-sm">{preview.maturity}</p>
                </div>
              </div>
            </div>
          </section>

          <section className="mt-8">
            <h2 className="font-['Sora'] text-2xl font-semibold">Secure & Transparent Investment</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {transparency.map((item) => (
                <article key={item.title} className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                  <item.icon className="h-5 w-5 text-[var(--exa-gold)]" />
                  <p className="mt-3 text-sm">{item.title}</p>
                </article>
              ))}
            </div>
          </section>

          <section className="mt-8 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-5">
            <h2 className="font-['Sora'] text-2xl font-semibold">Community Impact Dashboard</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {impact.map((item) => (
                <article key={item.label} className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                  <p className="text-xs uppercase tracking-[0.12em] text-[var(--exa-text-secondary)]">{item.label}</p>
                  <Counter target={item.target} suffix={item.suffix} />
                </article>
              ))}
            </div>
          </section>

          <section className="mt-8 rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] p-5">
            <h2 className="font-['Sora'] text-2xl font-semibold">Web3 Payment Integration</h2>
            <div className="mt-4 grid gap-4 lg:grid-cols-2">
              <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                <div className="grid gap-3 sm:grid-cols-2">
                  <button
                    type="button"
                    onClick={() => setWalletConnected((v) => !v)}
                    className="rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] to-[var(--exa-gold-light)] px-4 py-2 text-sm font-semibold text-[var(--exa-gold-contrast)]"
                  >
                    <span className="inline-flex items-center gap-2"><Wallet className="h-4 w-4" /> {walletConnected ? "Wallet Connected" : "Connect Wallet"}</span>
                  </button>
                  <button type="button" className="rounded-xl border border-[var(--exa-border-active)] px-4 py-2 text-sm text-[var(--exa-gold)]">Pay with Crypto</button>
                  <button type="button" className="rounded-xl border border-[var(--exa-border-active)] px-4 py-2 text-sm text-[var(--exa-gold)]">Pay with Local Currency</button>
                  <button type="button" className="rounded-xl border border-[var(--exa-border-active)] px-4 py-2 text-sm text-[var(--exa-gold)]">Installment Option</button>
                </div>
              </div>
              <div className="eth-motion rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] p-4">
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

          <section className="mt-8 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-5">
            <h2 className="font-['Sora'] text-2xl font-semibold">Farmer Onboarding</h2>
            <p className="mt-2 text-sm text-[var(--exa-text-secondary)]">
              Apply as a verified farmer to access leased farm capital and publish production updates.
            </p>
            <div className="mt-4 grid gap-4 lg:grid-cols-2">
              <label className="block text-sm text-[var(--exa-text-secondary)]">
                Full Name
                <input
                  type="text"
                  value={application.name}
                  onChange={(event) => setApplication((current) => ({ ...current, name: event.target.value }))}
                  className="mt-2 w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 outline-none focus:border-[var(--exa-border-active)]"
                />
              </label>
              <label className="block text-sm text-[var(--exa-text-secondary)]">
                Farm Location
                <input
                  type="text"
                  value={application.location}
                  onChange={(event) => setApplication((current) => ({ ...current, location: event.target.value }))}
                  className="mt-2 w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 outline-none focus:border-[var(--exa-border-active)]"
                />
              </label>
              <label className="block text-sm text-[var(--exa-text-secondary)]">
                Experience Years
                <input
                  type="number"
                  min={0}
                  value={application.experienceYears}
                  onChange={(event) => setApplication((current) => ({ ...current, experienceYears: event.target.value }))}
                  className="mt-2 w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 outline-none focus:border-[var(--exa-border-active)]"
                />
              </label>
              <label className="block text-sm text-[var(--exa-text-secondary)]">
                Equipment Summary
                <div className="mt-2 grid gap-2 rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] p-3">
                  <label className="inline-flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      checked={application.hasTractor}
                      onChange={(event) => setApplication((current) => ({ ...current, hasTractor: event.target.checked }))}
                    />
                    Tractor access
                  </label>
                  <label className="inline-flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      checked={application.hasIrrigation}
                      onChange={(event) =>
                        setApplication((current) => ({ ...current, hasIrrigation: event.target.checked }))
                      }
                    />
                    Irrigation support
                  </label>
                </div>
              </label>
              <label className="block text-sm text-[var(--exa-text-secondary)] lg:col-span-2">
                Farming Bio
                <textarea
                  rows={4}
                  value={application.bio}
                  onChange={(event) => setApplication((current) => ({ ...current, bio: event.target.value }))}
                  className="mt-2 w-full rounded-lg border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 outline-none focus:border-[var(--exa-border-active)]"
                />
              </label>
            </div>
            <div className="mt-4 flex flex-wrap items-center gap-3">
              <button
                type="button"
                onClick={handleFarmerApplication}
                disabled={applicationState.submitting}
                className="rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-5 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] disabled:cursor-not-allowed disabled:opacity-60"
              >
                {applicationState.submitting ? "Submitting..." : "Apply as Farmer"}
              </button>
              {applicationState.success ? <p className="text-sm text-emerald-300">{applicationState.success}</p> : null}
              {applicationState.error ? <p className="text-sm text-rose-300">{applicationState.error}</p> : null}
            </div>
          </section>

          <section className="mt-8">
            <h2 className="font-['Sora'] text-2xl font-semibold">How Subscription Works</h2>
            <div className="mt-4 grid gap-3 md:grid-cols-5">
              {["Create Account", "Choose Plan", "Subscribe", "Track Farm Growth", "Receive Harvest Returns"].map((step, index, arr) => (
                <article key={step} className="relative rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4">
                  <p className="text-xs text-[var(--exa-gold)]">{index + 1}</p>
                  <p className="mt-2 text-sm font-semibold">{step}</p>
                  {index < arr.length - 1 ? <span className="timeline-link" /> : null}
                </article>
              ))}
            </div>
          </section>

          <section className="mt-8 rounded-2xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-surface)] via-[var(--exa-surface-elevated)] to-transparent p-6 text-center">
            <span className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-1 text-xs text-[var(--exa-gold)]">
              <Sprout className="h-4 w-4" />
              Wealth + Sustainability
            </span>
            <h2 className="mt-4 font-['Sora'] text-3xl font-semibold sm:text-4xl">Grow Your Wealth While Growing Communities.</h2>
            <button
              type="button"
              onClick={scrollPlans}
              className="mt-5 rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-6 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)]"
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
