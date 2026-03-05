import { useMemo, useState } from "react";
import {
  AlertTriangle,
  BellRing,
  BotMessageSquare,
  Bug,
  CandlestickChart,
  ChevronDown,
  CircleCheckBig,
  CircleHelp,
  Coins,
  Copy,
  Gift,
  HandCoins,
  Landmark,
  LifeBuoy,
  Link as LinkIcon,
  LockKeyhole,
  Mail,
  Megaphone,
  Search,
  Shield,
  ShieldAlert,
  Sparkles,
  Ticket,
  Users,
  Wallet,
} from "lucide-react";

const quickActions = [
  { id: "chat", label: "Chat with Support", icon: BotMessageSquare, tone: "primary" },
  { id: "ticket", label: "Submit a Ticket", icon: Ticket },
  { id: "report", label: "Report Suspicious Activity", icon: ShieldAlert },
  { id: "status", label: "View System Status", icon: BellRing },
];

const categories = [
  { id: "account", label: "Account & Verification (KYC)", icon: Shield },
  { id: "funding", label: "Deposits & Withdrawals", icon: Wallet },
  { id: "trading", label: "Trading & Futures", icon: CandlestickChart },
  { id: "staking", label: "Staking & Rewards", icon: Coins },
  { id: "swap", label: "Token Swap", icon: Sparkles },
  { id: "p2p", label: "P2P Marketplace", icon: HandCoins },
  { id: "referral", label: "Referral Program", icon: Users },
  { id: "security", label: "Security & 2FA", icon: LockKeyhole },
  { id: "campaigns", label: "Campaigns & Bonuses", icon: Megaphone },
];

const faqSeed = [
  {
    id: "faq-1",
    q: "How long does KYC verification usually take?",
    a: "Most KYC checks complete within a few minutes. Manual checks can take up to 24 hours depending on document clarity.",
  },
  {
    id: "faq-2",
    q: "Why is my withdrawal still pending?",
    a: "Pending withdrawals may be caused by blockchain congestion, account security review, or incomplete verification requirements.",
  },
  {
    id: "faq-3",
    q: "How can I secure my account against phishing?",
    a: "Enable 2FA, verify official domains, never share your password/private key, and review security alerts regularly.",
  },
  {
    id: "faq-4",
    q: "When are staking rewards distributed?",
    a: "Reward distribution depends on the selected staking product and lock period. You can track payout timing inside staking details.",
  },
  {
    id: "faq-5",
    q: "How does referral reward settlement work?",
    a: "Referral rewards are calculated based on eligible activity and distributed automatically via the ExaEarn reward engine.",
  },
];

const articleSeed = [
  {
    id: "art-1",
    title: "How to Complete KYC in Under 5 Minutes",
    views: 48210,
    category: "Account & Verification",
    body: "Prepare a valid ID, ensure bright lighting, and verify your personal details exactly as shown on your document.",
  },
  {
    id: "art-2",
    title: "Withdrawal Pending: Complete Troubleshooting Guide",
    views: 41022,
    category: "Wallet",
    body: "Check network status, confirmation count, withdrawal queue, and security verification prompts before retrying.",
  },
  {
    id: "art-3",
    title: "Top 7 Security Settings Every Trader Should Enable",
    views: 39784,
    category: "Security",
    body: "2FA, anti-phishing code, trusted device management, withdrawal whitelist, and login notifications are essential.",
  },
  {
    id: "art-4",
    title: "Understanding Futures Liquidation Alerts",
    views: 32510,
    category: "Trading",
    body: "Liquidation alerts trigger when maintenance margin approaches risk threshold. Add collateral or reduce position size.",
  },
  {
    id: "art-5",
    title: "Referral Program: How Payouts Are Calculated",
    views: 29103,
    category: "Referral",
    body: "Payout rates vary by tier and product activity. Referral analytics are updated in near real-time on your dashboard.",
  },
  {
    id: "art-6",
    title: "How to Avoid P2P Settlement Disputes",
    views: 22111,
    category: "P2P",
    body: "Use verified payment methods, match payer details, and keep proof of transfer before releasing assets.",
  },
  {
    id: "art-7",
    title: "Token Swap Slippage Settings Explained",
    views: 19342,
    category: "Swap",
    body: "Slippage tolerance controls execution certainty vs price precision during volatile market conditions.",
  },
  {
    id: "art-8",
    title: "Campaign Bonus Eligibility Rules",
    views: 14890,
    category: "Campaigns",
    body: "Campaign rewards depend on eligibility criteria, region support, and completion of required actions.",
  },
];

function HelpSupportCenter({ onBack, onOpenLiveChat, onOpenTicketCenter }) {
  const [search, setSearch] = useState("");
  const [openFaq, setOpenFaq] = useState(faqSeed[0].id);
  const [articleLimit, setArticleLimit] = useState(4);
  const [selectedArticle, setSelectedArticle] = useState(null);
  const [toast, setToast] = useState("");

  const normalized = search.trim().toLowerCase();

  const filteredFaq = useMemo(() => {
    if (!normalized) return faqSeed;
    return faqSeed.filter((item) => item.q.toLowerCase().includes(normalized) || item.a.toLowerCase().includes(normalized));
  }, [normalized]);

  const filteredArticles = useMemo(() => {
    if (!normalized) return articleSeed;
    return articleSeed.filter(
      (item) =>
        item.title.toLowerCase().includes(normalized) ||
        item.category.toLowerCase().includes(normalized) ||
        item.body.toLowerCase().includes(normalized)
    );
  }, [normalized]);

  const visibleArticles = filteredArticles.slice(0, articleLimit);

  const fireToast = (message) => {
    setToast(message);
    setTimeout(() => setToast(""), 2200);
  };

  const triggerAction = (id) => {
    if (id === "chat") {
      onOpenLiveChat?.();
      return;
    }
    if (id === "ticket") {
      onOpenTicketCenter?.();
      return;
    }
    if (id === "report") {
      fireToast("Suspicious activity report flow opened.");
      return;
    }
    if (id === "status") {
      const target = document.getElementById("system-status");
      target?.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  };

  const copySupportEmail = async () => {
    try {
      await navigator.clipboard.writeText("support@exaearn.io");
      fireToast("Support email copied.");
    } catch (error) {
      fireToast("Unable to copy support email.");
    }
  };

  return (
    <main className="min-h-screen bg-[#070B14] text-white">
      <header
        className="sticky top-0 z-30 border-b border-[#D4AF37]/20 bg-gradient-to-r from-[#121A2A]/95 via-[#0E1524]/95 to-[#0A0F1D]/95 backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-6xl px-4 pb-4 pt-3 sm:px-6">
          <button
            type="button"
            onClick={onBack}
            className="mb-3 rounded-xl border border-white/15 bg-[#111827] px-3 py-1.5 text-sm text-[#E6EAF2] hover:border-[#D4AF37]/60"
          >
            Back
          </button>
          <h1 className="text-2xl font-semibold text-[#F8F1DE] sm:text-3xl">Help & Support Center</h1>
          <p className="mt-1 text-sm text-[#B8C0CF]">Find answers or contact our support team</p>
          <div className="mt-4 rounded-2xl border border-white/15 bg-[#101827]/90 px-3 py-3">
            <div className="flex items-center gap-2">
              <Search className="h-4 w-4 text-[#D4AF37]" />
              <input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search articles, topics, or questions..."
                className="w-full bg-transparent text-sm text-white placeholder:text-[#8F99AB] outline-none"
              />
            </div>
          </div>
        </div>
      </header>

      <section className="mx-auto w-full max-w-6xl px-4 pb-8 pt-5 sm:px-6">
        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
          {quickActions.map((item) => {
            const Icon = item.icon;
            return (
              <button
                key={item.id}
                type="button"
                onClick={() => triggerAction(item.id)}
                className={`rounded-2xl border px-3 py-3 text-left transition ${
                  item.tone === "primary"
                    ? "border-[#D4AF37]/55 bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] text-[#111827] shadow-[0_8px_20px_rgba(212,175,55,0.28)]"
                    : "border-white/12 bg-[#101827]/85 text-[#E6EAF2] hover:border-[#D4AF37]/45 hover:shadow-[0_0_0_1px_rgba(212,175,55,0.22)]"
                }`}
              >
                <div className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-white/15 bg-[#111827]/40">
                  <Icon className="h-4 w-4" />
                </div>
                <p className="mt-2 text-sm font-semibold">{item.label}</p>
              </button>
            );
          })}
        </div>

        <section className="mt-4 rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-base font-semibold text-[#F8F1DE]">Support Categories</h2>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {categories.map((item) => {
              const Icon = item.icon;
              return (
                <button
                  key={item.id}
                  type="button"
                  className="group rounded-2xl border border-white/12 bg-[#0C1424] p-3 text-left transition hover:-translate-y-0.5 hover:border-[#D4AF37]/55 hover:shadow-[0_0_0_1px_rgba(212,175,55,0.22),0_12px_24px_rgba(0,0,0,0.35)]"
                >
                  <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#D4AF37]/25 bg-[#D4AF37]/10 text-[#D4AF37] transition group-hover:shadow-[0_0_16px_rgba(212,175,55,0.34)]">
                    <Icon className="h-5 w-5" />
                  </span>
                  <p className="mt-2 text-sm font-medium text-[#E6EAF2]">{item.label}</p>
                </button>
              );
            })}
          </div>
        </section>

        <section className="mt-4 rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-base font-semibold text-[#F8F1DE]">Frequently Asked Questions</h2>
          <div className="space-y-2">
            {filteredFaq.length ? (
              filteredFaq.map((item) => {
                const isOpen = openFaq === item.id;
                return (
                  <article
                    key={item.id}
                    className={`overflow-hidden rounded-xl border bg-[#0C1424] transition ${isOpen ? "border-[#D4AF37]/55" : "border-white/10"}`}
                  >
                    <button
                      type="button"
                      onClick={() => setOpenFaq((prev) => (prev === item.id ? "" : item.id))}
                      className="flex w-full items-center justify-between px-3 py-3 text-left"
                    >
                      <span className="text-sm font-medium text-[#E6EAF2]">{item.q}</span>
                      <ChevronDown className={`h-4 w-4 text-[#D4AF37] transition ${isOpen ? "rotate-180" : ""}`} />
                    </button>
                    <div className={`grid transition-all duration-300 ${isOpen ? "grid-rows-[1fr]" : "grid-rows-[0fr]"}`}>
                      <div className="overflow-hidden px-3 pb-3 text-sm text-[#B8C0CF]">{item.a}</div>
                    </div>
                  </article>
                );
              })
            ) : (
              <p className="rounded-xl border border-white/10 bg-[#0C1424] px-3 py-3 text-sm text-[#98A1B2]">
                No FAQ results found for this search.
              </p>
            )}
          </div>
        </section>

        <section className="mt-4 rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-base font-semibold text-[#F8F1DE]">Popular Articles</h2>
          <div className="space-y-2">
            {visibleArticles.length ? (
              visibleArticles.map((article) => (
                <button
                  key={article.id}
                  type="button"
                  onClick={() => setSelectedArticle(article)}
                  className="grid w-full grid-cols-[1fr_auto] gap-3 rounded-xl border border-white/10 bg-[#0C1424] px-3 py-2 text-left transition hover:border-[#D4AF37]/45"
                >
                  <div>
                    <p className="text-sm font-medium text-[#E6EAF2]">{article.title}</p>
                    <p className="text-xs text-[#98A1B2]">{article.category}</p>
                  </div>
                  <p className="text-xs text-[#D4AF37]">{article.views.toLocaleString()} views</p>
                </button>
              ))
            ) : (
              <p className="rounded-xl border border-white/10 bg-[#0C1424] px-3 py-3 text-sm text-[#98A1B2]">
                No matching articles found.
              </p>
            )}
          </div>
          {articleLimit < filteredArticles.length ? (
            <button
              type="button"
              onClick={() => setArticleLimit((prev) => prev + 4)}
              className="mt-3 rounded-lg border border-white/15 bg-[#111827] px-3 py-1.5 text-xs text-[#D7DDEA]"
            >
              Load more articles
            </button>
          ) : null}
        </section>

        <section className="mt-4 rounded-2xl border border-[#EF4444]/35 bg-[#EF4444]/10 p-4">
          <p className="flex items-start gap-2 text-sm text-[#FECACA]">
            <AlertTriangle className="mt-0.5 h-4 w-4 text-[#F87171]" />
            ExaEarn will never ask for your password or private key.
          </p>
          <div className="mt-3 space-y-1 text-xs text-[#FECACA]/90">
            <p>- Verify official domains before signing in.</p>
            <p>- Never share OTP, recovery phrase, or private keys.</p>
            <p>- Enable 2FA and anti-phishing code in account security.</p>
            <button type="button" className="inline-flex items-center gap-1 text-[#FCA5A5] underline">
              <Shield className="h-3.5 w-3.5" />
              Open Security Center
            </button>
          </div>
        </section>

        <section id="system-status" className="mt-4 rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-base font-semibold text-[#F8F1DE]">System Status</h2>
          <div className="grid gap-2 sm:grid-cols-3">
            <StatusItem label="Trading" status="Operational" />
            <StatusItem label="Withdrawals" status="Operational" />
            <StatusItem label="P2P" status="Operational" />
          </div>
        </section>

        <footer className="mt-4 rounded-2xl border border-white/10 bg-[#0E1524]/90 p-4">
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-2 text-sm text-[#D7DDEA]">
              <button type="button" onClick={copySupportEmail} className="inline-flex items-center gap-2 hover:text-[#D4AF37]">
                <Mail className="h-4 w-4 text-[#D4AF37]" />
                support@exaearn.io
              </button>
              <button type="button" onClick={onOpenLiveChat} className="inline-flex items-center gap-2 hover:text-[#D4AF37]">
                <LifeBuoy className="h-4 w-4 text-[#D4AF37]" />
                Live chat support
              </button>
              <p className="inline-flex items-center gap-2">
                <CircleHelp className="h-4 w-4 text-[#D4AF37]" />
                Community channels: Telegram, Twitter/X, Discord
              </p>
            </div>
            <div className="flex flex-wrap items-end gap-3 text-xs text-[#A5AFC0] sm:justify-end">
              <a href="#" className="hover:text-[#D4AF37]">Terms of Service</a>
              <a href="#" className="hover:text-[#D4AF37]">Privacy Policy</a>
            </div>
          </div>
        </footer>
      </section>

      {selectedArticle ? (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-4 sm:items-center">
          <div className="w-full max-w-lg rounded-2xl border border-white/15 bg-[#0E1524] p-4">
            <p className="text-xs text-[#98A1B2]">{selectedArticle.category}</p>
            <h3 className="mt-1 text-lg font-semibold text-[#F8F1DE]">{selectedArticle.title}</h3>
            <p className="mt-2 text-sm text-[#C4CCD9]">{selectedArticle.body}</p>
            <div className="mt-3 flex items-center justify-between text-xs text-[#98A1B2]">
              <span>{selectedArticle.views.toLocaleString()} views</span>
              <button
                type="button"
                onClick={() => setSelectedArticle(null)}
                className="rounded-lg border border-white/15 bg-[#111827] px-3 py-1 text-[#D7DDEA]"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {toast ? (
        <div className="fixed right-4 top-24 z-50 rounded-xl border border-[#22C55E]/35 bg-[#22C55E]/12 px-3 py-2 text-xs text-[#BBF7D0] shadow-lg">
          {toast}
        </div>
      ) : null}
    </main>
  );
}

function StatusItem({ label, status }) {
  const ok = status === "Operational";
  return (
    <article className="rounded-xl border border-white/10 bg-[#0C1424] p-3">
      <p className="text-xs text-[#98A1B2]">{label}</p>
      <p className={`mt-1 inline-flex items-center gap-1 text-sm font-medium ${ok ? "text-[#86EFAC]" : "text-[#FCA5A5]"}`}>
        {ok ? <CircleCheckBig className="h-4 w-4" /> : <Bug className="h-4 w-4" />}
        {status}
      </p>
    </article>
  );
}

export default HelpSupportCenter;
