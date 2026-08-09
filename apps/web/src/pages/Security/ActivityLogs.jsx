import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, ArrowRight, FileSearch, ShieldCheck } from "lucide-react";
import { useAuth } from "../../context/AuthContext";

const typeLabels = {
  auth: "Authentication",
  security: "Security",
  transaction: "Transaction",
  api: "API",
  system: "System",
};

const statusLabels = {
  success: "Success",
  failed: "Failed",
  pending: "Pending",
};

const filterOptions = [
  { value: "all", label: "All Types" },
  { value: "auth", label: "Authentication" },
  { value: "security", label: "Security" },
  { value: "transaction", label: "Transaction" },
  { value: "system", label: "System" },
  { value: "api", label: "API" },
];

const statusOptions = [
  { value: "all", label: "All Statuses" },
  { value: "success", label: "Success" },
  { value: "failed", label: "Failed" },
  { value: "pending", label: "Pending" },
];

function ActivityLogs({ onBack }) {
  const { request } = useAuth();
  const [logs, setLogs] = useState([]);
  const [page, setPage] = useState(1);
  const [pageInfo, setPageInfo] = useState({ current_page: 1, last_page: 1 });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [typeFilter, setTypeFilter] = useState("all");
  const [statusFilter, setStatusFilter] = useState("all");
  const [search, setSearch] = useState("");

  useEffect(() => {
    let ignore = false;

    const fetchLogs = async () => {
      setLoading(true);
      setError("");

      try {
        const response = await request(`/api/logs/user?page=${page}`);
        if (ignore) return;

        const payload = response.logs || response.data || [];
        const items = Array.isArray(payload.data) ? payload.data : payload;
        setLogs(items);
        setPageInfo({
          current_page: payload.current_page ?? page,
          last_page: payload.last_page ?? page,
        });
      } catch (fetchError) {
        if (!ignore) {
          setError(fetchError.message || "Unable to load activity logs.");
          setLogs([]);
        }
      } finally {
        if (!ignore) {
          setLoading(false);
        }
      }
    };

    fetchLogs();

    return () => {
      ignore = true;
    };
  }, [request, page]);

  const filteredLogs = useMemo(() => {
    return logs.filter((log) => {
      const matchesType = typeFilter === "all" || log.type === typeFilter;
      const matchesStatus = statusFilter === "all" || log.status === statusFilter;
      const searchValue = search.trim().toLowerCase();
      const matchesSearch =
        !searchValue ||
        `${log.action} ${log.device} ${log.ip} ${log.type} ${log.status}`.toLowerCase().includes(searchValue);
      return matchesType && matchesStatus && matchesSearch;
    });
  }, [logs, typeFilter, statusFilter, search]);

  const canPrevious = page > 1;
  const canNext = page < pageInfo.last_page;

  return (
    <main className="min-h-screen bg-[var(--exa-bg-primary)] text-white">
      <header
        className="sticky top-0 z-30 border-b border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-4xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-start gap-3">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] p-2 text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)]"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div>
              <h1 className="text-lg font-semibold text-[var(--exa-text-primary)] sm:text-xl">Activity Logs</h1>
              <p className="text-xs text-[var(--exa-text-muted)] sm:text-sm">Review your recent account activity and security events.</p>
            </div>
          </div>
        </div>
      </header>

      <section className="mx-auto w-full max-w-4xl space-y-4 px-4 pb-8 pt-5 sm:px-6">
        <article className="rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-soft)]">
          <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 className="text-base font-semibold text-[var(--exa-text-primary)]">Security activity stream</h2>
              <p className="text-sm text-[var(--exa-text-muted)]">Filtered by event type, outcome, or any keyword in the log details.</p>
            </div>
            <div className="grid gap-2 sm:grid-cols-3">
              <label className="block">
                <span className="text-xs uppercase tracking-[0.12em] text-[var(--exa-text-muted)]">Type</span>
                <select
                  value={typeFilter}
                  onChange={(event) => setTypeFilter(event.target.value)}
                  className="mt-1 h-11 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm text-white outline-none focus:border-[var(--exa-border-active)]"
                >
                  {filterOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block">
                <span className="text-xs uppercase tracking-[0.12em] text-[var(--exa-text-muted)]">Status</span>
                <select
                  value={statusFilter}
                  onChange={(event) => setStatusFilter(event.target.value)}
                  className="mt-1 h-11 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 text-sm text-white outline-none focus:border-[var(--exa-border-active)]"
                >
                  {statusOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block">
                <span className="text-xs uppercase tracking-[0.12em] text-[var(--exa-text-muted)]">Search</span>
                <div className="relative">
                  <input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search logs"
                    className="mt-1 h-11 w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] px-3 pr-10 text-sm text-white outline-none focus:border-[var(--exa-border-active)]"
                  />
                  <FileSearch className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--exa-text-disabled)]" />
                </div>
              </label>
            </div>
          </div>

          <div className="space-y-3">
            {loading ? (
              <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-6 text-center text-sm text-[var(--exa-text-secondary)]">
                Loading activity logs...
              </div>
            ) : error ? (
              <div className="rounded-2xl border border-[#EF4444]/35 bg-[#EF4444]/10 p-6 text-center text-sm text-[#FCA5A5]">
                {error}
              </div>
            ) : filteredLogs.length === 0 ? (
              <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-6 text-center text-sm text-[var(--exa-text-secondary)]">
                No activity was found for the selected filters.
              </div>
            ) : (
              <div className="space-y-3">
                {filteredLogs.map((item) => (
                  <div key={item.id} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-bg-tertiary)] p-4">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                      <div>
                        <p className="text-sm font-semibold text-[var(--exa-text-primary)]">
                          {typeLabels[item.type] || item.type} - {item.action.replace(/_/g, " ")}
                        </p>
                        <p className="mt-1 text-xs text-[var(--exa-text-disabled)]">
                          {item.device || "Unknown device"} - {item.ip || "Unknown IP"}
                        </p>
                      </div>
                      <div className="flex flex-wrap items-center gap-2 text-xs">
                        <span className="rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-2 py-1 text-[var(--exa-text-primary)]">
                          {statusLabels[item.status] || item.status}
                        </span>
                        <span className="text-xs text-[var(--exa-text-muted)]">{item.created_at ? new Date(item.created_at).toLocaleString() : "Unknown time"}</span>
                      </div>
                    </div>
                    {item.data ? (
                      <div className="mt-3 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3 text-xs text-[var(--exa-text-muted)]">
                        <pre className="whitespace-pre-wrap break-words">{JSON.stringify(item.data, null, 2)}</pre>
                      </div>
                    ) : null}
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-xs text-[var(--exa-text-muted)]">
              Showing {filteredLogs.length} item{filteredLogs.length === 1 ? "" : "s"} from page {pageInfo.current_page}.
            </p>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setPage((current) => Math.max(1, current - 1))}
                disabled={!canPrevious || loading}
                className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2 text-xs text-white disabled:cursor-not-allowed disabled:opacity-40"
              >
                Previous
              </button>
              <button
                type="button"
                onClick={() => setPage((current) => Math.min(pageInfo.last_page, current + 1))}
                disabled={!canNext || loading}
                className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-hover)] px-3 py-2 text-xs text-white disabled:cursor-not-allowed disabled:opacity-40"
              >
                Next
              </button>
            </div>
          </div>
        </article>
      </section>
    </main>
  );
}

export default ActivityLogs;

