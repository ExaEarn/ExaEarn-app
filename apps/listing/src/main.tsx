import React, { FormEvent, useEffect, useMemo, useState } from "react";
import { createRoot } from "react-dom/client";
import { AlertTriangle, ArrowRight, CheckCircle2, ClipboardCheck, FileText, LockKeyhole, MessageSquare, ShieldCheck } from "lucide-react";
import "./styles.css";

type Organization = {
  id: number;
  legal_name: string;
  project_name: string;
  jurisdiction: string;
  website?: string | null;
};

type ListingApplication = {
  id: number;
  reference: string;
  application_type: string;
  application_status: string;
  integration_status: string;
  completion_percent: number;
  project_information?: Record<string, unknown>;
  asset_information?: Record<string, unknown>;
  blockchain_information?: Record<string, unknown>;
  reviews?: Array<{ review_type: string; status: string }>;
};

const apiBase = (import.meta.env.VITE_API_URL || "https://api.exaearn.com").replace(/\/$/, "");

async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${apiBase}${path}`, {
    credentials: "include",
    headers: { "Content-Type": "application/json", Accept: "application/json", ...(options.headers || {}) },
    ...options,
  });
  if (!response.ok) {
    const body = await response.json().catch(() => ({ message: response.statusText }));
    throw new Error(body.message || "Listing request failed.");
  }
  return response.json() as Promise<T>;
}

const requiredSections = [
  "project_information",
  "asset_information",
  "blockchain_information",
  "tokenomics",
  "technology",
  "security",
  "legal_compliance",
  "market_community",
  "liquidity",
  "listing_request",
];

function parseJson(value: string): Record<string, unknown> {
  try {
    const parsed = JSON.parse(value);
    return parsed && typeof parsed === "object" && !Array.isArray(parsed) ? parsed : {};
  } catch {
    return {};
  }
}

function StatusPill({ value }: { value: string }) {
  const tone = value.includes("APPROVED") || value === "LIVE" ? "good" : value.includes("REJECT") || value.includes("BLOCK") ? "bad" : "warn";
  return <span className={`pill ${tone}`}>{value.replaceAll("_", " ")}</span>;
}

function App() {
  const [organizations, setOrganizations] = useState<Organization[]>([]);
  const [applications, setApplications] = useState<ListingApplication[]>([]);
  const [selectedOrgId, setSelectedOrgId] = useState<number | "">("");
  const [selectedApplication, setSelectedApplication] = useState<ListingApplication | null>(null);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  const [orgForm, setOrgForm] = useState({
    legal_name: "",
    project_name: "",
    jurisdiction: "",
    website: "",
    business_email: "",
  });

  const [draftForm, setDraftForm] = useState({
    application_type: "NEW_TOKEN_LISTING",
    project_information: "{\"summary\":\"\",\"team\":\"\"}",
    asset_information: "{\"name\":\"\",\"symbol\":\"\",\"asset_type\":\"token\"}",
    blockchain_information: "{\"network\":\"\",\"contract_address\":\"\",\"token_standard\":\"ERC-20\"}",
    tokenomics: "{\"total_supply\":\"\",\"circulating_supply\":\"\"}",
    technology: "{\"repository\":\"\",\"audit_reports\":[]}",
    security: "{\"audit_provider\":\"\",\"known_incidents\":\"none\"}",
    legal_compliance: "{\"jurisdictions\":\"\",\"legal_opinion\":\"\"}",
    market_community: "{\"website\":\"\",\"community_channels\":[]}",
    liquidity: "{\"market_maker\":\"\",\"expected_depth\":\"\"}",
    listing_request: "{\"requested_pairs\":[\"TOKEN/USDT\"],\"launch_preference\":\"standard\"}",
  });

  const completion = useMemo(() => {
    const filled = requiredSections.filter((key) => Object.keys(parseJson(draftForm[key as keyof typeof draftForm])).length > 0).length;
    return Math.round((filled / requiredSections.length) * 100);
  }, [draftForm]);

  async function refresh() {
    const [orgResponse, appResponse] = await Promise.all([
      api<{ data: Organization[] }>("/listing/organizations"),
      api<{ data: { data: ListingApplication[] } | ListingApplication[] }>("/listing/applications"),
    ]);
    const appData = Array.isArray(appResponse.data) ? appResponse.data : appResponse.data.data;
    setOrganizations(orgResponse.data);
    setApplications(appData || []);
    setSelectedOrgId((current) => current || orgResponse.data[0]?.id || "");
  }

  useEffect(() => {
    refresh().catch((err: Error) => setError(err.message));
  }, []);

  async function createOrganization(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      await api("/listing/organizations", { method: "POST", body: JSON.stringify(orgForm) });
      await refresh();
      setMessage("Organization created.");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not create organization.");
    } finally {
      setBusy(false);
    }
  }

  async function saveDraft(event: FormEvent) {
    event.preventDefault();
    if (!selectedOrgId) return setError("Create or select an organization first.");
    setBusy(true);
    setError("");
    try {
      const payload = {
        idempotency_key: `listing-${selectedOrgId}-${draftForm.application_type}`,
        application_type: draftForm.application_type,
        ...Object.fromEntries(requiredSections.map((key) => [key, parseJson(draftForm[key as keyof typeof draftForm])])),
      };
      const response = await api<{ data: ListingApplication }>(`/listing/organizations/${selectedOrgId}/applications`, {
        method: "POST",
        body: JSON.stringify(payload),
      });
      setSelectedApplication(response.data);
      await refresh();
      setMessage("Draft saved.");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not save draft.");
    } finally {
      setBusy(false);
    }
  }

  async function submitApplication() {
    const application = selectedApplication || applications[0];
    if (!application) return setError("Save a draft before submitting.");
    setBusy(true);
    setError("");
    try {
      const response = await api<{ data: ListingApplication }>(`/listing/applications/${application.reference}/submit`, {
        method: "POST",
        body: JSON.stringify({ authorized_declaration: true }),
      });
      setSelectedApplication(response.data);
      await refresh();
      setMessage("Application submitted for ExaEarn review.");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not submit application.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="portal-shell">
      <section className="hero">
        <div>
          <p className="eyebrow">ExaEarn Listing Portal</p>
          <h1>Apply for a token listing with exchange-grade controls.</h1>
          <p className="hero-copy">
            Submit project, token, legal, technical, liquidity, and security information for formal review. Approval starts
            integration; it never activates trading automatically.
          </p>
          <div className="hero-actions">
            <a href="#application" className="button primary">Start Application <ArrowRight size={18} /></a>
            <a href="#status" className="button ghost">Track Status</a>
          </div>
        </div>
        <div className="assurance-panel" aria-label="Listing assurance">
          {[
            ["No fake markets", "Listings go live only after asset, custody, ledger, market-data and launch checks pass."],
            ["Maker-checker approval", "Final scheduling requires a second authorized operator."],
            ["Public safety", "Deposits and trading stay disabled until controlled activation."],
          ].map(([title, body]) => (
            <div className="assurance-item" key={title}>
              <ShieldCheck size={20} />
              <span><strong>{title}</strong>{body}</span>
            </div>
          ))}
        </div>
      </section>

      {(error || message) && (
        <div className={`notice ${error ? "error" : "success"}`} role="status">
          {error ? <AlertTriangle size={18} /> : <CheckCircle2 size={18} />}
          {error || message}
        </div>
      )}

      <section className="grid">
        <form className="panel" onSubmit={createOrganization}>
          <div className="section-title">
            <FileText />
            <div>
              <p>Step 1</p>
              <h2>Project Organization</h2>
            </div>
          </div>
          {(["legal_name", "project_name", "jurisdiction", "website", "business_email"] as const).map((field) => (
            <label key={field}>
              <span>{field.replaceAll("_", " ")}</span>
              <input value={orgForm[field]} onChange={(event) => setOrgForm({ ...orgForm, [field]: event.target.value })} />
            </label>
          ))}
          <button className="button primary full" disabled={busy}>Create Organization</button>
        </form>

        <form className="panel wide" id="application" onSubmit={saveDraft}>
          <div className="section-title">
            <ClipboardCheck />
            <div>
              <p>Step 2</p>
              <h2>Listing Application</h2>
            </div>
            <span className="progress">{completion}% ready</span>
          </div>
          <label>
            <span>Organization</span>
            <select value={selectedOrgId} onChange={(event) => setSelectedOrgId(Number(event.target.value))}>
              <option value="">Select organization</option>
              {organizations.map((organization) => (
                <option key={organization.id} value={organization.id}>{organization.project_name}</option>
              ))}
            </select>
          </label>
          <label>
            <span>Application type</span>
            <select value={draftForm.application_type} onChange={(event) => setDraftForm({ ...draftForm, application_type: event.target.value })}>
              {["NEW_TOKEN_LISTING", "ADDITIONAL_NETWORK", "ADDITIONAL_TRADING_PAIR", "TOKEN_MIGRATION", "REBRAND_TICKER_CHANGE"].map((type) => (
                <option key={type}>{type}</option>
              ))}
            </select>
          </label>
          <div className="section-grid">
            {requiredSections.map((key) => (
              <label key={key}>
                <span>{key.replaceAll("_", " ")}</span>
                <textarea
                  rows={4}
                  value={draftForm[key as keyof typeof draftForm]}
                  onChange={(event) => setDraftForm({ ...draftForm, [key]: event.target.value })}
                />
              </label>
            ))}
          </div>
          <div className="footer-actions">
            <button className="button ghost" type="submit" disabled={busy}>Save Draft</button>
            <button className="button primary" type="button" onClick={submitApplication} disabled={busy}>
              Submit Declaration
            </button>
          </div>
        </form>
      </section>

      <section className="panel" id="status">
        <div className="section-title">
          <LockKeyhole />
          <div>
            <p>Review pipeline</p>
            <h2>Your Listing Applications</h2>
          </div>
        </div>
        <div className="application-list">
          {applications.length === 0 && <p className="empty">No listing application has been saved yet.</p>}
          {applications.map((application) => (
            <button key={application.reference} className="application-row" onClick={() => setSelectedApplication(application)}>
              <span>
                <strong>{application.asset_information?.symbol?.toString() || application.reference}</strong>
                <small>{application.reference}</small>
              </span>
              <StatusPill value={application.application_status} />
              <StatusPill value={application.integration_status} />
            </button>
          ))}
        </div>
      </section>

      <section className="panel">
        <div className="section-title">
          <MessageSquare />
          <div>
            <p>Important</p>
            <h2>Listing Safety Rules</h2>
          </div>
        </div>
        <div className="rules">
          <p>ExaEarn never credits unknown token deposits until the asset and network are officially supported.</p>
          <p>Listing review does not create price, volume, liquidity, wallets, market data, or trading access.</p>
          <p>Production launch requires launch-day revalidation and can be paused by authorized operations staff.</p>
        </div>
      </section>
    </main>
  );
}

createRoot(document.getElementById("root")!).render(<App />);
