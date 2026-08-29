import { ClipboardCheck } from "lucide-react";

function LeasePanel({ onSubscribe }) {
  return (
    <section className="rounded-3xl border border-[var(--exa-border)] bg-gradient-to-br from-[var(--exa-surface)] via-[var(--exa-surface-elevated)] to-[var(--exa-surface)] p-5 shadow-[var(--exa-shadow-soft)]">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="font-['Sora'] text-lg font-semibold text-[var(--exa-text-primary)]">Operate a verified farm project</h2>
          <p className="mt-2 text-xs text-[var(--exa-text-muted)]">
            Submit your farmer profile for identity, operating and land-evidence review.
          </p>
        </div>
        <div className="flex h-9 w-9 items-center justify-center rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold)]">
          <ClipboardCheck className="h-4 w-4" aria-hidden="true" />
        </div>
      </div>

      <button
        type="button"
        onClick={onSubscribe}
        className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-[var(--exa-border)] bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] px-4 py-3 text-sm font-semibold text-[var(--exa-text-primary)] transition-all duration-300 hover:scale-[1.01] hover:shadow-[var(--exa-shadow-gold)]"
      >
        Start farmer application
      </button>
    </section>
  );
}

export default LeasePanel;
