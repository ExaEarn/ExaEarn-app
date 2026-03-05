function SummaryCard({ provider, denomination, fee, total, paymentLabel }) {
  return (
    <article className="buy-card rounded-2xl p-5 sm:p-6">
      <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Transaction Summary</h2>
      <div className="mt-5 space-y-3">
        <Row label="Selected Giftcard" value={provider} />
        <Row label="Denomination" value={denomination} />
        <Row label="Fees" value={`$${fee.toFixed(2)}`} />
        <Row label="Payment Source" value={paymentLabel} />
        <div className="my-1 h-px bg-gradient-to-r from-transparent via-auric-400/50 to-transparent" />
        <Row label="Final Payable" value={`$${total.toFixed(2)}`} emphasize />
      </div>
    </article>
  );
}

function Row({ label, value, emphasize = false }) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-xl border border-violet-300/20 bg-cosmic-900/55 px-4 py-3">
      <span className="text-sm text-violet-100/75">{label}</span>
      <span className={`text-sm font-semibold ${emphasize ? "text-auric-300" : "text-violet-50"}`}>{value}</span>
    </div>
  );
}

export default SummaryCard;
