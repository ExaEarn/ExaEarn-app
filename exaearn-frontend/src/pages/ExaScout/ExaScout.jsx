import { useMemo, useState } from "react";
import { ArrowLeft, BadgeCheck, ShieldCheck, Sparkles } from "lucide-react";
import Image from "../../assets/Image";
import PlayerCard from "./components/PlayerCard";
import ScoutDashboard from "./components/ScoutDashboard";
import HighlightGallery from "./components/HighlightGallery";
import RankingBoard from "./components/RankingBoard";
import SponsorshipPanel from "./components/SponsorshipPanel";
import ContractPanel from "./components/ContractPanel";
import { playerProfiles } from "./playerProfiles";

const players = playerProfiles.map((player) => ({
  id: player.id,
  name: player.fullName,
  age: player.age,
  position: player.position,
  country: player.nationality,
  rating: player.overallRating,
  verified: player.verified,
  nftStatus: player.nftMinted ? "Minted" : "Not Minted",
  highlights: Math.max(6, Math.round((player.stats.matches + player.stats.goals + player.stats.assists) / 4)),
}));

const highlights = [
  {
    id: "hi-01",
    title: "Coastal Derby Showcase",
    rating: 9.4,
    views: "18.2k",
    scoutInterest: 48,
  },
  {
    id: "hi-02",
    title: "Regional Cup Finals",
    rating: 9.1,
    views: "12.6k",
    scoutInterest: 36,
  },
  {
    id: "hi-03",
    title: "Academy Elite Trials",
    rating: 8.9,
    views: "9.3k",
    scoutInterest: 27,
  },
];

const leaderboard = {
  topRated: [
    { name: "Kofi Mensah", rating: 92 },
    { name: "Lina Haddad", rating: 90 },
    { name: "Amina Yusuf", rating: 89 },
  ],
  trending: [
    { name: "Zara Khan", score: "+24% scout interest" },
    { name: "Samuel Osei", score: "+18% video views" },
    { name: "Diego Alvarez", score: "+15% trial requests" },
  ],
  mostViewed: [
    { name: "Kofi Mensah", views: "48k" },
    { name: "Amina Yusuf", views: "41k" },
    { name: "Lina Haddad", views: "37k" },
  ],
};

const sponsorshipTiers = [
  { name: "Bronze", amount: "150 EXA", roi: "2% future earnings share" },
  { name: "Silver", amount: "350 EXA", roi: "4% future earnings share" },
  { name: "Gold", amount: "650 EXA", roi: "7% future earnings share" },
];

const feeModel = [
  { label: "Player Profile", value: "Freemium" },
  { label: "Scout Dashboard", value: "Premium Access" },
  { label: "NFT Contract Minting", value: "1.2% fee" },
  { label: "Sponsorship Platform", value: "0.8% fee" },
];

function ExaScout({
  onBack,
  onOpenCreatePlayerProfile,
  onOpenScoutTalent,
  onOpenViewProfile,
  onOpenHighlightPreview,
  onOpenInitiateContract,
}) {
  const [search, setSearch] = useState("");
  const [position, setPosition] = useState("All");
  const [country, setCountry] = useState("All");
  const [ranking, setRanking] = useState("Top Rated");

  const positions = useMemo(
    () => ["All", ...new Set(players.map((player) => player.position))],
    [],
  );
  const countries = useMemo(
    () => ["All", ...new Set(players.map((player) => player.country))],
    [],
  );

  const filteredPlayers = useMemo(() => {
    return players
      .filter((player) => {
        const matchSearch =
          !search ||
          player.name.toLowerCase().includes(search.toLowerCase()) ||
          player.position.toLowerCase().includes(search.toLowerCase());
        const matchPosition = position === "All" || player.position === position;
        const matchCountry = country === "All" || player.country === country;
        return matchSearch && matchPosition && matchCountry;
      })
      .sort((a, b) => {
        if (ranking === "Top Rated") return b.rating - a.rating;
        if (ranking === "Most Viewed") return b.highlights - a.highlights;
        return a.name.localeCompare(b.name);
      });
  }, [search, position, country, ranking]);

  return (
    <div className="min-h-screen text-white exa-bg app-shell">
      <div className="container w-full max-w-sm px-3 pt-4 pb-6 mx-auto sm:max-w-lg sm:px-4 sm:pt-6 md:max-w-2xl lg:max-w-5xl xl:max-w-6xl">
        <div className="p-4 shadow-xl glass-card rounded-3xl sm:p-6">
          <header className="mb-6 rounded-3xl border border-violet-300/20 bg-gradient-to-br from-cosmic-900/90 via-cosmic-800/70 to-cosmic-900/95 p-5">
            <div className="flex items-center justify-between gap-3">
              <div className="flex items-center gap-3">
                <div className="flex h-11 w-11 items-center justify-center rounded-2xl border border-auric-300/60 bg-cosmic-900/70">
                  <img src={Image.earn} alt="ExaScout" className="h-6 w-6 object-contain" />
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.25em] text-auric-300/70">ExaEarn</p>
                  <p className="text-sm text-violet-100/70">ExaScout Global</p>
                </div>
              </div>
              {onBack ? (
                <button type="button" onClick={onBack} className="btn-outline inline-flex items-center gap-2">
                  <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                  Back
                </button>
              ) : null}
            </div>

            <div className="mt-5 grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
              <div>
                <h1 className="font-['Sora'] text-3xl font-semibold text-violet-50 sm:text-4xl">
                  ExaScout Global – Decentralized Talent Discovery
                </h1>
                <p className="mt-3 text-sm leading-relaxed text-violet-100/75 sm:text-base">
                  Empowering undiscovered football talent through blockchain-verified scouting.
                </p>
                <div className="mt-4 flex flex-wrap gap-3">
                  <button type="button" onClick={onOpenCreatePlayerProfile} className="btn-gold">Create Player Profile</button>
                  <button type="button" onClick={onOpenScoutTalent} className="btn-outline">Scout Talent</button>
                </div>
              </div>
              <div className="grid gap-3">
                <div className="rounded-2xl border border-emerald-300/20 bg-emerald-500/10 p-4 text-sm text-emerald-100/80">
                  <div className="flex items-center gap-2 text-emerald-300">
                    <ShieldCheck className="h-4 w-4" aria-hidden="true" />
                    Blockchain-verified player identities
                  </div>
                </div>
                <div className="rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4 text-sm text-violet-100/70">
                  <div className="flex items-center gap-2 text-auric-300">
                    <BadgeCheck className="h-4 w-4" aria-hidden="true" />
                    NFT-backed contracts & sponsorships
                  </div>
                </div>
              </div>
            </div>
          </header>

          <section className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
            <div className="space-y-5">
              <ScoutDashboard
                search={search}
                onSearch={setSearch}
                position={position}
                onPosition={setPosition}
                country={country}
                onCountry={setCountry}
                ranking={ranking}
                onRanking={setRanking}
                positions={positions}
                countries={countries}
              />

              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {filteredPlayers.map((player) => (
                  <PlayerCard
                    key={player.id}
                    player={player}
                    onViewProfile={onOpenViewProfile}
                    onOpenHighlightPreview={onOpenHighlightPreview}
                  />
                ))}
              </div>
            </div>

            <div className="space-y-5">
              <RankingBoard leaderboard={leaderboard} />
              <ContractPanel onOpenInitiateContract={onOpenInitiateContract} />
            </div>
          </section>

          <section className="mt-8 grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
            <HighlightGallery items={highlights} />
            <SponsorshipPanel tiers={sponsorshipTiers} />
          </section>

          <section className="mt-8 rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4">
            <div className="flex items-center gap-2 text-auric-300">
              <Sparkles className="h-4 w-4" aria-hidden="true" />
              <h2 className="text-sm font-semibold text-violet-50">Monetization Transparency</h2>
            </div>
            <div className="mt-3 grid gap-2 sm:grid-cols-2">
              {feeModel.map((item) => (
                <div key={item.label} className="flex items-center justify-between rounded-xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-2 text-xs">
                  <span className="text-violet-100/70">{item.label}</span>
                  <span className="font-semibold text-auric-300">{item.value}</span>
                </div>
              ))}
            </div>
          </section>
        </div>
      </div>
    </div>
  );
}

export default ExaScout;
