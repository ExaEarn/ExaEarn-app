import { useEffect, useState } from "react";
import { ArrowLeft, BadgeCheck, FileSearch, LandPlot, ShieldCheck, Sprout } from "lucide-react";
import Image from "../../assets/Image";
import { useAuth } from "../../context/AuthContext";
import { applyAsFarmer, fetchAgriProjects } from "../../services/agriApi";
import "./SubscriptionPage.css";

const reviewSteps = [
  { icon: FileSearch, title: "Application review", detail: "Submission begins review; it does not grant verified status." },
  { icon: BadgeCheck, title: "Identity and farm evidence", detail: "ExaEarn reuses account verification and separately reviews land or operating evidence." },
  { icon: ShieldCheck, title: "Controlled project access", detail: "Only approved farmers can manage live projects or receive approved disbursements." },
];

function SubscriptionPage({ onBack }) {
  const { apiBaseUrl, token, user } = useAuth();
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [application, setApplication] = useState({ name: "", location: "", experienceYears: 0, bio: "", hasTractor: false, hasIrrigation: false });
  const [applicationState, setApplicationState] = useState({ submitting: false, error: "", success: "" });

  useEffect(() => {
    let active = true;
    fetchAgriProjects({ apiBaseUrl, token, params: { per_page: 50 } })
      .then((payload) => { if (active) setProjects(Array.isArray(payload?.data?.data) ? payload.data.data : []); })
      .catch(() => { if (active) setProjects([]); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [apiBaseUrl, token]);

  const handleFarmerApplication = async () => {
    if (!application.location.trim()) {
      setApplicationState({ submitting: false, error: "Farm location is required.", success: "" });
      return;
    }
    try {
      setApplicationState({ submitting: true, error: "", success: "" });
      await applyAsFarmer({ apiBaseUrl, token, payload: {
        name: application.name || user?.name || "",
        location: application.location,
        experience_years: Number(application.experienceYears || 0),
        bio: application.bio,
        equipment_details: { tractor: application.hasTractor, irrigation: application.hasIrrigation },
      } });
      setApplicationState({ submitting: false, error: "", success: "Application submitted for identity, farm and land review." });
    } catch (error) {
      setApplicationState({ submitting: false, error: error.message || "Unable to submit farmer application.", success: "" });
    }
  };

  return (
    <div className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)] subscription-page">
      <main className="mx-auto w-full max-w-6xl px-3 pb-10 pt-4 sm:px-5 sm:pt-6">
        <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)] sm:p-6">
          <header className="flex items-center justify-between gap-4">
            <div><p className="text-xs uppercase tracking-[0.18em] text-[var(--exa-gold)]">ExaEarn AgriTech</p><h1 className="mt-1 font-['Sora'] text-2xl font-semibold sm:text-3xl">Farmer and project access</h1></div>
            {onBack ? <button type="button" onClick={onBack} className="btn-outline inline-flex min-h-11 items-center gap-2"><ArrowLeft className="h-4 w-4" />Back</button> : null}
          </header>

          <section className="relative mt-6 min-h-[270px] overflow-hidden rounded-2xl border border-[var(--exa-border)] sm:min-h-[320px]">
            <img src={Image.agriculture} alt="Agricultural field" className="absolute inset-0 h-full w-full object-cover opacity-40" />
            <div className="absolute inset-0 bg-[linear-gradient(100deg,var(--exa-bg-primary)_5%,rgba(8,9,11,.88)_48%,rgba(8,9,11,.28))]" />
            <div className="relative max-w-2xl p-5 sm:p-8">
              <span className="inline-flex items-center gap-2 rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-surface)] px-3 py-1 text-xs text-[var(--exa-gold)]"><LandPlot className="h-4 w-4" />Evidence-led agriculture</span>
              <h2 className="mt-4 font-['Sora'] text-3xl font-semibold leading-tight sm:text-5xl">Build verified agricultural projects with accountable funding.</h2>
              <p className="mt-4 text-sm leading-6 text-[var(--exa-text-secondary)] sm:text-base">Apply as a farmer, submit real farm evidence, and follow the review process. Land, insurance, harvest and revenue are never marked verified from an upload alone.</p>
              <p className="mt-4 text-xs text-[var(--exa-text-muted)]">{loading ? "Loading project registry..." : `${projects.length} projects currently exist in the AgriTech registry.`}</p>
            </div>
          </section>

          <section className="mt-7 grid gap-3 md:grid-cols-3">
            {reviewSteps.map(({ icon: Icon, title, detail }) => <article key={title} className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4"><Icon className="h-5 w-5 text-[var(--exa-gold)]" aria-hidden="true" /><h3 className="mt-3 text-sm font-semibold">{title}</h3><p className="mt-2 text-xs leading-5 text-[var(--exa-text-secondary)]">{detail}</p></article>)}
          </section>

          <section className="mt-7 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 sm:p-6">
            <div className="flex items-start gap-3"><Sprout className="mt-1 h-6 w-6 text-[var(--exa-gold)]" aria-hidden="true" /><div><h2 className="font-['Sora'] text-xl font-semibold">Farmer application</h2><p className="mt-1 text-sm text-[var(--exa-text-secondary)]">This begins due diligence. Approval requires identity and land/farm evidence review.</p></div></div>
            <div className="mt-5 grid gap-4 sm:grid-cols-2">
              <label className="text-sm text-[var(--exa-text-secondary)]">Full name<input type="text" value={application.name} onChange={(event) => setApplication((current) => ({ ...current, name: event.target.value }))} className="exa-input mt-2 w-full" /></label>
              <label className="text-sm text-[var(--exa-text-secondary)]">Farm location<input required type="text" value={application.location} onChange={(event) => setApplication((current) => ({ ...current, location: event.target.value }))} className="exa-input mt-2 w-full" /></label>
              <label className="text-sm text-[var(--exa-text-secondary)]">Years of experience<input type="number" min="0" value={application.experienceYears} onChange={(event) => setApplication((current) => ({ ...current, experienceYears: event.target.value }))} className="exa-input mt-2 w-full" /></label>
              <label className="text-sm text-[var(--exa-text-secondary)]">Background<textarea value={application.bio} onChange={(event) => setApplication((current) => ({ ...current, bio: event.target.value }))} className="exa-input mt-2 min-h-24 w-full resize-y" /></label>
            </div>
            <div className="mt-4 flex flex-wrap gap-4 text-sm text-[var(--exa-text-secondary)]">
              <label className="inline-flex min-h-11 items-center gap-2"><input type="checkbox" checked={application.hasTractor} onChange={(event) => setApplication((current) => ({ ...current, hasTractor: event.target.checked }))} />Tractor access</label>
              <label className="inline-flex min-h-11 items-center gap-2"><input type="checkbox" checked={application.hasIrrigation} onChange={(event) => setApplication((current) => ({ ...current, hasIrrigation: event.target.checked }))} />Irrigation access</label>
            </div>
            <button type="button" onClick={handleFarmerApplication} disabled={applicationState.submitting} className="exa-button-primary mt-5 min-h-11 px-5 disabled:opacity-60">{applicationState.submitting ? "Submitting..." : "Submit for review"}</button>
            {applicationState.error ? <p role="alert" className="mt-3 text-sm text-rose-300">{applicationState.error}</p> : null}
            {applicationState.success ? <p role="status" className="mt-3 text-sm text-emerald-300">{applicationState.success}</p> : null}
          </section>
        </div>
      </main>
    </div>
  );
}

export default SubscriptionPage;
