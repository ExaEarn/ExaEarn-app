import { useMemo, useState } from "react";
import {
  ArrowLeft,
  BadgeCheck,
  Blocks,
  BookOpenCheck,
  CirclePlay,
  MapPinned,
  MessageCircleMore,
  Search,
  ShieldCheck,
  Sparkles,
  Star,
  UsersRound,
  Wallet,
} from "lucide-react";
import Image from "../../assets/Image";
import { playerProfiles } from "./playerProfiles";
import "./ScoutTalentPage.css";

const players = playerProfiles.map((player) => ({
  id: player.id,
  name: player.fullName,
  sport: player.position === "Sprinter" ? "Athletics" : "Football",
  position: player.position,
  age: player.age,
  club: player.currentClub,
  location: player.nationality,
  rating: player.overallRating,
  height:
    player.id === "st-1" ? 178 :
    player.id === "st-2" ? 170 :
    player.id === "st-3" ? 173 :
    player.id === "st-4" ? 176 :
    player.id === "st-5" ? 186 : 169,
  matches: player.stats.matches,
  goals: player.stats.goals,
  verified: player.verified,
  nft: player.nftMinted,
}));

const sports = ["All", "Football", "Basketball", "Athletics"];
const positions = ["All", "Winger", "Striker", "Midfielder", "Defender", "Goalkeeper", "Sprinter"];
const locations = ["All", "Nigeria", "Ghana", "Morocco", "Colombia", "Kenya"];

function ScoutTalentPage({ onBack, onViewProfile, onOpenHighlightPreview }) {
  const [search, setSearch] = useState("");
  const [sport, setSport] = useState("All");
  const [position, setPosition] = useState("All");
  const [location, setLocation] = useState("All");
  const [ageMax, setAgeMax] = useState(30);
  const [ratingMin, setRatingMin] = useState(80);
  const [heightMin, setHeightMin] = useState(165);
  const [verifiedOnly, setVerifiedOnly] = useState(false);
  const [nftOnly, setNftOnly] = useState(false);

  const filteredPlayers = useMemo(() => {
    return players.filter((player) => {
      const searchMatch =
        !search ||
        player.name.toLowerCase().includes(search.toLowerCase()) ||
        player.position.toLowerCase().includes(search.toLowerCase()) ||
        player.club.toLowerCase().includes(search.toLowerCase());

      return (
        searchMatch &&
        (sport === "All" || player.sport === sport) &&
        (position === "All" || player.position === position) &&
        (location === "All" || player.location === location) &&
        player.age <= ageMax &&
        player.rating >= ratingMin &&
        player.height >= heightMin &&
        (!verifiedOnly || player.verified) &&
        (!nftOnly || player.nft)
      );
    });
  }, [search, sport, position, location, ageMax, ratingMin, heightMin, verifiedOnly, nftOnly]);

  const aiRankingConcept = useMemo(() => {
    return filteredPlayers
      .map((player) => {
        const score = Math.round(player.rating * 0.5 + player.matches * 0.25 + player.goals * 0.2 + (player.verified ? 4 : 0) + (player.nft ? 3 : 0));
        return { ...player, score };
      })
      .sort((a, b) => b.score - a.score)
      .slice(0, 5);
  }, [filteredPlayers]);

  return (
    <main className="min-h-screen exa-bg scout-talent-page text-[#F8F8F8]">
      <div className="relative mx-auto w-full max-w-sm px-3 pb-10 pt-4 sm:max-w-lg sm:px-4 md:max-w-3xl lg:max-w-7xl">
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <span className="st-orb st-orb-a" />
          <span className="st-orb st-orb-b" />
          <div className="st-grid" />
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
              <button className="inline-flex items-center gap-2 rounded-xl border border-[#D4AF37] bg-[#D4AF37]/15 px-3 py-2 text-xs font-semibold text-[#D4AF37]">
                <Wallet className="h-4 w-4" />
                Wallet Ready
              </button>
            </div>

            <div className="mt-4 relative overflow-hidden rounded-2xl border border-[#D4AF37]/35">
              <img src={Image.sports} alt="Stadium analytics background" className="h-[240px] w-full object-cover opacity-42 sm:h-[300px]" />
              <div className="absolute inset-0 bg-[linear-gradient(130deg,rgba(11,11,11,0.45),rgba(11,11,11,0.9)),radial-gradient(circle_at_20%_20%,rgba(212,175,55,0.25),transparent_45%)]" />
              <div className="absolute inset-0 p-5 sm:p-7">
                <h1 className="max-w-3xl font-['Sora'] text-3xl font-semibold sm:text-5xl">
                  <span className="text-[#D4AF37]">Discover</span> the Next Generation of Champions.
                </h1>
                <p className="mt-3 max-w-2xl text-sm text-[#F8F8F8]/82 sm:text-base">
                  Scout verified athletes. Analyze performance data. Connect directly with rising stars.
                </p>
                <div className="mt-5 flex flex-wrap gap-3">
                  <button className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-5 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.3)]">
                    Start Scouting
                  </button>
                  <button className="rounded-xl border border-[#D4AF37]/65 bg-transparent px-5 py-3 text-sm font-semibold text-[#D4AF37]">
                    Post Scout Request
                  </button>
                </div>
              </div>
            </div>
          </header>

          <section className="mt-6 grid gap-5 lg:grid-cols-[0.85fr_1.15fr]">
            <aside className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
              <h2 className="font-['Sora'] text-lg font-semibold">Advanced Talent Search</h2>
              <div className="mt-4 space-y-4">
                <label className="text-sm">
                  Smart Search
                  <div className="relative mt-2">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#D4AF37]" />
                    <input
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                      className="st-input pl-9"
                      placeholder="Player name, position, club"
                    />
                  </div>
                </label>
                <label className="text-sm">Sport Type<select className="st-input" value={sport} onChange={(e) => setSport(e.target.value)}>{sports.map((s) => <option key={s}>{s}</option>)}</select></label>
                <label className="text-sm">Position<select className="st-input" value={position} onChange={(e) => setPosition(e.target.value)}>{positions.map((p) => <option key={p}>{p}</option>)}</select></label>
                <label className="text-sm">Location<select className="st-input" value={location} onChange={(e) => setLocation(e.target.value)}>{locations.map((l) => <option key={l}>{l}</option>)}</select></label>
                <label className="text-sm">Age Range: Under {ageMax}<input type="range" min={16} max={35} value={ageMax} onChange={(e) => setAgeMax(Number(e.target.value))} className="st-slider mt-2 w-full" /></label>
                <label className="text-sm">Performance Rating: {ratingMin}+<input type="range" min={60} max={95} value={ratingMin} onChange={(e) => setRatingMin(Number(e.target.value))} className="st-slider mt-2 w-full" /></label>
                <label className="text-sm">Height Min: {heightMin}cm<input type="range" min={150} max={200} value={heightMin} onChange={(e) => setHeightMin(Number(e.target.value))} className="st-slider mt-2 w-full" /></label>
                <label className="flex items-center justify-between rounded-lg border border-[#F8F8F8]/18 bg-[#F8F8F8]/[0.03] px-3 py-2 text-sm">Verified Status
                  <input type="checkbox" checked={verifiedOnly} onChange={(e) => setVerifiedOnly(e.target.checked)} className="st-check" />
                </label>
                <label className="flex items-center justify-between rounded-lg border border-[#F8F8F8]/18 bg-[#F8F8F8]/[0.03] px-3 py-2 text-sm">NFT Profile
                  <input type="checkbox" checked={nftOnly} onChange={(e) => setNftOnly(e.target.checked)} className="st-check" />
                </label>
              </div>
            </aside>

            <div className="space-y-5">
              <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {filteredPlayers.map((player) => (
                  <article key={player.id} className="st-player-card rounded-2xl border border-[#F8F8F8]/14 bg-[#0B0B0B]/58 p-4 transition duration-300 hover:-translate-y-1 hover:border-[#D4AF37]/65">
                    <img src={Image.kendrick} alt={player.name} className="h-34 w-full rounded-xl object-cover" />
                    <div className="mt-3 flex items-start justify-between gap-2">
                      <div>
                        <p className="font-semibold">{player.name}</p>
                        <p className="text-xs text-[#F8F8F8]/72">{player.position} • {player.age} • {player.club}</p>
                      </div>
                      <span className="rounded-full border border-[#D4AF37]/65 bg-[#D4AF37]/14 px-2 py-1 text-xs font-semibold text-[#D4AF37]">{player.rating}</span>
                    </div>
                    <div className="mt-2 grid grid-cols-2 gap-2 text-xs">
                      <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] px-2 py-1">Matches: {player.matches}</p>
                      <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] px-2 py-1">Goals: {player.goals}</p>
                    </div>
                    <div className="mt-2 flex items-center gap-2 text-xs">
                      {player.verified ? <span className="inline-flex items-center gap-1 text-[#D4AF37]"><BadgeCheck className="h-3.5 w-3.5" />Verified</span> : <span className="text-[#F8F8F8]/65">Unverified</span>}
                      {player.nft ? <span className="inline-flex items-center gap-1 text-[#D4AF37]"><Blocks className="h-3.5 w-3.5" />NFT</span> : null}
                    </div>
                    <div className="mt-3 grid gap-2">
                      <button
                        type="button"
                        onClick={() => onViewProfile?.(player.id)}
                        className="rounded-lg border border-[#D4AF37]/65 bg-transparent px-3 py-2 text-xs font-semibold text-[#D4AF37]"
                      >
                        View Profile
                      </button>
                      <div className="grid grid-cols-3 gap-2">
                        <button
                          type="button"
                          onClick={() => onOpenHighlightPreview?.(player.id)}
                          className="st-mini-btn"
                        >
                          <CirclePlay className="h-3.5 w-3.5" />
                          Watch
                        </button>
                        <button className="st-mini-btn">Full Stats</button>
                        <button className="st-mini-btn">PDF</button>
                      </div>
                    </div>
                  </article>
                ))}
              </section>

              <section className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
                <h3 className="font-['Sora'] text-lg font-semibold">AI Performance Insights</h3>
                <div className="mt-3 grid gap-4 lg:grid-cols-2">
                  <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                    <p className="text-xs text-[#D4AF37]">Performance Trend</p>
                    <div className="st-chart mt-2">
                      <div className="st-chart-line" />
                    </div>
                  </div>
                  <div className="rounded-xl border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                    <p className="text-xs text-[#D4AF37]">Radar: Speed / Strength / Stamina</p>
                    <div className="st-radar mt-2" />
                  </div>
                </div>
                <div className="mt-3 grid gap-2 text-xs sm:grid-cols-3">
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Injury History: Low Risk</p>
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Growth Projection: +18%</p>
                  <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">AI Confidence: 91%</p>
                </div>
              </section>
            </div>
          </section>

          <section className="mt-6 grid gap-5 lg:grid-cols-2">
            <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
              <h3 className="font-['Sora'] text-lg font-semibold">Direct Scout Connection</h3>
              <div className="mt-3 grid gap-2 sm:grid-cols-2">
                <button className="st-action-btn"><MessageCircleMore className="h-4 w-4" />Message Player</button>
                <button className="st-action-btn">Request Trial</button>
                <button className="st-action-btn">Save to Shortlist</button>
                <button className="st-action-btn">Book Virtual Interview</button>
              </div>
              <button className="mt-3 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-2 text-sm font-semibold text-[#0B0B0B]">
                Execute Scout Action
              </button>
            </article>

            <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
              <h3 className="font-['Sora'] text-lg font-semibold">Global Talent Map (Premium)</h3>
              <div className="st-map mt-3 rounded-xl border border-[#F8F8F8]/12">
                <span className="st-pin pin-a" />
                <span className="st-pin pin-b" />
                <span className="st-pin pin-c" />
                <span className="st-pin pin-d" />
              </div>
              <p className="mt-2 text-xs text-[#F8F8F8]/72">Click a region pin to filter players by country and scouting density.</p>
            </article>
          </section>

          <section className="mt-6 grid gap-5 lg:grid-cols-2">
            <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
              <h3 className="font-['Sora'] text-lg font-semibold">Trust & Verification</h3>
              <div className="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                <p className="st-trust"><UserCheckIcon />Verified Player Identity</p>
                <p className="st-trust"><ShieldCheck className="h-4 w-4 text-[#D4AF37]" />Performance Data Validation</p>
                <p className="st-trust"><Blocks className="h-4 w-4 text-[#D4AF37]" />Blockchain-backed Digital Profile</p>
                <p className="st-trust"><Star className="h-4 w-4 text-[#D4AF37]" />Scout Reputation Rating</p>
              </div>
            </article>

            <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
              <h3 className="font-['Sora'] text-lg font-semibold">NFT Player Marketplace Integration</h3>
              <div className="mt-3 grid gap-2 text-xs">
                <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Tokenized player profiles listed with verified performance snapshots.</p>
                <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Scouts and clubs can bid for trial rights or sponsorship NFTs.</p>
                <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Smart contracts automate payouts, bonuses, and reputation trails.</p>
              </div>
            </article>
          </section>

          <section className="mt-6 grid gap-5 lg:grid-cols-2">
            <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
              <h3 className="font-['Sora'] text-lg font-semibold">AI Talent Ranking Algorithm Concept</h3>
              <div className="mt-3 space-y-2 text-xs">
                <p className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">Score = (Performance Rating × 0.5) + (Match Impact × 0.25) + (Growth Potential × 0.15) + (Verification/Web3 Trust × 0.10)</p>
                {aiRankingConcept.map((player, idx) => (
                  <p key={player.id} className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-2">#{idx + 1} {player.name} - AI Scout Score {player.score}</p>
                ))}
              </div>
            </article>

            <article className="rounded-2xl border border-[#D4AF37]/24 bg-[#0B0B0B]/58 p-4">
              <h3 className="font-['Sora'] text-lg font-semibold">Platform Signals</h3>
              <div className="mt-3 grid gap-2 text-xs">
                <p className="st-signal"><MapPinned className="h-4 w-4 text-[#D4AF37]" />Global Talent Distribution Active</p>
                <p className="st-signal"><Sparkles className="h-4 w-4 text-[#D4AF37]" />AI-Assisted Scouting Recommendations</p>
                <p className="st-signal"><UsersRound className="h-4 w-4 text-[#D4AF37]" />Cross-club Collaboration Ready</p>
                <p className="st-signal"><BookOpenCheck className="h-4 w-4 text-[#D4AF37]" />Downloadable Player Reports</p>
              </div>
            </article>
          </section>

          <section className="mt-6 rounded-2xl border border-[#D4AF37]/35 bg-gradient-to-r from-[#D4AF37]/14 via-[#D4AF37]/6 to-transparent p-6 text-center">
            <h2 className="font-['Sora'] text-3xl font-semibold sm:text-4xl">Talent Is Everywhere. Opportunity Starts Here.</h2>
            <div className="mt-4 flex flex-wrap justify-center gap-3">
              <button className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-6 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.3)]">
                Begin Scouting Now
              </button>
              <button className="rounded-xl border border-[#F8F8F8]/35 bg-[#F8F8F8]/8 px-6 py-3 text-sm font-semibold text-[#F8F8F8]">
                Join as Official Scout
              </button>
            </div>
          </section>
        </section>
      </div>
    </main>
  );
}

function UserCheckIcon() {
  return <BadgeCheck className="h-4 w-4 text-[#D4AF37]" />;
}

export default ScoutTalentPage;
