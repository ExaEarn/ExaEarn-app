function SummaryPanel({
  amount,
  fee,
  receivable,
  rateText,
  currency,
  demandLevel = "Medium",
  inventoryStatus = "Available",
  marketFeedback = "Live market rate available",
  loading = false,
}) {
  return (
    <aside className="gift-card rounded-2xl p-5 sm:p-6">
      <h3 className="font-['Sora'] text-2xl font-semibold text-[var(--exa-text-primary)]">Transaction Summary</h3>
      <div className="mt-5 space-y-3">
        <SummaryRow label="Entered Value" value={`${amount.toFixed(2)} ${currency}`} />
        <SummaryRow label="Live Rate" value={loading ? "Fetching best rate..." : rateText} />
        <SummaryRow label="Demand" value={demandLevel} />
        <SummaryRow label="Inventory Signal" value={inventoryStatus} />
        <SummaryRow label="Processing Fee" value={`${fee.toFixed(2)} ${currency}`} />
        <div className="my-1 h-px bg-gradient-to-r from-transparent via-[var(--exa-gold)]/50 to-transparent" />
        <SummaryRow label="Final Receivable" value={`${receivable.toFixed(2)} EXA`} emphasize />
        <p className="rounded-xl border border-emerald-300/20 bg-emerald-500/5 px-4 py-3 text-sm text-emerald-100/85">
          {marketFeedback}
        </p>
      </div>
    </aside>
  );
}

function SummaryRow({ label, value, emphasize = false }) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-4 py-3">
      <span className="text-sm text-[var(--exa-text-secondary)]">{label}</span>
      <span className={`text-sm font-semibold ${emphasize ? "text-[var(--exa-gold-light)]" : "text-[var(--exa-text-primary)]"}`}>{value}</span>
    </div>
  );
}

export default SummaryPanel;
