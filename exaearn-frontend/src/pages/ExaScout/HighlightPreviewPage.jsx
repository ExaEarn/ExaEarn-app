import { useMemo, useState } from "react";
import {
  ArrowLeft,
  BadgeCheck,
  Blocks,
  CirclePlay,
  Clock3,
  Download,
  Heart,
  ShieldCheck,
  Share2,
  Star,
  Wallet,
} from "lucide-react";
import Image from "../../assets/Image";
import { playerProfiles } from "./playerProfiles";
import "./HighlightPreviewPage.css";

function shortenHash(hash) {
  return `${hash.slice(0, 10)}...${hash.slice(-8)}`;
}

function HighlightPreviewPage({ onBack, onViewProfile, playerId }) {
  const player = useMemo(() => playerProfiles.find((item) => item.id === playerId) || playerProfiles[0], [playerId]);
  const [activeClip, setActiveClip] = useState(0);

  const clips = useMemo(
    () => [
      { id: "cl-1", title: "League Showcase", competition: "Elite Regional Cup", duration: "03:24", type: "Match Highlights" },
      { id: "cl-2", title: "Training Session", competition: "Club Camp", duration: "02:11", type: "Training" },
      { id: "cl-3", title: "Skill Drill Pack", competition: "Technical Circuit", duration: "01:47", type: "Skill Drills" },
      { id: "cl-4", title: "Awards Ceremony", competition: "Season Honors", duration: "01:20", type: "Awards" },
    ],
    [],
  );

  const selectedClip = clips[activeClip];
  const txHash = "0x8a24fce7bb14a023d8af72ef0e4429c7ab51e0233d4f7b9f52d2d1713ce0ab98";

  return (
    <main className="min-h-screen exa-bg highlight-preview-page text-[#F8F8F8]">
      <div className="relative mx-auto w-full max-w-sm px-3 pb-10 pt-4 sm:max-w-lg sm:px-4 md:max-w-3xl lg:max-w-7xl">
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <span className="hp-orb hp-orb-a" />
          <span className="hp-orb hp-orb-b" />
          <div className="hp-grid" />
        </div>

        <section className="relative rounded-3xl border border-[#D4AF37]/30 bg-[#0B0B0B]/70 p-4 shadow-[0_24px_60px_rgba(0,0,0,0.5)] backdrop-blur-xl sm:p-6">
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
            </div>

            <div className="mt-4 grid gap-5 lg:grid-cols-[1.25fr_0.75fr]">
              <div className="relative overflow-hidden rounded-2xl border border-[#D4AF37]/35">
                <img src={Image.sports} alt="Highlight player" className="h-[260px] w-full object-cover opacity-45 sm:h-[360px]" />
                <div className="absolute inset-0 bg-[linear-gradient(130deg,rgba(11,11,11,0.4),rgba(11,11,11,0.9)),radial-gradient(circle_at_20%_20%,rgba(212,175,55,0.24),transparent_45%)]" />
                <div className="absolute inset-0 flex flex-col justify-between p-5 sm:p-7">
                  <div>
                    <p className="inline-flex items-center gap-2 rounded-full border border-[#D4AF37]/55 bg-[#D4AF37]/14 px-3 py-1 text-xs font-semibold text-[#D4AF37]">
                      <Clock3 className="h-3.5 w-3.5" />
                      {selectedClip.duration}
                    </p>
                  </div>
                  <div>
                    <button className="mb-3 inline-flex h-14 w-14 items-center justify-center rounded-full border border-[#D4AF37]/60 bg-[#D4AF37]/16 text-[#D4AF37] shadow-[0_0_20px_rgba(212,175,55,0.3)]">
                      <CirclePlay className="h-8 w-8" />
                    </button>
                    <h1 className="font-['Sora'] text-3xl font-semibold sm:text-4xl">{player.fullName}</h1>
                    <p className="mt-1 text-sm text-[#F8F8F8]/82">{player.position} • {selectedClip.competition} • {selectedClip.title}</p>
                    <div className="mt-2 flex flex-wrap gap-2 text-xs">
                      <span className="inline-flex items-center gap-1 rounded-full border border-[#D4AF37]/55 bg-[#D4AF37]/12 px-2 py-1 text-[#D4AF37]"><BadgeCheck className="h-3.5 w-3.5" />{player.verified ? "Verified" : "Pending"}</span>
                      <span className="inline-flex items-center gap-1 rounded-full border border-[#D4AF37]/55 bg-[#D4AF37]/12 px-2 py-1 text-[#D4AF37]"><Star className="h-3.5 w-3.5" />{player.overallRating}</span>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                      <button className="hp-action"><Heart className="h-4 w-4" />Like</button>
                      <button className="hp-action"><Share2 className="h-4 w-4" />Share</button>
                      <button className="hp-action">Save to Shortlist</button>
                      <button className="hp-action"><Download className="h-4 w-4" />Download Clip</button>
                    </div>
                  </div>
                </div>
              </div>

              <aside className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h2 className="font-['Sora'] text-lg font-semibold">Performance Snapshot</h2>
                <div className="mt-3 grid grid-cols-2 gap-2 text-xs">
                  <Metric label="Match Played" value={player.stats.matches} />
                  <Metric label="Goals / Assists" value={`${player.stats.goals}/${player.stats.assists}`} />
                  <Metric label="Key Passes" value={Math.max(player.stats.assists + 7, 9)} />
                  <Metric label={player.position === "Goalkeeper" ? "Saves" : "Tackles"} value={player.position === "Goalkeeper" ? 31 : 22} />
                  <Metric label="Speed Peak" value={`${player.ai.speed} km/h`} />
                  <Metric label="Perf. Rating" value={player.overallRating} gold />
                </div>
              </aside>
            </div>
          </header>

          <section className="mt-6 grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
            <div className="space-y-5">
              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">AI Highlight Breakdown</h3>
                <div className="mt-3 rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                  <p className="text-xs text-[#D4AF37]">Key Moments Timeline</p>
                  <div className="mt-2 grid gap-2 sm:grid-cols-3">
                    {["00:34 Sprint Break", "01:42 Key Assist", "02:57 Decisive Action"].map((item) => (
                      <button key={item} className="rounded-lg border border-[#F8F8F8]/18 bg-[#F8F8F8]/[0.03] px-2 py-2 text-xs text-left hover:border-[#D4AF37]/65">{item}</button>
                    ))}
                  </div>
                  <div className="mt-3 h-2 rounded-full bg-[#F8F8F8]/12">
                    <div className="h-full w-[82%] rounded-full bg-gradient-to-r from-[#D4AF37] to-[#f0d375]" />
                  </div>
                  <div className="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                    <p className="hp-chip">Shot Accuracy: 78%</p>
                    <p className="hp-chip">Dribble Success: 84%</p>
                    <p className="hp-chip">Defensive Contribution: 71%</p>
                    <p className="hp-chip">Movement Heatmap: High Activity</p>
                  </div>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">Additional Clips & Gallery</h3>
                <div className="mt-3 flex gap-3 overflow-x-auto pb-1">
                  {clips.map((clip, index) => (
                    <button
                      key={clip.id}
                      type="button"
                      onClick={() => setActiveClip(index)}
                      className={`min-w-[160px] rounded-xl border p-2 text-left ${activeClip === index ? "border-[#D4AF37] bg-[#D4AF37]/10 shadow-[0_0_14px_rgba(212,175,55,0.22)]" : "border-[#F8F8F8]/15 bg-[#F8F8F8]/[0.03]"}`}
                    >
                      <img src={Image.sports} alt={clip.title} className="h-20 w-full rounded-lg object-cover" />
                      <p className="mt-2 text-xs font-semibold">{clip.title}</p>
                      <p className="text-[11px] text-[#F8F8F8]/70">{clip.type}</p>
                    </button>
                  ))}
                </div>
              </article>
            </div>

            <aside className="space-y-5">
              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">Player Quick Profile</h3>
                <div className="mt-3 rounded-xl border border-[#D4AF37]/35 bg-[#D4AF37]/10 p-3">
                  <div className="flex items-center gap-3">
                    <div className="h-14 w-14 rounded-full border border-[#D4AF37] p-0.5">
                      <img src={Image.kendrick} alt={player.fullName} className="h-full w-full rounded-full object-cover" />
                    </div>
                    <div className="text-xs">
                      <p className="font-semibold">{player.fullName}</p>
                      <p>{player.age} • {player.nationality}</p>
                      <p>{player.currentClub}</p>
                    </div>
                  </div>
                  <div className="mt-2 grid grid-cols-2 gap-2 text-xs">
                    <p className="hp-chip">Height / Weight: 178cm / 72kg</p>
                    <p className="hp-chip">Dominant Foot: Right</p>
                    <p className="hp-chip">Wallet: {player.verified ? "Connected" : "Not Connected"}</p>
                    <p className="hp-chip">Rating: {player.overallRating}</p>
                  </div>
                  <button type="button" onClick={() => onViewProfile?.(player.id)} className="mt-3 w-full rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-2 text-sm font-semibold text-[#0B0B0B]">
                    View Full Profile
                  </button>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 hp-chain-panel">
                <h3 className="font-['Sora'] text-lg font-semibold">Verification & Ownership</h3>
                <div className="mt-3 space-y-2 text-xs">
                  <p className="hp-chip"><BadgeCheck className="h-4 w-4 text-[#D4AF37]" />Blockchain Verified Highlight</p>
                  <p className="hp-chip"><Blocks className="h-4 w-4 text-[#D4AF37]" />NFT Clip Mint Status: {player.nftMinted ? "Minted" : "Not Minted"}</p>
                  <p className="hp-chip"><Download className="h-4 w-4 text-[#D4AF37]" />Ownership Certificate Download</p>
                  <p className="hp-chip"><ShieldCheck className="h-4 w-4 text-[#D4AF37]" />Transaction Hash: {shortenHash(txHash)}</p>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">Scout Engagement</h3>
                <div className="mt-3 grid gap-2">
                  <button className="hp-action-full">Request Trial</button>
                  <button className="hp-action-full">Contact Player</button>
                  <button className="hp-action-full">Add to Watchlist</button>
                  <button className="hp-action-full">Rate Performance</button>
                  <button className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-2 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_22px_rgba(212,175,55,0.3)]">
                    Start Scouting Now
                  </button>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">AI Auto-Highlight Detection</h3>
                <div className="mt-2 space-y-2 text-xs text-[#F8F8F8]/82">
                  <p className="hp-chip">Event tagging by ball progression, acceleration burst, and final-third impact.</p>
                  <p className="hp-chip">Auto-clips generated from top xG chain actions and defensive recoveries.</p>
                  <p className="hp-chip">Confidence score: 89% with manual scout override controls.</p>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">NFT Highlight Marketplace Integration</h3>
                <div className="mt-2 space-y-2 text-xs text-[#F8F8F8]/82">
                  <p className="hp-chip">Mint signature highlight clips as collectible scouting assets.</p>
                  <p className="hp-chip">Clubs can purchase licensed access with on-chain usage terms.</p>
                  <p className="hp-chip">Revenue share auto-distributed to athlete, academy, and rights holder.</p>
                </div>
              </article>
            </aside>
          </section>

          <section className="mt-6 rounded-2xl border border-[#D4AF37]/35 bg-gradient-to-r from-[#D4AF37]/14 via-[#D4AF37]/6 to-transparent p-6 text-center">
            <h2 className="font-['Sora'] text-3xl font-semibold sm:text-4xl">See the Talent. Analyze the Data. Make the Move.</h2>
            <button className="mt-4 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-6 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.32)]">
              Start Scouting Now
            </button>
          </section>
        </section>
      </div>
    </main>
  );
}

function Metric({ label, value, gold = false }) {
  return (
    <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">
      <span className="block text-[11px] text-[#F8F8F8]/66">{label}</span>
      <span className={`text-sm font-semibold ${gold ? "text-[#D4AF37]" : ""}`}>{value}</span>
    </p>
  );
}

export default HighlightPreviewPage;
