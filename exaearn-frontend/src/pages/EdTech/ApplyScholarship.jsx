import { useRef } from "react";
import {
  ArrowLeft,
  Blocks,
  CheckCircle2,
  FileBadge2,
  Globe2,
  GraduationCap,
  HandCoins,
  ShieldCheck,
} from "lucide-react";

const studyAreas = [
  "Blockchain",
  "AI",
  "DeFi",
  "AgriTech",
  "Biotech",
  "Financial Literacy",
];

const overviewCards = [
  {
    title: "Full or Partial Tuition Coverage",
    description: "Scholarship tracks designed to reduce financial barriers to high-impact tech education.",
    icon: GraduationCap,
  },
  {
    title: "NFT-Based Certificate Access",
    description: "Eligible scholars gain access to verifiable, blockchain-backed certification pathways.",
    icon: FileBadge2,
  },
  {
    title: "Global Remote Learning",
    description: "Access premium learning cohorts globally with decentralized participation models.",
    icon: Globe2,
  },
  {
    title: "Sponsored by ExaEarn & Partners",
    description: "Funding pools are supported by ExaEarn, ecosystem contributors, and global donors.",
    icon: HandCoins,
  },
];

const eligibilityItems = [
  "Demonstrated interest in emerging technologies",
  "Financial need (optional documentation)",
  "Commitment to complete the course",
  "Access to internet-enabled device",
];

function ApplyScholarship({ onBack }) {
  const eligibilityRef = useRef(null);
  const applicationFormRef = useRef(null);

  const scrollToEligibility = () => {
    eligibilityRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  const scrollToApplication = () => {
    applicationFormRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  return (
    <main className="min-h-screen bg-[#0F1115] text-white">
      <div className="mx-auto w-full max-w-6xl px-4 pb-16 pt-7 sm:px-6 lg:px-8">
        {onBack ? (
          <button
            type="button"
            onClick={onBack}
            className="mb-6 inline-flex items-center gap-2 rounded-xl border border-[#D4AF37]/30 bg-[#111827] px-4 py-2 text-sm font-semibold text-[#F4D03F] transition duration-300 hover:border-[#D4AF37]/60 hover:shadow-lg hover:shadow-[#D4AF37]/20"
          >
            <ArrowLeft className="h-4 w-4" />
            Back to EdTech
          </button>
        ) : null}

        <section className="overflow-hidden rounded-3xl border border-[#D4AF37]/20 bg-gradient-to-br from-[#111827] via-[#0F1115] to-[#151922] px-6 py-14 sm:px-10 sm:py-20">
          <div className="grid items-center gap-10 lg:grid-cols-2">
            <div>
              <p className="text-xs uppercase tracking-[0.24em] text-[#D4AF37]/80">ExaEarn Scholarship Program</p>
              <h1 className="mt-4 text-4xl font-semibold leading-tight text-white sm:text-5xl">
                Apply for an ExaEarn <span className="bg-gradient-to-r from-[#C9A227] to-[#F4D03F] bg-clip-text text-transparent">Scholarship</span>
              </h1>
              <p className="mt-5 max-w-xl text-base leading-relaxed text-[#9CA3AF] sm:text-lg">
                Access world-class education in AI, Blockchain, DeFi, and emerging technologies regardless of financial background.
              </p>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <button
                  type="button"
                  onClick={scrollToApplication}
                  className="rounded-xl bg-gradient-to-r from-[#C9A227] via-[#D4AF37] to-[#F4D03F] px-6 py-3 text-sm font-semibold text-[#15100A] shadow-lg transition duration-300 hover:brightness-105 hover:shadow-[#D4AF37]/40"
                >
                  Start Application
                </button>
                <button
                  type="button"
                  onClick={scrollToEligibility}
                  className="rounded-xl border border-[#D4AF37]/40 bg-transparent px-6 py-3 text-sm font-semibold text-[#F4D03F] transition duration-300 hover:border-[#D4AF37]/70 hover:bg-[#D4AF37]/10"
                >
                  View Eligibility
                </button>
              </div>
            </div>

            <div className="rounded-2xl border border-[#D4AF37]/20 bg-[#1A1D24] p-6 shadow-lg">
              <div className="rounded-xl border border-[#D4AF37]/20 bg-[#111827] p-5">
                <p className="text-xs uppercase tracking-[0.2em] text-[#D4AF37]/80">Scholarship Tracks</p>
                <div className="mt-4 space-y-3">
                  {studyAreas.map((item) => (
                    <div key={item} className="flex items-center justify-between rounded-lg border border-[#D4AF37]/20 bg-[#1A1D24] px-3 py-2">
                      <span className="text-sm text-[#E5E7EB]">{item}</span>
                      <span className="text-xs font-semibold text-[#D4AF37]">Open</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </section>

        <section ref={eligibilityRef} className="mt-12">
          <div className="grid gap-4 sm:grid-cols-2">
            {overviewCards.map((item) => {
              const Icon = item.icon;
              return (
                <article
                  key={item.title}
                  className="rounded-xl border border-[#D4AF37]/20 bg-[#1A1D24] p-5 shadow-lg transition duration-300 hover:-translate-y-1 hover:border-[#D4AF37]/45 hover:shadow-[#D4AF37]/10"
                >
                  <span className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#D4AF37]/30 bg-[#D4AF37]/10 text-[#D4AF37]">
                    <Icon className="h-5 w-5" />
                  </span>
                  <h3 className="mt-4 text-lg font-semibold text-white">{item.title}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-[#9CA3AF]">{item.description}</p>
                </article>
              );
            })}
          </div>
        </section>

        <section ref={applicationFormRef} className="mt-12">
          <div className="mx-auto h-px w-40 bg-gradient-to-r from-transparent via-[#D4AF37]/70 to-transparent" />
          <div className="mx-auto mt-6 max-w-4xl">
            <h2 className="text-2xl font-semibold text-white">Eligibility Requirements</h2>
            <div className="mt-5 space-y-3">
              {eligibilityItems.map((item) => (
                <div key={item} className="flex items-center gap-3">
                  <CheckCircle2 className="h-5 w-5 shrink-0 text-[#D4AF37]" />
                  <p className="text-sm text-[#9CA3AF]">{item}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="mt-12">
          <div className="mx-auto max-w-3xl rounded-2xl border border-[#D4AF37]/20 bg-[#1A1D24] p-6 shadow-lg sm:p-8">
            <h2 className="text-2xl font-semibold text-white">Scholarship Application Form</h2>
            <form className="mt-6 space-y-4">
              <Field label="Full Name">
                <input
                  type="text"
                  placeholder="Enter full name"
                  className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                />
              </Field>

              <Field label="Email Address">
                <input
                  type="email"
                  placeholder="name@email.com"
                  className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                />
              </Field>

              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Country">
                  <input
                    type="text"
                    placeholder="Enter country"
                    className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                  />
                </Field>

                <Field label="Area of Study">
                  <select className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25">
                    <option value="" className="bg-[#111827] text-[#9CA3AF]">
                      Select area
                    </option>
                    {studyAreas.map((area) => (
                      <option key={area} value={area} className="bg-[#111827] text-white">
                        {area}
                      </option>
                    ))}
                  </select>
                </Field>
              </div>

              <Field label="Why do you deserve this scholarship?">
                <textarea
                  rows={5}
                  placeholder="Share your goals, motivation, and how this scholarship will support your learning journey."
                  className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                />
              </Field>

              <Field label="Educational Background">
                <input
                  type="text"
                  placeholder="Highest education level / relevant training"
                  className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                />
              </Field>

              <Field label="Upload Supporting Document">
                <div className="rounded-lg border border-dashed border-[#D4AF37]/35 bg-[#111827] px-4 py-5 text-sm text-[#9CA3AF]">
                  Upload transcript, proof of need, or recommendation letter (UI placeholder)
                </div>
              </Field>

              <Field label="Wallet Address (optional for crypto grants)">
                <input
                  type="text"
                  placeholder="0x..."
                  className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                />
              </Field>

              <label className="flex items-start gap-3 rounded-lg border border-[#D4AF37]/15 bg-[#111827]/80 p-3">
                <input
                  type="checkbox"
                  className="mt-0.5 h-4 w-4 rounded border-[#D4AF37]/40 bg-[#111827] text-[#D4AF37] focus:ring-[#D4AF37]/35"
                />
                <span className="text-sm text-[#9CA3AF]">I confirm the information provided is accurate and agree to ExaEarn scholarship review terms.</span>
              </label>
            </form>
          </div>
        </section>

        <section className="mt-12">
          <div className="rounded-2xl border border-[#D4AF37]/25 bg-[#1A1D24] px-5 py-5 shadow-lg sm:px-6">
            <p className="flex items-start gap-3 text-sm leading-relaxed text-[#9CA3AF] sm:text-base">
              <Blocks className="mt-0.5 h-5 w-5 shrink-0 text-[#D4AF37]" />
              Scholarship funds are distributed transparently through blockchain-backed allocation mechanisms to ensure fairness and accountability.
            </p>
          </div>
        </section>

        <section className="mt-6">
          <div className="rounded-xl border border-[#D4AF37]/20 bg-[#111827]/75 px-4 py-3">
            <p className="border-l-2 border-[#D4AF37] pl-3 text-sm text-[#9CA3AF]">
              ExaEarn protects applicant data with advanced encryption and decentralized verification protocols.
            </p>
          </div>
        </section>

        <section className="mx-auto mt-12 max-w-3xl text-center">
          <button
            type="button"
            className="w-full rounded-xl bg-gradient-to-r from-[#C9A227] via-[#D4AF37] to-[#F4D03F] px-6 py-4 text-base font-semibold text-[#15100A] shadow-lg transition duration-300 hover:brightness-105 hover:shadow-[#D4AF37]/40 sm:w-96"
          >
            Submit Scholarship Application
          </button>
          <p className="mt-3 text-xs text-[#9CA3AF]">Applications are reviewed within 7-14 days.</p>
        </section>

        <section className="sr-only">
          <ShieldCheck />
        </section>
      </div>
    </main>
  );
}

function Field({ label, children }) {
  return (
    <label className="block">
      <span className="mb-2 block text-sm font-medium text-white">{label}</span>
      {children}
    </label>
  );
}

export default ApplyScholarship;
