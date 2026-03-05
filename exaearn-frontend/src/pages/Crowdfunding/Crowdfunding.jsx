import { useMemo, useState } from "react";
import { ArrowLeft, Filter, Search, Sparkles } from "lucide-react";
import { campaignData } from "./campaignData";

const categories = ["All", "Technology", "Agriculture", "Education", "Health", "Web3", "AI", "Fintech"];

const campaigns = campaignData;

function formatNaira(amount) {
  return new Intl.NumberFormat("en-NG", {
    style: "currency",
    currency: "NGN",
    maximumFractionDigits: 0,
  }).format(amount);
}

function percent(raised, target) {
  return Math.min((raised / target) * 100, 100);
}

function Crowdfunding({ onBack, onCreateCampaign, onSupportCampaign, onViewCampaign }) {
  const [activeCategory, setActiveCategory] = useState("All");
  const [query, setQuery] = useState("");

  const featuredCampaign = campaigns[0];

  const filteredCampaigns = useMemo(() => {
    return campaigns.filter((campaign) => {
      const categoryMatch = activeCategory === "All" || campaign.category === activeCategory;
      const q = query.trim().toLowerCase();
      const queryMatch = !q || campaign.title.toLowerCase().includes(q) || campaign.description.toLowerCase().includes(q);
      return categoryMatch && queryMatch;
    });
  }, [activeCategory, query]);

  return (
    <main className="relative min-h-screen overflow-hidden bg-gradient-to-br from-[#050509] via-[#140822] to-[#1c0d32] px-4 py-8 text-violet-50 sm:px-6 sm:py-10">
      <div className="pointer-events-none absolute -left-24 top-20 h-56 w-56 rounded-full bg-purple-500/25 blur-3xl" />
      <div className="pointer-events-none absolute right-0 top-1/3 h-64 w-64 rounded-full bg-amber-300/10 blur-3xl" />

      <section className="mx-auto w-full max-w-7xl rounded-3xl border border-violet-300/15 bg-[#110a20]/70 p-4 shadow-[0_20px_80px_rgba(0,0,0,0.55)] backdrop-blur-xl sm:p-6 lg:p-8">
        <header className="rounded-2xl border border-violet-300/15 bg-[#140c24]/85 p-4 sm:p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              {onBack ? (
                <button
                  type="button"
                  onClick={onBack}
                  className="mb-3 inline-flex items-center gap-2 rounded-xl border border-violet-300/25 bg-violet-950/35 px-3 py-2 text-xs font-semibold text-violet-100 transition hover:border-amber-300/60 hover:text-amber-200"
                >
                  <ArrowLeft className="h-4 w-4" />
                  Back
                </button>
              ) : null}
              <h1 className="font-['Sora'] text-3xl font-semibold tracking-tight text-white sm:text-4xl">Crowdfunding</h1>
              <p className="mt-1 text-sm text-violet-100/70">Support ideas, fund innovation, empower communities</p>
            </div>
            <button
              type="button"
              onClick={onCreateCampaign}
              className="h-10 rounded-xl bg-gradient-to-r from-amber-300 via-amber-400 to-yellow-500 px-4 text-sm font-bold text-black shadow-[0_0_22px_rgba(245,158,11,0.35)] transition hover:brightness-105 active:scale-[0.99]"
            >
              Create Campaign
            </button>
          </div>

          <div className="mt-4 grid gap-2 sm:grid-cols-[1fr_auto]">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-violet-200/70" />
              <input
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Search campaigns"
                className="w-full rounded-xl border border-violet-300/20 bg-[#0f091a] py-2.5 pl-10 pr-3 text-sm text-white outline-none transition focus:border-amber-300/65"
              />
            </div>
            <button className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-violet-300/25 bg-violet-500/10 px-4 text-sm font-semibold text-violet-100 transition hover:border-amber-300/60 hover:text-amber-200">
              <Filter className="h-4 w-4" />
              Filter
            </button>
          </div>
        </header>

        <section className="mt-5 rounded-2xl border border-violet-300/15 bg-gradient-to-br from-[#22133b] via-[#1b112f] to-[#2d1f1a] p-5 shadow-[0_14px_35px_rgba(0,0,0,0.35)] sm:p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="max-w-3xl">
              <p className="inline-flex items-center gap-1 rounded-full border border-amber-300/35 bg-amber-300/12 px-2.5 py-1 text-[11px] font-semibold text-amber-200">
                <Sparkles className="h-3.5 w-3.5" />
                Featured Campaign
              </p>
              <h2 className="mt-2 font-['Sora'] text-2xl font-semibold text-white">{featuredCampaign.title}</h2>
              <p className="mt-2 text-sm text-violet-100/75">{featuredCampaign.description}</p>
            </div>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => onViewCampaign?.(featuredCampaign.id)}
                className="h-10 rounded-xl border border-violet-300/20 bg-violet-500/15 px-4 text-sm font-semibold text-violet-100 transition hover:border-amber-300/55 hover:text-amber-200"
              >
                View Campaign
              </button>
              <button
                type="button"
                onClick={() => onSupportCampaign?.(featuredCampaign.id)}
                className="h-10 rounded-xl border border-amber-300/55 bg-gradient-to-r from-amber-300 via-amber-400 to-yellow-500 px-4 text-sm font-semibold text-black transition hover:brightness-105"
              >
                Support Campaign
              </button>
            </div>
          </div>

          <div className="mt-4">
            <div className="flex items-center justify-between text-xs">
              <span className="text-violet-100/70">{formatNaira(featuredCampaign.raised)} raised</span>
              <span className="text-violet-100/70">Target: {formatNaira(featuredCampaign.target)}</span>
            </div>
            <div className="mt-1.5 h-2.5 w-full rounded-full bg-violet-950/65">
              <div
                className="h-full rounded-full bg-gradient-to-r from-amber-300 via-amber-400 to-yellow-500 transition-all duration-500"
                style={{ width: `${percent(featuredCampaign.raised, featuredCampaign.target)}%` }}
              />
            </div>
          </div>
        </section>

        <section className="mt-5 rounded-2xl border border-violet-300/15 bg-[#140c24]/80 p-3">
          <div className="flex gap-2 overflow-x-auto pb-1">
            {categories.map((category) => (
              <button
                key={category}
                type="button"
                onClick={() => setActiveCategory(category)}
                className={`whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition ${activeCategory === category ? "bg-gradient-to-r from-amber-300 to-yellow-500 text-black shadow-[0_0_18px_rgba(245,158,11,0.45)]" : "border border-violet-300/20 bg-violet-500/10 text-violet-100/85 hover:border-violet-200/45 hover:text-white"}`}
              >
                {category}
              </button>
            ))}
          </div>
        </section>

        <section className="mt-5 grid gap-4 md:grid-cols-2">
          {filteredCampaigns.length ? (
            filteredCampaigns.map((campaign) => (
              <article
                key={campaign.id}
                className="rounded-2xl border border-violet-300/15 bg-[#120b20]/85 p-4 shadow-[0_10px_24px_rgba(0,0,0,0.35)] transition duration-300 hover:-translate-y-0.5 hover:border-amber-300/35 sm:p-5"
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h3 className="text-base font-semibold text-white">{campaign.title}</h3>
                    <p className="mt-1 text-sm text-violet-100/70">{campaign.description}</p>
                  </div>
                  <span className="rounded-full border border-violet-300/25 bg-violet-500/12 px-2.5 py-1 text-xs font-semibold text-violet-100">
                    {campaign.category}
                  </span>
                </div>

                <div className="mt-4">
                  <div className="flex items-center justify-between text-xs">
                    <span className="text-violet-100/70">{formatNaira(campaign.raised)} raised</span>
                    <span className="text-violet-100/70">Target: {formatNaira(campaign.target)}</span>
                  </div>
                  <div className="mt-1.5 h-2.5 w-full rounded-full bg-violet-950/65">
                    <div
                      className="h-full rounded-full bg-gradient-to-r from-amber-300 via-amber-400 to-yellow-500 transition-all duration-500"
                      style={{ width: `${percent(campaign.raised, campaign.target)}%` }}
                    />
                  </div>
                </div>

                <div className="mt-4 flex items-center justify-between gap-2">
                  <span className="text-xs text-violet-100/65">{campaign.daysRemaining} days remaining</span>
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => onViewCampaign?.(campaign.id)}
                      className="h-9 rounded-xl border border-violet-300/20 bg-violet-500/12 px-4 text-sm font-semibold text-violet-100 transition hover:border-amber-300/55 hover:text-amber-200"
                    >
                      View Campaign
                    </button>
                    <button
                      type="button"
                      onClick={() => onSupportCampaign?.(campaign.id)}
                      className="h-9 rounded-xl border border-amber-300/55 bg-gradient-to-r from-amber-300 via-amber-400 to-yellow-500 px-4 text-sm font-semibold text-black transition hover:brightness-105"
                    >
                      Support Campaign
                    </button>
                  </div>
                </div>
              </article>
            ))
          ) : (
            <div className="col-span-full rounded-2xl border border-violet-300/15 bg-[#120b20]/85 p-12 text-center">
              <p className="text-base font-semibold text-violet-50">No Campaigns Available</p>
              <p className="mt-1 text-sm text-violet-100/65">Be the first to start a movement</p>
              <button
                type="button"
                onClick={onCreateCampaign}
                className="mt-4 h-10 rounded-xl bg-gradient-to-r from-amber-300 to-yellow-500 px-4 text-sm font-bold text-black transition hover:brightness-105"
              >
                Create Campaign
              </button>
            </div>
          )}
        </section>

        <p className="mt-6 text-center text-xs text-violet-100/60">
          All contributions are transparently recorded within the ExaEarn ecosystem.
        </p>
      </section>
    </main>
  );
}

export default Crowdfunding;

