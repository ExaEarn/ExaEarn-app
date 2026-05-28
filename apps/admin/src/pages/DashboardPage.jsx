import { Activity, Database, Radio, Server, ShieldAlert, Wallet } from "lucide-react";
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { GlassPanel, PageShell, Pill, StatCard } from "../components/AdminPrimitives";
import { useAdminStore } from "../store/useAdminStore";
import { useAdminRealtime } from "../hooks/useAdminRealtime";

const chartCards = [
  { key: "userGrowth", label: "User Growth", color: "#f9e2ad" },
  { key: "tradingVolume", label: "Trading Volume", color: "#a377ff" },
  { key: "rewards", label: "Rewards", color: "#56d8ff" },
  { key: "revenue", label: "Revenue", color: "#7ce38b" },
];

const statusIcons = {
  "Laravel API": Server,
  "Queue Workers": Activity,
  Redis: Database,
  Database,
  WebSocket: Radio,
  "Blockchain Service": Wallet,
};

export function DashboardPage() {
  const stats = useAdminStore((state) => state.stats);
  const charts = useAdminStore((state) => state.charts);
  const serverStatus = useAdminStore((state) => state.serverStatus);
  const heartbeat = useAdminRealtime();

  return (
    <PageShell
      eyebrow="Exchange-grade command"
      title="ExaEarn Admin Dashboard"
      description="One operating surface for users, wallets, markets, rewards, treasury custody, KYC reviews, and infrastructure health."
    >
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {stats.map((stat) => (
          <StatCard key={stat.label} label={stat.label} value={stat.value} change={stat.change} />
        ))}
      </div>

      <div className="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
        <GlassPanel>
          <div className="flex items-center justify-between">
            <div>
              <p className="text-xs uppercase tracking-[0.22em] text-violet-100/40">Performance Signals</p>
              <h2 className="mt-2 font-['Sora'] text-xl font-semibold text-white">Growth and revenue flow</h2>
            </div>
            <Pill tone={heartbeat.connectionState === "error" ? "danger" : heartbeat.feedStatus === "warning" || heartbeat.feedStatus === "degraded" ? "warning" : "success"}>
              Socket {heartbeat.connectionState}
            </Pill>
          </div>
          <div className="mt-6 grid gap-5 lg:grid-cols-2">
            {chartCards.map((card) => (
              <div key={card.key} className="rounded-[24px] border border-white/8 bg-white/[0.03] p-4">
                <div className="flex items-center justify-between">
                  <h3 className="font-medium text-white">{card.label}</h3>
                  <span className="text-xs text-violet-100/50">7d</span>
                </div>
                <div className="mt-4 h-44">
                  <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={charts[card.key] ?? []}>
                      <defs>
                        <linearGradient id={`fill-${card.key}`} x1="0" x2="0" y1="0" y2="1">
                          <stop offset="5%" stopColor={card.color} stopOpacity={0.55} />
                          <stop offset="95%" stopColor={card.color} stopOpacity={0.02} />
                        </linearGradient>
                      </defs>
                      <CartesianGrid stroke="rgba(255,255,255,0.06)" vertical={false} />
                      <XAxis dataKey="name" tick={{ fill: "#9aa4b2", fontSize: 12 }} axisLine={false} tickLine={false} />
                      <YAxis tick={{ fill: "#9aa4b2", fontSize: 12 }} axisLine={false} tickLine={false} />
                      <Tooltip
                        contentStyle={{
                          background: "rgba(10,8,22,0.94)",
                          border: "1px solid rgba(255,255,255,0.08)",
                          borderRadius: "18px",
                        }}
                      />
                      <Area type="monotone" dataKey="value" stroke={card.color} fill={`url(#fill-${card.key})`} strokeWidth={3} />
                    </AreaChart>
                  </ResponsiveContainer>
                </div>
              </div>
            ))}
          </div>
        </GlassPanel>

        <GlassPanel>
          <div className="flex items-center gap-3">
            <ShieldAlert className="h-5 w-5 text-auric-300" />
            <div>
              <p className="text-xs uppercase tracking-[0.22em] text-violet-100/40">Realtime Signals</p>
              <h2 className="mt-1 font-['Sora'] text-xl font-semibold text-white">Operations pulse</h2>
            </div>
          </div>
          <div className="mt-6 grid gap-3">
            {[
              ["Queue Depth", heartbeat.queueDepth],
              ["WebSocket Clients", heartbeat.websocketClients],
              ["Security Alerts", heartbeat.securityAlerts],
              ["Blockchain Latency", `${heartbeat.blockchainLatency}ms`],
            ].map(([label, value]) => (
              <div key={label} className="flex items-center justify-between rounded-2xl border border-white/8 bg-white/[0.03] px-4 py-3">
                <span className="text-sm text-violet-100/70">{label}</span>
                <span className="font-semibold text-white">{value}</span>
              </div>
            ))}
          </div>
          <div className="mt-6 space-y-3">
            {serverStatus.map((service) => {
              const Icon = statusIcons[service.service] ?? Activity;
              const tone = service.status === "online" ? "success" : "warning";
              return (
                <div key={service.service} className="flex items-center justify-between rounded-2xl border border-white/8 px-4 py-3">
                  <div className="flex items-center gap-3">
                    <Icon className="h-4 w-4 text-violet-100/60" />
                    <span className="text-sm text-violet-50">{service.service}</span>
                  </div>
                  <Pill tone={tone}>{service.status}</Pill>
                </div>
              );
            })}
          </div>
        </GlassPanel>
      </div>
    </PageShell>
  );
}
