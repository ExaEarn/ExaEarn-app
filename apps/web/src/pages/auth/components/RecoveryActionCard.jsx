import { ArrowRight } from "lucide-react";

function RecoveryActionCard({ title, description, icon, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="w-full rounded-2xl border border-[var(--exa-border-subtle)] bg-white/[0.028] p-4 text-left transition duration-200 hover:-translate-y-0.5 hover:border-[var(--exa-border-active)] hover:bg-[var(--exa-gold-surface)] active:translate-y-0 exa-focusable"
    >
      <div className="flex items-center gap-4">
        {icon ? <span className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]">{icon}</span> : null}
        <div className="min-w-0 flex-1">
          <h3 className="text-base font-semibold text-[var(--exa-text-primary)]">{title}</h3>
          {description ? <p className="mt-1 text-sm leading-relaxed text-[var(--exa-text-muted)]">{description}</p> : null}
        </div>
        <ArrowRight className="h-5 w-5 flex-shrink-0 text-[var(--exa-text-muted)]" aria-hidden="true" />
      </div>
    </button>
  );
}

export default RecoveryActionCard;