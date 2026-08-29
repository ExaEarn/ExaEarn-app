import { useEffect, useMemo, useState } from "react";
import { Archive, ArrowLeft, Bell, CheckCheck, ChevronRight, Clock, Filter, Loader2, Settings } from "lucide-react";

const FILTERS = [
  { key: "", label: "All" },
  { key: "money", label: "Money" },
  { key: "trading", label: "Trading" },
  { key: "payments", label: "Payments" },
  { key: "earn", label: "Earn" },
  { key: "ecosystem", label: "Ecosystem" },
  { key: "security", label: "Security" },
];

function normalizePage(payload) {
  return payload?.data ?? payload ?? {};
}

function ActivityCenter({ request, onBack, onNavigate, onOpenPreferences }) {
  const [tab, setTab] = useState("notifications");
  const [category, setCategory] = useState("");
  const [page, setPage] = useState(1);
  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState({ total: 0, current_page: 1, last_page: 1 });
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [busyId, setBusyId] = useState("");

  const endpoint = useMemo(() => {
    if (tab === "activity") {
      const filter = category ? `&category=${encodeURIComponent(category)}` : "";
      return `/api/activity-center/activity?per_page=20&page=${page}${filter}`;
    }
    return `/api/activity-center/notifications?per_page=20&page=${page}`;
  }, [category, page, tab]);

  const load = async () => {
    setLoading(true);
    setError("");
    try {
      const payload = normalizePage(await request(endpoint, { method: "GET" }));
      setItems(payload.items || []);
      setPagination(payload.pagination || { total: 0, current_page: page, last_page: 1 });
      const stats = await request("/api/notifications/stats", { method: "GET" }).catch(() => null);
      setUnreadCount(Number(stats?.data?.unread ?? 0));
    } catch (exception) {
      setError(exception?.message || "Could not load Activity Center.");
      setItems([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, [endpoint]);

  const markAllRead = async () => {
    setBusyId("all");
    try {
      await request("/api/notifications/mark-all-read", { method: "POST" });
      await load();
    } finally {
      setBusyId("");
    }
  };

  const archive = async (item) => {
    const id = String(item.id || "").replace("notification:", "");
    if (!id) return;
    setBusyId(item.id);
    try {
      await request(`/api/notifications/${id}`, { method: "DELETE" });
      await load();
    } finally {
      setBusyId("");
    }
  };

  const markRead = async (item) => {
    const id = String(item.id || "").replace("notification:", "");
    if (!id || !item.unread) return;
    setBusyId(item.id);
    try {
      await request(`/api/notifications/${id}/read`, { method: "PUT" });
      await load();
    } finally {
      setBusyId("");
    }
  };

  const openItem = async (item) => {
    if (tab === "notifications") {
      await markRead(item);
    }
    const target = String(item.deep_link || "").replace(/^\//, "");
    if (target && onNavigate) {
      const map = { assets: "assets", trade: "trade", earn: "staking", security: "security", settings: "settings", exacard: "exacard", giftcard: "giftcard", exaai: "aiAssistant", kyc: "kycVerification" };
      onNavigate(map[target] || target);
    }
  };

  return (
    <main className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <header className="sticky top-0 z-30 border-b border-[var(--exa-border)] bg-[var(--exa-surface-elevated)]/95 backdrop-blur">
        <div className="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3">
          <button type="button" onClick={onBack} className="grid h-10 w-10 place-items-center rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)]" aria-label="Back">
            <ArrowLeft className="h-4 w-4" />
          </button>
          <div className="min-w-0 flex-1">
            <h1 className="text-base font-semibold sm:text-xl">Activity Center</h1>
            <p className="text-xs text-[var(--exa-text-muted)]">Notifications that need attention and your account activity history.</p>
          </div>
          <button type="button" onClick={onOpenPreferences} className="grid h-10 w-10 place-items-center rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] text-[var(--exa-text-secondary)]" aria-label="Notification preferences">
            <Settings className="h-4 w-4" />
          </button>
        </div>
      </header>

      <section className="mx-auto max-w-5xl space-y-4 px-4 pb-24 pt-4">
        <div className="grid grid-cols-2 gap-2 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-1">
          <button type="button" onClick={() => { setTab("notifications"); setPage(1); }} className={`rounded-xl px-3 py-2 text-sm font-semibold ${tab === "notifications" ? "bg-[var(--exa-gold)] text-[var(--exa-gold-contrast)]" : "text-[var(--exa-text-secondary)]"}`}>
            Notifications {unreadCount ? <span className="ml-1">({unreadCount})</span> : null}
          </button>
          <button type="button" onClick={() => { setTab("activity"); setPage(1); }} className={`rounded-xl px-3 py-2 text-sm font-semibold ${tab === "activity" ? "bg-[var(--exa-gold)] text-[var(--exa-gold-contrast)]" : "text-[var(--exa-text-secondary)]"}`}>
            Activity
          </button>
        </div>

        {tab === "activity" ? (
          <div className="flex gap-2 overflow-x-auto pb-1" aria-label="Activity filters">
            {FILTERS.map((filter) => (
              <button key={filter.key || "all"} type="button" onClick={() => { setCategory(filter.key); setPage(1); }} className={`shrink-0 rounded-full border px-3 py-2 text-xs font-semibold ${category === filter.key ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]" : "border-[var(--exa-border)] bg-[var(--exa-surface)] text-[var(--exa-text-secondary)]"}`}>
                {filter.key ? <Filter className="mr-1 inline h-3 w-3" /> : null}{filter.label}
              </button>
            ))}
          </div>
        ) : (
          <div className="flex justify-end">
            <button type="button" onClick={markAllRead} disabled={busyId === "all" || unreadCount === 0} className="inline-flex items-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface)] px-3 py-2 text-xs font-semibold text-[var(--exa-text-secondary)] disabled:opacity-45">
              <CheckCheck className="h-4 w-4" /> Mark all read
            </button>
          </div>
        )}

        <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-2 shadow-[var(--exa-shadow-panel)]">
          {loading ? (
            <div className="grid gap-2 p-2">{[0, 1, 2, 3].map((index) => <div key={index} className="h-16 animate-pulse rounded-xl bg-white/[.06]" />)}</div>
          ) : error ? (
            <div className="p-6 text-center"><p className="text-sm text-rose-200">{error}</p><button type="button" onClick={load} className="mt-3 rounded-xl bg-[var(--exa-gold)] px-4 py-2 text-sm font-semibold text-[var(--exa-gold-contrast)]">Try again</button></div>
          ) : items.length ? (
            <div className="divide-y divide-[var(--exa-border)]">
              {items.map((item) => <CenterRow key={item.id} item={item} tab={tab} busy={busyId === item.id} onOpen={() => openItem(item)} onArchive={() => archive(item)} />)}
            </div>
          ) : (
            <div className="p-8 text-center"><Bell className="mx-auto h-8 w-8 text-[var(--exa-text-muted)]" /><p className="mt-3 text-sm font-semibold">Nothing here yet</p><p className="mt-1 text-xs text-[var(--exa-text-muted)]">{tab === "activity" ? "Your account activity will appear here." : "Notifications that need your attention will appear here."}</p></div>
          )}
        </section>

        <div className="flex items-center justify-between">
          <button type="button" onClick={() => setPage((value) => Math.max(1, value - 1))} disabled={page <= 1} className="rounded-xl border border-[var(--exa-border)] px-3 py-2 text-xs text-[var(--exa-text-secondary)] disabled:opacity-40">Previous</button>
          <span className="text-xs text-[var(--exa-text-muted)]">Page {pagination.current_page || page} of {pagination.last_page || 1}</span>
          <button type="button" onClick={() => setPage((value) => value + 1)} disabled={(pagination.current_page || page) >= (pagination.last_page || 1)} className="rounded-xl border border-[var(--exa-border)] px-3 py-2 text-xs text-[var(--exa-text-secondary)] disabled:opacity-40">Next</button>
        </div>
      </section>
    </main>
  );
}

function CenterRow({ item, tab, busy, onOpen, onArchive }) {
  return (
    <article className="flex items-center gap-3 p-3">
      <span className={`mt-1 h-2.5 w-2.5 shrink-0 rounded-full ${item.unread ? "bg-[var(--exa-gold)]" : "bg-[var(--exa-border)]"}`} />
      <button type="button" onClick={onOpen} className="min-w-0 flex-1 text-left">
        <span className="flex flex-wrap items-center gap-2">
          <strong className="text-sm text-[var(--exa-text-primary)]">{item.title || item.action || "Account update"}</strong>
          <small className="rounded-full border border-[var(--exa-border)] px-2 py-0.5 text-[10px] uppercase text-[var(--exa-text-muted)]">{item.product || item.category || item.source}</small>
        </span>
        <span className="mt-1 block truncate text-xs text-[var(--exa-text-muted)]">{item.description || item.status || "Open for details."}</span>
        <span className="mt-1 inline-flex items-center gap-1 text-[10px] text-[var(--exa-text-muted)]"><Clock className="h-3 w-3" />{item.timestamp ? new Date(item.timestamp).toLocaleString() : "Recently"}</span>
      </button>
      {tab === "notifications" ? (
        <button type="button" onClick={onArchive} disabled={busy} className="grid h-9 w-9 place-items-center rounded-xl border border-[var(--exa-border)] text-[var(--exa-text-secondary)] disabled:opacity-45" aria-label="Archive notification">
          {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Archive className="h-4 w-4" />}
        </button>
      ) : item.deep_link ? <ChevronRight className="h-4 w-4 text-[var(--exa-text-muted)]" /> : null}
    </article>
  );
}

export default ActivityCenter;
