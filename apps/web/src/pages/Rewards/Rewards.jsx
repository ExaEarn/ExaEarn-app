import { useEffect, useMemo, useState } from "react";
import {
  ArrowLeft,
  Copy,
  Flame,
  Gift,
  MessageCircle,
  Rocket,
  Send,
  ShieldCheck,
  Sparkles,
  Trophy,
  Users,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import { useWebSocketEvent } from "../../services/webSocketService";

const rewardTabs = ["All", "Staking Rewards", "Task-to-Earn", "Referral Bonus", "Special Campaigns"];

const taskData = [
  {
    id: "task-1",
    title: "Complete Profile Setup",
    description: "Verify identity and security settings to unlock account benefits.",
    reward: "Earn 5 XRP",
    progress: 100,
    status: "Completed",
    category: "Task-to-Earn",
  },
  {
    id: "task-2",
    title: "Stake XRP in Flex Pool",
    description: "Stake a minimum of 500 XRP for daily yield rewards.",
    reward: "Earn 20 XRP",
    progress: 55,
    status: "In Progress",
    category: "Staking Rewards",
  },
  {
    id: "task-3",
    title: "Invite First Referral",
    description: "Share your code and onboard a verified friend.",
    reward: "Earn 12 XRP",
    progress: 0,
    status: "Available",
    category: "Referral Bonus",
  },
  {
    id: "task-4",
    title: "Join Liquidity Sprint",
    description: "Participate in the seasonal campaign to earn a multiplier bonus.",
    reward: "Earn 40 XRP",
    progress: 15,
    status: "In Progress",
    category: "Special Campaigns",
  },
];

function statusStyles(status) {
  if (status === "Completed") return "border-emerald-300/35 bg-emerald-500/15 text-emerald-200";
  if (status === "In Progress") return "border-amber-300/35 bg-amber-500/15 text-amber-200";
  return "border-violet-300/35 bg-violet-500/18 text-violet-100";
}

function actionLabel(status) {
  if (status === "Completed") return "Claim Reward";
  if (status === "In Progress") return "Continue";
  return "Start Task";
}

function Rewards({ onBack }) {
  const { apiBaseUrl } = useAuth();
  const [activeTab, setActiveTab] = useState("All");
  const [points, setPoints] = useState({
    total_points: "0.00000000",
    available_points: "0.00000000",
    locked_points: "0.00000000",
  });

  useEffect(() => {
    let ignore = false;

    const fetchBalance = async () => {
      const base = (apiBaseUrl || "").replace(/\/+$/, "");
      if (!base) {
        return;
      }

      try {
        const response = await fetch(`${base}/api/exapoints/balance`, {
          credentials: "include",
          headers: {
            Accept: "application/json",
          },
        });

        const payload = await response.json();
        if (!ignore && payload?.data) {
          setPoints({
            total_points: payload.data.total_points ?? "0.00000000",
            available_points: payload.data.available_points ?? "0.00000000",
            locked_points: payload.data.locked_points ?? "0.00000000",
          });
        }
      } catch {
        // best-effort realtime bootstrap
      }
    };

    fetchBalance();

    return () => {
      ignore = true;
    };
  }, [apiBaseUrl]);

  useWebSocketEvent("exapoint:update", (data) => {
    setPoints((prev) => ({
      total_points: data?.total_points ?? prev.total_points,
      available_points: data?.available_points ?? prev.available_points,
      locked_points: data?.locked_points ?? prev.locked_points,
    }));
  });

  const filteredTasks = useMemo(() => {
    if (activeTab === "All") return taskData;
    return taskData.filter((task) => task.category === activeTab);
  }, [activeTab]);

  return (
    <main className="relative min-h-screen overflow-hidden bg-gradient-to-br from-[#050509] via-[#140822] to-[#1c0d32] px-4 py-8 text-violet-50 sm:px-6 sm:py-10">
      <div className="absolute w-56 h-56 rounded-full pointer-events-none -left-20 top-24 bg-purple-500/25 blur-3xl" />
      <div className="absolute right-0 w-64 h-64 rounded-full pointer-events-none top-1/3 bg-amber-300/10 blur-3xl" />

      <section className="mx-auto w-full max-w-7xl rounded-3xl border border-violet-300/15 bg-[#110a20]/70 p-4 shadow-[0_20px_80px_rgba(0,0,0,0.55)] backdrop-blur-xl sm:p-6 lg:p-8">
        <header className="rounded-2xl border border-violet-300/15 bg-[#140c24]/85 p-4 sm:p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              {onBack ? (
                <button
                  type="button"
                  onClick={onBack}
                  className="inline-flex items-center gap-2 px-3 py-2 mb-3 text-xs font-semibold transition border rounded-xl border-violet-300/25 bg-violet-950/35 text-violet-100 hover:border-amber-300/60 hover:text-amber-200"
                >
                  <ArrowLeft className="w-4 h-4" />
                  Back
                </button>
              ) : null}
              <h1 className="font-['Sora'] text-3xl font-semibold tracking-tight text-white sm:text-4xl">Rewards Center</h1>
              <p className="mt-1 text-sm text-violet-100/70">Earn crypto by completing tasks, staking, and referring friends.</p>
            </div>
            <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-300/40 bg-amber-300/12 px-3 py-1.5 text-xs font-semibold text-amber-200">
              <Flame className="h-3.5 w-3.5" />
              Active Campaigns
            </span>
          </div>
        </header>

        <section className="mt-5 rounded-2xl border border-violet-300/15 bg-gradient-to-br from-[#23113a] via-[#1b102f] to-[#2f1f1a] p-5 shadow-[0_14px_35px_rgba(0,0,0,0.35)] sm:p-6">
          <div className="grid gap-4 sm:grid-cols-3">
            <div>
              <p className="text-xs text-violet-100/65">Total Rewards Earned</p>
              <p className="mt-1 text-3xl font-semibold text-amber-200 drop-shadow-[0_0_14px_rgba(251,191,36,0.35)]">{points.total_points} XP</p>
            </div>
            <div>
              <p className="text-xs text-violet-100/65">Available Points</p>
              <p className="mt-1 text-2xl font-semibold text-violet-50">{points.available_points} XP</p>
            </div>
            <div className="flex flex-col items-start sm:items-end">
              <p className="text-xs text-violet-100/65">Locked Points</p>
              <p className="mt-1 text-2xl font-semibold text-emerald-200">{points.locked_points} XP</p>
              <button className="mt-3 h-10 rounded-xl bg-gradient-to-r from-amber-300 via-amber-400 to-yellow-500 px-4 text-sm font-bold text-black shadow-[0_0_22px_rgba(245,158,11,0.35)] transition hover:brightness-105 active:scale-[0.99]">
                ExaPoints Live
              </button>
            </div>
          </div>
        </section>

        <section className="mt-5 rounded-2xl border border-violet-300/15 bg-[#140c24]/80 p-3">
          <div className="flex gap-2 pb-1 overflow-x-auto">
            {rewardTabs.map((tab) => (
              <button
                key={tab}
                type="button"
                onClick={() => setActiveTab(tab)}
                className={`whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition ${activeTab === tab ? "bg-gradient-to-r from-amber-300 to-yellow-500 text-black shadow-[0_0_18px_rgba(245,158,11,0.45)]" : "border border-violet-300/20 bg-violet-500/10 text-violet-100/85 hover:border-violet-200/45 hover:text-white"}`}
              >
                {tab}
              </button>
            ))}
          </div>
        </section>

        <section className="mt-5 grid gap-4 xl:grid-cols-[1.55fr_1fr]">
          <div className="space-y-3">
            {filteredTasks.length ? (
              filteredTasks.map((task) => (
                <article
                  key={task.id}
                  className="rounded-2xl border border-violet-300/15 bg-[#120b20]/85 p-4 shadow-[0_10px_24px_rgba(0,0,0,0.35)] transition duration-300 hover:-translate-y-0.5 hover:border-amber-300/35 sm:p-5"
                >
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-base font-semibold text-white">{task.title}</p>
                      <p className="mt-1 text-sm text-violet-100/70">{task.description}</p>
                    </div>
                    <span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold ${statusStyles(task.status)}`}>
                      {task.status}
                    </span>
                  </div>

                  <div className="mt-3">
                    <div className="flex items-center justify-between text-xs">
                      <span className="text-amber-200">{task.reward}</span>
                      <span className="text-violet-100/60">{task.progress}%</span>
                    </div>
                    <div className="mt-1.5 h-2.5 w-full rounded-full bg-violet-950/65">
                      <div
                        className="h-full transition-all duration-500 rounded-full bg-gradient-to-r from-amber-300 via-amber-400 to-yellow-500"
                        style={{ width: `${task.progress}%` }}
                      />
                    </div>
                  </div>

                  <button className="h-10 px-4 mt-4 text-sm font-semibold transition border rounded-xl border-violet-300/20 bg-violet-500/15 text-violet-100 hover:border-amber-300/55 hover:text-amber-200">
                    {actionLabel(task.status)}
                  </button>
                </article>
              ))
            ) : (
              <div className="rounded-2xl border border-violet-300/15 bg-[#120b20]/85 p-12 text-center">
                <Gift className="w-10 h-10 mx-auto text-violet-200/65" />
                <p className="mt-3 text-base font-semibold text-violet-50">No rewards yet</p>
                <p className="mt-1 text-sm text-violet-100/65">Start completing tasks to earn crypto.</p>
              </div>
            )}
          </div>

          <div className="space-y-4">
            <article className="rounded-2xl border border-violet-300/15 bg-[#120b20]/90 p-4 shadow-[0_10px_24px_rgba(0,0,0,0.35)] sm:p-5">
              <div className="flex items-center justify-between">
                <h2 className="font-['Sora'] text-lg font-semibold text-violet-50">Referral Program</h2>
                <Users className="w-5 h-5 text-amber-200" />
              </div>

              <div className="p-3 mt-3 border rounded-xl border-violet-300/20 bg-violet-500/10">
                <p className="text-xs text-violet-100/65">Referral Code</p>
                <div className="flex items-center justify-between gap-2 mt-1">
                  <p className="font-semibold text-white">EXA-7Y9K-REF</p>
                  <button className="inline-flex items-center justify-center w-8 h-8 transition border rounded-lg border-violet-300/25 bg-violet-500/10 text-violet-100 hover:border-amber-300/55 hover:text-amber-200">
                    <Copy className="w-4 h-4" />
                  </button>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-2 mt-3">
                <div className="p-3 border rounded-xl border-violet-300/15 bg-violet-500/10">
                  <p className="text-xs text-violet-100/65">Total Referrals</p>
                  <p className="mt-1 text-lg font-semibold text-white">38</p>
                </div>
                <div className="p-3 border rounded-xl border-violet-300/15 bg-violet-500/10">
                  <p className="text-xs text-violet-100/65">Referral Earnings</p>
                  <p className="mt-1 text-lg font-semibold text-amber-200">286 XRP</p>
                </div>
              </div>

              <button className="mt-3 h-10 w-full rounded-xl bg-gradient-to-r from-amber-300 to-yellow-500 text-sm font-bold text-black transition hover:brightness-105 active:scale-[0.99]">
                Invite Friends
              </button>

              <div className="flex items-center gap-2 mt-3">
                <button className="inline-flex items-center justify-center transition border rounded-lg h-9 w-9 border-violet-300/25 bg-violet-500/12 text-violet-100 hover:border-amber-300/55 hover:text-amber-200">
                  <Send className="w-4 h-4" />
                </button>
                <button className="inline-flex items-center justify-center transition border rounded-lg h-9 w-9 border-violet-300/25 bg-violet-500/12 text-violet-100 hover:border-amber-300/55 hover:text-amber-200">
                  <MessageCircle className="w-4 h-4" />
                </button>
                <button className="inline-flex items-center justify-center transition border rounded-lg h-9 w-9 border-violet-300/25 bg-violet-500/12 text-violet-100 hover:border-amber-300/55 hover:text-amber-200">
                  <Rocket className="w-4 h-4" />
                </button>
              </div>

              <p className="mt-3 text-xs text-violet-100/65">Earn 5% of your friends&apos; staking rewards.</p>
            </article>

            <article className="rounded-2xl border border-violet-300/15 bg-[#120b20]/90 p-4 shadow-[0_10px_24px_rgba(0,0,0,0.35)] sm:p-5">
              <div className="flex items-center justify-between">
                <h2 className="font-['Sora'] text-lg font-semibold text-violet-50">Staking Bonus</h2>
                <span className="inline-flex items-center gap-1 rounded-full border border-amber-300/35 bg-amber-300/12 px-2 py-0.5 text-[11px] font-semibold text-amber-200">
                  <Sparkles className="h-3.5 w-3.5" />
                  Boosted Rewards
                </span>
              </div>

              <div className="mt-3 space-y-2 text-sm">
                <div className="p-3 border rounded-xl border-violet-300/15 bg-violet-500/10">
                  <p className="text-xs text-violet-100/65">Active Staking Pools</p>
                  <p className="font-semibold text-white">XRP Flex, XRP Locked 90D, ExaToken Prime</p>
                </div>
                <div className="p-3 border rounded-xl border-violet-300/15 bg-violet-500/10">
                  <p className="text-xs text-violet-100/65">Bonus APY</p>
                  <p className="font-semibold text-emerald-200">Up to +6.5%</p>
                </div>
                <div className="p-3 border rounded-xl border-violet-300/15 bg-violet-500/10">
                  <p className="text-xs text-violet-100/65">Multiplier & Lock Period</p>
                  <p className="font-semibold text-white">1.35x Reward Multiplier  |  30-90 Days</p>
                </div>
              </div>
            </article>
          </div>
        </section>

        <section className="px-4 py-3 mt-5 text-sm border rounded-xl border-emerald-300/20 bg-emerald-500/5 text-emerald-100/85">
          <p className="flex items-center gap-2">
            <ShieldCheck className="w-4 h-4 text-emerald-300" />
            Your rewards are secured by smart contract escrow and transparent reward distribution.
          </p>
        </section>

        <section className="px-4 py-3 mt-5 border rounded-xl border-violet-300/15 bg-violet-500/8">
          <p className="flex items-center gap-2 text-sm text-violet-100/80">
            <Trophy className="w-4 h-4 text-amber-200" />
            New weekly reward campaigns are added every Monday.
          </p>
        </section>
      </section>
    </main>
  );
}

export default Rewards;

