import { useState } from "react";
import {
  AlertTriangle,
  BadgeCheck,
  Bug,
  ChevronDown,
  Coins,
  CreditCard,
  Headset,
  LockKeyhole,
  MessageCircle,
  Search,
  Shield,
  Sparkles,
  Ticket,
  UserCheck,
  Users,
  Wallet,
  X,
} from "lucide-react";

const inputClassName =
  "w-full rounded-xl border border-white/15 bg-[#0C1424] px-3 py-2.5 text-sm text-white outline-none placeholder:text-[#8E98AA] focus:border-[#D4AF37]/70";

const supportCategories = [
  { id: "account", label: "Account & Verification", icon: UserCheck },
  { id: "deposits", label: "Deposits & Withdrawals", icon: Wallet },
  { id: "staking", label: "Staking & Rewards", icon: Coins },
  { id: "swap", label: "Token Swap", icon: Sparkles },
  { id: "security", label: "Security & 2FA", icon: Shield },
  { id: "p2p", label: "P2P & Marketplace", icon: CreditCard },
  { id: "referral", label: "Referral & Affiliate Program", icon: Users },
  { id: "bug", label: "Report a Bug", icon: Bug },
];

const faqItems = [
  {
    id: "faq-1",
    question: "How long does KYC verification take?",
    answer: "Most KYC submissions are reviewed within a few minutes. In rare cases, manual verification may take up to 24 hours.",
  },
  {
    id: "faq-2",
    question: "Why is my withdrawal pending?",
    answer:
      "Withdrawals may be pending due to network congestion, security review, or incomplete account verification. You can track status from Transactions.",
  },
  {
    id: "faq-3",
    question: "How do I enable 2FA?",
    answer: "Go to Settings > Security, choose Authenticator App, scan the QR code, then confirm with your 6-digit verification code.",
  },
  {
    id: "faq-4",
    question: "How does referral commission work?",
    answer: "Referral rewards are distributed based on eligible transactions completed by your invited users, according to the current program terms.",
  },
];

function SupportCenter({ onBack, onOpenLiveChat }) {
  const [search, setSearch] = useState("");
  const [ticketForm, setTicketForm] = useState({
    fullName: "",
    email: "",
    userId: "",
    category: "Account & Verification",
    subject: "",
    description: "",
    screenshotName: "",
  });
  const [openFaqId, setOpenFaqId] = useState(faqItems[0].id);
  const [showSuccessModal, setShowSuccessModal] = useState(false);

  const handleInputChange = (field, value) => {
    setTicketForm((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmitTicket = (event) => {
    event.preventDefault();
    setShowSuccessModal(true);
    setTicketForm({
      fullName: "",
      email: "",
      userId: "",
      category: "Account & Verification",
      subject: "",
      description: "",
      screenshotName: "",
    });
  };

  return (
    <main className="min-h-screen bg-[#070B14] text-white">
      <div className="mx-auto w-full max-w-6xl px-4 pb-10 pt-5 sm:px-6 lg:px-8">
        <section className="rounded-2xl border border-[#D4AF37]/25 bg-gradient-to-br from-[#121A2A] via-[#0E1524] to-[#0A0F1D] p-5 shadow-[0_14px_34px_rgba(0,0,0,0.35)]">
          <div className="mb-4 flex items-start justify-between gap-4">
            <div>
              <h1 className="text-2xl font-semibold text-[#F8F1DE] sm:text-3xl">ExaEarn Support Center</h1>
              <p className="mt-1 text-sm text-[#D9DEE8]">We&apos;re here to help you 24/7</p>
            </div>
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-white/15 bg-[#111827] px-3 py-2 text-sm text-[#E6EAF2] hover:border-[#D4AF37]/60"
            >
              Back
            </button>
          </div>

          <label className="block">
            <span className="sr-only">Search help articles</span>
            <div className="flex items-center gap-2 rounded-xl border border-white/15 bg-[#101827]/85 px-3 py-3">
              <Search className="h-4 w-4 text-[#D4AF37]" />
              <input
                type="text"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search help articles..."
                className="w-full bg-transparent text-sm text-white placeholder:text-[#9CA3AF] outline-none"
              />
            </div>
          </label>
        </section>

        <section className="mt-5">
          <h2 className="mb-3 text-lg font-semibold text-[#F8F1DE]">Quick Help</h2>
          <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
            {supportCategories.map((item) => {
              const Icon = item.icon;
              return (
                <button
                  key={item.id}
                  type="button"
                  className="group rounded-2xl border border-[#D4AF37]/20 bg-[#111827]/90 p-4 text-left transition-all duration-300 hover:-translate-y-0.5 hover:border-[#D4AF37]/65 hover:shadow-[0_0_0_1px_rgba(212,175,55,0.32),0_14px_24px_rgba(0,0,0,0.35)]"
                >
                  <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#D4AF37]/25 bg-[#0D1524] text-[#D4AF37] transition group-hover:shadow-[0_0_16px_rgba(212,175,55,0.35)]">
                    <Icon className="h-5 w-5" />
                  </span>
                  <p className="mt-3 text-sm font-semibold leading-snug text-[#E8ECF3]">{item.label}</p>
                </button>
              );
            })}
          </div>
        </section>

        <section className="mt-5 rounded-2xl border border-white/10 bg-[#101827]/85 p-5">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h3 className="text-lg font-semibold text-[#F8F1DE]">Live Support</h3>
              <p className="mt-1 text-sm text-[#B8C0CF]">Average response time: under 3 minutes</p>
            </div>
            <div className="flex items-center gap-2">
              <span className="inline-flex items-center gap-1 rounded-full border border-[#22C55E]/30 bg-[#22C55E]/12 px-3 py-1 text-xs font-semibold text-[#9CF4BD]">
                <BadgeCheck className="h-3.5 w-3.5" />
                24/7
              </span>
              <button
                type="button"
                onClick={onOpenLiveChat}
                className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] px-4 py-2 text-sm font-semibold text-[#111827] shadow-[0_8px_20px_rgba(212,175,55,0.3)]"
              >
                <MessageCircle className="h-4 w-4" />
                Chat with Support
              </button>
            </div>
          </div>
        </section>

        <section className="mt-5 rounded-2xl border border-white/10 bg-[#101827]/85 p-5">
          <h3 className="mb-4 text-lg font-semibold text-[#F8F1DE]">Submit Ticket</h3>
          <form onSubmit={handleSubmitTicket} className="grid gap-3 sm:grid-cols-2">
            <Field label="Full Name">
              <input
                type="text"
                value={ticketForm.fullName}
                onChange={(event) => handleInputChange("fullName", event.target.value)}
                required
                className={inputClassName}
              />
            </Field>
            <Field label="Email">
              <input
                type="email"
                value={ticketForm.email}
                onChange={(event) => handleInputChange("email", event.target.value)}
                required
                className={inputClassName}
              />
            </Field>
            <Field label="User ID">
              <input
                type="text"
                value={ticketForm.userId}
                onChange={(event) => handleInputChange("userId", event.target.value)}
                className={inputClassName}
              />
            </Field>
            <Field label="Category">
              <select
                value={ticketForm.category}
                onChange={(event) => handleInputChange("category", event.target.value)}
                className={inputClassName}
              >
                {supportCategories.map((item) => (
                  <option key={item.id} value={item.label} className="bg-[#0B1220]">
                    {item.label}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Subject" full>
              <input
                type="text"
                value={ticketForm.subject}
                onChange={(event) => handleInputChange("subject", event.target.value)}
                required
                className={inputClassName}
              />
            </Field>
            <Field label="Description" full>
              <textarea
                rows={5}
                value={ticketForm.description}
                onChange={(event) => handleInputChange("description", event.target.value)}
                required
                className={`${inputClassName} resize-none`}
              />
            </Field>
            <Field label="Upload screenshot" full>
              <label className="flex cursor-pointer items-center justify-between rounded-xl border border-dashed border-white/20 bg-[#0C1424] px-3 py-3 text-sm text-[#C5CCDA] hover:border-[#D4AF37]/50">
                <span>{ticketForm.screenshotName || "Click to upload screenshot"}</span>
                <span className="rounded-lg border border-white/15 bg-[#111827] px-2 py-1 text-xs">Upload</span>
                <input
                  type="file"
                  accept="image/*"
                  className="hidden"
                  onChange={(event) => {
                    const fileName = event.target.files?.[0]?.name || "";
                    handleInputChange("screenshotName", fileName);
                  }}
                />
              </label>
            </Field>
            <div className="sm:col-span-2">
              <button
                type="submit"
                className="w-full rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] px-4 py-3 text-sm font-semibold text-[#111827] shadow-[0_10px_24px_rgba(212,175,55,0.3)] transition hover:brightness-105"
              >
                Submit Ticket
              </button>
            </div>
          </form>
        </section>

        <section className="mt-5 rounded-2xl border border-white/10 bg-[#101827]/85 p-5">
          <h3 className="mb-3 text-lg font-semibold text-[#F8F1DE]">Frequently Asked Questions</h3>
          <div className="space-y-2">
            {faqItems.map((item) => {
              const isOpen = openFaqId === item.id;
              return (
                <article
                  key={item.id}
                  className={`overflow-hidden rounded-xl border bg-[#0C1424]/80 transition ${
                    isOpen ? "border-[#D4AF37]/55" : "border-white/10"
                  }`}
                >
                  <button
                    type="button"
                    onClick={() => setOpenFaqId((prev) => (prev === item.id ? "" : item.id))}
                    className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
                  >
                    <span className="text-sm font-medium text-[#E6EAF2]">{item.question}</span>
                    <ChevronDown className={`h-4 w-4 text-[#D4AF37] transition ${isOpen ? "rotate-180" : ""}`} />
                  </button>
                  <div className={`grid transition-all duration-300 ${isOpen ? "grid-rows-[1fr]" : "grid-rows-[0fr]"}`}>
                    <div className="overflow-hidden">
                      <p className="px-4 pb-4 text-sm text-[#B8C0CF]">{item.answer}</p>
                    </div>
                  </div>
                </article>
              );
            })}
          </div>
        </section>

        <section className="mt-5 rounded-2xl border border-[#EF4444]/35 bg-[#EF4444]/10 p-4">
          <p className="flex items-start gap-2 text-sm text-[#FECACA]">
            <LockKeyhole className="mt-0.5 h-4 w-4 text-[#F87171]" />
            ExaEarn will never ask for your private key or password.
          </p>
        </section>

        <footer className="mt-6 rounded-2xl border border-white/10 bg-[#0E1524]/90 p-4">
          <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-[#B8C0CF]">
            <a href="#" className="hover:text-[#D4AF37]">Terms of Service</a>
            <a href="#" className="hover:text-[#D4AF37]">Privacy Policy</a>
            <a href="#" className="hover:text-[#D4AF37]">Security Center</a>
            <a href="#" className="hover:text-[#D4AF37]">Community</a>
          </div>
          <div className="mt-3 flex items-center gap-2">
            <SocialChip label="Telegram" icon={MessageCircle} />
            <SocialChip label="Twitter/X" icon={X} />
            <SocialChip label="Discord" icon={Headset} />
          </div>
        </footer>
      </div>

      {showSuccessModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="w-full max-w-sm rounded-2xl border border-[#D4AF37]/35 bg-[#0F172A] p-5 text-center">
            <Ticket className="mx-auto h-8 w-8 text-[#D4AF37]" />
            <h4 className="mt-2 text-lg font-semibold text-[#F8F1DE]">Ticket Submitted</h4>
            <p className="mt-1 text-sm text-[#B8C0CF]">Our support team will contact you shortly.</p>
            <button
              type="button"
              onClick={() => setShowSuccessModal(false)}
              className="mt-4 w-full rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] py-2 text-sm font-semibold text-[#111827]"
            >
              Close
            </button>
          </div>
        </div>
      ) : null}
    </main>
  );
}

function Field({ label, full = false, children }) {
  return (
    <label className={full ? "sm:col-span-2" : ""}>
      <span className="mb-1.5 block text-xs font-medium text-[#D5DBE8]">{label}</span>
      {children}
    </label>
  );
}

function SocialChip({ label, icon: Icon }) {
  return (
    <a
      href="#"
      className="inline-flex items-center gap-1.5 rounded-lg border border-white/15 bg-[#111827] px-2.5 py-1.5 text-xs text-[#D5DBE8] hover:border-[#D4AF37]/55 hover:text-[#F8F1DE]"
    >
      <Icon className="h-3.5 w-3.5 text-[#D4AF37]" />
      {label}
    </a>
  );
}

export default SupportCenter;
