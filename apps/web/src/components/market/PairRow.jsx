import { Star } from "lucide-react";
import { useRef } from "react";
import PairDetailsIcon from "./PairDetailsIcon";

function formatPrice(value) {
  if (value >= 1000) return value.toLocaleString(undefined, { maximumFractionDigits: 2 });
  if (value >= 1) return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 });
  return value.toLocaleString(undefined, { minimumFractionDigits: 4, maximumFractionDigits: 6 });
}

function PairRow({
  item,
  fiat = "USD",
  isFavorite,
  onOpenDetails,
  onToggleFavorite,
  onOpenChart,
  onCopyPair,
  style,
}) {
  const pressTimerRef = useRef(null);
  const longPressTriggeredRef = useRef(false);

  const startLongPress = () => {
    longPressTriggeredRef.current = false;
    pressTimerRef.current = setTimeout(() => {
      longPressTriggeredRef.current = true;
      onToggleFavorite(item.pair);
    }, 460);
  };

  const clearLongPress = () => {
    if (pressTimerRef.current) {
      clearTimeout(pressTimerRef.current);
      pressTimerRef.current = null;
    }
  };

  const handleRowClick = () => {
    if (!longPressTriggeredRef.current) {
      onOpenDetails(item);
    }
  };

  const changePositive = item.change24h >= 0;
  const fiatSymbol = fiat === "NGN" ? "₦" : "$";

  return (
    <div style={style} className="px-4 py-1">
      <button
        type="button"
        onMouseDown={startLongPress}
        onMouseUp={clearLongPress}
        onMouseLeave={clearLongPress}
        onTouchStart={startLongPress}
        onTouchEnd={clearLongPress}
        onClick={handleRowClick}
        className="w-full rounded-2xl border border-white/10 bg-[#0f1720] p-3 text-left transition-all duration-100 hover:-translate-y-[1px] hover:border-[#6b2cff]/55 hover:shadow-[0_0_18px_rgba(107,44,255,0.24)] active:translate-y-0"
        aria-label={`Open ${item.pair} market details`}
      >
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <span className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#1b2740] text-xs font-bold text-[#ffb900]">
                {item.base.slice(0, 2)}
              </span>
              <div className="min-w-0">
                <p className="truncate text-sm font-semibold text-white">{item.pair}</p>
                <p className="text-xs text-[#9aa4b2]">Vol {item.volume}</p>
              </div>
            </div>
          </div>

          <div className="text-right">
            <p className="text-base font-semibold text-white">{formatPrice(item.last)}</p>
            <p className="text-xs text-[#9aa4b2]">
              {fiatSymbol}
              {formatPrice(item.fiat ?? item.last)}
            </p>
          </div>
        </div>

        <div className="mt-3 flex items-center justify-between">
          <div className="flex items-center gap-2">
            {item.leverage ? (
              <span className="rounded-md border border-[#6b2cff]/45 bg-[#6b2cff]/15 px-2 py-0.5 text-[10px] font-semibold text-[#d3c0ff]">
                {item.leverage}
              </span>
            ) : null}
            <button
              type="button"
              onClick={(event) => {
                event.stopPropagation();
                onToggleFavorite(item.pair);
              }}
              className={`inline-flex h-6 w-6 items-center justify-center rounded-md transition ${
                isFavorite
                  ? "text-[#ffb900] drop-shadow-[0_0_8px_rgba(255,185,0,0.5)]"
                  : "text-[#9aa4b2] hover:text-[#ffb900]"
              }`}
              aria-label={`Toggle favorite for ${item.pair}`}
            >
              <Star className={`h-4 w-4 transition ${isFavorite ? "fill-current scale-110" : ""}`} />
            </button>
          </div>

          <div className="flex items-center gap-2">
            <span
              className={`rounded-full px-2.5 py-1 text-xs font-semibold transition-colors duration-300 ${
                changePositive ? "bg-[#18c06c]/20 text-[#6ef3a9]" : "bg-[#ff5b6b]/20 text-[#ff93a0]"
              }`}
            >
              {changePositive ? "+" : ""}
              {item.change24h.toFixed(2)}%
            </span>
            <PairDetailsIcon
              onOpenChart={() => onOpenChart(item)}
              onCopyPair={() => onCopyPair(item.pair)}
            />
          </div>
        </div>
      </button>
    </div>
  );
}

export default PairRow;
