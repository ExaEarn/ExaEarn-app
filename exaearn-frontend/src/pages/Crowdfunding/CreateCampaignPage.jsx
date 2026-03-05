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

  return (
    <main className="min-h-screen exa-bg create-campaign-page text-[#F8F8F8]">
      <div className="relative mx-auto w-full max-w-sm px-3 pb-10 pt-4 sm:max-w-lg sm:px-4 md:max-w-3xl lg:max-w-7xl">
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <div className="cc-node cc-node-a" />
          <div className="cc-node cc-node-b" />
          <div className="cc-node cc-node-c" />
          <div className="cc-grid" />
        </div>

        <section className="relative rounded-3xl border border-[#D4AF37]/30 bg-[#0B0B0B]/68 p-4 shadow-[0_22px_60px_rgba(0,0,0,0.5)] backdrop-blur-xl sm:p-6">
          <header className="rounded-2xl border border-[#D4AF37]/25 bg-[#0B0B0B]/55 p-4 sm:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="text-xs uppercase tracking-[0.25em] text-[#D4AF37]/85">ExaEarn Crowdfunding</p>
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
            </div>

            <div className="relative mt-4 overflow-hidden rounded-2xl border border-[#D4AF37]/35">
              <img src={Image.crowdfund} alt="Futuristic crowdfunding dashboard" className="h-[260px] w-full object-cover opacity-40 sm:h-[320px]" />
              <div className="absolute inset-0 bg-[linear-gradient(130deg,rgba(11,11,11,0.45),rgba(11,11,11,0.9)),radial-gradient(circle_at_20%_15%,rgba(212,175,55,0.28),transparent_45%)]" />
              <div className="absolute inset-0 p-5 sm:p-7">
                <h1 className="max-w-3xl font-['Sora'] text-3xl font-semibold leading-tight text-[#F8F8F8] sm:text-5xl">
                  <span className="text-[#D4AF37]">Turn Your Idea</span> Into Funded Reality.
                </h1>
                <p className="mt-3 max-w-2xl text-sm text-[#F8F8F8]/85 sm:text-base">
                  Launch your campaign. Raise capital globally. Build with community support.
                </p>
                <button
                  type="button"
                  onClick={scrollToWizard}
                  className="mt-5 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-5 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.35)]"
                >
                  Start Creating Campaign
                </button>
              </div>
            </div>
          </header>

          <section id="campaign-wizard" className="mt-6 grid gap-5 lg:grid-cols-[1.35fr_0.9fr]">
            <div className="rounded-2xl border border-[#D4AF37]/25 bg-[#0B0B0B]/62 p-4 sm:p-5">
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
                              ? "border-[#D4AF37] bg-[#D4AF37]/15 text-[#D4AF37]"
                              : "border-[#F8F8F8]/20 bg-[#F8F8F8]/5 text-[#F8F8F8]/70"
                          }`}
                        >
                          {stepNo}
                        </div>
                        {stepNo < steps.length ? <span className={`cc-connector ${done ? "active" : ""}`} /> : null}
                      </div>
                    );
                  })}
                </div>
                <p className="mt-3 text-sm font-semibold text-[#D4AF37]">Step {step} - {steps[step - 1]}</p>
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
                            durationDays === days ? "border-[#D4AF37] bg-[#D4AF37]/15 text-[#D4AF37]" : "border-[#F8F8F8]/20 bg-[#F8F8F8]/5 text-[#F8F8F8]/75"
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
                            fundingType === type ? "border-[#D4AF37] bg-[#D4AF37]/15 text-[#D4AF37]" : "border-[#F8F8F8]/20 bg-[#F8F8F8]/5 text-[#F8F8F8]/75"
                          }`}
                        >
                          {type}
                        </button>
                      ))}
                    </div>
                  </label>
                  <div className="rounded-xl border border-[#D4AF37]/25 bg-[#F8F8F8]/[0.03] p-3">
                    <div className="flex items-center justify-between text-xs">
                      <span>Live Goal Preview</span>
                      <span className="text-[#D4AF37]">{fundingProgress.toFixed(1)}%</span>
                    </div>
                    <div className="mt-2 h-2.5 rounded-full bg-[#F8F8F8]/12">
                      <div className="h-full rounded-full bg-gradient-to-r from-[#D4AF37] via-[#f1d57b] to-[#D4AF37]" style={{ width: `${fundingProgress}%` }} />
                    </div>
                    <p className="mt-2 text-xs text-[#F8F8F8]/75">{formatNaira(minimumContribution)} minimum against {formatNaira(fundingGoal)} goal</p>
                  </div>
                </div>
              ) : null}

              {step === 3 ? (
                <div className="grid gap-4">
                  <div className="grid gap-4 sm:grid-cols-2">
                    <label className="cc-dropzone text-sm">
                      Upload Cover Image
                      <input type="file" className="mt-3 text-xs text-[#F8F8F8]/80" />
                    </label>
                    <label className="cc-dropzone text-sm">
                      Upload Pitch Video
                      <input type="file" className="mt-3 text-xs text-[#F8F8F8]/80" />
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
                    <div key={`${reward.amount}-${index}`} className="grid gap-3 rounded-xl border border-[#F8F8F8]/15 bg-[#F8F8F8]/[0.03] p-3 sm:grid-cols-[170px_1fr]">
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
                    className="mt-1 inline-flex w-fit items-center gap-2 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-2 text-sm font-semibold text-[#0B0B0B]"
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
                      <input type="file" className="mt-3 text-xs text-[#F8F8F8]/80" />
                    </label>
                    <label className="cc-dropzone text-sm">
                      Business Registration Upload
                      <input type="file" className="mt-3 text-xs text-[#F8F8F8]/80" />
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
                  <label className="flex cursor-pointer items-center justify-between rounded-xl border border-[#F8F8F8]/15 bg-[#F8F8F8]/[0.03] px-3 py-3 text-sm">
                    Smart Contract Option
                    <button
                      type="button"
                      onClick={() => setSmartContractEnabled((value) => !value)}
                      className={`h-6 w-12 rounded-full border p-0.5 transition ${smartContractEnabled ? "border-[#D4AF37] bg-[#D4AF37]/20" : "border-[#F8F8F8]/25 bg-[#F8F8F8]/10"}`}
                    >
                      <span className={`block h-4.5 w-4.5 rounded-full bg-[#D4AF37] transition ${smartContractEnabled ? "translate-x-5" : "translate-x-0"}`} />
                    </button>
                  </label>
                  <div className="rounded-xl border border-[#D4AF37]/28 bg-[#D4AF37]/8 p-3 text-sm text-[#F8F8F8]/85">
                    All campaigns are verified and blockchain recorded for transparency.
                  </div>
                </div>
              ) : null}

              <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-[#F8F8F8]/12 pt-4">
                <button
                  type="button"
                  onClick={prev}
                  disabled={step === 1}
                  className="rounded-xl border border-[#F8F8F8]/25 bg-[#F8F8F8]/5 px-4 py-2 text-sm font-semibold text-[#F8F8F8] disabled:cursor-not-allowed disabled:opacity-45"
                >
                  Previous
                </button>
                {step < steps.length ? (
                  <button
                    type="button"
                    onClick={next}
                    className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-2 text-sm font-semibold text-[#0B0B0B]"
                  >
                    Continue
                  </button>
                ) : (
                  <button
                    type="button"
                    onClick={() => setPublished(true)}
                    className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-2 text-sm font-semibold text-[#0B0B0B]"
                  >
                    Publish Campaign
                  </button>
                )}
              </div>
              {published ? <p className="mt-3 text-sm text-[#D4AF37]">Campaign draft published successfully.</p> : null}
            </div>

            <aside className="space-y-4">
              <article className="rounded-2xl border border-[#D4AF37]/25 bg-[#0B0B0B]/62 p-4 transition duration-300 hover:border-[#D4AF37]/60 hover:shadow-[0_12px_30px_rgba(212,175,55,0.18)]">
                <p className="text-xs uppercase tracking-[0.2em] text-[#D4AF37]">Real-Time Preview</p>
                <h3 className="mt-2 font-['Sora'] text-xl font-semibold">{title.trim() || "Untitled Campaign"}</h3>
                <p className="mt-2 text-sm text-[#F8F8F8]/75">{description.trim() || "Campaign summary appears here as you type."}</p>
                <div className="mt-4 rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                  <div className="flex items-center justify-between text-xs">
                    <span>Funding Goal</span>
                    <span className="font-semibold text-[#D4AF37]">{formatNaira(fundingGoal)}</span>
                  </div>
                  <div className="mt-2 h-2.5 rounded-full bg-[#F8F8F8]/12">
                    <div className="h-full rounded-full bg-gradient-to-r from-[#D4AF37] via-[#f1d57b] to-[#D4AF37]" style={{ width: `${clamp(successProbability, 5, 100)}%` }} />
                  </div>
                </div>
                <div className="mt-3 grid gap-2 text-xs">
                  <p className="flex items-center justify-between rounded-lg border border-[#F8F8F8]/12 px-3 py-2">
                    Estimated Success Probability
                    <span className="font-semibold text-[#D4AF37]">{successProbability}%</span>
                  </p>
                  <p className="flex items-center justify-between rounded-lg border border-[#F8F8F8]/12 px-3 py-2">
                    Projected Community Reach
                    <span className="font-semibold text-[#D4AF37]">{projectedReach.toLocaleString()}</span>
                  </p>
                  <p className="flex items-center justify-between rounded-lg border border-[#F8F8F8]/12 px-3 py-2">
                    Funding Type
                    <span className="font-semibold text-[#D4AF37]">{fundingType}</span>
                  </p>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/25 bg-[#0B0B0B]/62 p-4">
                <p className="text-sm font-semibold text-[#D4AF37]">Why Launch on ExaEarn?</p>
                <div className="mt-3 grid gap-2 text-sm">
                  <p className="inline-flex items-center gap-2"><Globe2 className="h-4 w-4 text-[#D4AF37]" /> Global Investor Access</p>
                  <p className="inline-flex items-center gap-2"><Blocks className="h-4 w-4 text-[#D4AF37]" /> Web3 Smart Contract Integration</p>
                  <p className="inline-flex items-center gap-2"><FileCheck2 className="h-4 w-4 text-[#D4AF37]" /> Transparent Fund Tracking</p>
                  <p className="inline-flex items-center gap-2"><Users className="h-4 w-4 text-[#D4AF37]" /> Community Governance</p>
                  <p className="inline-flex items-center gap-2"><HandCoins className="h-4 w-4 text-[#D4AF37]" /> Cross-border Payments</p>
                </div>
              </article>
            </aside>
          </section>

          <section className="mt-7 rounded-2xl border border-[#D4AF37]/35 bg-gradient-to-r from-[#D4AF37]/14 via-[#D4AF37]/5 to-transparent p-6 text-center">
            <h2 className="font-['Sora'] text-3xl font-semibold text-[#F8F8F8] sm:text-4xl">Your Vision Deserves Funding. The World Is Ready.</h2>
            <button
              type="button"
              onClick={() => {
                setStep(5);
                scrollToWizard();
              }}
              className="mt-5 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-7 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_28px_rgba(212,175,55,0.36)]"
            >
              Publish Campaign
            </button>
            <p className="mt-2 text-xs text-[#F8F8F8]/65">By publishing, you agree to ExaEarn crowdfunding terms & policies.</p>
          </section>

          <section className="mt-6 grid gap-3 rounded-2xl border border-[#D4AF37]/22 bg-[#0B0B0B]/58 p-4 sm:grid-cols-3">
            <p className="inline-flex items-center gap-2 text-xs"><BadgeCheck className="h-4 w-4 text-[#D4AF37]" /> Investor trust architecture</p>
            <p className="inline-flex items-center gap-2 text-xs"><ShieldCheck className="h-4 w-4 text-[#D4AF37]" /> Compliance-first verification flow</p>
            <p className="inline-flex items-center gap-2 text-xs"><Wallet className="h-4 w-4 text-[#D4AF37]" /> Fiat and crypto payout rails</p>
          </section>
        </section>
      </div>
    </main>
  );
}

export default CreateCampaignPage;
