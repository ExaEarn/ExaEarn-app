import { useMemo, useState } from "react";
import { ArrowLeft, Search, Sparkles, Wallet, RefreshCw } from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import { useWeb3Wallet } from "../../hooks/useWeb3Wallet";
import { useCrowdfunding } from "../../hooks/useCrowdfunding";

const categories = ["All", "Technology", "Agriculture", "Education", "Health", "Web3", "AI", "Fintech", "General"];

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

function humanStatus(status) {
  return String(status || "active").replace(/^./, (char) => char.toUpperCase());
}

function Crowdfunding({ onBack, onCreateCampaign, onSupportCampaign, onViewCampaign }) {
  const [activeCategory, setActiveCategory] = useState("All");
  const [query, setQuery] = useState("");
  const { apiBaseUrl, token } = useAuth();
  const wallet = useWeb3Wallet();
  const { campaigns, loading, error, refresh, txState, dataSource } = useCrowdfunding({ apiBaseUrl, token, wallet });

  const featuredCampaign = campaigns[0] || null;

  const filteredCampaigns = useMemo(() => {
    return campaigns.filter((campaign) => {
      const categoryMatch = activeCategory === "All" || (campaign.category || "General") === activeCategory;
      const q = query.trim().toLowerCase();
      const queryMatch =
        !q ||
        String(campaign.title || "")
          .toLowerCase()
          .includes(q) ||
        String(campaign.description || "")
          .toLowerCase()
          .includes(q);
      return categoryMatch && queryMatch;
    });
  }, [activeCategory, campaigns, query]);

  return (
    <main className="relative min-h-screen overflow-hidden bg-[var(--exa-bg-primary)] px-4 py-8 text-[var(--exa-text-primary)] sm:px-6 sm:py-10">
      <section className="mx-auto w-full max-w-7xl rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)] backdrop-blur-xl sm:p-6 lg:p-8">
        <header className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 sm:p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              {onBack ? (
                <button
                  type="button"
                  onClick={onBack}
                  className="mb-3 inline-flex items-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-xs font-semibold text-[var(--exa-text-secondary)] transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                >
                  <ArrowLeft className="h-4 w-4" />
                  Back
                </button>
              ) : null}
              <h1 className="font-['Sora'] text-3xl font-semibold tracking-tight text-[var(--exa-text-primary)] sm:text-4xl">Crowdfunding</h1>
              <p className="mt-1 text-sm text-[var(--exa-text-muted)]">Escrow-backed campaigns with contributor governance</p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={refresh}
                className="inline-flex h-10 items-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 text-xs font-semibold text-[var(--exa-text-secondary)]"
              >
                <RefreshCw className="h-3.5 w-3.5" /> Refresh
              </button>
              <button
                type="button"
                onClick={() => wallet.connectMetaMask()}
                className="inline-flex h-10 items-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 text-xs font-semibold text-[var(--exa-text-secondary)]"
              >
                <Wallet className="h-3.5 w-3.5" />
                {wallet.isConnected ? wallet.shortAddress : "Connect Wallet"}
              </button>
              <button
                type="button"
                onClick={onCreateCampaign}
                className="h-10 rounded-xl bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 text-sm font-bold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] transition hover:brightness-105 active:scale-[0.99]"
              >
                Create Campaign
              </button>
            </div>
          </div>

          <div className="mt-3 flex flex-wrap items-center gap-2 text-xs">
            <span className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-2 py-1 text-[var(--exa-text-secondary)]">Data: {dataSource}</span>
            {loading ? <span className="text-[var(--exa-text-secondary)]">Syncing campaigns...</span> : null}
            {error ? <span className="text-[var(--exa-gold-light)]">{error}</span> : null}
            {txState.status !== "idle" ? (
              <span className="text-emerald-200">Tx: {txState.message}{txState.hash ? ` (${txState.hash.slice(0, 10)}...)` : ""}</span>
            ) : null}
          </div>

          <div className="mt-4 grid gap-2 sm:grid-cols-[1fr_auto]">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--exa-text-muted)]" />
              <input
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Search campaigns"
                className="w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] py-2.5 pl-10 pr-3 text-sm text-[var(--exa-text-primary)] outline-none transition focus:border-[var(--exa-border-active)]"
              />
            </div>
            <div className="inline-flex h-10 items-center justify-center rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 text-xs font-semibold text-[var(--exa-text-secondary)]">
              Lifecycle statuses: active / funded / failed / completed / frozen
            </div>
          </div>
        </header>

        {featuredCampaign ? (
          <section className="mt-5 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-5 shadow-[var(--exa-shadow-soft)] sm:p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="max-w-3xl">
              <p className="inline-flex items-center gap-1 rounded-full border border-amber-300/35 bg-amber-300/12 px-2.5 py-1 text-[11px] font-semibold text-[var(--exa-gold-light)]">
                <Sparkles className="h-3.5 w-3.5" />
                Featured Campaign
              </p>
              <h2 className="mt-2 font-['Sora'] text-2xl font-semibold text-[var(--exa-text-primary)]">{featuredCampaign.title}</h2>
              <p className="mt-2 text-sm text-[var(--exa-text-secondary)]">{featuredCampaign.description}</p>
              <p className="mt-2 text-xs text-[var(--exa-text-secondary)]">Status: {humanStatus(featuredCampaign.status)}</p>
            </div>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => onViewCampaign?.(featuredCampaign.id)}
                className="h-10 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 text-sm font-semibold text-[var(--exa-text-secondary)] transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
              >
                View Campaign
              </button>
              <button
                type="button"
                onClick={() => onSupportCampaign?.(featuredCampaign.id)}
                className="h-10 rounded-xl border border-amber-300/55 bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 text-sm font-semibold text-[var(--exa-gold-contrast)] transition hover:brightness-105"
              >
                Support Campaign
              </button>
            </div>
          </div>

          <div className="mt-4">
            <div className="flex items-center justify-between text-xs">
              <span className="text-[var(--exa-text-muted)]">{formatNaira(featuredCampaign.raised)} raised</span>
              <span className="text-[var(--exa-text-muted)]">Target: {formatNaira(featuredCampaign.goal_amount || featuredCampaign.target || 0)}</span>
            </div>
            <div className="mt-1.5 h-2.5 w-full rounded-full bg-[var(--exa-surface-hover)]">
              <div
                className="h-full rounded-full bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] transition-all duration-500"
                style={{ width: `${percent(featuredCampaign.raised_amount || featuredCampaign.raised || 0, featuredCampaign.goal_amount || featuredCampaign.target || 1)}%` }}
              />
            </div>
          </div>
          </section>
        ) : null}

        <section className="mt-5 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-3">
          <div className="flex gap-2 overflow-x-auto pb-1">
            {categories.map((category) => (
              <button
                key={category}
                type="button"
                onClick={() => setActiveCategory(category)}
                className={`whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition ${activeCategory === category ? "bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)]" : "border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)] hover:text-[var(--exa-text-primary)]"}`}
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
                className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-soft)] transition duration-300 hover:-translate-y-0.5 hover:border-[var(--exa-border-active)] sm:p-5"
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h3 className="text-base font-semibold text-[var(--exa-text-primary)]">{campaign.title}</h3>
                    <p className="mt-1 text-sm text-[var(--exa-text-muted)]">{campaign.description}</p>
                  </div>
                  <span className="rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-2.5 py-1 text-xs font-semibold text-[var(--exa-text-secondary)]">
                    {campaign.category || "General"}
                  </span>
                </div>

                <div className="mt-4">
                  <div className="flex items-center justify-between text-xs">
                    <span className="text-[var(--exa-text-muted)]">{formatNaira(campaign.raised_amount || campaign.raised || 0)} raised</span>
                    <span className="text-[var(--exa-text-muted)]">Target: {formatNaira(campaign.goal_amount || campaign.target || 0)}</span>
                  </div>
                  <div className="mt-1.5 h-2.5 w-full rounded-full bg-[var(--exa-surface-hover)]">
                    <div
                      className="h-full rounded-full bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] transition-all duration-500"
                      style={{ width: `${percent(campaign.raised_amount || campaign.raised || 0, campaign.goal_amount || campaign.target || 1)}%` }}
                    />
                  </div>
                </div>

                <div className="mt-4 flex items-center justify-between gap-2">
                  <span className="text-xs text-[var(--exa-text-muted)]">{humanStatus(campaign.status)}</span>
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => onViewCampaign?.(campaign.id)}
                      className="h-9 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 text-sm font-semibold text-[var(--exa-text-secondary)] transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                    >
                      View Campaign
                    </button>
                    <button
                      type="button"
                      onClick={() => onSupportCampaign?.(campaign.id)}
                      className="h-9 rounded-xl border border-amber-300/55 bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 text-sm font-semibold text-[var(--exa-gold-contrast)] transition hover:brightness-105"
                    >
                      Support Campaign
                    </button>
                  </div>
                </div>
              </article>
            ))
          ) : (
            <div className="col-span-full rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-12 text-center">
              <p className="text-base font-semibold text-[var(--exa-text-primary)]">No Campaigns Available</p>
              <p className="mt-1 text-sm text-[var(--exa-text-muted)]">Be the first to start a movement</p>
              <button
                type="button"
                onClick={onCreateCampaign}
                className="mt-4 h-10 rounded-xl bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 text-sm font-bold text-[var(--exa-gold-contrast)] transition hover:brightness-105"
              >
                Create Campaign
              </button>
            </div>
          )}
        </section>

        <p className="mt-6 text-center text-xs text-[var(--exa-text-muted)]">
          All contributions are transparently recorded within the ExaEarn ecosystem.
        </p>
      </section>
    </main>
  );
}

export default Crowdfunding;
