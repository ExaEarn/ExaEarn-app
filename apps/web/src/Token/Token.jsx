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
  { label: "Public & Community", percentage: 40, colorClass: "bg-gradient-to-r from-auric-400 to-auric-500" },
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
        <header className="token-card rounded-3xl p-6 sm:p-8">
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
              <h1 className="mt-1 font-['Sora'] text-5xl font-bold tracking-tight text-violet-50 sm:text-6xl">
                Exa<span className="token-metallic">Token</span>
              </h1>
              <p className="mt-3 text-xl font-medium text-violet-100">Native token powering the ExaEarn Labs Ecosystem</p>
              <p className="mt-4 max-w-2xl text-sm leading-relaxed text-violet-100/75 sm:text-base">
                ExaToken aligns utility, rewards, and governance into one asset layer powering DeFi flows across staking,
                NFT activity, payments, and ecosystem participation.
              </p>
            </div>
            <div className="mx-auto flex h-36 w-36 items-center justify-center rounded-full border border-auric-300/65 bg-cosmic-900/70 shadow-button-glow sm:h-44 sm:w-44">
              <div className="token-coin flex h-28 w-28 items-center justify-center rounded-full border border-auric-300/80 sm:h-36 sm:w-36">
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
            <Boxes className="h-5 w-5 text-auric-400" aria-hidden="true" />
            <h2 className="font-['Sora'] text-3xl font-semibold text-violet-50">Token Utility</h2>
          </div>
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {utilities.map((item) => (
              <UtilityCard key={item.title} icon={item.icon} title={item.title} description={item.description} />
            ))}
          </div>
        </section>

        <section className="mt-8">
          <div className="mb-4 flex items-center gap-3">
            <Scale className="h-5 w-5 text-auric-400" aria-hidden="true" />
            <h2 className="font-['Sora'] text-3xl font-semibold text-violet-50">Tokenomics</h2>
          </div>
          <div className="token-card space-y-3 rounded-3xl p-5 sm:p-6">
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
          <article className="token-card rounded-3xl p-6">
            <h2 className="font-['Sora'] text-3xl font-semibold text-violet-50">Your ExaToken Rewards</h2>
            <div className="mt-6 space-y-4">
              <div className="flex items-center justify-between rounded-xl border border-violet-300/20 bg-cosmic-900/55 px-4 py-3">
                <span className="text-violet-100/70">Token Balance</span>
                <span className="font-semibold text-auric-300">0.000 EXA</span>
              </div>
              <div className="flex items-center justify-between rounded-xl border border-violet-300/20 bg-cosmic-900/55 px-4 py-3">
                <span className="text-violet-100/70">Rewards Earned</span>
                <span className="font-semibold text-auric-300">0.000 EXA</span>
              </div>
            </div>

            <button
              type="button"
              onClick={handleClaim}
              disabled={isClaiming}
              className="mt-6 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-auric-300/80 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 px-4 py-3 text-lg font-semibold text-cosmic-900 transition-all duration-300 hover:scale-[1.01] hover:shadow-button-glow active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-65 disabled:shadow-none"
            >
              <Wallet className="h-5 w-5" aria-hidden="true" />
              {isClaiming ? "Claiming..." : "Claim Rewards"}
            </button>
          </article>

          <article className="token-card rounded-3xl p-6">
            <h2 className="font-['Sora'] text-3xl font-semibold text-violet-50">Roadmap Access</h2>
            <p className="mt-3 text-sm leading-relaxed text-violet-100/75 sm:text-base">
              Follow upcoming utility expansions, reward phases, exchange milestones, and governance releases.
            </p>

            <a
              href="#"
              className="token-roadmap-btn mt-8 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-auric-300/80 bg-cosmic-900/60 px-4 py-4 text-lg font-semibold text-auric-300 transition-all duration-300 hover:-translate-y-0.5 hover:text-auric-200"
            >
              <Compass className="h-5 w-5" aria-hidden="true" />
              View ExaToken Roadmap
              <ArrowUpRight className="h-5 w-5" aria-hidden="true" />
            </a>
          </article>
        </section>

        <section className="mt-8 token-card rounded-3xl p-6 sm:p-7">
          <h2 className="font-['Sora'] text-3xl font-semibold text-violet-50">Community & Engagement</h2>
          <p className="mt-3 text-sm leading-relaxed text-violet-100/75 sm:text-base">
            Stay connected and be part of the ExaEarn Labs ecosystem.
          </p>
          <p className="mt-4 text-sm font-medium text-violet-100/85 sm:text-base">Join our growing community to:</p>
          <ul className="mt-3 space-y-2 text-sm text-violet-100/75 sm:text-base">
            <li>Receive updates about ExaToken launch and rewards.</li>
            <li>Participate in discussions, events, and campaigns.</li>
            <li>Share ideas and feedback to help shape the ecosystem.</li>
          </ul>

          <div className="mt-6 grid gap-3 sm:grid-cols-3">
            {socialLinks.map((item) => (
              <a
                key={item.label}
                href={item.href}
                className="token-social-btn flex items-center justify-between rounded-xl border border-violet-300/25 bg-cosmic-900/55 px-4 py-3 text-sm font-medium text-violet-100 transition-all duration-300 hover:-translate-y-0.5 hover:border-auric-300/60 hover:text-auric-200"
              >
                <span className="flex items-center gap-2">
                  <span className="inline-flex h-7 w-7 items-center justify-center rounded-full border border-auric-300/35 bg-cosmic-800/90 text-auric-300">
                    {item.icon}
                  </span>
                  {item.label}
                </span>
                <span className="text-violet-200/85">{item.action}</span>
              </a>
            ))}
          </div>

          <a
            href="#"
            className="token-community-cta mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-auric-300/80 bg-gradient-to-r from-auric-300/90 via-auric-400 to-auric-500 px-4 py-3 text-center text-base font-semibold text-cosmic-900 transition-all duration-300 hover:scale-[1.01] hover:shadow-button-glow"
          >
            Join our community today and stay updated on rewards, staking opportunities, and the upcoming launch of ExaToken
          </a>
        </section>
      </section>
    </main>
  );
}

export default Token;
