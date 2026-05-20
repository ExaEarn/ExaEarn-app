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
    <main className="min-h-screen bg-[#070B14] text-white">
      <header
        className="sticky top-0 z-30 border-b border-[#D4AF37]/20 bg-gradient-to-r from-[#121A2A]/95 via-[#0E1524]/95 to-[#0A0F1D]/95 backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-4xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-start gap-3">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-white/15 bg-[#111827] p-2 text-[#E6EAF2] hover:border-[#D4AF37]/60"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div>
              <h1 className="text-lg font-semibold text-[#F8F1DE] sm:text-xl">Activity Logs</h1>
              <p className="text-xs text-[#B8C0CF] sm:text-sm">Review your recent account activity and security events.</p>
            </div>
          </div>
        </div>
      </header>

      <section className="mx-auto w-full max-w-4xl space-y-4 px-4 pb-8 pt-5 sm:px-6">
        <article className="rounded-2xl border border-[#D4AF37]/45 bg-[#101827]/90 p-4 shadow-[0_12px_26px_rgba(0,0,0,0.3)]">
          <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 className="text-base font-semibold text-[#F8F1DE]">Security activity stream</h2>
              <p className="text-sm text-[#AAB3C3]">Filtered by event type, outcome, or any keyword in the log details.</p>
            </div>
            <div className="grid gap-2 sm:grid-cols-3">
              <label className="block">
                <span className="text-xs uppercase tracking-[0.12em] text-[#AAB3C3]">Type</span>
                <select
                  value={typeFilter}
                  onChange={(event) => setTypeFilter(event.target.value)}
                  className="mt-1 h-11 w-full rounded-xl border border-white/10 bg-[#0C1424] px-3 text-sm text-white outline-none focus:border-[#D4AF37]/60"
                >
                  {filterOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block">
                <span className="text-xs uppercase tracking-[0.12em] text-[#AAB3C3]">Status</span>
                <select
                  value={statusFilter}
                  onChange={(event) => setStatusFilter(event.target.value)}
                  className="mt-1 h-11 w-full rounded-xl border border-white/10 bg-[#0C1424] px-3 text-sm text-white outline-none focus:border-[#D4AF37]/60"
                >
                  {statusOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block">
                <span className="text-xs uppercase tracking-[0.12em] text-[#AAB3C3]">Search</span>
                <div className="relative">
                  <input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search logs"
                    className="mt-1 h-11 w-full rounded-xl border border-white/10 bg-[#0C1424] px-3 pr-10 text-sm text-white outline-none focus:border-[#D4AF37]/60"
                  />
                  <FileSearch className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#98A1B2]" />
                </div>
              </label>
            </div>
          </div>

          <div className="space-y-3">
            {loading ? (
              <div className="rounded-2xl border border-white/10 bg-[#0C1424] p-6 text-center text-sm text-[#D7DDEA]">
                Loading activity logs...
              </div>
            ) : error ? (
              <div className="rounded-2xl border border-[#EF4444]/35 bg-[#EF4444]/10 p-6 text-center text-sm text-[#FCA5A5]">
                {error}
              </div>
            ) : filteredLogs.length === 0 ? (
              <div className="rounded-2xl border border-white/10 bg-[#0C1424] p-6 text-center text-sm text-[#D7DDEA]">
                No activity was found for the selected filters.
              </div>
            ) : (
              <div className="space-y-3">
                {filteredLogs.map((item) => (
                  <div key={item.id} className="rounded-2xl border border-white/10 bg-[#0C1424] p-4">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                      <div>
                        <p className="text-sm font-semibold text-[#F8F1DE]">
                          {typeLabels[item.type] || item.type} • {item.action.replace(/_/g, " ")}
                        </p>
                        <p className="mt-1 text-xs text-[#98A1B2]">
                          {item.device || "Unknown device"} • {item.ip || "Unknown IP"}
                        </p>
                      </div>
                      <div className="flex flex-wrap items-center gap-2 text-xs">
                        <span className="rounded-full border border-[#D4AF37]/25 bg-[#D4AF37]/10 px-2 py-1 text-[#F8F1DE]">
                          {statusLabels[item.status] || item.status}
                        </span>
                        <span className="text-xs text-[#AAB3C3]">{item.created_at ? new Date(item.created_at).toLocaleString() : "Unknown time"}</span>
                      </div>
                    </div>
                    {item.data ? (
                      <div className="mt-3 rounded-xl border border-white/10 bg-[#121A2A] p-3 text-xs text-[#C1C9D4]">
                        <pre className="whitespace-pre-wrap break-words">{JSON.stringify(item.data, null, 2)}</pre>
                      </div>
                    ) : null}
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-xs text-[#AAB3C3]">
              Showing {filteredLogs.length} item{filteredLogs.length === 1 ? "" : "s"} from page {pageInfo.current_page}.
            </p>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setPage((current) => Math.max(1, current - 1))}
                disabled={!canPrevious || loading}
                className="rounded-xl border border-white/10 bg-[#111827] px-3 py-2 text-xs text-white disabled:cursor-not-allowed disabled:opacity-40"
              >
                Previous
              </button>
              <button
                type="button"
                onClick={() => setPage((current) => Math.min(pageInfo.last_page, current + 1))}
                disabled={!canNext || loading}
                className="rounded-xl border border-white/10 bg-[#111827] px-3 py-2 text-xs text-white disabled:cursor-not-allowed disabled:opacity-40"
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
