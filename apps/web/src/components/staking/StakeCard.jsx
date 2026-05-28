import { ArrowDownRight, ArrowUpRight, Lock } from "lucide-react";

function StakeCard({ stakingBalance, availableBalance, lockPeriod, apyEstimate, onStake, onUnstake }) {
  return (
    <section className="rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4 shadow-cosmic-card transition-all duration-300 hover:-translate-y-1 hover:border-auric-300/60 hover:shadow-button-glow">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-auric-300/70">Stake XRP</p>
          <h2 className="mt-1 text-lg font-semibold text-violet-50">Staking Overview</h2>
        </div>
        <div className="inline-flex items-center gap-2 rounded-full border border-violet-300/25 bg-cosmic-900/70 px-3 py-1 text-xs text-violet-100/70">
          <Lock className="h-3.5 w-3.5 text-auric-300" aria-hidden="true" />
          {lockPeriod}
        </div>
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-2">
        <div className="rounded-2xl border border-violet-300/20 bg-cosmic-900/70 p-3">
          <p className="text-xs text-violet-100/60">Staked XRP</p>
          <p className="mt-2 text-2xl font-semibold text-auric-300">{stakingBalance.toLocaleString()} XRP</p>
        </div>
        <div className="rounded-2xl border border-violet-300/20 bg-cosmic-900/70 p-3">
          <p className="text-xs text-violet-100/60">Available XRP</p>
          <p className="mt-2 text-2xl font-semibold text-violet-50">{availableBalance.toLocaleString()} XRP</p>
        </div>
      </div>

      <div className="mt-4 flex items-center justify-between rounded-2xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-2 text-xs">
        <span className="text-violet-100/70">Estimated APY (emission range)</span>
        <span className="font-semibold text-auric-300">{apyEstimate}</span>
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-2">
        <button
          type="button"
          onClick={onStake}
          className="inline-flex items-center justify-center gap-2 rounded-xl border border-auric-300/60 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 px-4 py-3 text-xs font-semibold text-cosmic-900 transition-all duration-300 hover:scale-[1.02] hover:shadow-button-glow"
        >
          <ArrowUpRight className="h-4 w-4" aria-hidden="true" />
          Stake
        </button>
        <button
          type="button"
          onClick={onUnstake}
          className="inline-flex items-center justify-center gap-2 rounded-xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3 text-xs font-semibold text-violet-100/75 transition-all duration-300 hover:-translate-y-0.5 hover:border-auric-300/60 hover:text-auric-200 hover:shadow-button-glow"
        >
          <ArrowDownRight className="h-4 w-4" aria-hidden="true" />
          Unstake
        </button>
      </div>

      <button
        type="button"
        onClick={onUnstake}
        className="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-violet-300/25 bg-cosmic-900/70 px-4 py-3 text-xs font-semibold text-violet-100/75 transition-all duration-300 hover:-translate-y-0.5 hover:border-auric-300/60 hover:text-auric-200 hover:shadow-button-glow"
      >
        <ArrowDownRight className="h-4 w-4" aria-hidden="true" />
        Withdraw
      </button>
    </section>
  );
}

export default StakeCard;
