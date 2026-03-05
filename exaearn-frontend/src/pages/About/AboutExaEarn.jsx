import {
  ArrowRight,
  BadgeCheck,
  Blocks,
  BookOpenCheck,
  Coins,
  Globe2,
  GraduationCap,
  HandCoins,
  Landmark,
  Leaf,
  LockKeyhole,
  Scale,
  ShieldCheck,
  Sprout,
  Store,
  Users,
} from "lucide-react";

const problems = [
  { title: "P2P Scam Risks", icon: ShieldCheck },
  { title: "Limited Financial Access", icon: Landmark },
  { title: "Low-Yield Savings Systems", icon: HandCoins },
  { title: "Poor Reward Transparency", icon: Scale },
  { title: "Weak Agri Blockchain Adoption", icon: Sprout },
];

const pillars = [
  { title: "Token Swap & Staking Infrastructure", icon: Coins, desc: "Deep liquidity, efficient swaps, and yield pathways for users." },
  { title: "P2P & Secure Marketplace", icon: Store, desc: "Escrow-protected trades with anti-fraud safeguards." },
  { title: "Referral & Smart Contract Rewards", icon: Blocks, desc: "Automated, transparent reward distribution at scale." },
  { title: "Agricultural Blockchain Integration", icon: Leaf, desc: "Tokenized access to agriculture-backed growth opportunities." },
  { title: "Education & Skill Monetization", icon: GraduationCap, desc: "Learn-to-earn pathways with measurable outcomes." },
  { title: "NFT & Token Utility System", icon: BadgeCheck, desc: "Interoperable digital assets with practical ecosystem utility." },
  { title: "Secure KYC & Compliance Layer", icon: LockKeyhole, desc: "Institutional-grade verification and controls." },
];

const roadmap = [
  "Phase 1: MVP Launch",
  "Phase 2: Token Expansion",
  "Phase 3: Agricultural Integration",
  "Phase 4: Global Expansion",
  "Phase 5: Advanced Web3 Infrastructure",
];

function AboutExaEarn({ onBack }) {
  return (
    <main className="min-h-screen bg-[#070B14] text-white">
      <section className="relative overflow-hidden border-b border-[#D4AF37]/20 bg-gradient-to-br from-[#121A2A] via-[#0E1524] to-[#0A0F1D] px-4 pb-10 pt-6 sm:px-6">
        <div className="pointer-events-none absolute -left-10 top-0 h-44 w-44 rounded-full bg-[#D4AF37]/16 blur-3xl" />
        <div className="pointer-events-none absolute -right-12 top-12 h-52 w-52 rounded-full bg-[#8B5CF6]/16 blur-3xl" />
        <div className="mx-auto w-full max-w-6xl">
          <button
            type="button"
            onClick={onBack}
            className="mb-4 rounded-xl border border-white/15 bg-[#111827] px-3 py-1.5 text-sm text-[#E6EAF2] hover:border-[#D4AF37]/60"
          >
            Back
          </button>
          <h1 className="max-w-4xl text-3xl font-semibold leading-tight text-[#F8F1DE] sm:text-4xl">
            Building the Future of Decentralized Finance & Real-World Impact
          </h1>
          <p className="mt-3 max-w-3xl text-sm text-[#C2CAD8] sm:text-base">
            ExaEarn is a next-generation DeFi ecosystem bridging blockchain, finance, agriculture, and real-world economic empowerment.
          </p>
          <div className="mt-6 flex flex-wrap gap-3">
            <button className="rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] px-5 py-2.5 text-sm font-semibold text-[#111827] shadow-[0_10px_24px_rgba(212,175,55,0.28)]">
              Explore the Ecosystem
            </button>
            <button className="rounded-xl border border-white/20 bg-[#111827] px-5 py-2.5 text-sm text-[#E6EAF2]">
              Read Whitepaper
            </button>
          </div>
        </div>
      </section>

      <div className="mx-auto w-full max-w-6xl space-y-4 px-4 pb-10 pt-5 sm:px-6">
        <section className="grid gap-3 md:grid-cols-2">
          <FeatureCard
            title="Mission"
            icon={Globe2}
            text="To create a secure, scalable, and sustainable Web3 financial infrastructure accessible to everyone."
          />
          <FeatureCard
            title="Vision"
            icon={ArrowRight}
            text="To bridge decentralized finance with real-world economic growth across emerging markets."
          />
        </section>

        <section className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-lg font-semibold text-[#F8F1DE]">What ExaEarn Solves</h2>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            {problems.map((item) => {
              const Icon = item.icon;
              return (
                <article key={item.title} className="rounded-2xl border border-white/12 bg-[#0C1424] p-3">
                  <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#D4AF37]/30 bg-[#D4AF37]/10 text-[#D4AF37]">
                    <Icon className="h-4 w-4" />
                  </span>
                  <p className="mt-2 text-sm text-[#DDE3EE]">{item.title}</p>
                </article>
              );
            })}
          </div>
        </section>

        <section className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-lg font-semibold text-[#F8F1DE]">Core Ecosystem Pillars</h2>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {pillars.map((item) => {
              const Icon = item.icon;
              return (
                <article key={item.title} className="rounded-2xl border border-white/12 bg-[#0C1424] p-3 transition hover:border-[#D4AF37]/50">
                  <div className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#D4AF37]/30 bg-[#D4AF37]/10 text-[#D4AF37]">
                    <Icon className="h-4 w-4" />
                  </div>
                  <h3 className="mt-2 text-sm font-semibold text-[#E6EAF2]">{item.title}</h3>
                  <p className="mt-1 text-xs text-[#AEB7C7]">{item.desc}</p>
                </article>
              );
            })}
          </div>
        </section>

        <section className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
          <article className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
            <h2 className="text-lg font-semibold text-[#F8F1DE]">ExaToken Utility</h2>
            <p className="mt-2 text-sm text-[#C2CAD8]">
              ExaToken powers rewards, governance pathways, fee optimization, and marketplace participation across the ExaEarn ecosystem.
            </p>
            <ul className="mt-3 space-y-2 text-sm text-[#DDE3EE]">
              <li>- Staking rewards</li>
              <li>- Fee discounts</li>
              <li>- Governance potential</li>
              <li>- Marketplace utility</li>
            </ul>
          </article>
          <article className="rounded-2xl border border-[#D4AF37]/30 bg-[#0C1424] p-4">
            <div className="relative mx-auto mt-2 h-40 w-40 rounded-full border border-[#D4AF37]/35 bg-gradient-to-br from-[#D4AF37]/20 via-[#D4AF37]/5 to-transparent">
              <div className="absolute inset-4 animate-pulse rounded-full border border-[#D4AF37]/50" />
              <div className="absolute inset-0 flex items-center justify-center text-[#F3D88F]">
                <Coins className="h-12 w-12" />
              </div>
            </div>
            <p className="mt-4 text-center text-sm text-[#C2CAD8]">Native reward token synchronized with ecosystem growth.</p>
          </article>
        </section>

        <section className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-lg font-semibold text-[#F8F1DE]">Security & Transparency</h2>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {[
              "Smart contract automation",
              "Encrypted data protection",
              "Anti-scam architecture",
              "Transparent reward distribution",
            ].map((item) => (
              <article key={item} className="rounded-xl border border-white/10 bg-[#0C1424] p-3">
                <ShieldCheck className="h-4 w-4 text-[#D4AF37]" />
                <p className="mt-2 text-sm text-[#DDE3EE]">{item}</p>
              </article>
            ))}
          </div>
        </section>

        <section className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-lg font-semibold text-[#F8F1DE]">Roadmap</h2>
          <div className="space-y-3">
            {roadmap.map((item, idx) => (
              <div key={item} className="flex items-start gap-3">
                <div className="mt-1 h-3 w-3 rounded-full bg-[#D4AF37]" />
                <div>
                  <p className="text-sm font-medium text-[#E6EAF2]">{item}</p>
                  {idx < roadmap.length - 1 ? <div className="ml-1 mt-1 h-6 w-px bg-[#D4AF37]/35" /> : null}
                </div>
              </div>
            ))}
          </div>
        </section>

        <section className="grid gap-4 lg:grid-cols-[0.7fr_1.3fr]">
          <article className="rounded-2xl border border-white/10 bg-[#0C1424] p-4">
            <div className="mx-auto h-36 w-36 rounded-2xl border border-[#D4AF37]/35 bg-gradient-to-br from-[#1E293B] to-[#0F172A]" />
            <p className="mt-2 text-center text-xs text-[#9AA3B4]">Founder portrait placeholder</p>
          </article>
          <article className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
            <h2 className="text-lg font-semibold text-[#F8F1DE]">Founder&apos;s Vision</h2>
            <blockquote className="mt-2 border-l-2 border-[#D4AF37]/60 pl-3 text-sm text-[#DDE3EE]">
              “ExaEarn is built for long-term impact. We are designing infrastructure that empowers people, strengthens markets,
              and connects blockchain innovation to real-world economic growth.”
            </blockquote>
          </article>
        </section>

        <section className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
          <h2 className="mb-3 text-lg font-semibold text-[#F8F1DE]">Community & Growth</h2>
          <div className="grid gap-3 sm:grid-cols-3">
            <StatCard label="Users" value="250K+" />
            <StatCard label="Transactions" value="14.8M+" />
            <StatCard label="Ecosystem Reach" value="40+ Regions" />
          </div>
          <div className="mt-3 flex flex-wrap gap-2 text-xs text-[#D7DDEA]">
            <button className="rounded-lg border border-white/15 bg-[#111827] px-3 py-1.5">Telegram</button>
            <button className="rounded-lg border border-white/15 bg-[#111827] px-3 py-1.5">Twitter/X</button>
            <button className="rounded-lg border border-white/15 bg-[#111827] px-3 py-1.5">Discord</button>
          </div>
        </section>

        <section className="rounded-2xl border border-[#D4AF37]/30 bg-[#D4AF37]/12 p-5 text-center shadow-[0_0_24px_rgba(212,175,55,0.2)]">
          <h2 className="text-xl font-semibold text-[#F8F1DE]">Join the ExaEarn Movement</h2>
          <div className="mt-4 flex flex-wrap justify-center gap-3">
            <button className="rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] px-5 py-2.5 text-sm font-semibold text-[#111827]">
              Create Account
            </button>
            <button className="rounded-xl border border-white/20 bg-[#111827] px-5 py-2.5 text-sm text-[#E6EAF2]">
              Join Community
            </button>
          </div>
        </section>
      </div>
    </main>
  );
}

function FeatureCard({ title, text, icon: Icon }) {
  return (
    <article className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4 shadow-[0_12px_24px_rgba(0,0,0,0.3)]">
      <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#D4AF37]/35 bg-[#D4AF37]/10 text-[#D4AF37]">
        <Icon className="h-5 w-5" />
      </span>
      <h2 className="mt-2 text-base font-semibold text-[#F8F1DE]">{title}</h2>
      <p className="mt-1 text-sm text-[#C2CAD8]">{text}</p>
    </article>
  );
}

function StatCard({ label, value }) {
  return (
    <article className="rounded-xl border border-white/10 bg-[#0C1424] p-3">
      <p className="text-xs text-[#9AA3B4]">{label}</p>
      <p className="mt-1 text-lg font-semibold text-[#F3D88F]">{value}</p>
    </article>
  );
}

export default AboutExaEarn;
