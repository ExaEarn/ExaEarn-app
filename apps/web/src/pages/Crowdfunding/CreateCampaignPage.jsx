import { useMemo, useState } from "react";
import {
  ArrowLeft,
  BadgeCheck,
  Blocks,
  FileCheck2,
  Globe2,
  HandCoins,
  Landmark,
  ShieldCheck,
  Users,
  Wallet,
} from "lucide-react";
import Image from "../../assets/Image";
import { useAuth } from "../../context/AuthContext";
import { useWeb3Wallet } from "../../hooks/useWeb3Wallet";
import { useCrowdfunding } from "../../hooks/useCrowdfunding";
import "./CreateCampaignPage.css";

const steps = [
  "Basic Information",
  "Funding Details",
  "Media & Story",
  "Rewards & Equity",
  "Review & Publish",
];

const categories = ["AgriTech", "Web3", "AI", "Fintech", "Education", "Health"];
const durations = [30, 60, 90];
const fundingTypes = ["Donation-based", "Reward-based", "Equity-based", "Tokenized (Web3)"];

function formatNaira(amount) {
  return new Intl.NumberFormat("en-NG", {
    style: "currency",
    currency: "NGN",
    maximumFractionDigits: 0,
  }).format(amount);
}

function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value));
}

function CreateCampaignPage({ onBack }) {
  const { apiBaseUrl, token } = useAuth();
  const wallet = useWeb3Wallet();
  const { createCampaignFlow, txState } = useCrowdfunding({ apiBaseUrl, token, wallet, poll: false });
  const [step, setStep] = useState(1);
  const [title, setTitle] = useState("");
  const [category, setCategory] = useState(categories[0]);
  const [description, setDescription] = useState("");
  const [country, setCountry] = useState("");
  const [founderName, setFounderName] = useState("");
  const [teamMembers, setTeamMembers] = useState("");
  const [fundingGoal, setFundingGoal] = useState(5000000);
  const [minimumContribution, setMinimumContribution] = useState(10000);
  const [durationDays, setDurationDays] = useState(60);
  const [fundingType, setFundingType] = useState(fundingTypes[1]);
  const [story, setStory] = useState("");
  const [problem, setProblem] = useState("");
  const [solution, setSolution] = useState("");
  const [marketOpportunity, setMarketOpportunity] = useState("");
  const [roadmap, setRoadmap] = useState("");
  const [walletAddress, setWalletAddress] = useState("");
  const [bankDetails, setBankDetails] = useState("");
  const [smartContractEnabled, setSmartContractEnabled] = useState(true);
  const [published, setPublished] = useState(false);
  const [publishError, setPublishError] = useState("");
  const [rewards, setRewards] = useState([
    { amount: 10000, description: "Early Supporter Badge" },
    { amount: 50000, description: "Product Early Access" },
    { amount: 100000, description: "2% Equity Token" },
    { amount: 500000, description: "Governance Voting Rights" },
  ]);

  const fundingProgress = useMemo(() => clamp((minimumContribution / Math.max(fundingGoal, 1)) * 100, 3, 100), [fundingGoal, minimumContribution]);
  const successProbability = useMemo(() => {
    const goalScore = fundingGoal <= 10000000 ? 30 : fundingGoal <= 30000000 ? 22 : 14;
    const durationScore = durationDays === 60 ? 24 : durationDays === 90 ? 16 : 20;
    const rewardScore = rewards.length >= 4 ? 24 : rewards.length >= 2 ? 17 : 10;
    const storyScore = story.length > 120 ? 24 : story.length > 50 ? 15 : 8;
    return clamp(goalScore + durationScore + rewardScore + storyScore, 20, 92);
  }, [fundingGoal, durationDays, rewards.length, story.length]);
  const projectedReach = useMemo(() => Math.round((successProbability * 130 + rewards.length * 240) * (durationDays / 30)), [successProbability, rewards.length, durationDays]);

  const next = () => setStep((current) => clamp(current + 1, 1, steps.length));
  const prev = () => setStep((current) => clamp(current - 1, 1, steps.length));
  const scrollToWizard = () => document.getElementById("campaign-wizard")?.scrollIntoView({ behavior: "smooth", block: "start" });

  const updateReward = (index, field, value) => {
    setRewards((current) => current.map((reward, idx) => (idx === index ? { ...reward, [field]: value } : reward)));
  };

  const publishCampaign = async () => {
    setPublishError("");
    try {
      if (!wallet.isConnected) {
        await wallet.connectMetaMask();
      }

      await createCampaignFlow({
        title,
        category,
        description,
        goal_amount: fundingGoal,
        minimum_contribution: minimumContribution,
        deadline_days: durationDays,
        funding_type: fundingType,
        story,
        problem,
        solution,
        market_opportunity: marketOpportunity,
        roadmap,
        rewards,
        payout_wallet: walletAddress,
        payout_bank_details: bankDetails,
        smart_contract_enabled: smartContractEnabled,
      });

      setPublished(true);
    } catch (error) {
      setPublishError(error?.message || "Failed to publish campaign.");
      setPublished(false);
    }
  };

  return (
    <main className="min-h-screen exa-bg create-campaign-page text-[var(--exa-text-primary)]">
      <div className="relative mx-auto w-full max-w-sm px-3 pb-10 pt-4 sm:max-w-lg sm:px-4 md:max-w-3xl lg:max-w-7xl">
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <div className="cc-node cc-node-a" />
          <div className="cc-node cc-node-b" />
          <div className="cc-node cc-node-c" />
          <div className="cc-grid" />
        </div>

        <section className="relative rounded-3xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)] backdrop-blur-xl sm:p-6">
          <header className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 sm:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="text-xs uppercase tracking-[0.25em] text-[var(--exa-gold)]">ExaEarn Crowdfunding</p>
              {onBack ? (
                <button
                  type="button"
                  onClick={onBack}
                  className="inline-flex items-center gap-2 rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-xs font-semibold text-[var(--exa-text-primary)] transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                >
                  <ArrowLeft className="h-4 w-4" />
                  Back
                </button>
              ) : null}
            </div>

            <div className="relative mt-4 overflow-hidden rounded-2xl border border-[var(--exa-border-active)]">
              <img src={Image.crowdfund} alt="Futuristic crowdfunding dashboard" className="h-[260px] w-full object-cover opacity-40 sm:h-[320px]" />
              <div className="absolute inset-0 bg-[linear-gradient(130deg,rgba(11,11,11,0.45),rgba(11,11,11,0.9)),radial-gradient(circle_at_20%_15%,rgba(212,175,55,0.28),transparent_45%)]" />
              <div className="absolute inset-0 p-5 sm:p-7">
                <h1 className="max-w-3xl font-['Sora'] text-3xl font-semibold leading-tight text-[var(--exa-text-primary)] sm:text-5xl">
                  <span className="text-[var(--exa-gold)]">Turn Your Idea</span> Into Funded Reality.
                </h1>
                <p className="mt-3 max-w-2xl text-sm text-[var(--exa-text-secondary)] sm:text-base">
                  Launch your campaign. Raise capital globally. Build with community support.
                </p>
                <button
                  type="button"
                  onClick={scrollToWizard}
                  className="mt-5 rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-5 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)]"
                >
                  Start Creating Campaign
                </button>
              </div>
            </div>
          </header>

          <section id="campaign-wizard" className="mt-6 grid gap-5 lg:grid-cols-[1.35fr_0.9fr]">
            <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 sm:p-5">
              <div className="mb-5">
                <div className="grid grid-cols-5 gap-2">
                  {steps.map((label, index) => {
                    const stepNo = index + 1;
                    const active = stepNo === step;
                    const done = stepNo < step;
                    return (
                      <div key={label} className="relative">
                        <div
                          className={`flex h-9 w-9 items-center justify-center rounded-full border text-xs font-semibold ${
                            done || active
                              ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold)]"
                              : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-muted)]"
                          }`}
                        >
                          {stepNo}
                        </div>
                        {stepNo < steps.length ? <span className={`cc-connector ${done ? "active" : ""}`} /> : null}
                      </div>
                    );
                  })}
                </div>
                <p className="mt-3 text-sm font-semibold text-[var(--exa-gold)]">Step {step} - {steps[step - 1]}</p>
              </div>

              {step === 1 ? (
                <div className="grid gap-4">
                  <label className="text-sm">
                    Campaign Title
                    <input value={title} onChange={(e) => setTitle(e.target.value)} className="cc-input" placeholder="Type campaign title" />
                  </label>
                  <label className="text-sm">
                    Category
                    <select value={category} onChange={(e) => setCategory(e.target.value)} className="cc-input">
                      {categories.map((option) => (
                        <option key={option} value={option}>{option}</option>
                      ))}
                    </select>
                  </label>
                  <label className="text-sm">
                    Short Description
                    <textarea value={description} onChange={(e) => setDescription(e.target.value)} className="cc-input min-h-[96px]" />
                  </label>
                  <div className="grid gap-4 sm:grid-cols-2">
                    <label className="text-sm">
                      Country / Region
                      <input value={country} onChange={(e) => setCountry(e.target.value)} className="cc-input" />
                    </label>
                    <label className="text-sm">
                      Founder Name
                      <input value={founderName} onChange={(e) => setFounderName(e.target.value)} className="cc-input" />
                    </label>
                  </div>
                  <label className="text-sm">
                    Team Members
                    <input value={teamMembers} onChange={(e) => setTeamMembers(e.target.value)} className="cc-input" placeholder="Name, role, name, role" />
                  </label>
                </div>
              ) : null}

              {step === 2 ? (
                <div className="grid gap-4">
                  <div className="grid gap-4 sm:grid-cols-2">
                    <label className="text-sm">
                      Funding Goal (NGN / USD / Crypto equiv.)
                      <input
                        type="number"
                        value={fundingGoal}
                        onChange={(e) => setFundingGoal(Math.max(1000, Number(e.target.value) || 1000))}
                        className="cc-input"
                      />
                    </label>
                    <label className="text-sm">
                      Minimum Contribution
                      <input
                        type="number"
                        value={minimumContribution}
                        onChange={(e) => setMinimumContribution(Math.max(100, Number(e.target.value) || 100))}
                        className="cc-input"
                      />
                    </label>
                  </div>
                  <label className="text-sm">
                    Campaign Duration
                    <div className="mt-2 flex flex-wrap gap-2">
                      {durations.map((days) => (
                        <button
                          key={days}
                          type="button"
                          onClick={() => setDurationDays(days)}
                          className={`rounded-lg border px-3 py-2 text-xs font-semibold transition ${
                            durationDays === days ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-secondary)]"
                          }`}
                        >
                          {days} Days
                        </button>
                      ))}
                    </div>
                  </label>
                  <label className="text-sm">
                    Funding Type
                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                      {fundingTypes.map((type) => (
                        <button
                          key={type}
                          type="button"
                          onClick={() => setFundingType(type)}
                          className={`rounded-lg border px-3 py-2 text-left text-xs font-semibold transition ${
                            fundingType === type ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-secondary)]"
                          }`}
                        >
                          {type}
                        </button>
                      ))}
                    </div>
                  </label>
                  <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3">
                    <div className="flex items-center justify-between text-xs">
                      <span>Live Goal Preview</span>
                      <span className="text-[var(--exa-gold)]">{fundingProgress.toFixed(1)}%</span>
                    </div>
                    <div className="mt-2 h-2.5 rounded-full bg-[var(--exa-surface-hover)]">
                      <div className="h-full rounded-full bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)]" style={{ width: `${fundingProgress}%` }} />
                    </div>
                    <p className="mt-2 text-xs text-[var(--exa-text-secondary)]">{formatNaira(minimumContribution)} minimum against {formatNaira(fundingGoal)} goal</p>
                  </div>
                </div>
              ) : null}

              {step === 3 ? (
                <div className="grid gap-4">
                  <div className="grid gap-4 sm:grid-cols-2">
                    <label className="cc-dropzone text-sm">
                      Upload Cover Image
                      <input type="file" className="mt-3 text-xs text-[var(--exa-text-secondary)]" />
                    </label>
                    <label className="cc-dropzone text-sm">
                      Upload Pitch Video
                      <input type="file" className="mt-3 text-xs text-[var(--exa-text-secondary)]" />
                    </label>
                  </div>
                  <label className="text-sm">
                    Campaign Story
                    <textarea value={story} onChange={(e) => setStory(e.target.value)} className="cc-input min-h-[110px]" placeholder="Rich campaign narrative..." />
                  </label>
                  <div className="grid gap-4 sm:grid-cols-2">
                    <label className="text-sm">
                      Problem Statement
                      <textarea value={problem} onChange={(e) => setProblem(e.target.value)} className="cc-input min-h-[92px]" />
                    </label>
                    <label className="text-sm">
                      Solution
                      <textarea value={solution} onChange={(e) => setSolution(e.target.value)} className="cc-input min-h-[92px]" />
                    </label>
                  </div>
                  <label className="text-sm">
                    Market Opportunity
                    <textarea value={marketOpportunity} onChange={(e) => setMarketOpportunity(e.target.value)} className="cc-input min-h-[88px]" />
                  </label>
                  <label className="text-sm">
                    Roadmap Timeline
                    <textarea value={roadmap} onChange={(e) => setRoadmap(e.target.value)} className="cc-input min-h-[88px]" />
                  </label>
                </div>
              ) : null}

              {step === 4 ? (
                <div className="grid gap-3">
                  {rewards.map((reward, index) => (
                    <div key={`${reward.amount}-${index}`} className="grid gap-3 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3 sm:grid-cols-[170px_1fr]">
                      <input
                        type="number"
                        value={reward.amount}
                        onChange={(e) => updateReward(index, "amount", Number(e.target.value) || 0)}
                        className="cc-input"
                      />
                      <input
                        value={reward.description}
                        onChange={(e) => updateReward(index, "description", e.target.value)}
                        className="cc-input"
                        placeholder="Reward or equity description"
                      />
                    </div>
                  ))}
                  <button
                    type="button"
                    onClick={() => setRewards((current) => [...current, { amount: 25000, description: "New Reward Tier" }])}
                    className="mt-1 inline-flex w-fit items-center gap-2 rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 py-2 text-sm font-semibold text-[var(--exa-gold-contrast)]"
                  >
                    + Add Reward Tier
                  </button>
                </div>
              ) : null}

              {step === 5 ? (
                <div className="grid gap-4">
                  <div className="grid gap-4 sm:grid-cols-2">
                    <label className="cc-dropzone text-sm">
                      KYC Verification Upload
                      <input type="file" className="mt-3 text-xs text-[var(--exa-text-secondary)]" />
                    </label>
                    <label className="cc-dropzone text-sm">
                      Business Registration Upload
                      <input type="file" className="mt-3 text-xs text-[var(--exa-text-secondary)]" />
                    </label>
                  </div>
                  <div className="grid gap-4 sm:grid-cols-2">
                    <label className="text-sm">
                      Wallet Address (Optional)
                      <input value={walletAddress} onChange={(e) => setWalletAddress(e.target.value)} className="cc-input" />
                    </label>
                    <label className="text-sm">
                      Bank Details (Fiat Payout)
                      <input value={bankDetails} onChange={(e) => setBankDetails(e.target.value)} className="cc-input" />
                    </label>
                  </div>
                  <label className="flex cursor-pointer items-center justify-between rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-3 text-sm">
                    Smart Contract Option
                    <button
                      type="button"
                      onClick={() => setSmartContractEnabled((value) => !value)}
                      className={`h-6 w-12 rounded-full border p-0.5 transition ${smartContractEnabled ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-hover)]"}`}
                    >
                      <span className={`block h-4.5 w-4.5 rounded-full bg-[var(--exa-gold)] transition ${smartContractEnabled ? "translate-x-5" : "translate-x-0"}`} />
                    </button>
                  </label>
                  <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-gold-surface)] p-3 text-sm text-[var(--exa-text-secondary)]">
                    All campaigns are verified and blockchain recorded for transparency.
                  </div>
                </div>
              ) : null}

              <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--exa-border)] pt-4">
                <button
                  type="button"
                  onClick={prev}
                  disabled={step === 1}
                  className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-2 text-sm font-semibold text-[var(--exa-text-primary)] disabled:cursor-not-allowed disabled:opacity-45"
                >
                  Previous
                </button>
                {step < steps.length ? (
                  <button
                    type="button"
                    onClick={next}
                    className="rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 py-2 text-sm font-semibold text-[var(--exa-gold-contrast)]"
                  >
                    Continue
                  </button>
                ) : (
                  <button
                    type="button"
                    onClick={publishCampaign}
                    className="rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 py-2 text-sm font-semibold text-[var(--exa-gold-contrast)]"
                  >
                    Publish Campaign
                  </button>
                )}
              </div>
              {published ? <p className="mt-3 text-sm text-[var(--exa-gold)]">Campaign draft published successfully.</p> : null}
              {publishError ? <p className="mt-3 text-sm text-rose-300">{publishError}</p> : null}
              {txState.status !== "idle" ? (
                <p className="mt-2 text-xs text-[var(--exa-text-secondary)]">
                  Transaction status: {txState.message}{txState.hash ? ` (${txState.hash.slice(0, 10)}...)` : ""}
                </p>
              ) : null}
            </div>

            <aside className="space-y-4">
              <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 transition duration-300 hover:border-[var(--exa-border-active)] hover:shadow-[var(--exa-shadow-gold)]">
                <p className="text-xs uppercase tracking-[0.2em] text-[var(--exa-gold)]">Real-Time Preview</p>
                <h3 className="mt-2 font-['Sora'] text-xl font-semibold">{title.trim() || "Untitled Campaign"}</h3>
                <p className="mt-2 text-sm text-[var(--exa-text-secondary)]">{description.trim() || "Campaign summary appears here as you type."}</p>
                <div className="mt-4 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3">
                  <div className="flex items-center justify-between text-xs">
                    <span>Funding Goal</span>
                    <span className="font-semibold text-[var(--exa-gold)]">{formatNaira(fundingGoal)}</span>
                  </div>
                  <div className="mt-2 h-2.5 rounded-full bg-[var(--exa-surface-hover)]">
                    <div className="h-full rounded-full bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)]" style={{ width: `${clamp(successProbability, 5, 100)}%` }} />
                  </div>
                </div>
                <div className="mt-3 grid gap-2 text-xs">
                  <p className="flex items-center justify-between rounded-lg border border-[var(--exa-border)] px-3 py-2">
                    Estimated Success Probability
                    <span className="font-semibold text-[var(--exa-gold)]">{successProbability}%</span>
                  </p>
                  <p className="flex items-center justify-between rounded-lg border border-[var(--exa-border)] px-3 py-2">
                    Projected Community Reach
                    <span className="font-semibold text-[var(--exa-gold)]">{projectedReach.toLocaleString()}</span>
                  </p>
                  <p className="flex items-center justify-between rounded-lg border border-[var(--exa-border)] px-3 py-2">
                    Funding Type
                    <span className="font-semibold text-[var(--exa-gold)]">{fundingType}</span>
                  </p>
                </div>
              </article>

              <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
                <p className="text-sm font-semibold text-[var(--exa-gold)]">Why Launch on ExaEarn?</p>
            <div className="mt-3 grid gap-2 text-sm">
              <p className="inline-flex items-center gap-2"><Wallet className="h-4 w-4 text-[var(--exa-gold)]" /> Wallet: {wallet.isConnected ? wallet.shortAddress : "Not connected"}</p>
              <p className="inline-flex items-center gap-2"><Globe2 className="h-4 w-4 text-[var(--exa-gold)]" /> Global Investor Access</p>
                  <p className="inline-flex items-center gap-2"><Blocks className="h-4 w-4 text-[var(--exa-gold)]" /> Web3 Smart Contract Integration</p>
                  <p className="inline-flex items-center gap-2"><FileCheck2 className="h-4 w-4 text-[var(--exa-gold)]" /> Transparent Fund Tracking</p>
                  <p className="inline-flex items-center gap-2"><Users className="h-4 w-4 text-[var(--exa-gold)]" /> Community Governance</p>
                  <p className="inline-flex items-center gap-2"><HandCoins className="h-4 w-4 text-[var(--exa-gold)]" /> Cross-border Payments</p>
                </div>
              </article>
            </aside>
          </section>

          <section className="mt-7 rounded-2xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-surface)] via-[var(--exa-surface-elevated)] to-transparent p-6 text-center">
            <h2 className="font-['Sora'] text-3xl font-semibold text-[var(--exa-text-primary)] sm:text-4xl">Your Vision Deserves Funding. The World Is Ready.</h2>
            <button
              type="button"
              onClick={() => {
                setStep(5);
                scrollToWizard();
              }}
              className="mt-5 rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-7 py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)]"
            >
              Publish Campaign
            </button>
            <p className="mt-2 text-xs text-[var(--exa-text-muted)]">By publishing, you agree to ExaEarn crowdfunding terms & policies.</p>
          </section>

          <section className="mt-6 grid gap-3 rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-4 sm:grid-cols-3">
            <p className="inline-flex items-center gap-2 text-xs"><BadgeCheck className="h-4 w-4 text-[var(--exa-gold)]" /> Investor trust architecture</p>
            <p className="inline-flex items-center gap-2 text-xs"><ShieldCheck className="h-4 w-4 text-[var(--exa-gold)]" /> Compliance-first verification flow</p>
            <p className="inline-flex items-center gap-2 text-xs"><Wallet className="h-4 w-4 text-[var(--exa-gold)]" /> Fiat and crypto payout rails</p>
          </section>
        </section>
      </div>
    </main>
  );
}

export default CreateCampaignPage;
