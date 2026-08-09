import { useEffect, useMemo, useState } from "react";
import {
  ArrowDownLeft,
  ArrowUpRight,
  ArrowLeft,
  Download,
  ExternalLink,
  Filter,
  Gift,
  Search,
  Shuffle,
  Users,
  XCircle,
} from "lucide-react";
import { useAuth } from "../../context/AuthContext";
import { useLanguage } from "../../context/LanguageContext.jsx";
import { fetchUserTransactions } from "../../services/transactionApi";

const filterTabs = ["All", "Deposits", "Withdrawals", "Rewards", "P2P"];

const transactionsSeed = [
  {
    id: "TX-EXA-901223",
    type: "Deposit",
    asset: "USDT",
    amount: "+2,400.00",
    amountFiat: "NGN 3,940,000",
    status: "Completed",
    date: "Feb 24, 2026",
    time: "08:14 PM",
    network: "TRC20",
    confirmations: "32/32",
    fee: "0.00 USDT",
    ref: "DEP-447201",
  },
  {
    id: "TX-EXA-901198",
    type: "Withdrawal",
    asset: "XRP",
    amount: "-1,250.00",
    amountFiat: "NGN 1,710,200",
    status: "Pending",
    date: "Feb 24, 2026",
    time: "03:52 PM",
    network: "XRP Ledger",
    confirmations: "Processing",
    fee: "0.45 XRP",
    ref: "WDR-882104",
  },
  {
    id: "TX-EXA-901156",
    type: "Reward",
    asset: "ExaToken",
    amount: "+540.00",
    amountFiat: "NGN 420,100",
    status: "Completed",
    date: "Feb 23, 2026",
    time: "10:19 AM",
    network: "ExaChain",
    confirmations: "Finalized",
    fee: "0.00 EXA",
    ref: "RWD-201844",
  },
  {
    id: "TX-EXA-901110",
    type: "P2P",
    asset: "BTC",
    amount: "+0.0184",
    amountFiat: "NGN 3,100,000",
    status: "Failed",
    date: "Feb 22, 2026",
    time: "05:47 PM",
    network: "Bitcoin",
    confirmations: "Failed",
    fee: "0.00012 BTC",
    ref: "P2P-710662",
  },
  {
    id: "TX-EXA-901089",
    type: "Withdrawal",
    asset: "ETH",
    amount: "-0.920",
    amountFiat: "NGN 7,202,000",
    status: "Completed",
    date: "Feb 22, 2026",
    time: "11:12 AM",
    network: "ERC20",
    confirmations: "18/18",
    fee: "0.0021 ETH",
    ref: "WDR-880405",
  },
];

function typeConfig(type) {
  if (type === "Deposit") return { icon: ArrowDownLeft, color: "text-emerald-300", bg: "bg-emerald-500/12 border-emerald-300/20" };
  if (type === "Withdrawal") return { icon: ArrowUpRight, color: "text-rose-300", bg: "bg-rose-500/12 border-rose-300/20" };
  if (type === "Reward") return { icon: Gift, color: "text-[var(--exa-gold-light)]", bg: "bg-amber-400/12 border-amber-300/30" };
  if (type === "P2P") return { icon: Users, color: "text-[var(--exa-gold-light)]", bg: "bg-[var(--exa-gold-surface)] border-[var(--exa-border-active)]" };
  return { icon: Shuffle, color: "text-slate-200", bg: "bg-slate-600/15 border-slate-300/20" };
}

function statusClasses(status) {
  if (status === "Completed") return "border-emerald-300/35 bg-emerald-400/15 text-emerald-200";
  if (status === "Pending") return "border-amber-300/35 bg-amber-400/15 text-[var(--exa-gold-light)]";
  return "border-rose-300/35 bg-rose-400/15 text-rose-200";
}

function StatusDot({ status }) {
  if (status === "Completed") return <span className="h-2.5 w-2.5 rounded-full bg-emerald-400" />;
  if (status === "Pending") return <span className="status-pulse h-2.5 w-2.5 rounded-full bg-amber-400" />;
  return <XCircle className="h-3.5 w-3.5 text-rose-300" />;
}

function humanType(type) {
  const mapping = {
    deposit: "Deposit",
    withdrawal: "Withdrawal",
    internal_transfer: "P2P",
    trade: "Trade",
    staking_lock: "Staking",
    staking_reward: "Reward",
    nft_purchase: "NFT",
    lottery_bet: "Lottery",
    lottery_reward: "Reward",
    referral_reward: "Reward",
    platform_reward: "Reward",
  };

  return mapping[type] || "Activity";
}

function humanStatus(status) {
  if (!status) return "Pending";
  const normalized = String(status).toLowerCase();
  if (normalized === "completed") return "Completed";
  if (normalized === "failed") return "Failed";
  if (normalized === "cancelled") return "Failed";
  if (normalized === "processing") return "Pending";
  return "Pending";
}

function formatDateTime(value) {
  if (!value) return { date: "--", time: "--" };
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return { date: "--", time: "--" };
  }
  return {
    date: date.toLocaleDateString("en-US", { month: "short", day: "2-digit", year: "numeric" }),
    time: date.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" }),
  };
}

function mapApiTransaction(tx, userId) {
  const { date, time } = formatDateTime(tx.created_at || tx.updated_at);
  const type = humanType(tx.type);
  const status = humanStatus(tx.status);
  const amountValue = Number(tx.amount || 0);
  const isCredit = ["Deposit", "Reward"].includes(type);
  const amountPrefix = isCredit ? "+" : "-";
  const amount = `${amountPrefix}${Math.abs(amountValue).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 8 })}`;

  return {
    id: tx.transaction_id || String(tx.id || ""),
    type,
    asset: tx.currency || "EXA",
    amount,
    amountFiat: "NGN --",
    status,
    date,
    time,
    network: tx.metadata?.network || "Base",
    confirmations: tx.metadata?.confirmations || "--",
    fee: tx.fee ? `${tx.fee} ${tx.currency}` : `0.00 ${tx.currency || "EXA"}`,
    ref: tx.reference || "--",
    txHash: tx.tx_hash || "",
    raw: tx,
  };
}

function Transactions({ onBack }) {
  const { apiBaseUrl, token, user } = useAuth();
  const { t } = useLanguage();
  const userId = user?.id ?? user?.user_id ?? null;
  const [activeTab, setActiveTab] = useState("All");
  const [query, setQuery] = useState("");
  const [transactions, setTransactions] = useState(transactionsSeed);
  const [selectedTx, setSelectedTx] = useState(transactionsSeed[0]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const typeLabel = (type) => {
    const keys = {
      Deposit: "transactions.deposit",
      Withdrawal: "transactions.withdrawal",
      Reward: "transactions.reward",
      Trade: "transactions.trade",
      Staking: "transactions.staking",
      NFT: "transactions.nft",
      Lottery: "transactions.lottery",
      Activity: "transactions.activity",
      P2P: "transactions.p2p",
    };
    return t(keys[type] || "transactions.activity");
  };

  const statusLabel = (status) => {
    const keys = {
      Completed: "transactions.completed",
      Pending: "transactions.pending",
      Failed: "transactions.failed",
    };
    return t(keys[status] || "transactions.pending");
  };

  const tabLabel = (tab) => {
    const keys = {
      All: "transactions.all",
      Deposits: "transactions.deposits",
      Withdrawals: "transactions.withdrawals",
      Rewards: "transactions.rewards",
      P2P: "transactions.p2p",
    };
    return t(keys[tab] || tab);
  };

  useEffect(() => {
    let isMounted = true;

    async function loadTransactions() {
      if (!apiBaseUrl || !userId) {
        return;
      }

      setLoading(true);
      setError("");
      try {
        const payload = await fetchUserTransactions({ apiBaseUrl, token, userId, perPage: 50 });
        const rows = payload?.data?.data ?? payload?.data ?? [];
        const mapped = rows.map((tx) => mapApiTransaction(tx, userId));
        if (isMounted && mapped.length) {
          setTransactions(mapped);
          setSelectedTx(mapped[0]);
        }
      } catch (err) {
        if (isMounted) {
          setError(err?.message || t("transactions.unableToLoad"));
        }
      } finally {
        if (isMounted) {
          setLoading(false);
        }
      }
    }

    loadTransactions();

    return () => {
      isMounted = false;
    };
  }, [apiBaseUrl, token, userId]);

  const filteredTransactions = useMemo(() => {
    return transactions.filter((tx) => {
      const tabMatch = activeTab === "All" || tx.type === activeTab.slice(0, -1) || tx.type === activeTab;
      const q = query.trim().toLowerCase();
      const queryMatch = !q || tx.id.toLowerCase().includes(q) || tx.asset.toLowerCase().includes(q) || tx.ref.toLowerCase().includes(q);
      return tabMatch && queryMatch;
    });
  }, [activeTab, query, transactions]);

  const stats = useMemo(() => {
    const deposits = transactions.filter((tx) => tx.type === "Deposit" && tx.status !== "Failed").length;
    const withdrawals = transactions.filter((tx) => tx.type === "Withdrawal" && tx.status !== "Failed").length;
    const rewards = transactions.filter((tx) => tx.type === "Reward").length;
    const pending = transactions.filter((tx) => tx.status === "Pending").length;
    return [
      { label: t("transactions.totalDeposits"), value: deposits, tone: "from-emerald-400/20 to-emerald-300/5" },
      { label: t("transactions.totalWithdrawals"), value: withdrawals, tone: "from-rose-400/20 to-rose-300/5" },
      { label: t("transactions.rewardsEarned"), value: rewards, tone: "from-amber-400/20 to-amber-300/5" },
      { label: t("transactions.pendingTransactions"), value: pending, tone: "from-[var(--exa-gold-surface)] to-transparent" },
    ];
  }, [t, transactions]);

  return (
    <main className="relative min-h-screen overflow-hidden bg-[var(--exa-bg-primary)] px-4 py-8 text-[var(--exa-text-primary)] sm:px-6 sm:py-10">
      <section className="mx-auto w-full max-w-7xl rounded-3xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-panel)] backdrop-blur-xl sm:p-6 lg:p-8">
        <header className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-4 sm:p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              {onBack ? (
                <button
                  type="button"
                  onClick={onBack}
                  className="mb-3 inline-flex items-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-xs font-semibold text-[var(--exa-text-secondary)] transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
                >
                  <ArrowLeft className="h-4 w-4" />
                  {t("common.back")}
                </button>
              ) : null}
              <h1 className="font-['Sora'] text-3xl font-semibold tracking-tight text-[var(--exa-text-primary)] sm:text-4xl">{t("transactions.title")}</h1>
              <p className="mt-1 text-sm text-[var(--exa-text-secondary)]">{t("transactions.subtitle")}</p>
            </div>
            <div className="flex items-center gap-2">
              <button className="inline-flex h-10 items-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 text-sm font-semibold text-[var(--exa-text-secondary)] transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]">
                <Download className="h-4 w-4" />
                {t("transactions.export")}
              </button>
              <button className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-secondary)] transition hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]">
                <Filter className="h-4 w-4" />
              </button>
            </div>
          </div>
        </header>

        <section className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {stats.map((item) => (
            <article
              key={item.label}
              className={`rounded-2xl border border-[var(--exa-border)] bg-gradient-to-br ${item.tone} p-4 shadow-[var(--exa-shadow-soft)]`}
            >
              <p className="text-xs text-[var(--exa-text-muted)]">{item.label}</p>
              <p className="mt-2 text-2xl font-semibold text-[var(--exa-text-primary)]">{item.value}</p>
            </article>
          ))}
        </section>

        <section className="mt-5 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-3">
          {error ? (
            <div className="mb-3 rounded-xl border border-rose-300/30 bg-rose-500/10 px-3 py-2 text-xs text-rose-200">
              {error}
            </div>
          ) : null}
          <div className="flex gap-2 overflow-x-auto pb-1">
            {filterTabs.map((tab) => (
              <button
                key={tab}
                type="button"
                onClick={() => setActiveTab(tab)}
                className={`rounded-full px-4 py-2 text-sm font-semibold transition ${activeTab === tab ? "bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)]" : "border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)] hover:text-[var(--exa-text-primary)]"}`}
              >
                {tabLabel(tab)}
              </button>
            ))}
          </div>
          <div className="relative mt-3">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--exa-text-muted)]" />
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder={t("transactions.searchPlaceholder")}
              className="w-full rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] py-2.5 pl-10 pr-3 text-sm text-white outline-none transition focus:border-[var(--exa-border-active)]"
            />
          </div>
        </section>

        <section className="mt-5 grid gap-4 xl:grid-cols-[1.55fr_1fr]">
          <div className="space-y-3">
            {loading ? (
              <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-12 text-center">
                <Shuffle className="mx-auto h-10 w-10 text-[var(--exa-text-muted)]" />
                <p className="mt-3 text-base font-semibold text-[var(--exa-text-primary)]">{t("transactions.loadingTitle")}</p>
                <p className="mt-1 text-sm text-[var(--exa-text-muted)]">{t("transactions.loadingBody")}</p>
              </div>
            ) : filteredTransactions.length ? (
              filteredTransactions.map((tx) => {
                const config = typeConfig(tx.type);
                const TypeIcon = config.icon;
                return (
                  <article
                    key={tx.id}
                    className="cursor-pointer rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-soft)] transition duration-300 hover:-translate-y-0.5 hover:border-[var(--exa-border-active)]"
                    onClick={() => setSelectedTx(tx)}
                  >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div className="flex min-w-0 items-start gap-3">
                        <span className={`inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border ${config.bg}`}>
                          <TypeIcon className={`h-4.5 w-4.5 ${config.color}`} />
                        </span>
                        <div className="min-w-0">
                          <p className="text-sm font-semibold text-[var(--exa-text-primary)]">{typeLabel(tx.type)}</p>
                          <p className="text-xs text-[var(--exa-text-muted)]">{tx.asset}  |  {tx.ref}</p>
                          <p className="mt-1 text-xs text-[var(--exa-text-muted)]">{tx.date} {t("transactions.at")} {tx.time}</p>
                        </div>
                      </div>
                      <div className="text-right">
                        <p className={`text-sm font-semibold ${tx.amount.startsWith("+") ? "text-emerald-300" : "text-rose-300"}`}>{tx.amount} {tx.asset}</p>
                        <p className="text-xs text-[var(--exa-text-muted)]">{tx.amountFiat}</p>
                        <span className={`mt-1 inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold ${statusClasses(tx.status)}`}>
                          <StatusDot status={tx.status} />
                          {statusLabel(tx.status)}
                        </span>
                      </div>
                    </div>
                  </article>
                );
              })
            ) : (
              <div className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-12 text-center">
                <Shuffle className="mx-auto h-10 w-10 text-[var(--exa-text-muted)]" />
                <p className="mt-3 text-base font-semibold text-[var(--exa-text-primary)]">{t("transactions.emptyTitle")}</p>
                <p className="mt-1 text-sm text-[var(--exa-text-muted)]">{t("transactions.emptyBody")}</p>
              </div>
            )}
          </div>

          <aside className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 shadow-[var(--exa-shadow-soft)] sm:p-5">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="font-['Sora'] text-lg font-semibold text-[var(--exa-text-primary)]">{t("transactions.detailTitle")}</h2>
              <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold ${statusClasses(selectedTx.status)}`}>
                <StatusDot status={selectedTx.status} />
                {statusLabel(selectedTx.status)}
              </span>
            </div>

            <div className="space-y-2 text-sm">
              <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3">
                <p className="text-xs text-[var(--exa-text-muted)]">{t("transactions.typeAsset")}</p>
                <p className="font-semibold text-[var(--exa-text-primary)]">{typeLabel(selectedTx.type)}  |  {selectedTx.asset}</p>
              </div>
              <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3">
                <p className="text-xs text-[var(--exa-text-muted)]">{t("transactions.amount")}</p>
                <p className="font-semibold text-[var(--exa-text-primary)]">{selectedTx.amount} {selectedTx.asset}</p>
              </div>
              <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3">
                <p className="text-xs text-[var(--exa-text-muted)]">{t("transactions.networkConfirmations")}</p>
                <p className="font-semibold text-[var(--exa-text-primary)]">{selectedTx.network}  |  {selectedTx.confirmations}</p>
              </div>
              <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3">
                <p className="text-xs text-[var(--exa-text-muted)]">{t("transactions.dateTime")}</p>
                <p className="font-semibold text-[var(--exa-text-primary)]">{selectedTx.date} {t("transactions.at")} {selectedTx.time}</p>
              </div>
              <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3">
                <p className="text-xs text-[var(--exa-text-muted)]">{t("transactions.feeReference")}</p>
                <p className="font-semibold text-[var(--exa-text-primary)]">{selectedTx.fee}  |  {selectedTx.ref}</p>
              </div>
              <div className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3">
                <p className="text-xs text-[var(--exa-text-muted)]">{t("transactions.blockchainViewer")}</p>
                <a
                  href="#"
                  onClick={(event) => event.preventDefault()}
                  className="inline-flex items-center gap-1 font-semibold text-[var(--exa-gold-light)] transition hover:text-[var(--exa-gold)]"
                >
                  {selectedTx.id}
                  <ExternalLink className="h-3.5 w-3.5" />
                </a>
              </div>
            </div>
          </aside>
        </section>

        <p className="mt-6 text-center text-xs text-[var(--exa-text-muted)]">
          {t("transactions.secureRecorded")}
        </p>
      </section>

      <style>{`
        .status-pulse {
          animation: txPulse 1.4s ease-in-out infinite;
          box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.45);
        }
        @keyframes txPulse {
          0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.4);
          }
          70% {
            transform: scale(1);
            box-shadow: 0 0 0 8px rgba(251, 191, 36, 0);
          }
          100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(251, 191, 36, 0);
          }
        }
      `}</style>
    </main>
  );
}

export default Transactions;
