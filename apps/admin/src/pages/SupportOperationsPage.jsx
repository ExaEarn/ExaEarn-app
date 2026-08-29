import { useEffect, useState } from "react";
import { AlertTriangle, BookOpen, LifeBuoy, MessageSquare, RefreshCcw, Send, ShieldAlert, TicketCheck } from "lucide-react";
import { adminHttp } from "../services/http";

const tabs = ["Overview", "Tickets", "Disputes", "Knowledge Base", "Live Chat", "Settings"];

export function SupportOperationsPage() {
  const [active, setActive] = useState("Overview");
  const [overview, setOverview] = useState(null);
  const [tickets, setTickets] = useState([]);
  const [disputes, setDisputes] = useState(null);
  const [kb, setKb] = useState([]);
  const [liveChat, setLiveChat] = useState({ settings: null, agents: [], conversations: [], health: null });
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const load = async () => {
    setLoading(true);
    setError("");
    try {
      const [summary, ticketRows, disputeRows, articleRows, chatSettings, chatAgents, chatConversations, chatHealth] = await Promise.all([
        adminHttp("/api/admin/support/overview"),
        adminHttp("/api/admin/support/tickets"),
        adminHttp("/api/admin/support/disputes"),
        adminHttp("/api/admin/support/knowledge-base"),
        adminHttp("/api/admin/support/live-chat/settings"),
        adminHttp("/api/admin/support/live-chat/agents"),
        adminHttp("/api/admin/support/live-chat/conversations"),
        adminHttp("/api/admin/support/live-chat/health"),
      ]);
      setOverview(summary?.data ?? summary);
      setTickets(ticketRows?.data?.data ?? ticketRows?.data ?? []);
      setDisputes(disputeRows?.data ?? disputeRows);
      setKb(articleRows?.data?.data ?? articleRows?.data ?? []);
      setLiveChat({
        settings: chatSettings?.data ?? chatSettings,
        agents: chatAgents?.data?.data ?? chatAgents?.data ?? [],
        conversations: chatConversations?.data?.data ?? chatConversations?.data ?? [],
        health: chatHealth?.data ?? chatHealth,
      });
    } catch (err) {
      setError(err?.message || "Support operations could not load.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  return (
    <main className="min-h-screen bg-[#070b14] p-5 text-white">
      <div className="mx-auto max-w-7xl">
        <header className="flex flex-col gap-4 border-b border-white/10 pb-5 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[#d4af37]">Customer Operations</p>
            <h1 className="mt-2 text-3xl font-semibold text-[#f8f1de]">Support Center</h1>
            <p className="mt-2 max-w-3xl text-sm text-[#aab4c4]">
              Unified ticketing, SLA, disputes and knowledge-base operations. Product disputes remain authoritative in their own domains.
            </p>
          </div>
          <button onClick={load} className="inline-flex items-center gap-2 rounded-xl border border-white/15 px-4 py-2 text-sm text-[#e6eaf2]">
            <RefreshCcw className="h-4 w-4" /> Refresh
          </button>
        </header>

        {error ? <div className="mt-4 rounded-xl border border-red-400/30 bg-red-500/10 p-3 text-sm text-red-100">{error}</div> : null}

        <nav className="mt-5 flex gap-2 overflow-x-auto">
          {tabs.map((tab) => (
            <button key={tab} onClick={() => setActive(tab)} className={`rounded-full px-4 py-2 text-sm ${active === tab ? "bg-[#d4af37] text-[#111827]" : "border border-white/10 text-[#c8d0dd]"}`}>
              {tab}
            </button>
          ))}
        </nav>

        {loading ? <p className="mt-6 text-sm text-[#aab4c4]">Loading support operations...</p> : null}

        {active === "Overview" ? <Overview data={overview} /> : null}
        {active === "Tickets" ? <TicketTable rows={tickets} /> : null}
        {active === "Disputes" ? <DisputePanel data={disputes} /> : null}
        {active === "Knowledge Base" ? <KbPanel rows={kb} /> : null}
        {active === "Live Chat" ? <LiveChatPanel data={liveChat} onReload={load} /> : null}
        {active === "Settings" ? <InfoPanel icon={ShieldAlert} title="Support safety policy" body="Support can assign, reply, escalate and coordinate. It cannot adjust balances, mutate ledgers or override product dispute outcomes." /> : null}
      </div>
    </main>
  );
}

function LiveChatPanel({ data, onReload }) {
  const [saving, setSaving] = useState(false);
  const [reply, setReply] = useState("");
  const settings = data.settings || {};
  const activeConversation = data.conversations?.[0];

  const updateSettings = async (patch) => {
    setSaving(true);
    try {
      await adminHttp("/api/admin/support/live-chat/settings", {
        method: "PUT",
        body: JSON.stringify(patch),
      });
      await onReload();
    } finally {
      setSaving(false);
    }
  };

  const heartbeat = async () => {
    await adminHttp("/api/admin/support/live-chat/heartbeat", {
      method: "POST",
      body: JSON.stringify({ status: "ONLINE", support_enabled: true }),
    });
    await onReload();
  };

  const sendReply = async () => {
    if (!activeConversation || !reply.trim()) return;
    await adminHttp(`/api/admin/support/live-chat/conversations/${activeConversation.id}/messages`, {
      method: "POST",
      headers: { "Idempotency-Key": `admin-chat-${activeConversation.id}-${Date.now()}` },
      body: JSON.stringify({ body: reply }),
    });
    setReply("");
    await onReload();
  };

  const convertToTicket = async () => {
    if (!activeConversation) return;
    await adminHttp(`/api/admin/support/live-chat/conversations/${activeConversation.id}/convert-to-ticket`, {
      method: "POST",
      body: JSON.stringify({ subject: "Live chat follow-up" }),
    });
    await onReload();
  };

  return (
    <section className="mt-5 grid gap-4 xl:grid-cols-[360px_1fr]">
      <aside className="space-y-4">
        <article className="rounded-2xl border border-white/10 bg-[#101827] p-4">
          <MessageSquare className="h-5 w-5 text-[#d4af37]" />
          <h3 className="mt-3 text-lg font-semibold text-[#f8f1de]">Live chat settings</h3>
          <p className="mt-1 text-sm text-[#aab4c4]">Public chat stays off until a supervisor enables it and staffed agents are online.</p>
          <div className="mt-4 space-y-2 text-sm">
            {[
              ["Live chat", "live_chat_enabled"],
              ["Public", "public_chat_enabled"],
              ["Web", "web_chat_enabled"],
              ["Mobile", "mobile_chat_enabled"],
              ["Operating hours", "operating_hours_enabled"],
              ["Maintenance", "maintenance_mode"],
            ].map(([label, key]) => (
              <button key={key} disabled={saving} onClick={() => updateSettings({ [key]: !settings[key] })} className="flex w-full items-center justify-between rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-left">
                <span className="text-[#d7ddea]">{label}</span>
                <span className={settings[key] ? "text-[#9cf4bd]" : "text-[#8792a5]"}>{settings[key] ? "ON" : "OFF"}</span>
              </button>
            ))}
          </div>
        </article>
        <article className="rounded-2xl border border-white/10 bg-[#101827] p-4">
          <h3 className="font-semibold text-[#f8f1de]">Health</h3>
          <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-[#aab4c4]">
            <Metric label="Backend" value={data.health?.backend || "-"} />
            <Metric label="Realtime" value={data.health?.realtime || "-"} />
            <Metric label="Waiting" value={data.health?.waiting ?? 0} />
            <Metric label="Online agents" value={data.health?.agents_online ?? 0} />
          </div>
          <button onClick={heartbeat} className="mt-3 w-full rounded-xl bg-[#d4af37] py-2 text-sm font-semibold text-[#111827]">Mark me online</button>
        </article>
      </aside>

      <div className="space-y-4">
        <section className="grid gap-3 lg:grid-cols-3">
          {data.agents?.map((agent) => (
            <article key={agent.id} className="rounded-2xl border border-white/10 bg-[#101827] p-4">
              <p className="font-semibold text-[#f8f1de]">{agent.admin?.name || agent.admin?.email || `Admin ${agent.admin_id}`}</p>
              <p className="mt-1 text-xs text-[#aab4c4]">{agent.status} · max {agent.max_concurrent_chats}</p>
            </article>
          ))}
          {!data.agents?.length ? <InfoPanel icon={ShieldAlert} title="No agents configured" body="Create or heartbeat support-agent profiles through Admin. No source-code change is required to staff chat later." /> : null}
        </section>

        <section className="rounded-2xl border border-white/10 bg-[#101827] p-4">
          <div className="flex items-center justify-between gap-3">
            <h3 className="font-semibold text-[#f8f1de]">Conversations</h3>
            <button onClick={onReload} className="rounded-lg border border-white/10 px-3 py-1 text-xs text-[#d7ddea]">Refresh</button>
          </div>
          <div className="mt-3 space-y-2">
            {data.conversations?.map((chat) => (
              <div key={chat.id} className="rounded-xl border border-white/10 bg-white/[0.03] p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="font-semibold text-[#f8f1de]">{chat.conversation_number || `Chat ${chat.id}`}</p>
                  <span className="text-xs text-[#d4af37]">{chat.status}</span>
                </div>
                <p className="mt-1 text-xs text-[#aab4c4]">{chat.user?.email || `User ${chat.user_id}`} · {chat.product || "General"}</p>
              </div>
            ))}
            {!data.conversations?.length ? <p className="py-4 text-center text-sm text-[#8792a5]">No live chat conversations yet.</p> : null}
          </div>
          {activeConversation ? (
            <div className="mt-4 flex gap-2">
              <input value={reply} onChange={(event) => setReply(event.target.value)} placeholder={`Reply to ${activeConversation.conversation_number}`} className="min-h-10 flex-1 rounded-xl border border-white/10 bg-[#070b14] px-3 text-sm text-white outline-none" />
              <button onClick={sendReply} className="rounded-xl bg-[#d4af37] px-3 text-[#111827]"><Send className="h-4 w-4" /></button>
              <button onClick={convertToTicket} className="rounded-xl border border-white/15 px-3 text-xs text-[#d7ddea]">Ticket</button>
            </div>
          ) : null}
        </section>
      </div>
    </section>
  );
}

function Metric({ label, value }) {
  return <div className="rounded-xl border border-white/10 bg-white/[0.03] p-3"><p className="text-[#8792a5]">{label}</p><p className="mt-1 font-semibold text-[#f8f1de]">{value}</p></div>;
}

function Overview({ data }) {
  const cards = [
    ["Open Tickets", data?.open_tickets ?? 0, LifeBuoy],
    ["Unassigned", data?.unassigned ?? 0, AlertTriangle],
    ["Urgent", data?.urgent ?? 0, ShieldAlert],
    ["Resolved Today", data?.resolved_today ?? 0, TicketCheck],
  ];
  return (
    <section className="mt-5 grid gap-3 md:grid-cols-4">
      {cards.map(([label, value, Icon]) => (
        <article key={label} className="rounded-2xl border border-white/10 bg-[#101827] p-4">
          <Icon className="h-5 w-5 text-[#d4af37]" />
          <p className="mt-3 text-xs uppercase text-[#8792a5]">{label}</p>
          <p className="mt-1 text-2xl font-semibold text-[#f8f1de]">{value}</p>
        </article>
      ))}
      <article className="rounded-2xl border border-white/10 bg-[#101827] p-4 md:col-span-4">
        <p className="text-sm font-semibold text-[#f8f1de]">SLA status: {data?.sla?.status ?? "ON_TRACK"}</p>
        <p className="mt-1 text-sm text-[#aab4c4]">At risk {data?.sla?.at_risk ?? 0} · Breached {data?.sla?.breached ?? 0}</p>
      </article>
    </section>
  );
}

function TicketTable({ rows }) {
  return (
    <section className="mt-5 overflow-hidden rounded-2xl border border-white/10 bg-[#101827]">
      <table className="w-full min-w-[760px] text-left text-sm">
        <thead className="bg-white/5 text-xs uppercase text-[#8792a5]">
          <tr><th className="p-3">Ticket</th><th>User</th><th>Category</th><th>Product</th><th>Priority</th><th>Status</th><th>SLA</th></tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.id} className="border-t border-white/10">
              <td className="p-3 font-semibold text-[#f8f1de]">{row.ticket_number}</td>
              <td className="p-3 text-[#c8d0dd]">{row.user?.email || row.user_id}</td>
              <td className="p-3 text-[#c8d0dd]">{row.category}</td>
              <td className="p-3 text-[#c8d0dd]">{row.product || "General"}</td>
              <td className="p-3 text-[#d4af37]">{row.priority}</td>
              <td className="p-3 text-[#c8d0dd]">{row.status}</td>
              <td className="p-3 text-[#c8d0dd]">{row.resolution_due_at ? new Date(row.resolution_due_at).toLocaleString() : "-"}</td>
            </tr>
          ))}
          {!rows.length ? <tr><td colSpan="7" className="p-5 text-center text-[#8792a5]">No support tickets yet.</td></tr> : null}
        </tbody>
      </table>
    </section>
  );
}

function DisputePanel({ data }) {
  return <InfoPanel icon={AlertTriangle} title="Unified dispute operations" body={`P2P: ${data?.p2p?.length ?? 0} recent · ExaCard: ${data?.exacard?.length ?? 0} recent. Support links and coordinates; product domains remain authoritative.`} />;
}

function KbPanel({ rows }) {
  return (
    <section className="mt-5 grid gap-3 md:grid-cols-2">
      {rows.map((row) => <article key={row.id} className="rounded-2xl border border-white/10 bg-[#101827] p-4"><BookOpen className="h-5 w-5 text-[#d4af37]" /><h3 className="mt-3 font-semibold text-[#f8f1de]">{row.title}</h3><p className="mt-1 text-sm text-[#aab4c4]">{row.summary || "No summary"}</p></article>)}
      {!rows.length ? <InfoPanel icon={BookOpen} title="Knowledge base CMS" body="No published support articles yet. Admins can create versioned articles through the support API." /> : null}
    </section>
  );
}

function InfoPanel({ icon: Icon, title, body }) {
  return <section className="mt-5 rounded-2xl border border-white/10 bg-[#101827] p-5"><Icon className="h-5 w-5 text-[#d4af37]" /><h3 className="mt-3 text-lg font-semibold text-[#f8f1de]">{title}</h3><p className="mt-2 text-sm text-[#aab4c4]">{body}</p></section>;
}
