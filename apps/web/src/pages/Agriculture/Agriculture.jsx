import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, LandPlot } from "lucide-react";
import LandCard from "./components/LandCard";
import LeasePanel from "./components/LeasePanel";
import logo from "../../assets/images/exaearn-logo.png";
import { useAuth } from "../../context/AuthContext";
import { fetchAgriProjects } from "../../services/agriApi";

const feeCards = [
  {
    label: "Tokenization Fee",
    value: "2%",
    detail: "NFT minting, compliance checks, and registry onboarding.",
  },
  {
    label: "Transfer Fee",
    value: "1.5%",
    detail: "Secondary trades routed to the protocol treasury.",
  },
  {
    label: "Leasing Subscription",
    value: "Monthly Plan",
    detail: "Subscription access for farmer leasing and support.",
  },
];

function Agriculture({ onBack, onOpenSubscribe, onOpenAcquireShare }) {
  const { apiBaseUrl, token } = useAuth();
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    let active = true;

    const loadProjects = async () => {
      try {
        setLoading(true);
        setError("");
        const payload = await fetchAgriProjects({
          apiBaseUrl,
          token,
          params: { per_page: 6 },
        });
        if (!active) {
          return;
        }

        const nextProjects = Array.isArray(payload?.data?.data) ? payload.data.data : [];
        setProjects(nextProjects);
      } catch (nextError) {
        if (active) {
          setError(nextError.message || "Unable to load agricultural projects.");
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    };

    loadProjects();

    return () => {
      active = false;
    };
  }, [apiBaseUrl, token]);

  const parcelData = useMemo(() => {
    const themes = ["emerald", "gold", "violet"];

    return projects.map((project, index) => {
      const totalShares = Number(project?.share?.total_shares || 0);
      const sharesAvailable = Number(project?.share?.shares_available || 0);
      const fundedPercent = totalShares > 0 ? Math.round(((totalShares - sharesAvailable) / totalShares) * 100) : 0;

      return {
        id: project.id,
        name: project.project_name,
        size: `${project.farm_size} ${project.farm_size_unit || "acres"}`,
        location: project.location,
        availability: Math.max(0, Math.min(100, 100 - fundedPercent)),
        theme: themes[index % themes.length],
      };
    });
  }, [projects]);

  return (
    <div className="min-h-screen text-[var(--exa-text-primary)] bg-[var(--exa-bg-primary)] ">
      <div className="container w-full max-w-sm px-3 pt-4 pb-6 mx-auto sm:max-w-lg sm:px-4 sm:pt-6 md:max-w-2xl lg:max-w-5xl xl:max-w-6xl">
        <div className="p-4 shadow-xl glass-card rounded-3xl sm:p-6">
          <header className="mb-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="flex h-11 w-11 items-center justify-center rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)]">
                  <img src={logo} alt="ExaEarn" className="h-6 w-6 object-contain" />
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.25em] text-[var(--exa-gold)]">ExaEarn</p>
                  <p className="text-sm text-[var(--exa-text-muted)]">Agriculture</p>
                </div>
              </div>
              {onBack ? (
                <button type="button" onClick={onBack} className="btn-outline inline-flex items-center gap-2">
                  <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                  Back
                </button>
              ) : null}
            </div>

            <div className="mt-5 rounded-3xl border border-[var(--exa-border)] bg-gradient-to-br from-[var(--exa-surface)] via-[var(--exa-surface-elevated)] to-[var(--exa-surface)] p-5 shadow-[var(--exa-shadow-soft)]">
              <div className="flex items-start gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold)]">
                  <LandPlot className="h-5 w-5" aria-hidden="true" />
                </div>
                <div>
                  <h1 className="font-['Sora'] text-3xl font-semibold text-[var(--exa-text-primary)] sm:text-4xl">
                    NFT & Tokenized
                    <span className="text-[var(--exa-gold)]"> Land Ownership</span>
                  </h1>
                  <p className="mt-3 text-sm leading-relaxed text-[var(--exa-text-secondary)] sm:text-base">
                    Secure blockchain land registry and subscription investment for agriculture.
                  </p>
                  <p className="mt-2 text-xs text-[var(--exa-text-secondary)]">
                    {loading ? "Loading live projects..." : `${projects.length} live projects available for funding.`}
                  </p>
                  <button
                    type="button"
                    onClick={onOpenSubscribe}
                    className="mt-4 rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 py-2 text-xs font-semibold text-[var(--exa-gold-contrast)] transition-all duration-300 hover:scale-[1.01] hover:shadow-[var(--exa-shadow-gold)]"
                  >
                    Subscribe Now
                  </button>
                </div>
              </div>
            </div>
          </header>

          <section className="grid gap-3 sm:grid-cols-2">
            <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
              <p className="text-sm font-semibold text-[var(--exa-text-primary)]">Land Disputes Solved</p>
              <p className="mt-2 text-xs text-[var(--exa-text-muted)]">
                Blockchain-based land titles to prevent conflicts.
              </p>
            </div>
            <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
              <p className="text-sm font-semibold text-[var(--exa-text-primary)]">Invest in Farmland</p>
              <p className="mt-2 text-xs text-[var(--exa-text-muted)]">Own fractional shares & lease to farmers.</p>
            </div>
          </section>

          <section className="mt-6">
            <div className="flex items-center justify-between">
              <h2 className="font-['Sora'] text-lg font-semibold text-[var(--exa-text-primary)]">Available Land Parcels</h2>
              <div className="flex items-center gap-1 text-[var(--exa-gold)]">
                <span className="h-1.5 w-1.5 rounded-full bg-[var(--exa-gold)]" />
                <span className="h-1.5 w-1.5 rounded-full bg-[var(--exa-gold-surface)]" />
              </div>
            </div>
            <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
              {parcelData.map((parcel) => (
                <LandCard key={parcel.id} parcel={parcel} onAcquireShare={onOpenAcquireShare} />
              ))}
            </div>
            {loading ? <p className="mt-3 text-xs text-[var(--exa-text-secondary)]">Fetching tokenized farm inventory...</p> : null}
            {!loading && error ? <p className="mt-3 text-xs text-rose-300">{error}</p> : null}
            {!loading && !error && parcelData.length === 0 ? (
              <p className="mt-3 text-xs text-[var(--exa-text-secondary)]">No farm projects have been published yet.</p>
            ) : null}
          </section>

          <section className="mt-6">
            <LeasePanel onSubscribe={onOpenSubscribe} />
          </section>

          <section className="mt-6">
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-4 py-3 text-xs text-[var(--exa-text-muted)]">
              {feeCards.map((fee, index) => (
                <div key={fee.label} className="flex items-center gap-2">
                  <span className="font-semibold text-[var(--exa-text-primary)]">{fee.label}</span>
                  <span className="text-[var(--exa-gold)]">{fee.value}</span>
                  {index < feeCards.length - 1 ? <span className="text-[var(--exa-text-secondary)]">|</span> : null}
                </div>
              ))}
            </div>
          </section>
        </div>
      </div>
    </div>
  );
}

export default Agriculture;
