import {
  ArrowLeft,
  Award,
  Bot,
  Building2,
  CircleHelp,
  CreditCard,
  FlaskConical,
  Gift,
  GraduationCap,
  HandHeart,
  HeartPulse,
  Search,
  Sparkles,
  Trophy,
  Wallet,
} from "lucide-react";

const moreItems = [
  { id: "exabank", label: "ExaBank", icon: Building2 },
  { id: "exalife", label: "ExaLife", icon: HeartPulse },
  { id: "exacard", label: "ExaCard", icon: CreditCard },
  { id: "exaai", label: "ExaAI", icon: Bot, badge: "NEW" },
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

function MorePage({ onBack, onOpenRewards, onOpenReferral, onOpenHelpSupport }) {
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
  };

  return (
    <main className="min-h-screen bg-gradient-to-b from-[#0A0F1D] via-[#0B1224] to-[#070B14] text-white">
      <header className="sticky top-0 z-20 border-b border-[#D4AF37]/25 bg-[#0A0F1D]/95 backdrop-blur">
        <div className="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
          <button
            type="button"
            onClick={onBack}
            className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#D4AF37]/30 bg-[#111827] text-[#F3E8C8] transition hover:border-[#D4AF37]/70"
          >
            <ArrowLeft className="h-5 w-5" />
          </button>
          <h1 className="text-xl font-semibold tracking-tight text-[#F8F1DE]">More</h1>
          <button
            type="button"
            className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#D4AF37]/30 bg-[#111827] text-[#F3E8C8]"
          >
            <Search className="h-5 w-5" />
          </button>
        </div>
      </header>

      <section className="mx-auto w-full max-w-5xl px-4 pb-8 pt-6 sm:px-6 lg:px-8">
        <div className="mb-4 rounded-2xl border border-[#D4AF37]/30 bg-gradient-to-r from-[#D4AF37]/15 via-[#D4AF37]/10 to-transparent p-4">
          <p className="text-sm text-[#F3E8C8]/90">ExaEarn Ecosystem</p>
          <h2 className="mt-1 text-lg font-semibold text-[#F8F1DE]">Access all ExaEarn products and utilities</h2>
        </div>

        <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-5">
          {moreItems.map((item) => {
            const Icon = item.icon;
            return (
              <button
                key={item.id}
                type="button"
                onClick={() => handleOpenItem(item)}
                className="relative rounded-2xl border border-[#D4AF37]/20 bg-[#111827] p-3 text-center transition duration-300 hover:-translate-y-0.5 hover:border-[#D4AF37]/55 hover:shadow-[0_10px_20px_rgba(0,0,0,0.35)]"
              >
                {item.badge ? <Badge type={item.badge} /> : null}
                <span className="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full border border-[#D4AF37]/25 bg-[#0F172A] text-[#D4AF37]">
                  <Icon className="h-5 w-5" />
                </span>
                <p className="mt-2 text-[11px] font-semibold leading-tight text-[#E8EAF0]">{item.label}</p>
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
      <span className="absolute right-2 top-2 rounded-full bg-gradient-to-r from-[#D4AF37] to-[#E7C96C] px-2 py-0.5 text-[9px] font-bold text-[#111827]">
        HOT
      </span>
    );
  }

  return (
    <span className="absolute right-2 top-2 rounded-full bg-gradient-to-r from-[#D4AF37] to-[#E7C96C] px-2 py-0.5 text-[9px] font-bold text-[#111827]">
      NEW
    </span>
  );
}

export default MorePage;
