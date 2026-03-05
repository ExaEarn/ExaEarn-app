import { ArrowRight } from "lucide-react";

function RecoveryActionCard({ title, description, icon, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="w-full rounded-3xl border border-violet-300/20 bg-cosmic-900/70 p-5 text-left shadow-cosmic-glow/40 transition-all duration-300 hover:-translate-y-0.5 hover:border-auric-300/45 active:scale-[0.995]"
    >
      <div className="flex items-center gap-4">
        {icon ? (
          <span className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-auric-300/35 bg-cosmic-900/80 text-auric-300">
            {icon}
          </span>
        ) : null}

        <div className="min-w-0 flex-1">
          <h3 className="font-['Sora'] text-lg font-semibold text-violet-50">{title}</h3>
          {description ? <p className="mt-1 text-sm leading-relaxed text-violet-100/70">{description}</p> : null}
        </div>

        <ArrowRight className="h-5 w-5 flex-shrink-0 text-violet-100/65" aria-hidden="true" />
      </div>
    </button>
  );
}

export default RecoveryActionCard;
