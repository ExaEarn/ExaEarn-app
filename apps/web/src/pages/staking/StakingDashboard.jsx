import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, ShieldCheck, Wallet } from "lucide-react";
import StakeCard from "../../components/staking/StakeCard.jsx";
import RewardsCard from "../../components/staking/RewardsCard.jsx";
import LockSelector from "../../components/staking/LockSelector.jsx";

const lockOptions = [
  { value: "Flexible", multiplier: "1.0x" },
  { value: "30 Days", multiplier: "1.25x" },
  { value: "90 Days", multiplier: "1.6x" },
  { value: "180 Days", multiplier: "2.1x" },
];

function StakingDashboard({ onBack }) {
  const [stakingBalance, setStakingBalance] = useState(1240.5);
  const [availableBalance, setAvailableBalance] = useState(680.2);
  const [pendingRewards, setPendingRewards] = useState(0);
  const [selectedLockPeriod, setSelectedLockPeriod] = useState("90 Days");

  const apyEstimate = useMemo(() => {
    if (selectedLockPeriod === "180 Days") return "16.8% - 22.4%";
    if (selectedLockPeriod === "90 Days") return "12.5% - 17.1%";
    if (selectedLockPeriod === "30 Days") return "8.2% - 11.4%";
    return "4.5% - 7.2%";
  }, [selectedLockPeriod]);

  useEffect(() => {
    const intervalId = setInterval(() => {
      setPendingRewards((prev) => parseFloat((prev + 0.18).toFixed(3)));
    }, 3000);
    return () => clearInterval(intervalId);
  }, []);

  const handleStake = () => {
    if (availableBalance <= 0) return;
    const amount = Math.min(50, availableBalance);
    setAvailableBalance((prev) => parseFloat((prev - amount).toFixed(2)));
    setStakingBalance((prev) => parseFloat((prev + amount).toFixed(2)));
  };

  const handleUnstake = () => {
    if (stakingBalance <= 0) return;
    const amount = Math.min(50, stakingBalance);
    setStakingBalance((prev) => parseFloat((prev - amount).toFixed(2)));
    setAvailableBalance((prev) => parseFloat((prev + amount).toFixed(2)));
  };

  const handleClaim = () => {
    if (pendingRewards <= 0) return;
    setPendingRewards(0);
  };

  return (
    <div className="min-h-screen text-white exa-bg app-shell">
      <div className="container w-full max-w-sm px-3 pt-4 pb-6 mx-auto sm:max-w-lg sm:px-4 sm:pt-6 md:max-w-2xl lg:max-w-5xl xl:max-w-6xl">
        <div className="p-4 shadow-xl glass-card rounded-3xl sm:p-6">
          <header className="mb-6 rounded-3xl border border-violet-300/20 bg-gradient-to-br from-cosmic-900/90 via-cosmic-800/70 to-cosmic-900/95 p-5">
            {onBack ? (
              <div className="mb-4 flex justify-start">
                <button type="button" onClick={onBack} className="btn-outline inline-flex items-center gap-2">
                  <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                  Back
                </button>
              </div>
            ) : null}
            <div className="flex flex-wrap items-center justify-between gap-4">
              <div>
                <p className="text-xs uppercase tracking-[0.25em] text-auric-300/70">ExaEarn Labs</p>
                <h1 className="font-['Sora'] text-3xl font-semibold text-violet-50 sm:text-4xl">
                  XRP Staking Dashboard
                </h1>
                <p className="mt-2 text-sm text-violet-100/70">
                  Liquidity-safe staking with emission-based ExaToken rewards.
                </p>
              </div>
              <div className="flex items-center gap-3 rounded-2xl border border-violet-300/20 bg-cosmic-900/70 px-4 py-3 text-xs text-violet-100/70">
                <Wallet className="h-4 w-4 text-auric-300" aria-hidden="true" />
                Connected: 0x4a...0c51
              </div>
            </div>

            <div className="mt-4 rounded-2xl border border-emerald-300/20 bg-emerald-500/10 p-4 text-xs text-emerald-100/85">
              <div className="flex items-start gap-2">
                <ShieldCheck className="mt-0.5 h-4 w-4 text-emerald-300" aria-hidden="true" />
                <p>
                  Rewards are distributed in ExaToken from the staking pool. XRP deposits remain fully withdrawable.
                </p>
              </div>
            </div>
          </header>

          <section className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
            <div className="space-y-5">
              <StakeCard
                stakingBalance={stakingBalance}
                availableBalance={availableBalance}
                lockPeriod={selectedLockPeriod}
                apyEstimate={apyEstimate}
                onStake={handleStake}
                onUnstake={handleUnstake}
              />
              <LockSelector
                options={lockOptions}
                selected={selectedLockPeriod}
                onSelect={setSelectedLockPeriod}
              />
            </div>
            <div className="space-y-5">
              <RewardsCard
                pendingRewards={pendingRewards}
                onClaim={handleClaim}
              />
            </div>
          </section>
        </div>
      </div>
    </div>
  );
}

export default StakingDashboard;
