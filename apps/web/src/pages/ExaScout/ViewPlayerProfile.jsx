import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  BadgeCheck,
  Blocks,
  CalendarClock,
  CirclePlay,
  Download,
  MessageCircleMore,
  ShieldCheck,
  Star,
  Trophy,
  Wallet,
} from "lucide-react";
import Image from "../../assets/Image";
import { playerProfiles } from "./playerProfiles";
import "./ViewPlayerProfile.css";

function shortenWallet(address) {
  if (!address) return "";
  return `${address.slice(0, 6)}...${address.slice(-4)}`;
}

function Counter({ value }) {
  const [count, setCount] = useState(0);
  useEffect(() => {
    let start;
    let raf;
    const duration = 1000;
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

function ViewPlayerProfile({ onBack, playerId }) {
  const player = useMemo(() => playerProfiles.find((p) => p.id === playerId) || playerProfiles[0], [playerId]);
  const [activeMedia, setActiveMedia] = useState(0);
  const [message, setMessage] = useState("");
  const [messages, setMessages] = useState(["Impressive output over the last 12 months.", "Can we schedule a scouting interview this week?"]);

  const media = useMemo(
    () => [
      { id: "vd", label: "Highlight Reel", image: Image.sports, video: true },
      { id: "img1", label: "Match Action", image: Image.kendrick },
      { id: "img2", label: "Training Session", image: Image.campaigns },
      { id: "img3", label: "Performance Snapshot", image: Image.assets },
    ],
    [],
  );

  const trend = useMemo(() => [72, 75, 79, 81, 84, 88, player.overallRating], [player.overallRating]);
  const trendPath = useMemo(() => {
    const points = trend.map((value, index) => `${(index / (trend.length - 1)) * 100},${100 - (value - 60) * 2}`).join(" ");
    return points;
  }, [trend]);

  const postMessage = () => {
    const trimmed = message.trim();
    if (!trimmed) return;
    setMessages((current) => [trimmed, ...current]);
    setMessage("");
  };

  const overallPlatformRanking = useMemo(() => {
    return playerProfiles
      .map((profile) => ({
        id: profile.id,
        name: profile.fullName,
        score: Math.round(profile.overallRating * 0.52 + profile.ai.growthPotential * 0.28 + profile.ai.tactical * 0.2),
      }))
      .sort((a, b) => b.score - a.score);
  }, []);

  return (
    <main className="min-h-screen exa-bg view-player-page text-[#F8F8F8]">
      <div className="relative mx-auto w-full max-w-sm px-3 pb-10 pt-4 sm:max-w-lg sm:px-4 md:max-w-3xl lg:max-w-7xl">
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <span className="vpp-orb vpp-orb-a" />
          <span className="vpp-orb vpp-orb-b" />
          <div className="vpp-grid" />
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
            </div>

            <div className="mt-4 relative overflow-hidden rounded-2xl border border-[#D4AF37]/35">
              <img src={Image.sports} alt="Player profile hero" className="h-[260px] w-full object-cover opacity-42 sm:h-[340px]" />
              <div className="absolute inset-0 bg-[linear-gradient(130deg,rgba(11,11,11,0.45),rgba(11,11,11,0.9)),radial-gradient(circle_at_20%_20%,rgba(212,175,55,0.25),transparent_45%)]" />
              <div className="absolute inset-0 p-5 sm:p-7">
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div className="flex items-start gap-4">
                    <div className="h-24 w-24 rounded-full border-2 border-[#D4AF37] p-1 shadow-[0_0_22px_rgba(212,175,55,0.32)] sm:h-28 sm:w-28">
                      <img src={Image.kendrick} alt={player.fullName} className="h-full w-full rounded-full object-cover" />
                    </div>
                    <div>
                      <h1 className="font-['Sora'] text-3xl font-semibold sm:text-4xl">{player.fullName}</h1>
                      <p className="mt-1 text-sm text-[#F8F8F8]/80">{player.position} • {player.age} • {player.nationality}</p>
                      <p className="text-sm text-[#F8F8F8]/80">{player.currentClub} • Jersey #{player.jerseyNumber}</p>
                      <p className="mt-2 inline-flex items-center gap-2 text-xs">
                        <span className="inline-flex items-center gap-1 rounded-full border border-[#D4AF37]/65 bg-[#D4AF37]/14 px-2 py-1 text-[#D4AF37]">
                          <BadgeCheck className="h-3.5 w-3.5" />
                          {player.verified ? "Verified" : "Pending Verification"}
                        </span>
                        <span className="inline-flex items-center gap-1 rounded-full border border-[#D4AF37]/65 bg-[#D4AF37]/14 px-2 py-1 text-[#D4AF37]">
                          <Star className="h-3.5 w-3.5" />
                          Rating {player.overallRating}
                        </span>
                      </p>
                    </div>
                  </div>
                </div>

                <div className="mt-4 flex flex-wrap gap-3">
                  <button className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-2 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.3)]">Contact Player</button>
                  <button className="rounded-xl border border-[#D4AF37]/65 bg-transparent px-4 py-2 text-sm font-semibold text-[#D4AF37]">Save to Shortlist</button>
                  <button className="inline-flex items-center gap-2 rounded-xl border border-[#F8F8F8]/35 bg-[#F8F8F8]/8 px-4 py-2 text-sm font-semibold text-[#F8F8F8]"><Download className="h-4 w-4 text-[#D4AF37]" />Download Player Report</button>
                </div>
              </div>
            </div>
          </header>

          <section className="mt-6 grid gap-5 lg:grid-cols-[1.15fr_0.85fr]">
            <div className="space-y-5">
              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h2 className="font-['Sora'] text-xl font-semibold">Performance Statistics</h2>
                <div className="mt-4 grid gap-3 sm:grid-cols-3">
                  <StatBox label="Matches Played" value={player.stats.matches} />
                  <StatBox label="Goals" value={player.stats.goals} />
                  <StatBox label="Assists" value={player.stats.assists} />
                  <StatBox label="Clean Sheets" value={player.stats.cleanSheets} />
                  <StatBox label="Yellow / Red" value={`${player.stats.yellowCards} / ${player.stats.redCards}`} />
                  <StatBox label="Minutes Played" value={player.stats.minutesPlayed} />
                </div>
                <div className="mt-4 rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                  <p className="text-xs text-[#D4AF37]">Performance Trend</p>
                  <svg viewBox="0 0 100 100" className="mt-2 h-28 w-full">
                    <polyline fill="none" stroke="#D4AF37" strokeWidth="2.2" points={trendPath} />
                  </svg>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h2 className="font-['Sora'] text-xl font-semibold">Media & Highlights</h2>
                <div className="mt-4 grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
                  <div className="relative overflow-hidden rounded-xl border border-[#D4AF37]/35">
                    <img src={media[activeMedia].image} alt={media[activeMedia].label} className="h-56 w-full object-cover transition duration-300 hover:scale-[1.03]" />
                    <div className="absolute inset-0 flex items-center justify-center bg-black/30">
                      <CirclePlay className="h-14 w-14 text-[#D4AF37]" />
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    {media.map((item, index) => (
                      <button key={item.id} type="button" onClick={() => setActiveMedia(index)} className={`overflow-hidden rounded-xl border ${activeMedia === index ? "border-[#D4AF37] shadow-[0_0_16px_rgba(212,175,55,0.2)]" : "border-[#F8F8F8]/12"}`}>
                        <img src={item.image} alt={item.label} className="h-24 w-full object-cover transition hover:scale-105" />
                      </button>
                    ))}
                  </div>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h2 className="font-['Sora'] text-xl font-semibold">Player Bio</h2>
                <div className="mt-3 space-y-2 text-sm text-[#F8F8F8]/84">
                  <p><span className="text-[#D4AF37] font-semibold">Personal Background:</span> {player.bio.background}</p>
                  <p><span className="text-[#D4AF37] font-semibold">Playing Style:</span> {player.bio.style}</p>
                </div>
                <div className="mt-4">
                  <p className="text-sm font-semibold text-[#D4AF37]">Achievements & Awards</p>
                  <div className="mt-2 grid gap-2">
                    {player.bio.achievements.map((achievement) => (
                      <p key={achievement} className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2 text-xs">{achievement}</p>
                    ))}
                  </div>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h2 className="font-['Sora'] text-xl font-semibold">Career & Club History</h2>
                <div className="mt-4 space-y-3">
                  {player.career.map((entry, idx) => (
                    <div key={`${entry.year}-${entry.club}`} className="vpp-timeline-row">
                      <div className="vpp-timeline-node">{idx + 1}</div>
                      <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3 text-xs">
                        <p className="font-semibold text-[#D4AF37]">{entry.year} - {entry.club}</p>
                        <p className="mt-1 text-[#F8F8F8]/82">Apps: {entry.appearances} | Goals: {entry.goals}</p>
                        <p className="text-[#F8F8F8]/72">{entry.achievements}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </article>
            </div>

            <aside className="space-y-5">
              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">AI Performance Analysis</h3>
                <div className="mt-3 grid gap-2 text-xs">
                  <AIPill label="Speed" value={player.ai.speed} />
                  <AIPill label="Strength" value={player.ai.strength} />
                  <AIPill label="Stamina" value={player.ai.stamina} />
                  <AIPill label="Tactical Intelligence" value={player.ai.tactical} />
                  <AIPill label="Growth Potential" value={player.ai.growthPotential} />
                </div>
                <div className="mt-3 rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                  <p className="text-xs text-[#D4AF37]">AI Player Ranking Score</p>
                  <p className="mt-1 text-3xl font-semibold text-[#D4AF37]">{player.ai.rankingScore}</p>
                  <p className="text-xs text-[#F8F8F8]/72">Calculated from output consistency, physical profile, tactical intelligence and verification trust signals.</p>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4 vpp-chain-panel">
                <h3 className="font-['Sora'] text-lg font-semibold">Digital Identity & Web3</h3>
                <div className="mt-3 space-y-2 text-xs">
                  <p className="vpp-chip"><Wallet className="h-4 w-4 text-[#D4AF37]" />Wallet: {shortenWallet(player.walletAddress)}</p>
                  <p className="vpp-chip"><Blocks className="h-4 w-4 text-[#D4AF37]" />NFT Player Card: {player.nftMinted ? "Minted" : "Not Minted"}</p>
                  <p className="vpp-chip"><BadgeCheck className="h-4 w-4 text-[#D4AF37]" />Blockchain Verified</p>
                  <button className="mt-1 w-full rounded-lg border border-[#D4AF37]/65 bg-[#D4AF37]/12 px-3 py-2 text-xs font-semibold text-[#D4AF37]">Download Ownership Certificate</button>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">Scout Interaction</h3>
                <div className="mt-3 grid gap-2">
                  <button className="vpp-action"><MessageCircleMore className="h-4 w-4" />Send Message</button>
                  <button className="vpp-action">Request Trial</button>
                  <button className="vpp-action">Book Virtual Interview</button>
                  <button className="vpp-action">Add to Watchlist</button>
                  <button className="mt-1 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-2 text-sm font-semibold text-[#0B0B0B]">Scout This Player</button>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">SportsTech Transfer Marketplace</h3>
                <div className="mt-3 space-y-2 text-xs text-[#F8F8F8]/82">
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Tokenized transfer listings with transparent valuation metrics.</p>
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Clubs submit digital offers, scout agencies verify player fit.</p>
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Smart contracts enforce milestone-based release and payout terms.</p>
                </div>
              </article>

              <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">Global AI Ranking (Top Profiles)</h3>
                <div className="mt-3 space-y-2">
                  {overallPlatformRanking.slice(0, 5).map((entry, idx) => (
                    <p key={entry.id} className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] px-3 py-2 text-xs">
                      <span className="text-[#D4AF37] font-semibold">#{idx + 1}</span> {entry.name}
                      <span className="float-right text-[#D4AF37]">{entry.score}</span>
                    </p>
                  ))}
                </div>
              </article>
            </aside>
          </section>

          <section className="mt-6 rounded-2xl border border-[#D4AF37]/35 bg-gradient-to-r from-[#D4AF37]/14 via-[#D4AF37]/6 to-transparent p-6 text-center">
            <h2 className="font-['Sora'] text-3xl font-semibold sm:text-4xl">Talent Backed by Data. Verified by Technology.</h2>
            <button className="mt-4 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-6 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.3)]">
              Scout This Player
            </button>
          </section>
        </section>
      </div>
    </main>
  );
}

function StatBox({ label, value }) {
  return (
    <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
      <p className="text-xs text-[#F8F8F8]/68">{label}</p>
      <p className="mt-1 text-lg font-semibold text-[#D4AF37]">{value}</p>
    </div>
  );
}

function AIPill({ label, value }) {
  return (
    <div className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] px-3 py-2">
      <div className="flex items-center justify-between">
        <p>{label}</p>
        <p className="font-semibold text-[#D4AF37]">{value}</p>
      </div>
      <div className="mt-1 h-1.5 rounded-full bg-[#F8F8F8]/12">
        <div className="h-full rounded-full bg-gradient-to-r from-[#D4AF37] to-[#f0d375]" style={{ width: `${value}%` }} />
      </div>
    </div>
  );
}

export default ViewPlayerProfile;
