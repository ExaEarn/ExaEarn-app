import { formatNaira } from "../hooks/useGiftcardLiveRate";

function SummaryCard({
  provider,
  denomination,
  fee,
  total,
  paymentLabel,
  availability = "Unknown",
  sellRate,
  demandLevel = "Medium",
  marketFeedback = "Live market rate available",
  loading = false,
}) {
  return (
    <article className="buy-card rounded-2xl p-5 sm:p-6">
      <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Transaction Summary</h2>
      <div className="mt-5 space-y-3">
        <Row label="Selected Giftcard" value={provider} />
        <Row label="Denomination" value={denomination} />
        <Row label="Inventory Status" value={availability} />
        <Row label="Demand" value={demandLevel} />
        <Row label="Live Sell Rate" value={loading ? "Updating price..." : sellRate ? `${formatNaira(sellRate)}/$` : "-"} />
        <Row label="Fees" value={formatNaira(fee)} />
        <Row label="Payment Source" value={paymentLabel} />
        <div className="my-1 h-px bg-gradient-to-r from-transparent via-auric-400/50 to-transparent" />
        <Row label="Final Payable" value={formatNaira(total)} emphasize />
        <p className="rounded-xl border border-emerald-300/20 bg-emerald-500/5 px-4 py-3 text-sm text-emerald-100/85">
          {marketFeedback}
        </p>
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
