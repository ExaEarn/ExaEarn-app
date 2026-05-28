import { Copy, LineChart } from "lucide-react";

function PairDetailsIcon({ onOpenChart, onCopyPair }) {
  return (
    <div className="flex items-center gap-1">
      <button
        type="button"
        onClick={onOpenChart}
        className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-white/15 bg-[#111d2b] text-[#9aa4b2] hover:border-[#6b2cff]/60 hover:text-[#ffb900]"
        aria-label="Open mini chart"
      >
        <LineChart className="h-4 w-4" />
      </button>
      <button
        type="button"
        onClick={onCopyPair}
        className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-white/15 bg-[#111d2b] text-[#9aa4b2] hover:border-[#6b2cff]/60 hover:text-[#ffb900]"
        aria-label="Copy pair symbol"
      >
        <Copy className="h-4 w-4" />
      </button>
    </div>
  );
}

export default PairDetailsIcon;
