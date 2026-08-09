import {
  ArrowLeft,
  Award,
  Bot,
  Building2,
  CircleHelp,
  Coins,
  CreditCard,
  FlaskConical,
  Gift,
  GraduationCap,
  HandHeart,
  HeartPulse,
  Headphones,
  ReceiptText,
  Search,
  Sparkles,
  Trophy,
  Wallet,
} from "lucide-react";

const moreItems = [
  { id: "exabank", label: "ExaBank", icon: Building2 },
  { id: "exalife", label: "ExaLife", icon: HeartPulse },
  { id: "exacard", label: "ExaCard", icon: CreditCard },
  { id: "exaai", label: "ExaAI", icon: Bot, badge: "NEW", action: "aiAssistant" },
  { id: "token", label: "Token", icon: Coins, action: "token" },
  { id: "transactions", label: "Transactions", icon: ReceiptText, action: "transactions" },
  { id: "sports", label: "Sports Talent Pool", icon: Trophy, action: "sports" },
  { id: "support", label: "Customer Support", icon: Headphones, action: "helpSupport" },
  { id: "foundation", label: "ExaEarn Foundation", icon: HandHeart },
  { id: "exapay", label: "ExaPay", icon: Wallet },
  { id: "exalabs", label: "ExaLabs", icon: FlaskConical },
  { id: "academy", label: "ExaAcademy", icon: GraduationCap },
  { id: "rewards", label: "Rewards", icon: Gift, badge: "HOT", action: "rewards" },
  { id: "help", label: "Helps & FAQ", icon: CircleHelp, action: "helpSupport" },
  { id: "referral", label: "Referral Program", icon: Sparkles, action: "referral" },
  { id: "quests", label: "Quest & Tasks", icon: Trophy },
  { id: "certificate", label: "Certificate", icon: Award },
];

function MorePage({
  onBack,
  onOpenRewards,
  onOpenReferral,
  onOpenHelpSupport,
  onOpenAiAssistant,
  onOpenToken,
  onOpenTransactions,
  onOpenSports,
}) {
  const handleOpenItem = (item) => {
    if (item.action === "rewards") {
      onOpenRewards?.();
    }
    if (item.action === "referral") {
      onOpenReferral?.();
    }
    if (item.action === "helpSupport") {
      onOpenHelpSupport?.();
    }
    if (item.action === "aiAssistant") {
      onOpenAiAssistant?.();
    }
    if (item.action === "token") {
      onOpenToken?.();
    }
    if (item.action === "transactions") {
      onOpenTransactions?.();
    }
    if (item.action === "sports") {
      onOpenSports?.();
    }
  };

  return (
    <main className="min-h-screen bg-[var(--exa-bg-primary)] text-[var(--exa-text-primary)]">
      <header className="sticky top-0 z-20 border-b border-[var(--exa-border-active)] bg-[var(--exa-surface)] backdrop-blur">
        <div className="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
          <button
            type="button"
            onClick={onBack}
            className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold-light)] transition hover:border-[var(--exa-border-active)]"
          >
            <ArrowLeft className="h-5 w-5" />
          </button>
          <h1 className="text-xl font-semibold tracking-tight text-[var(--exa-text-primary)]">More</h1>
          <button
            type="button"
            className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold-light)]"
          >
            <Search className="h-5 w-5" />
          </button>
        </div>
      </header>

      <section className="mx-auto w-full max-w-5xl px-4 pb-8 pt-6 sm:px-6 lg:px-8">
        <div className="mb-4 rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] p-4">
          <p className="text-sm text-[var(--exa-text-secondary)]">ExaEarn Ecosystem</p>
          <h2 className="mt-1 text-lg font-semibold text-[var(--exa-text-primary)]">Access all ExaEarn products and utilities</h2>
        </div>

        <div className="grid grid-cols-3 gap-2.5 sm:grid-cols-4 lg:grid-cols-5">
          {moreItems.map((item) => {
            const Icon = item.icon;
            return (
              <button
                key={item.id}
                type="button"
                onClick={() => handleOpenItem(item)}
                className="relative min-h-[104px] rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-2.5 text-center transition duration-300 hover:-translate-y-0.5 hover:border-[var(--exa-border-active)] hover:shadow-[var(--exa-shadow-soft)]"
              >
                {item.badge ? <Badge type={item.badge} /> : null}
                <span className="mx-auto inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold-light)]">
                  <Icon className="h-5 w-5" />
                </span>
                <p className="mt-2 text-[11px] font-semibold leading-tight text-[var(--exa-text-primary)]">{item.label}</p>
              </button>
            );
          })}
        </div>
      </section>
    </main>
  );
}

function Badge({ type }) {
  if (type === "HOT") {
    return (
      <span className="absolute right-2 top-2 rounded-full bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-2 py-0.5 text-[9px] font-bold text-[var(--exa-gold-contrast)]">
        HOT
      </span>
    );
  }

  return (
    <span className="absolute right-2 top-2 rounded-full bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-2 py-0.5 text-[9px] font-bold text-[var(--exa-gold-contrast)]">
      NEW
    </span>
  );
}

export default MorePage;
