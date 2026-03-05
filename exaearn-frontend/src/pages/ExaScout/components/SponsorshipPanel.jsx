function SponsorshipPanel({ tiers }) {
  return (
    <section className="rounded-2xl border border-violet-300/20 bg-cosmic-900/60 p-4 shadow-cosmic-card">
      <p className="text-xs uppercase tracking-[0.2em] text-auric-300/70">Sponsorship System</p>
      <h2 className="mt-1 text-lg font-semibold text-violet-50">Sponsor a Rising Talent</h2>
      <p className="mt-2 text-xs text-violet-100/70">
        Investors back players with EXA and earn a transparent share of future earnings.
      </p>

      <div className="mt-4 grid gap-3">
        {tiers.map((tier) => (
          <div key={tier.name} className="flex items-center justify-between rounded-xl border border-violet-300/20 bg-cosmic-900/70 px-3 py-3 text-xs">
            <div>
              <p className="font-semibold text-violet-50">{tier.name}</p>
              <p className="mt-1 text-violet-100/60">{tier.roi}</p>
            </div>
            <span className="font-semibold text-auric-300">{tier.amount}</span>
          </div>
        ))}
      </div>

      <button type="button" className="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-auric-300/60 bg-gradient-to-r from-auric-300 via-auric-400 to-auric-500 px-4 py-3 text-xs font-semibold text-cosmic-900 transition-all duration-300 hover:scale-[1.01] hover:shadow-button-glow">
        Sponsor Talent
      </button>
    </section>
  );
}

export default SponsorshipPanel;
