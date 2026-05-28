import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  BadgeCheck,
  Blocks,
  BriefcaseBusiness,
  CalendarClock,
  Landmark,
  Link2,
  ShieldCheck,
  Sprout,
  UsersRound,
  Wallet,
} from "lucide-react";
import Image from "../../assets/Image";
import { useAuth } from "../../context/AuthContext";
import { fetchAgriProject, fetchAgriProjects, investInAgriProject } from "../../services/agriApi";
import "./AcquireShare.css";

const transparencyItems = [
  {
    icon: Blocks,
    title: "Blockchain-backed records",
    description: "Every share issuance and distribution is immutably logged on-chain for public auditability.",
  },
  {
    icon: BadgeCheck,
    title: "Verified farm partners",
    description: "Projects are screened with due diligence, land documentation, and periodic operational reports.",
  },
  {
    icon: ShieldCheck,
    title: "Smart contract payouts",
    description: "Returns are distributed through automated settlement logic to minimize processing risk.",
  },
  {
    icon: Link2,
    title: "Community impact tracking",
    description: "Impact metrics are tied to each project so investors can track outcomes beyond ROI.",
  },
];

const steps = ["Create Account", "Verify Identity", "Select Farm Project", "Acquire Shares", "Track & Earn"];

function addMonths(date, months) {
  const copy = new Date(date);
  copy.setMonth(copy.getMonth() + months);
  return copy;
}

function formatCurrency(value, currency = "NGN") {
  return new Intl.NumberFormat("en-NG", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

function mapProject(project) {
  const projectNode = project?.project || project;
  const fundingTarget = Number(project?.funding?.target || projectNode?.investment_target || 0);
  const raised = Number(project?.funding?.raised || 0);
  const totalShares = Number(projectNode?.share?.total_shares || project?.funding?.total_shares || 0);
  const sharesAvailable = Number(projectNode?.share?.shares_available || project?.funding?.shares_available || 0);

  return {
    id: String(projectNode.id),
    name: projectNode.project_name,
    crop: projectNode.crop_type,
    roiMin: 12,
    roiMax: 22,
    durationMonths: Number(projectNode.duration || 0),
    sharePriceNgn: Number(projectNode?.share?.price_per_share || 0),
    sharePriceUsd: Number(projectNode?.share?.price_per_share || 0),
    funding: fundingTarget > 0 ? Math.min(100, Math.round((raised / fundingTarget) * 100)) : 0,
    location: projectNode.location,
    expectedYield: Number(projectNode.expected_yield || 0),
    expectedHarvestDate: projectNode.expected_harvest_date || null,
    totalShares,
    sharesAvailable,
    progress: Array.isArray(project?.progress) ? project.progress : [],
  };
}

function ImpactCounter({ value, suffix }) {
  return (
    <p className="mt-2 text-3xl font-semibold text-[#D4AF37] sm:text-4xl">
      {Number(value || 0).toLocaleString()}
      {suffix}
    </p>
  );
}

function AcquireShare({ onBack, initialProjectId = null }) {
  const { apiBaseUrl, token, user } = useAuth();
  const [projects, setProjects] = useState([]);
  const [selectedProjectId, setSelectedProjectId] = useState(initialProjectId ? String(initialProjectId) : "");
  const [shareCount, setShareCount] = useState(5);
  const [walletConnected, setWalletConnected] = useState(false);
  const [loading, setLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");
  const [statusMessage, setStatusMessage] = useState("");

  useEffect(() => {
    let active = true;

    const loadProjects = async () => {
      try {
        setLoading(true);
        setErrorMessage("");
        const payload = await fetchAgriProjects({
          apiBaseUrl,
          token,
          params: { per_page: 20 },
        });
        const list = Array.isArray(payload?.data?.data) ? payload.data.data : [];
        const normalized = list.map(mapProject);

        if (!active) {
          return;
        }

        setProjects(normalized);
        setSelectedProjectId((current) => current || (normalized[0]?.id ?? ""));
      } catch (error) {
        if (active) {
          setErrorMessage(error.message || "Unable to load agricultural projects.");
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

  useEffect(() => {
    if (!selectedProjectId) {
      return undefined;
    }

    let active = true;

    const loadProject = async () => {
      try {
        const payload = await fetchAgriProject({
          apiBaseUrl,
          token,
          projectId: selectedProjectId,
        });

        if (!active) {
          return;
        }

        const normalized = mapProject(payload?.data || {});
        setProjects((current) => {
          const hasProject = current.some((project) => project.id === normalized.id);
          if (!hasProject) {
            return [normalized, ...current];
          }

          return current.map((project) => (project.id === normalized.id ? normalized : project));
        });
      } catch {
        // Keep the list data if dashboard refresh fails.
      }
    };

    loadProject();

    return () => {
      active = false;
    };
  }, [apiBaseUrl, selectedProjectId, token]);

  const selectedProject = useMemo(
    () => projects.find((project) => project.id === selectedProjectId) || projects[0],
    [projects, selectedProjectId],
  );

  const projection = useMemo(() => {
    if (!selectedProject) {
      return {
        investmentNgn: 0,
        grossReturn: 0,
        profit: 0,
        maturityDate: "N/A",
      };
    }

    const investmentNgn = selectedProject.sharePriceNgn * shareCount;
    const roiMid = (selectedProject.roiMin + selectedProject.roiMax) / 2;
    const grossReturn = investmentNgn * (1 + roiMid / 100);
    const profit = grossReturn - investmentNgn;
    const maturityDate = selectedProject.expectedHarvestDate
      ? new Date(selectedProject.expectedHarvestDate).toLocaleDateString("en-NG", {
          year: "numeric",
          month: "long",
          day: "numeric",
        })
      : addMonths(new Date(), selectedProject.durationMonths).toLocaleDateString("en-NG", {
          year: "numeric",
          month: "long",
          day: "numeric",
        });

    return {
      investmentNgn,
      grossReturn,
      profit,
      maturityDate,
    };
  }, [selectedProject, shareCount]);

  const impactStats = useMemo(() => {
    const projectCount = projects.length;
    const activeProjects = projects.filter((project) => project.funding > 0).length;
    const totalShares = projects.reduce((sum, project) => sum + Number(project.totalShares || 0), 0);
    const soldShares = projects.reduce(
      (sum, project) => sum + Math.max(0, Number(project.totalShares || 0) - Number(project.sharesAvailable || 0)),
      0,
    );

    return [
      { label: "Live Farm Projects", value: projectCount, suffix: "" },
      { label: "Active Funding Pools", value: activeProjects, suffix: "" },
      { label: "Tokenized Shares", value: totalShares, suffix: "" },
      { label: "Sold Shares", value: soldShares, suffix: "" },
    ];
  }, [projects]);

  const latestProgress = selectedProject?.progress?.slice(0, 3) || [];

  const scrollToProjects = () => {
    const element = document.getElementById("active-farms");
    if (element) {
      element.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  };

  const scrollToCalculator = () => {
    const element = document.getElementById("investment-calculator");
    if (element) {
      element.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  };

  const handleAcquireShares = async () => {
    if (!selectedProject?.id) {
      return;
    }

    try {
      setIsSubmitting(true);
      setErrorMessage("");
      setStatusMessage("");

      await investInAgriProject({
        apiBaseUrl,
        token,
        projectId: selectedProject.id,
        sharesOwned: shareCount,
        metadata: {
          source: "acquire-share-page",
          wallet_connected: walletConnected,
          investor_email: user?.email || null,
        },
      });

      setStatusMessage(`Purchased ${shareCount} shares in ${selectedProject.name}.`);

      const payload = await fetchAgriProject({
        apiBaseUrl,
        token,
        projectId: selectedProject.id,
      });
      const normalized = mapProject(payload?.data || {});
      setProjects((current) => current.map((project) => (project.id === normalized.id ? normalized : project)));
    } catch (error) {
      setErrorMessage(error.message || "Unable to complete share purchase.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen text-[#F8F8F8] exa-bg app-shell acquire-share-page">
      <div className="relative mx-auto w-full max-w-sm px-3 pb-10 pt-4 sm:max-w-lg sm:px-4 md:max-w-2xl lg:max-w-5xl xl:max-w-6xl">
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <div className="network-orb orb-one" />
          <div className="network-orb orb-two" />
          <div className="network-grid" />
        </div>

        <div className="relative rounded-3xl border border-[#D4AF37]/30 bg-[#0B0B0B]/70 p-4 shadow-[0_24px_56px_rgba(0,0,0,0.45)] backdrop-blur-xl sm:p-6">
          <header className="mb-8">
            <div className="flex items-center justify-between">
              <div className="text-xs uppercase tracking-[0.26em] text-[#D4AF37]/85">ExaEarn Acquire Share</div>
              {onBack ? (
                <button
                  type="button"
                  onClick={onBack}
                  className="inline-flex items-center gap-2 rounded-xl border border-[#D4AF37]/40 bg-[#0B0B0B]/65 px-3 py-2 text-xs font-medium text-[#F8F8F8] transition-all hover:border-[#D4AF37] hover:text-[#D4AF37]"
                >
                  <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                  Back
                </button>
              ) : null}
            </div>

            <section className="relative mt-5 overflow-hidden rounded-3xl border border-[#D4AF37]/35 hero-panel">
              <img
                src={Image.agriculture}
                alt="Farm landscape with Web3 overlays"
                className="h-[320px] w-full object-cover opacity-45 sm:h-[360px]"
              />
              <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(212,175,55,0.3),transparent_45%),linear-gradient(130deg,rgba(11,11,11,0.45),rgba(11,11,11,0.92))]" />
              <div className="absolute inset-0 p-5 sm:p-7">
                <div className="hero-chip">
                  <Landmark className="h-4 w-4" aria-hidden="true" />
                  Real Asset Fractional Ownership
                </div>
                <h1 className="mt-4 max-w-3xl font-['Sora'] text-3xl font-semibold leading-tight text-[#F8F8F8] sm:text-5xl">
                  <span className="text-[#D4AF37]">Invest in Agriculture.</span> Own Real Assets. Earn Real Returns.
                </h1>
                <p className="mt-4 max-w-2xl text-sm leading-relaxed text-[#F8F8F8]/85 sm:text-base">
                  Acquire fractional shares in verified agricultural projects across Africa. Track growth. Monitor
                  harvest cycles. Earn transparently.
                </p>
                <p className="mt-3 text-xs text-[#F8F8F8]/70">
                  {loading ? "Loading live project catalog..." : `${projects.length} projects synced from the Agri API.`}
                </p>
                <div className="mt-6 flex flex-wrap gap-3">
                  <button
                    type="button"
                    onClick={scrollToProjects}
                    className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] to-[#f0d16e] px-5 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.4)] transition-all hover:-translate-y-0.5"
                  >
                    Acquire Shares Now
                  </button>
                  <button
                    type="button"
                    onClick={scrollToProjects}
                    className="rounded-xl border border-[#F8F8F8]/60 bg-[#F8F8F8]/8 px-5 py-3 text-sm font-semibold text-[#F8F8F8] backdrop-blur-sm transition-all hover:border-[#D4AF37]/75 hover:text-[#D4AF37]"
                  >
                    View Active Farms
                  </button>
                </div>
              </div>
            </section>
          </header>

          <section id="active-farms" className="mb-8">
            <div className="mb-4 flex items-center justify-between gap-3">
              <h2 className="font-['Sora'] text-2xl font-semibold text-[#F8F8F8]">Available Farm Projects</h2>
              <div className="rounded-full border border-[#D4AF37]/45 px-3 py-1 text-xs text-[#D4AF37]">
                Verified Opportunities
              </div>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
              {projects.map((project) => (
                <article
                  key={project.id}
                  className="group rounded-2xl border border-[#D4AF37]/20 bg-[#0B0B0B]/65 p-5 shadow-[0_14px_28px_rgba(0,0,0,0.3)] backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:border-[#D4AF37]/45 hover:shadow-[0_18px_34px_rgba(212,175,55,0.14)]"
                >
                  <div className="flex items-start justify-between gap-3">
                    <h3 className="font-['Sora'] text-lg font-semibold text-[#F8F8F8]">{project.name}</h3>
                    <span className="rounded-full border border-[#D4AF37]/35 px-3 py-1 text-xs text-[#D4AF37]">
                      {project.crop}
                    </span>
                  </div>
                  <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div className="rounded-xl border border-[#F8F8F8]/10 bg-[#F8F8F8]/[0.04] p-3">
                      <p className="text-[#F8F8F8]/65">Expected ROI</p>
                      <p className="mt-1 font-semibold text-[#D4AF37]">
                        {project.roiMin}% - {project.roiMax}%
                      </p>
                    </div>
                    <div className="rounded-xl border border-[#F8F8F8]/10 bg-[#F8F8F8]/[0.04] p-3">
                      <p className="text-[#F8F8F8]/65">Duration</p>
                      <p className="mt-1 font-semibold text-[#F8F8F8]">{project.durationMonths} months</p>
                    </div>
                    <div className="col-span-2 rounded-xl border border-[#F8F8F8]/10 bg-[#F8F8F8]/[0.04] p-3">
                      <p className="text-[#F8F8F8]/65">Minimum Share Price</p>
                      <p className="mt-1 font-semibold text-[#F8F8F8]">
                        {formatCurrency(project.sharePriceNgn)} or ${project.sharePriceUsd}
                      </p>
                    </div>
                    <div className="col-span-2 rounded-xl border border-[#F8F8F8]/10 bg-[#F8F8F8]/[0.04] p-3">
                      <p className="text-[#F8F8F8]/65">Location / Yield</p>
                      <p className="mt-1 font-semibold text-[#F8F8F8]">
                        {project.location} • {project.expectedYield.toLocaleString()} expected yield
                      </p>
                    </div>
                  </div>
                  <div className="mt-4">
                    <div className="flex items-center justify-between text-xs">
                      <span className="text-[#F8F8F8]/70">Funding Progress</span>
                      <span className="font-semibold text-[#D4AF37]">{project.funding}% Complete</span>
                    </div>
                    <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-[#F8F8F8]/12">
                      <div
                        className="h-full rounded-full bg-gradient-to-r from-[#D4AF37] via-[#eed48a] to-[#D4AF37]"
                        style={{ width: `${project.funding}%` }}
                      />
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={() => {
                      setSelectedProjectId(project.id);
                      scrollToCalculator();
                    }}
                    className="mt-5 w-full rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] to-[#f2d67a] px-4 py-3 text-sm font-semibold text-[#0B0B0B] transition-all group-hover:shadow-[0_0_20px_rgba(212,175,55,0.35)]"
                  >
                    Acquire Share
                  </button>
                </article>
              ))}
            </div>
            {loading ? <p className="mt-4 text-sm text-[#F8F8F8]/70">Loading active farm projects...</p> : null}
            {!loading && !projects.length ? (
              <p className="mt-4 text-sm text-[#F8F8F8]/70">No tokenized farm projects are available yet.</p>
            ) : null}
          </section>

          <section
            id="investment-calculator"
            className="mb-8 rounded-2xl border border-[#D4AF37]/25 bg-[#F8F8F8]/[0.03] p-5 backdrop-blur-lg"
          >
            <div className="flex items-center gap-2 text-[#D4AF37]">
              <BriefcaseBusiness className="h-5 w-5" aria-hidden="true" />
              <h2 className="font-['Sora'] text-xl font-semibold">Investment Calculator</h2>
            </div>
            <div className="mt-4 grid gap-4 lg:grid-cols-2">
              <div className="space-y-4 rounded-xl border border-[#F8F8F8]/12 bg-[#0B0B0B]/55 p-4">
                <label className="block text-sm text-[#F8F8F8]/85">
                  Select Farm Project
                  <select
                    value={selectedProjectId}
                    onChange={(event) => setSelectedProjectId(event.target.value)}
                    className="mt-2 w-full rounded-lg border border-[#D4AF37]/35 bg-[#0B0B0B] px-3 py-2 text-sm text-[#F8F8F8] outline-none transition-all focus:border-[#D4AF37]"
                  >
                    {projects.map((project) => (
                      <option key={project.id} value={project.id}>
                        {project.name}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block text-sm text-[#F8F8F8]/85">
                  Number of Shares
                  <input
                    type="number"
                    min={1}
                    max={Math.max(1, selectedProject?.sharesAvailable || 1)}
                    value={shareCount}
                    onChange={(event) => setShareCount(Math.max(1, Number(event.target.value) || 1))}
                    className="mt-2 w-full rounded-lg border border-[#D4AF37]/35 bg-[#0B0B0B] px-3 py-2 text-sm text-[#F8F8F8] outline-none transition-all focus:border-[#D4AF37]"
                  />
                </label>
                <div className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3 text-xs text-[#F8F8F8]/75">
                  Shares available: {Number(selectedProject?.sharesAvailable || 0).toLocaleString()} /{" "}
                  {Number(selectedProject?.totalShares || 0).toLocaleString()}
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3 rounded-xl border border-[#D4AF37]/25 bg-[#0B0B0B]/60 p-4">
                <div className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                  <p className="text-xs text-[#F8F8F8]/70">Projected Return</p>
                  <p className="mt-2 text-base font-semibold text-[#D4AF37]">{formatCurrency(projection.grossReturn)}</p>
                </div>
                <div className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                  <p className="text-xs text-[#F8F8F8]/70">Estimated Profit</p>
                  <p className="mt-2 text-base font-semibold text-[#D4AF37]">{formatCurrency(projection.profit)}</p>
                </div>
                <div className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                  <p className="text-xs text-[#F8F8F8]/70">Capital Invested</p>
                  <p className="mt-2 text-base font-semibold text-[#F8F8F8]">{formatCurrency(projection.investmentNgn)}</p>
                </div>
                <div className="rounded-lg border border-[#F8F8F8]/12 bg-[#F8F8F8]/[0.03] p-3">
                  <p className="text-xs text-[#F8F8F8]/70">Maturity Date</p>
                  <p className="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-[#F8F8F8]">
                    <CalendarClock className="h-4 w-4 text-[#D4AF37]" aria-hidden="true" />
                    {projection.maturityDate}
                  </p>
                </div>
              </div>
            </div>
            <div className="mt-4 flex flex-wrap items-center gap-3">
              <button
                type="button"
                onClick={handleAcquireShares}
                disabled={isSubmitting || loading || !selectedProject?.id}
                className="rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] to-[#f2d67a] px-4 py-3 text-sm font-semibold text-[#0B0B0B] disabled:cursor-not-allowed disabled:opacity-60"
              >
                {isSubmitting ? "Processing..." : "Confirm Share Purchase"}
              </button>
              {statusMessage ? <p className="text-sm text-emerald-300">{statusMessage}</p> : null}
              {errorMessage ? <p className="text-sm text-rose-300">{errorMessage}</p> : null}
            </div>
          </section>

          <section className="mb-8 rounded-2xl border border-[#D4AF37]/20 bg-[#0B0B0B]/65 p-5">
            <h2 className="font-['Sora'] text-2xl font-semibold text-[#F8F8F8]">Live Farm Progress</h2>
            <div className="mt-4 grid gap-3">
              {latestProgress.length ? (
                latestProgress.map((entry) => (
                  <article key={entry.id} className="rounded-xl border border-[#F8F8F8]/10 bg-[#F8F8F8]/[0.03] p-4">
                    <div className="flex items-center justify-between gap-3">
                      <p className="text-sm font-semibold capitalize text-[#D4AF37]">{entry.growth_stage}</p>
                      <p className="text-xs text-[#F8F8F8]/60">{new Date(entry.recorded_at).toLocaleDateString("en-NG")}</p>
                    </div>
                    <p className="mt-2 text-sm text-[#F8F8F8]/80">{entry.update_description}</p>
                    <p className="mt-2 text-xs text-[#F8F8F8]/60">Verification: {entry.verification_status}</p>
                  </article>
                ))
              ) : (
                <p className="text-sm text-[#F8F8F8]/70">No farm progress reports have been published for this project yet.</p>
              )}
            </div>
          </section>

          <section className="mb-8">
            <h2 className="font-['Sora'] text-2xl font-semibold text-[#F8F8F8]">Transparency & Security</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              {transparencyItems.map((item) => (
                <article
                  key={item.title}
                  className="rounded-2xl border border-[#D4AF37]/20 bg-[#F8F8F8]/[0.03] p-4 backdrop-blur-md"
                >
                  <item.icon className="h-6 w-6 text-[#D4AF37]" aria-hidden="true" />
                  <h3 className="mt-3 text-lg font-semibold text-[#F8F8F8]">{item.title}</h3>
                  <p className="mt-1 text-sm leading-relaxed text-[#F8F8F8]/75">{item.description}</p>
                </article>
              ))}
            </div>
          </section>

          <section className="mb-8 rounded-2xl border border-[#D4AF37]/20 bg-[#0B0B0B]/65 p-5">
            <h2 className="font-['Sora'] text-2xl font-semibold text-[#F8F8F8]">Community Impact</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {impactStats.map((stat) => (
                <article key={stat.label} className="rounded-xl border border-[#F8F8F8]/10 bg-[#F8F8F8]/[0.03] p-4">
                  <p className="text-xs uppercase tracking-[0.14em] text-[#F8F8F8]/65">{stat.label}</p>
                  <ImpactCounter value={stat.value} suffix={stat.suffix} />
                </article>
              ))}
            </div>
          </section>

          <section className="mb-8">
            <h2 className="font-['Sora'] text-2xl font-semibold text-[#F8F8F8]">How It Works</h2>
            <div className="mt-5 grid gap-3 md:grid-cols-5">
              {steps.map((step, index) => (
                <div key={step} className="relative rounded-xl border border-[#D4AF37]/20 bg-[#F8F8F8]/[0.03] p-4">
                  <p className="text-xs text-[#D4AF37]">Step {index + 1}</p>
                  <p className="mt-2 text-sm font-semibold text-[#F8F8F8]">{step}</p>
                  {index < steps.length - 1 ? <span className="timeline-connector" aria-hidden="true" /> : null}
                </div>
              ))}
            </div>
          </section>

          <section className="mb-8 overflow-hidden rounded-2xl border border-[#D4AF37]/25 bg-[#0B0B0B]/65 p-5">
            <h2 className="font-['Sora'] text-2xl font-semibold text-[#F8F8F8]">Web3 Integration</h2>
            <div className="mt-4 grid gap-4 lg:grid-cols-2">
              <div className="rounded-xl border border-[#F8F8F8]/10 bg-[#F8F8F8]/[0.03] p-4">
                <p className="text-sm text-[#F8F8F8]/80">Connect your wallet and fund with crypto or local payment rails.</p>
                <div className="mt-4 flex flex-wrap gap-3">
                  <button
                    type="button"
                    onClick={() => setWalletConnected((current) => !current)}
                    className="inline-flex items-center gap-2 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] to-[#f1d57a] px-4 py-2 text-sm font-semibold text-[#0B0B0B]"
                  >
                    <Wallet className="h-4 w-4" aria-hidden="true" />
                    {walletConnected ? "Wallet Connected" : "Connect Wallet"}
                  </button>
                  <button
                    type="button"
                    className="rounded-xl border border-[#F8F8F8]/50 bg-[#F8F8F8]/10 px-4 py-2 text-sm font-semibold text-[#F8F8F8] transition-all hover:border-[#D4AF37]/70 hover:text-[#D4AF37]"
                  >
                    Fund via Crypto or Local Payment
                  </button>
                </div>
              </div>
              <div className="network-canvas rounded-xl border border-[#D4AF37]/20 bg-[#F8F8F8]/[0.03] p-4">
                <div className="network-node node-a" />
                <div className="network-node node-b" />
                <div className="network-node node-c" />
                <div className="network-node node-d" />
                <div className="network-link link-ab" />
                <div className="network-link link-bc" />
                <div className="network-link link-cd" />
                <div className="network-link link-da" />
              </div>
            </div>
          </section>

          <section className="rounded-2xl border border-[#D4AF37]/30 bg-gradient-to-r from-[#D4AF37]/15 via-[#D4AF37]/5 to-transparent p-6 text-center">
            <div className="mx-auto max-w-3xl">
              <div className="inline-flex items-center gap-2 rounded-full border border-[#D4AF37]/45 bg-[#0B0B0B]/65 px-3 py-1 text-xs uppercase tracking-[0.16em] text-[#D4AF37]">
                <Sprout className="h-4 w-4" aria-hidden="true" />
                Build Prosperity
              </div>
              <h2 className="mt-4 font-['Sora'] text-3xl font-semibold text-[#F8F8F8] sm:text-4xl">
                Grow Your Wealth While Growing Communities.
              </h2>
              <button
                type="button"
                onClick={scrollToProjects}
                className="mt-5 rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] to-[#f2d57a] px-6 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_24px_rgba(212,175,55,0.35)] transition-all hover:-translate-y-0.5"
              >
                Start Acquiring Shares Today
              </button>
            </div>
          </section>
        </div>

        <div className="mt-5 grid gap-3 sm:grid-cols-3">
          <div className="rounded-xl border border-[#D4AF37]/20 bg-[#0B0B0B]/55 p-3 text-center text-xs text-[#F8F8F8]/75">
            <UsersRound className="mx-auto h-4 w-4 text-[#D4AF37]" aria-hidden="true" />
            Community-first investment rails
          </div>
          <div className="rounded-xl border border-[#D4AF37]/20 bg-[#0B0B0B]/55 p-3 text-center text-xs text-[#F8F8F8]/75">
            <Blocks className="mx-auto h-4 w-4 text-[#D4AF37]" aria-hidden="true" />
            Auditable blockchain proofs
          </div>
          <div className="rounded-xl border border-[#D4AF37]/20 bg-[#0B0B0B]/55 p-3 text-center text-xs text-[#F8F8F8]/75">
            <ShieldCheck className="mx-auto h-4 w-4 text-[#D4AF37]" aria-hidden="true" />
            Institutional-grade security posture
          </div>
        </div>
      </div>
    </div>
  );
}

export default AcquireShare;
