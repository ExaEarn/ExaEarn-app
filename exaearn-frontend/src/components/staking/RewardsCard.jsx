import { Clock, Sparkles } from "lucide-react";

function RewardsCard({ pendingRewards, onClaim }) {
  const hasRewards = pendingRewards > 0;

  return (
    <section className="rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4 shadow-cosmic-card transition-all duration-300 hover:-translate-y-1 hover:border-auric-300/60 hover:shadow-button-glow">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-auric-300/70">Rewards</p>
          <h2 className="mt-1 text-lg font-semibold text-violet-50">ExaToken Emissions</h2>
        </div>
        <div className="flex h-10 w-10 items-center justify-center rounded-2xl border border-auric-300/60 bg-cosmic-900/70 text-auric-300">
          <Sparkles className="h-5 w-5" aria-hidden="true" />
        </div>
      </div>

      <div className="mt-4 rounded-2xl border border-violet-300/20 bg-cosmic-900/70 p-4 text-center">
        <p className="text-xs text-violet-100/60">Pending Rewards</p>
        <p className="mt-2 text-3xl font-semibold text-auric-300">{pendingRewards.toFixed(3)} EXA</p>
        <p className="mt-2 text-xs text-violet-100/60">Rewards emitted every cycle, not fixed promises.</p>
      </div>

      <div className="mt-4 flex items-center justify-between rounded-2xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-2 text-xs">
        <span className="text-violet-100/70">Emission Countdown</span>
        <span className="flex items-center gap-1 text-auric-300">
          <Clock className="h-3.5 w-3.5" aria-hidden="true" />
          23:42:18
        </span>
      </div>

      <button
        type="button"
        onClick={onClaim}
        disabled={!hasRewards}
        className={`mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border px-4 py-3 text-xs font-semibold transition-all duration-300 ${
          hasRewards
            ? "border-auric-300/70 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 text-cosmic-900 hover:scale-[1.02] hover:shadow-button-glow"
            : "cursor-not-allowed border-violet-300/25 bg-cosmic-900/70 text-violet-100/50"
        }`}
      >
        Claim Rewards
      </button>
    </section>
  );
}

export default RewardsCard;
