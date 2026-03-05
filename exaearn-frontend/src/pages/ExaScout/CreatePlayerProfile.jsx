import { useMemo, useState } from "react";
import {
  ArrowLeft,
  BadgeCheck,
  Blocks,
  CircleUserRound,
  FileBadge2,
  LockKeyhole,
  ShieldCheck,
  Sparkles,
  Wallet,
} from "lucide-react";
import Image from "../../assets/Image";
import "./CreatePlayerProfile.css";

const steps = [
  "Basic Information",
  "Physical Attributes",
  "Performance Statistics",
  "Media Upload",
  "Verification & NFT",
];

const positions = ["Goalkeeper", "Defender", "Midfielder", "Winger", "Striker"];
const nationalities = ["Nigeria", "Ghana", "Morocco", "Colombia", "Pakistan", "Kenya", "South Africa"];

function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value));
}

function CreatePlayerProfile({ onBack }) {
  const [activeStep, setActiveStep] = useState(1);
  const [walletConnected, setWalletConnected] = useState(false);
  const [mintProfile, setMintProfile] = useState(true);

  const [form, setForm] = useState({
    fullName: "",
    nickname: "",
    dob: "",
    nationality: nationalities[0],
    club: "",
    position: positions[2],
    jersey: "10",
    height: "178",
    weight: "72",
    dominantFoot: "Right",
    speed: 78,
    strength: 74,
    stamina: 80,
    matches: "28",
    goals: "14",
    assists: "9",
    cleanSheets: "0",
    yellowCards: "3",
    redCards: "0",
  });

  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));

  const completedFields = useMemo(() => {
    const required = ["fullName", "dob", "club", "position", "height", "weight", "matches", "goals", "assists"];
    const count = required.filter((key) => String(form[key]).trim().length > 0).length;
    return Math.round((count / required.length) * 100);
  }, [form]);

  const aiRating = useMemo(() => {
    const matches = Number(form.matches) || 0;
    const goals = Number(form.goals) || 0;
    const assists = Number(form.assists) || 0;
    const disciplinePenalty = (Number(form.yellowCards) || 0) * 0.8 + (Number(form.redCards) || 0) * 2.2;

    const physicalScore = form.speed * 0.36 + form.strength * 0.28 + form.stamina * 0.36;
    const outputScore = clamp((goals * 2.8 + assists * 1.9 + matches * 0.5) - disciplinePenalty, 0, 100);
    const score = clamp(Math.round(physicalScore * 0.55 + outputScore * 0.45), 45, 99);
    return score;
  }, [form]);

  const statSummary = useMemo(
    () => [
      { label: "Matches", value: form.matches || "0" },
      { label: "Goals", value: form.goals || "0" },
      { label: "Assists", value: form.assists || "0" },
      { label: "Rating", value: aiRating },
    ],
    [form, aiRating],
  );

  return (
    <main className="min-h-screen exa-bg create-player-page text-[#F8F8F8]">
      <div className="relative mx-auto w-full max-w-sm px-3 pb-10 pt-4 sm:max-w-lg sm:px-4 md:max-w-3xl lg:max-w-7xl">
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <span className="cpp-orb cpp-orb-a" />
          <span className="cpp-orb cpp-orb-b" />
          <div className="cpp-grid" />
        </div>

        <section className="relative rounded-3xl border border-[#D4AF37]/30 bg-[#0B0B0B]/70 p-4 shadow-[0_24px_58px_rgba(0,0,0,0.5)] backdrop-blur-xl sm:p-6">
          <header className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-6">
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
              <button className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-2 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.3)]">
                Save & Continue
              </button>
            </div>

            <h1 className="mt-4 font-['Sora'] text-3xl font-semibold sm:text-4xl">Create Your Professional Player Profile</h1>
            <p className="mt-2 text-sm text-[#F8F8F8]/76 sm:text-base">Showcase your skills. Track performance. Get discovered globally.</p>

            <div className="mt-4">
              <div className="flex flex-wrap gap-2">
                {steps.map((step, index) => {
                  const num = index + 1;
                  return (
                    <button
                      key={step}
                      type="button"
                      onClick={() => setActiveStep(num)}
                      className={`rounded-lg border px-3 py-2 text-xs font-semibold ${
                        activeStep === num ? "border-[#D4AF37] bg-[#D4AF37]/14 text-[#D4AF37]" : "border-[#F8F8F8]/20 bg-[#F8F8F8]/5 text-[#F8F8F8]/72"
                      }`}
                    >
                      {num}. {step}
                    </button>
                  );
                })}
              </div>
              <div className="mt-3 h-2 rounded-full bg-[#F8F8F8]/12">
                <div className="h-full rounded-full bg-gradient-to-r from-[#D4AF37] to-[#f0d375] transition-all duration-500" style={{ width: `${(activeStep / steps.length) * 100}%` }} />
              </div>
            </div>
          </header>

          <section className="mt-6 grid gap-5 lg:grid-cols-[1.25fr_0.85fr]">
            <div className="space-y-5">
              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 sm:p-5">
                {activeStep === 1 ? (
                  <div>
                    <h2 className="font-['Sora'] text-xl font-semibold">Basic Details</h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                      <label className="text-sm">Full Name<input className="cpp-input" value={form.fullName} onChange={(e) => updateField("fullName", e.target.value)} /></label>
                      <label className="text-sm">Nickname<input className="cpp-input" value={form.nickname} onChange={(e) => updateField("nickname", e.target.value)} /></label>
                      <label className="text-sm">Date of Birth<input type="date" className="cpp-input" value={form.dob} onChange={(e) => updateField("dob", e.target.value)} /></label>
                      <label className="text-sm">Nationality<select className="cpp-input" value={form.nationality} onChange={(e) => updateField("nationality", e.target.value)}>{nationalities.map((n) => <option key={n}>{n}</option>)}</select></label>
                      <label className="text-sm">Current Club<input className="cpp-input" value={form.club} onChange={(e) => updateField("club", e.target.value)} /></label>
                      <label className="text-sm">Preferred Position<select className="cpp-input" value={form.position} onChange={(e) => updateField("position", e.target.value)}>{positions.map((p) => <option key={p}>{p}</option>)}</select></label>
                      <label className="text-sm">Jersey Number<input className="cpp-input" value={form.jersey} onChange={(e) => updateField("jersey", e.target.value)} /></label>
                    </div>
                  </div>
                ) : null}

                {activeStep === 2 ? (
                  <div>
                    <h2 className="font-['Sora'] text-xl font-semibold">Physical Attributes</h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                      <label className="text-sm">Height (cm)<input className="cpp-input" value={form.height} onChange={(e) => updateField("height", e.target.value)} /></label>
                      <label className="text-sm">Weight (kg)<input className="cpp-input" value={form.weight} onChange={(e) => updateField("weight", e.target.value)} /></label>
                      <label className="text-sm">Dominant Foot<select className="cpp-input" value={form.dominantFoot} onChange={(e) => updateField("dominantFoot", e.target.value)}><option>Left</option><option>Right</option><option>Both</option></select></label>
                    </div>
                    <div className="mt-4 grid gap-4">
                      <label className="text-sm">Speed Rating ({form.speed})<input type="range" min={40} max={99} value={form.speed} onChange={(e) => updateField("speed", Number(e.target.value))} className="cpp-slider mt-2 w-full" /></label>
                      <label className="text-sm">Strength Rating ({form.strength})<input type="range" min={40} max={99} value={form.strength} onChange={(e) => updateField("strength", Number(e.target.value))} className="cpp-slider mt-2 w-full" /></label>
                      <label className="text-sm">Stamina Rating ({form.stamina})<input type="range" min={40} max={99} value={form.stamina} onChange={(e) => updateField("stamina", Number(e.target.value))} className="cpp-slider mt-2 w-full" /></label>
                    </div>
                  </div>
                ) : null}

                {activeStep === 3 ? (
                  <div>
                    <h2 className="font-['Sora'] text-xl font-semibold">Performance Statistics</h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                      <label className="text-sm">Matches Played<input className="cpp-input" value={form.matches} onChange={(e) => updateField("matches", e.target.value)} /></label>
                      <label className="text-sm">Goals Scored<input className="cpp-input" value={form.goals} onChange={(e) => updateField("goals", e.target.value)} /></label>
                      <label className="text-sm">Assists<input className="cpp-input" value={form.assists} onChange={(e) => updateField("assists", e.target.value)} /></label>
                      <label className="text-sm">Clean Sheets<input className="cpp-input" value={form.cleanSheets} onChange={(e) => updateField("cleanSheets", e.target.value)} /></label>
                      <label className="text-sm">Yellow Cards<input className="cpp-input" value={form.yellowCards} onChange={(e) => updateField("yellowCards", e.target.value)} /></label>
                      <label className="text-sm">Red Cards<input className="cpp-input" value={form.redCards} onChange={(e) => updateField("redCards", e.target.value)} /></label>
                    </div>
                    <div className="mt-4 rounded-xl border border-[#D4AF37]/26 bg-[#D4AF37]/9 p-3 text-xs">
                      Dynamic Stat Preview: {form.matches} matches | {form.goals} goals | {form.assists} assists
                    </div>
                  </div>
                ) : null}

                {activeStep === 4 ? (
                  <div>
                    <h2 className="font-['Sora'] text-xl font-semibold">Media Upload</h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-3">
                      {["Profile Picture", "Highlight Video", "Performance Clips"].map((item) => (
                        <label key={item} className="cpp-dropzone text-xs">
                          {item}
                          <input type="file" className="mt-2 text-[11px] text-[#F8F8F8]/80" />
                        </label>
                      ))}
                    </div>
                  </div>
                ) : null}

                {activeStep === 5 ? (
                  <div>
                    <h2 className="font-['Sora'] text-xl font-semibold">Verification & Digital Identity</h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                      <label className="cpp-dropzone text-xs">
                        Upload ID (Optional)
                        <input type="file" className="mt-2 text-[11px] text-[#F8F8F8]/80" />
                      </label>
                      <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                        <p className="text-xs text-[#D4AF37]">Connect Wallet (NFT Player Card)</p>
                        <button type="button" onClick={() => setWalletConnected((v) => !v)} className="mt-3 rounded-lg border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] to-[#be9020] px-3 py-2 text-xs font-semibold text-[#0B0B0B]">
                          {walletConnected ? "Wallet Connected" : "Connect Wallet"}
                        </button>
                      </div>
                    </div>
                    <div className="cpp-chain-panel mt-4 rounded-xl border border-[#D4AF37]/30 p-3">
                      <label className="flex items-center justify-between text-sm">
                        Mint My Player Profile as NFT
                        <button
                          type="button"
                          onClick={() => setMintProfile((v) => !v)}
                          className={`h-6 w-12 rounded-full border p-0.5 transition ${mintProfile ? "border-[#D4AF37] bg-[#D4AF37]/20" : "border-[#F8F8F8]/25 bg-[#F8F8F8]/10"}`}
                        >
                          <span className={`block h-4.5 w-4.5 rounded-full bg-[#D4AF37] transition ${mintProfile ? "translate-x-5" : "translate-x-0"}`} />
                        </button>
                      </label>
                    </div>
                  </div>
                ) : null}
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <div className="flex items-center gap-2 text-[#D4AF37]">
                  <LockKeyhole className="h-4 w-4" />
                  <h3 className="text-sm font-semibold">Security & Privacy</h3>
                </div>
                <div className="mt-2 grid gap-2 text-xs text-[#F8F8F8]/80 sm:grid-cols-3">
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Data protected with secure storage protocols.</p>
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Verified profiles get priority discovery visibility.</p>
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Blockchain-backed digital ownership is optional.</p>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/35 bg-gradient-to-r from-[#D4AF37]/14 via-[#D4AF37]/6 to-transparent p-6 text-center">
                <h2 className="font-['Sora'] text-3xl font-semibold sm:text-4xl">Your Talent Deserves Global Recognition.</h2>
                <div className="mt-4 flex flex-wrap justify-center gap-3">
                  <button className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-6 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.3)]">
                    Publish Player Profile
                  </button>
                  <button className="rounded-xl border border-[#F8F8F8]/35 bg-[#F8F8F8]/8 px-6 py-3 text-sm font-semibold text-[#F8F8F8]">
                    Save as Draft
                  </button>
                </div>
              </article>
            </div>

            <aside className="space-y-5">
              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <p className="text-xs uppercase tracking-[0.16em] text-[#D4AF37]">Live Player Card Preview</p>
                <div className="mt-3 rounded-2xl border border-[#D4AF37]/45 bg-[linear-gradient(145deg,rgba(212,175,55,0.18),rgba(11,11,11,0.72))] p-3 shadow-[0_0_26px_rgba(212,175,55,0.2)]">
                  <div className="overflow-hidden rounded-xl border border-[#F8F8F8]/15">
                    <img src={Image.kendrick} alt="Player preview" className="h-36 w-full object-cover" />
                  </div>
                  <div className="mt-3 flex items-center justify-between">
                    <div>
                      <p className="font-semibold">{form.fullName || "Unnamed Player"}</p>
                      <p className="text-xs text-[#F8F8F8]/72">{form.position} | #{form.jersey || "--"}</p>
                    </div>
                    <span className="rounded-full border border-[#D4AF37]/60 bg-[#D4AF37]/15 px-2 py-1 text-xs font-semibold text-[#D4AF37]">
                      AI {aiRating}
                    </span>
                  </div>
                  <div className="mt-3 grid grid-cols-2 gap-2">
                    {statSummary.map((item) => (
                      <div key={item.label} className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] px-2 py-1.5 text-xs">
                        <p className="text-[#F8F8F8]/65">{item.label}</p>
                        <p className="font-semibold text-[#D4AF37]">{item.value}</p>
                      </div>
                    ))}
                  </div>
                  <div className="mt-3 flex items-center justify-between text-xs">
                    <span className="inline-flex items-center gap-1 text-[#D4AF37]"><FileBadge2 className="h-3.5 w-3.5" />NFT Concept</span>
                    <span className="inline-flex items-center gap-1">{walletConnected ? <BadgeCheck className="h-3.5 w-3.5 text-[#D4AF37]" /> : <Wallet className="h-3.5 w-3.5 text-[#F8F8F8]/72" />}{walletConnected ? "Wallet Linked" : "No Wallet"}</span>
                  </div>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">AI Performance Rating</h3>
                <p className="mt-2 text-sm text-[#F8F8F8]/78">Weighted from physical attributes and output stats.</p>
                <div className="mt-3 h-2.5 rounded-full bg-[#F8F8F8]/12">
                  <div className="h-full rounded-full bg-gradient-to-r from-[#D4AF37] to-[#f0d375] transition-all duration-500" style={{ width: `${aiRating}%` }} />
                </div>
                <p className="mt-2 text-2xl font-semibold text-[#D4AF37]">{aiRating}/99</p>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <div className="grid gap-2 text-xs">
                  <p className="inline-flex items-center gap-2"><ShieldCheck className="h-4 w-4 text-[#D4AF37]" />Verified identities trusted by scouts</p>
                  <p className="inline-flex items-center gap-2"><Blocks className="h-4 w-4 text-[#D4AF37]" />Optional on-chain profile ownership</p>
                  <p className="inline-flex items-center gap-2"><CircleUserRound className="h-4 w-4 text-[#D4AF37]" />Global visibility for top profiles</p>
                  <p className="inline-flex items-center gap-2"><Sparkles className="h-4 w-4 text-[#D4AF37]" />Professional athlete branding standard</p>
                </div>
              </article>
            </aside>
          </section>
        </section>
      </div>
    </main>
  );
}

export default CreatePlayerProfile;
