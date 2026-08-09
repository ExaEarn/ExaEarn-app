import { useState } from "react";
import {
  ArrowLeft,
  ArrowUpRight,
  Boxes,
  Coins,
  Compass,
  Gem,
  Gift,
  Landmark,
  MessageCircle,
  Scale,
  Send,
  Twitter,
  Vote,
  Wallet,
} from "lucide-react";
import Image from "../assets/Image";
import AllocationBar from "./AllocationBar";
import TokenStatCard from "./TokenStatCard";
import UtilityCard from "./UtilityCard";
import "./Token.css";

const stats = [
  { label: "Total Supply", value: "1,000,000,000 EXA", hint: "Fixed Genesis Supply" },
  { label: "Token Type", value: "Utility / Governance", hint: "Multi-Use Across ExaEarn" },
  { label: "Chain Strategy", value: "MULTI CHAIN", hint: "Ecosystem" },
  {
    label: "Token Status",
    value: "Pre Launch",
    hint: "Initial deployment to be announced. Contract address not yet deployed.",
  },
  {
    label: "Mission",
    value: "Ecosystem Value Engine",
    hint: "ExaToken is designed to reward contributions, stimulate ecosystem activity, and enable value exchange across the ExaEarn Labs ecosystem.",
  },
  {
    label: "Community & Engagement",
    value: "Stay Connected",
    hint: "Join discussions, events, and campaigns to shape ExaEarn Labs.",
  },
];

const utilities = [
  {
    title: "Transaction Fees",
    description: "Use EXA for low-fee payments and internal protocol operations.",
    icon: <Coins className="h-5 w-5" aria-hidden="true" />,
  },
  {
    title: "Staking Mechanism",
    description: "Stake EXA to secure ecosystem pools and unlock yield tiers.",
    icon: <Landmark className="h-5 w-5" aria-hidden="true" />,
  },
  {
    title: "Governance & Voting",
    description: "Vote on treasury priorities, upgrades, and key DAO proposals.",
    icon: <Vote className="h-5 w-5" aria-hidden="true" />,
  },
  {
    title: "Rewards System",
    description: "Earn EXA from quests, campaign actions, referrals, and activity milestones.",
    icon: <Gift className="h-5 w-5" aria-hidden="true" />,
  },
  {
    title: "NFT Payments",
    description: "Pay for NFT minting, listing, and premium assets using EXA.",
    icon: <Gem className="h-5 w-5" aria-hidden="true" />,
  },
];

const tokenomics = [
  { label: "Public & Community", percentage: 40, colorClass: "bg-gradient-to-r from-[var(--exa-gold-dark)] to-[var(--exa-gold-light)]" },
  { label: "Staking Rewards", percentage: 20, colorClass: "bg-gradient-to-r from-fuchsia-400 to-violet-500" },
  { label: "Team & Advisors", percentage: 15, colorClass: "bg-gradient-to-r from-sky-300 to-cyan-400" },
  { label: "Partnerships", percentage: 10, colorClass: "bg-gradient-to-r from-orange-400 to-amber-500" },
  { label: "Ecosystem Development", percentage: 10, colorClass: "bg-gradient-to-r from-pink-400 to-fuchsia-500" },
  { label: "Reserve & Liquidity", percentage: 5, colorClass: "bg-gradient-to-r from-indigo-300 to-violet-300" },
];

const socialLinks = [
  { label: "Discord", action: "Join Now", icon: <MessageCircle className="h-4 w-4" aria-hidden="true" />, href: "#" },
  { label: "Telegram", action: "Join Now", icon: <Send className="h-4 w-4" aria-hidden="true" />, href: "#" },
  { label: "Twitter", action: "Follow", icon: <Twitter className="h-4 w-4" aria-hidden="true" />, href: "#" },
];

function Token({ onBack }) {
  const [isClaiming, setIsClaiming] = useState(false);

  const handleClaim = async () => {
    if (isClaiming) {
      return;
    }

    setIsClaiming(true);
    try {
      await new Promise((resolve) => setTimeout(resolve, 1300));
    } finally {
      setIsClaiming(false);
    }
  };

  return (
    <main className="token-bg min-h-screen px-4 py-10 sm:px-6 sm:py-12">
      <section className="token-shell mx-auto w-full max-w-6xl rounded-[2rem] p-5 sm:p-8 lg:p-10">
        <header className="border border-[var(--exa-border)] bg-[var(--exa-surface)] shadow-[var(--exa-shadow-soft)] rounded-3xl p-6 sm:p-8">
          {onBack ? (
            <div className="mb-4 flex justify-start">
              <button type="button" onClick={onBack} className="btn-outline inline-flex items-center gap-2">
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Back
              </button>
            </div>
          ) : null}
          <div className="grid items-center gap-6 lg:grid-cols-[1.45fr_0.55fr]">
            <div>
              <h1 className="mt-1 font-['Sora'] text-5xl font-bold tracking-tight text-[var(--exa-text-primary)] sm:text-6xl">
                Exa<span className="token-metallic">Token</span>
              </h1>
              <p className="mt-3 text-xl font-medium text-[var(--exa-text-secondary)]">Native token powering the ExaEarn Labs Ecosystem</p>
              <p className="mt-4 max-w-2xl text-sm leading-relaxed text-[var(--exa-text-secondary)] sm:text-base">
                ExaToken aligns utility, rewards, and governance into one asset layer powering DeFi flows across staking,
                NFT activity, payments, and ecosystem participation.
              </p>
            </div>
            <div className="mx-auto flex h-36 w-36 items-center justify-center rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] shadow-[var(--exa-shadow-gold)] sm:h-44 sm:w-44">
              <div className="token-coin flex h-28 w-28 items-center justify-center rounded-full border border-[var(--exa-border-active)] sm:h-36 sm:w-36">
                <img src={Image.earn} alt="ExaEarn logo" className="token-logo h-16 w-16 object-contain sm:h-20 sm:w-20" />
              </div>
            </div>
          </div>
        </header>

        <section className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {stats.map((item) => (
            <TokenStatCard key={item.label} label={item.label} value={item.value} hint={item.hint} />
          ))}
        </section>

        <section className="mt-8">
          <div className="mb-4 flex items-center gap-3">
            <Boxes className="h-5 w-5 text-[var(--exa-gold)]" aria-hidden="true" />
            <h2 className="font-['Sora'] text-3xl font-semibold text-[var(--exa-text-primary)]">Token Utility</h2>
          </div>
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {utilities.map((item) => (
              <UtilityCard key={item.title} icon={item.icon} title={item.title} description={item.description} />
            ))}
          </div>
        </section>

        <section className="mt-8">
          <div className="mb-4 flex items-center gap-3">
            <Scale className="h-5 w-5 text-[var(--exa-gold)]" aria-hidden="true" />
            <h2 className="font-['Sora'] text-3xl font-semibold text-[var(--exa-text-primary)]">Tokenomics</h2>
          </div>
          <div className="border border-[var(--exa-border)] bg-[var(--exa-surface)] shadow-[var(--exa-shadow-soft)] space-y-3 rounded-3xl p-5 sm:p-6">
            {tokenomics.map((item) => (
              <AllocationBar
                key={item.label}
                label={item.label}
                percentage={item.percentage}
                colorClass={item.colorClass}
              />
            ))}
          </div>
        </section>

        <section className="mt-8 grid gap-4 lg:grid-cols-2">
          <article className="border border-[var(--exa-border)] bg-[var(--exa-surface)] shadow-[var(--exa-shadow-soft)] rounded-3xl p-6">
            <h2 className="font-['Sora'] text-3xl font-semibold text-[var(--exa-text-primary)]">Your ExaToken Rewards</h2>
            <div className="mt-6 space-y-4">
              <div className="flex items-center justify-between rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-3">
                <span className="text-[var(--exa-text-muted)]">Token Balance</span>
                <span className="font-semibold text-[var(--exa-gold)]">0.000 EXA</span>
              </div>
              <div className="flex items-center justify-between rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-3">
                <span className="text-[var(--exa-text-muted)]">Rewards Earned</span>
                <span className="font-semibold text-[var(--exa-gold)]">0.000 EXA</span>
              </div>
            </div>

            <button
              type="button"
              onClick={handleClaim}
              disabled={isClaiming}
              className="mt-6 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 py-3 text-lg font-semibold text-[var(--exa-gold-contrast)] transition-all duration-300 hover:scale-[1.01] hover:shadow-[var(--exa-shadow-gold)] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-65 disabled:shadow-none"
            >
              <Wallet className="h-5 w-5" aria-hidden="true" />
              {isClaiming ? "Claiming..." : "Claim Rewards"}
            </button>
          </article>

          <article className="border border-[var(--exa-border)] bg-[var(--exa-surface)] shadow-[var(--exa-shadow-soft)] rounded-3xl p-6">
            <h2 className="font-['Sora'] text-3xl font-semibold text-[var(--exa-text-primary)]">Roadmap Access</h2>
            <p className="mt-3 text-sm leading-relaxed text-[var(--exa-text-secondary)] sm:text-base">
              Follow upcoming utility expansions, reward phases, exchange milestones, and governance releases.
            </p>

            <a
              href="#"
              className="token-roadmap-btn mt-8 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-[var(--exa-border-active)] bg-[var(--exa-surface)] px-4 py-4 text-lg font-semibold text-[var(--exa-gold)] transition-all duration-300 hover:-translate-y-0.5 hover:text-[var(--exa-gold-light)]"
            >
              <Compass className="h-5 w-5" aria-hidden="true" />
              View ExaToken Roadmap
              <ArrowUpRight className="h-5 w-5" aria-hidden="true" />
            </a>
          </article>
        </section>

        <section className="mt-8 border border-[var(--exa-border)] bg-[var(--exa-surface)] shadow-[var(--exa-shadow-soft)] rounded-3xl p-6 sm:p-7">
          <h2 className="font-['Sora'] text-3xl font-semibold text-[var(--exa-text-primary)]">Community & Engagement</h2>
          <p className="mt-3 text-sm leading-relaxed text-[var(--exa-text-secondary)] sm:text-base">
            Stay connected and be part of the ExaEarn Labs ecosystem.
          </p>
          <p className="mt-4 text-sm font-medium text-[var(--exa-text-secondary)] sm:text-base">Join our growing community to:</p>
          <ul className="mt-3 space-y-2 text-sm text-[var(--exa-text-secondary)] sm:text-base">
            <li>Receive updates about ExaToken launch and rewards.</li>
            <li>Participate in discussions, events, and campaigns.</li>
            <li>Share ideas and feedback to help shape the ecosystem.</li>
          </ul>

          <div className="mt-6 grid gap-3 sm:grid-cols-3">
            {socialLinks.map((item) => (
              <a
                key={item.label}
                href={item.href}
                className="token-social-btn flex items-center justify-between rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-3 text-sm font-medium text-[var(--exa-text-secondary)] transition-all duration-300 hover:-translate-y-0.5 hover:border-[var(--exa-border-active)] hover:text-[var(--exa-gold-light)]"
              >
                <span className="flex items-center gap-2">
                  <span className="inline-flex h-7 w-7 items-center justify-center rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold)]">
                    {item.icon}
                  </span>
                  {item.label}
                </span>
                <span className="text-[var(--exa-text-secondary)]">{item.action}</span>
              </a>
            ))}
          </div>

          <a
            href="#"
            className="token-community-cta mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-dark)]/90 via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 py-3 text-center text-base font-semibold text-[var(--exa-gold-contrast)] transition-all duration-300 hover:scale-[1.01] hover:shadow-[var(--exa-shadow-gold)]"
          >
            Join our community today and stay updated on rewards, staking opportunities, and the upcoming launch of ExaToken
          </a>
        </section>
      </section>
    </main>
  );
}

export default Token;
