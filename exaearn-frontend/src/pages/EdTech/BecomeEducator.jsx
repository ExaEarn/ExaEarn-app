import { useRef } from "react";
import {
  ArrowLeft,
  Bot,
  CheckCircle2,
  Globe2,
  ShieldCheck,
  Sparkles,
  Star,
  Wallet,
} from "lucide-react";

const expertiseAreas = [
  "Blockchain",
  "AI",
  "DeFi",
  "AgriTech",
  "Biotech",
  "Financial Literacy",
];

const featureCards = [
  {
    title: "Global Reach",
    description: "Teach students worldwide using decentralized infrastructure.",
    icon: Globe2,
  },
  {
    title: "Earn in Crypto",
    description: "Receive payments in digital assets with transparent smart-contract payouts.",
    icon: Wallet,
  },
  {
    title: "Tokenized Courses",
    description: "Offer NFT-gated premium content and certification.",
    icon: Sparkles,
  },
  {
    title: "AI-Powered Insights",
    description: "Advanced analytics to track student performance.",
    icon: Bot,
  },
];

const earningItems = [
  "Course Sales Revenue Share",
  "Certification Fees",
  "Referral Rewards",
  "Token Staking Incentives",
];

const educatorRequirements = [
  "Verified expertise",
  "Professional experience",
  "High-quality course content",
  "Compliance with ExaEarn standards",
];

function BecomeEducator({ onBack, embedded = false }) {
  const formSectionRef = useRef(null);

  const scrollToApplicationForm = () => {
    formSectionRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  return (
    <main className={`${embedded ? "bg-[#0F1115]" : "min-h-screen bg-[#0F1115]"} text-white`}>
      <div className={`mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8 ${embedded ? "pb-10 pt-8" : "pb-16 pt-7"}`}>
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
              <p className="text-xs uppercase tracking-[0.24em] text-[#D4AF37]/80">ExaEarn EdTech</p>
              <h1 className="mt-4 text-4xl font-semibold leading-tight text-white sm:text-5xl">
                Become an <span className="bg-gradient-to-r from-[#C9A227] to-[#F4D03F] bg-clip-text text-transparent">Educator</span> on ExaEarn
              </h1>
              <p className="mt-5 max-w-xl text-base leading-relaxed text-[#9CA3AF] sm:text-lg">
                Teach the skills shaping the AI and Blockchain economy. Earn globally in crypto.
              </p>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <button
                  type="button"
                  onClick={scrollToApplicationForm}
                  className="rounded-xl bg-gradient-to-r from-[#C9A227] via-[#D4AF37] to-[#F4D03F] px-6 py-3 text-sm font-semibold text-[#15100A] shadow-lg transition duration-300 hover:brightness-105 hover:shadow-[#D4AF37]/40"
                >
                  Apply Now
                </button>
                <button
                  type="button"
                  className="rounded-xl border border-[#D4AF37]/40 bg-transparent px-6 py-3 text-sm font-semibold text-[#F4D03F] transition duration-300 hover:border-[#D4AF37]/70 hover:bg-[#D4AF37]/10"
                >
                  Learn More
                </button>
              </div>
            </div>

            <div className="rounded-2xl border border-[#D4AF37]/20 bg-[#1A1D24] p-6 shadow-lg">
              <div className="rounded-xl border border-[#D4AF37]/20 bg-[#111827] p-5">
                <p className="text-xs uppercase tracking-[0.2em] text-[#D4AF37]/80">Web3 Educator Network</p>
                <div className="mt-4 space-y-3">
                  {[
                    "Blockchain",
                    "AI",
                    "DeFi",
                    "AgriTech",
                    "Biotech",
                    "Financial Literacy",
                  ].map((item) => (
                    <div key={item} className="flex items-center justify-between rounded-lg border border-[#D4AF37]/20 bg-[#1A1D24] px-3 py-2">
                      <span className="text-sm text-[#E5E7EB]">{item}</span>
                      <span className="text-xs font-semibold text-[#D4AF37]">Live</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="mt-12">
          <h2 className="text-2xl font-semibold text-white sm:text-3xl">Why Teach on ExaEarn</h2>
          <div className="mt-6 grid gap-4 sm:grid-cols-2">
            {featureCards.map((item) => {
              const Icon = item.icon;
              return (
                <article
                  key={item.title}
                  className="rounded-xl border border-[#D4AF37]/20 bg-[#1A1D24] p-5 transition duration-300 hover:-translate-y-1 hover:border-[#D4AF37]/45 hover:shadow-lg hover:shadow-[#D4AF37]/10"
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

        <section className="mt-12">
          <div className="mx-auto h-px w-40 bg-gradient-to-r from-transparent via-[#D4AF37]/70 to-transparent" />
          <div className="mx-auto mt-6 max-w-4xl rounded-2xl border border-[#D4AF37]/20 bg-[#1A1D24] p-6 shadow-lg sm:p-8">
            <h2 className="text-center text-2xl font-semibold text-white">How You Earn on ExaEarn</h2>
            <div className="mt-6 divide-y divide-[#D4AF37]/15">
              {earningItems.map((item) => (
                <div key={item} className="flex items-center gap-3 py-4">
                  <Star className="h-4 w-4 shrink-0 text-[#D4AF37]" />
                  <span className="text-sm text-[#E5E7EB]">{item}</span>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section ref={formSectionRef} className="mt-12">
          <div className="mx-auto max-w-3xl rounded-2xl border border-[#D4AF37]/20 bg-[#1A1D24] p-6 shadow-lg sm:p-8">
            <h2 className="text-2xl font-semibold text-white">Educator Application Form</h2>
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
                <Field label="Area of Expertise">
                  <select className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25">
                    <option value="" className="bg-[#111827] text-[#9CA3AF]">
                      Select expertise
                    </option>
                    {expertiseAreas.map((area) => (
                      <option key={area} value={area} className="bg-[#111827] text-white">
                        {area}
                      </option>
                    ))}
                  </select>
                </Field>

                <Field label="Years of Experience">
                  <input
                    type="text"
                    placeholder="e.g. 5 years"
                    className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                  />
                </Field>
              </div>

              <Field label="Portfolio / LinkedIn">
                <input
                  type="url"
                  placeholder="https://linkedin.com/in/yourprofile"
                  className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                />
              </Field>

              <Field label="Course Title Proposal">
                <input
                  type="text"
                  placeholder="Proposed course title"
                  className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                />
              </Field>

              <Field label="Course Description">
                <textarea
                  rows={5}
                  placeholder="Describe your proposed course, outcomes, and audience."
                  className="w-full rounded-lg border border-[#D4AF37]/25 bg-[#111827] px-4 py-3 text-sm text-white outline-none transition duration-300 placeholder:text-[#9CA3AF]/80 focus:border-[#D4AF37]/60 focus:ring-2 focus:ring-[#D4AF37]/25"
                />
              </Field>

              <Field label="Upload Credentials">
                <div className="rounded-lg border border-dashed border-[#D4AF37]/35 bg-[#111827] px-4 py-5 text-sm text-[#9CA3AF]">
                  Upload CV, certificates, or proof of expertise (UI placeholder)
                </div>
              </Field>

              <label className="flex items-start gap-3 rounded-lg border border-[#D4AF37]/15 bg-[#111827]/80 p-3">
                <input
                  type="checkbox"
                  className="mt-0.5 h-4 w-4 rounded border-[#D4AF37]/40 bg-[#111827] text-[#D4AF37] focus:ring-[#D4AF37]/35"
                />
                <span className="text-sm text-[#9CA3AF]">I agree to ExaEarn Educator Terms and content quality policy.</span>
              </label>
            </form>
          </div>
        </section>

        <section className="mx-auto mt-12 max-w-3xl">
          <h2 className="text-2xl font-semibold text-white">Educator Requirements</h2>
          <div className="mt-5 space-y-3">
            {educatorRequirements.map((item) => (
              <div key={item} className="flex items-center gap-3">
                <CheckCircle2 className="h-5 w-5 shrink-0 text-[#D4AF37]" />
                <p className="text-sm text-[#9CA3AF]">{item}</p>
              </div>
            ))}
          </div>
        </section>

        <section className="mt-12">
          <div className="rounded-2xl border border-[#D4AF37]/25 bg-[#1A1D24] px-5 py-5 shadow-lg sm:px-6">
            <p className="flex items-start gap-3 text-sm leading-relaxed text-[#9CA3AF] sm:text-base">
              <ShieldCheck className="mt-0.5 h-5 w-5 shrink-0 text-[#D4AF37]" />
              ExaEarn protects course integrity using decentralized verification and AI-powered quality review systems.
            </p>
          </div>
        </section>

        <section className="mx-auto mt-12 max-w-3xl text-center">
          <button
            type="button"
            className="w-full rounded-xl bg-gradient-to-r from-[#C9A227] via-[#D4AF37] to-[#F4D03F] px-6 py-4 text-base font-semibold text-[#15100A] shadow-lg transition duration-300 hover:brightness-105 hover:shadow-[#D4AF37]/40 sm:w-80"
          >
            Submit Application
          </button>
          <p className="mt-3 text-xs text-[#9CA3AF]">Applications are reviewed within 5-7 business days.</p>
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

export default BecomeEducator;
