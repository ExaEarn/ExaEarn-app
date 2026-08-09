import { PieChart } from "lucide-react";

function FractionalOwnership({ highlights, benefits }) {
  return (
    <section className="campaign-card">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs uppercase tracking-[0.25em] text-[var(--exa-gold)]">Fractional Ownership</p>
          <h2 className="mt-2 font-['Sora'] text-2xl font-semibold text-[var(--exa-text-primary)] sm:text-3xl">
            Tokenized Parcels for Shared Ownership
          </h2>
          <p className="mt-2 text-sm text-[var(--exa-text-secondary)]">
            Each land parcel is split into compliant tokens, enabling transparent ownership, yield sharing, and liquid
            exits.
          </p>
        </div>
        <div className="flex h-14 w-14 items-center justify-center rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold)] shadow-[var(--exa-shadow-gold)]">
          <PieChart className="h-6 w-6" aria-hidden="true" />
        </div>
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-3">
        {highlights.map((item) => (
          <div key={item.label} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4 text-center">
            <p className="text-sm text-[var(--exa-text-secondary)]">{item.label}</p>
            <p className="mt-2 text-lg font-semibold text-[var(--exa-gold)]">{item.value}</p>
          </div>
        ))}
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {benefits.map((benefit) => (
          <div key={benefit.title} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
            <div className="flex items-center gap-2 text-[var(--exa-gold)]">
              {benefit.icon}
              <p className="text-sm font-semibold text-[var(--exa-text-primary)]">{benefit.title}</p>
            </div>
            <p className="mt-2 text-xs text-[var(--exa-text-secondary)]">{benefit.description}</p>
          </div>
        ))}
      </div>
    </section>
  );
}

export default FractionalOwnership;
