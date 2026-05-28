import { Sparkles } from "lucide-react";

function LeasePanel({ onSubscribe }) {
  return (
    <section className="rounded-3xl border border-violet-300/20 bg-gradient-to-br from-cosmic-900/80 via-cosmic-800/70 to-cosmic-900/90 p-5 shadow-cosmic-card">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="font-['Sora'] text-lg font-semibold text-violet-50">Lease Land with Tokens</h2>
          <p className="mt-2 text-xs text-violet-100/70">
            Subscribe to lease farmland using your tokens.
          </p>
        </div>
        <div className="flex h-9 w-9 items-center justify-center rounded-2xl border border-auric-300/60 bg-cosmic-900/70 text-auric-300">
          <Sparkles className="h-4 w-4" aria-hidden="true" />
        </div>
      </div>

      <button
        type="button"
        onClick={onSubscribe}
        className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-violet-300/30 bg-gradient-to-r from-violet-500/80 via-fuchsia-500/70 to-violet-600/80 px-4 py-3 text-sm font-semibold text-white transition-all duration-300 hover:scale-[1.01] hover:shadow-button-glow"
      >
        Subscribe Now
      </button>
    </section>
  );
}

export default LeasePanel;
