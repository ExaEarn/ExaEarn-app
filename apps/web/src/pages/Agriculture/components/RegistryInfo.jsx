import { Shield } from "lucide-react";

function RegistryInfo({ items }) {
  return (
    <section className="campaign-card">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs uppercase tracking-[0.25em] text-[var(--exa-gold)]">Project evidence</p>
          <h2 className="mt-2 font-['Sora'] text-2xl font-semibold text-[var(--exa-text-primary)] sm:text-3xl">
            Reviewed records with a visible audit trail
          </h2>
          <p className="mt-2 text-sm text-[var(--exa-text-secondary)]">
            Submitted evidence remains unverified until an authorized reviewer records a decision. Digital records do not replace legal title review.
          </p>
        </div>
        <div className="flex h-14 w-14 items-center justify-center rounded-2xl border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold)] shadow-[var(--exa-shadow-gold)]">
          <Shield className="h-6 w-6" aria-hidden="true" />
        </div>
      </div>

      <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {items.map((item) => (
          <div key={item.title} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
            <div className="flex items-center gap-2 text-[var(--exa-gold)]">
              {item.icon}
              <p className="text-sm font-semibold text-[var(--exa-text-primary)]">{item.title}</p>
            </div>
            <p className="mt-2 text-xs text-[var(--exa-text-secondary)]">{item.description}</p>
          </div>
        ))}
      </div>
    </section>
  );
}

export default RegistryInfo;
