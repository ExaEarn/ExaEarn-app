import { PieChart } from "lucide-react";

function FractionalOwnership({ highlights, benefits }) {
  return (
    <section className="campaign-card">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs uppercase tracking-[0.25em] text-auric-300/70">Fractional Ownership</p>
          <h2 className="mt-2 font-['Sora'] text-2xl font-semibold text-violet-50 sm:text-3xl">
            Tokenized Parcels for Shared Ownership
          </h2>
          <p className="mt-2 text-sm text-violet-100/75">
            Each land parcel is split into compliant tokens, enabling transparent ownership, yield sharing, and liquid
            exits.
          </p>
        </div>
        <div className="flex h-14 w-14 items-center justify-center rounded-2xl border border-auric-300/60 bg-cosmic-900/70 text-auric-300 shadow-button-glow">
          <PieChart className="h-6 w-6" aria-hidden="true" />
        </div>
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-3">
        {highlights.map((item) => (
          <div key={item.label} className="rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4 text-center">
            <p className="text-sm text-violet-100/60">{item.label}</p>
            <p className="mt-2 text-lg font-semibold text-auric-300">{item.value}</p>
          </div>
        ))}
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {benefits.map((benefit) => (
          <div key={benefit.title} className="rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4">
            <div className="flex items-center gap-2 text-auric-300">
              {benefit.icon}
              <p className="text-sm font-semibold text-violet-50">{benefit.title}</p>
            </div>
            <p className="mt-2 text-xs text-violet-100/65">{benefit.description}</p>
          </div>
        ))}
      </div>
    </section>
  );
}

export default FractionalOwnership;
