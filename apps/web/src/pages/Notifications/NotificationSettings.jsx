import { useEffect, useState } from "react";
import { ArrowLeft, Check, Loader2, ShieldCheck } from "lucide-react";

function NotificationSettings({ request, onBack }) {
  const [preferences, setPreferences] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [toast, setToast] = useState("");

  const load = async () => {
    setLoading(true);
    setError("");
    try {
      const payload = await request("/api/notifications/preferences", { method: "GET" });
      setPreferences(payload?.data || []);
    } catch (exception) {
      setError(exception?.message || "Could not load notification preferences.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const update = (scope, key, value) => {
    setPreferences((items) => items.map((item) => {
      if (item.scope !== scope || item.mandatory) return item;
      return { ...item, [key]: value };
    }));
  };

  const save = async () => {
    setSaving(true);
    setError("");
    try {
      const payload = await request("/api/notifications/preferences", {
        method: "PUT",
        body: JSON.stringify({ preferences }),
      });
      setPreferences(payload?.data || []);
      setToast("Notification preferences updated.");
      setTimeout(() => setToast(""), 2200);
    } catch (exception) {
      setError(exception?.message || "Could not save notification preferences.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <main className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <header className="sticky top-0 z-30 border-b border-[var(--exa-border)] bg-[var(--exa-surface-elevated)]/95 backdrop-blur">
        <div className="mx-auto flex max-w-4xl items-center gap-3 px-4 py-3">
          <button type="button" onClick={onBack} className="grid h-10 w-10 place-items-center rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)]" aria-label="Back">
            <ArrowLeft className="h-4 w-4" />
          </button>
          <div className="min-w-0 flex-1">
            <h1 className="text-base font-semibold sm:text-xl">Notification Preferences</h1>
            <p className="text-xs text-[var(--exa-text-muted)]">Choose optional channels. Required financial, security and compliance alerts stay protected.</p>
          </div>
        </div>
      </header>

      <section className="mx-auto max-w-4xl space-y-4 px-4 pb-24 pt-4">
        <article className="rounded-2xl border border-cyan-300/20 bg-cyan-300/[.06] p-4">
          <div className="flex gap-3">
            <ShieldCheck className="mt-0.5 h-5 w-5 shrink-0 text-cyan-200" />
            <div>
              <h2 className="text-sm font-semibold">Mandatory alerts remain enabled</h2>
              <p className="mt-1 text-xs leading-5 text-[var(--exa-text-muted)]">Security, compliance and critical financial notifications cannot be disabled by marketing preferences.</p>
            </div>
          </div>
        </article>

        {error ? <div className="rounded-xl border border-rose-400/25 bg-rose-400/10 p-3 text-sm text-rose-100">{error}</div> : null}

        <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-2 shadow-[var(--exa-shadow-panel)]">
          {loading ? (
            <div className="grid gap-2 p-2">{[0, 1, 2, 3, 4].map((index) => <div key={index} className="h-16 animate-pulse rounded-xl bg-white/[.06]" />)}</div>
          ) : (
            <div className="divide-y divide-[var(--exa-border)]">
              {preferences.map((item) => <PreferenceRow key={item.scope} item={item} onChange={update} />)}
            </div>
          )}
        </section>

        <button type="button" onClick={save} disabled={saving || loading} className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-[var(--exa-gold)] px-4 text-sm font-semibold text-[var(--exa-gold-contrast)] disabled:opacity-45 sm:w-auto">
          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
          Save Preferences
        </button>
      </section>

      {toast ? <div className="fixed right-4 top-20 z-50 rounded-xl border border-emerald-300/25 bg-emerald-400/10 px-3 py-2 text-xs text-emerald-100">{toast}</div> : null}
    </main>
  );
}

function PreferenceRow({ item, onChange }) {
  const label = item.scope.replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
  return (
    <article className="grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
      <div>
        <div className="flex flex-wrap items-center gap-2">
          <h3 className="text-sm font-semibold">{label}</h3>
          {item.mandatory ? <span className="rounded-full border border-cyan-300/25 px-2 py-0.5 text-[10px] font-semibold text-cyan-100">Mandatory</span> : null}
        </div>
        <p className="mt-1 text-xs text-[var(--exa-text-muted)]">{item.scope === "marketing" ? "Product updates and promotional messages." : "Account and product updates for this category."}</p>
      </div>
      <div className="grid grid-cols-3 gap-2">
        {[
          ["in_app_enabled", "In-app"],
          ["email_enabled", "Email"],
          ["push_enabled", "Push"],
        ].map(([key, channel]) => (
          <button key={key} type="button" disabled={item.mandatory} onClick={() => onChange(item.scope, key, !item[key])} className={`min-h-10 rounded-xl border px-3 text-xs font-semibold ${item[key] ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-muted)]"} disabled:cursor-not-allowed disabled:opacity-75`}>
            {channel}
          </button>
        ))}
      </div>
    </article>
  );
}

export default NotificationSettings;
