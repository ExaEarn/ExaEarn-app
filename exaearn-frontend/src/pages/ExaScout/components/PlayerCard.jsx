import { BadgeCheck, Play, ShieldCheck } from "lucide-react";

function PlayerCard({ player, onViewProfile, onOpenHighlightPreview }) {
  return (
    <article className="rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4 shadow-cosmic-card">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-auric-300/70">Player Profile</p>
          <h3 className="mt-1 text-lg font-semibold text-violet-50">{player.name}</h3>
          <p className="mt-1 text-xs text-violet-100/65">
            {player.age} yrs · {player.position} · {player.country}
          </p>
        </div>
        {player.verified ? (
          <span className="inline-flex items-center gap-1 rounded-full border border-emerald-300/40 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-200">
            <ShieldCheck className="h-3.5 w-3.5" aria-hidden="true" />
            Verified
          </span>
        ) : (
          <span className="inline-flex items-center gap-1 rounded-full border border-violet-300/30 bg-cosmic-900/70 px-2.5 py-1 text-xs font-semibold text-violet-100/70">
            <BadgeCheck className="h-3.5 w-3.5" aria-hidden="true" />
            Pending
          </span>
        )}
      </div>

      <div className="mt-4 space-y-2 text-xs text-violet-100/70">
        <div className="flex items-center justify-between rounded-xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-2">
          <span>Skill Rating</span>
          <span className="font-semibold text-auric-300">{player.rating}</span>
        </div>
        <div className="flex items-center justify-between rounded-xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-2">
          <span>NFT Contract</span>
          <span className="font-semibold text-auric-300">{player.nftStatus}</span>
        </div>
      </div>

      <div className="mt-4 flex items-center justify-between gap-2">
        <button
          type="button"
          onClick={() => onOpenHighlightPreview?.(player.id)}
          className="inline-flex items-center gap-2 rounded-xl border border-violet-300/30 bg-cosmic-900/70 px-3 py-2 text-xs font-semibold text-violet-100/75 transition-all duration-300 hover:border-auric-300/60 hover:text-auric-200"
        >
          <Play className="h-3.5 w-3.5" aria-hidden="true" />
          Highlight Preview
        </button>
        <button type="button" onClick={() => onViewProfile?.(player.id)} className="btn-gold text-xs">
          View Profile
        </button>
      </div>
    </article>
  );
}

export default PlayerCard;
