import { useMemo, useState } from "react";
import PairRow from "./PairRow";

const ROW_HEIGHT = 110;
const OVERSCAN = 4;

function PairList({
  items,
  favoritesMap,
  onOpenDetails,
  onToggleFavorite,
  onOpenChart,
  onCopyPair,
  fiat,
  viewportHeight = 460,
}) {
  const [scrollTop, setScrollTop] = useState(0);

  const totalHeight = items.length * ROW_HEIGHT;
  const visibleCount = Math.ceil(viewportHeight / ROW_HEIGHT);

  const [startIndex, endIndex] = useMemo(() => {
    const start = Math.max(0, Math.floor(scrollTop / ROW_HEIGHT) - OVERSCAN);
    const end = Math.min(items.length - 1, start + visibleCount + OVERSCAN * 2);
    return [start, end];
  }, [items.length, scrollTop, visibleCount]);

  const visibleItems = useMemo(() => {
    return items.slice(startIndex, endIndex + 1);
  }, [items, startIndex, endIndex]);

  return (
    <section className="relative">
      <div
        className="overflow-auto"
        style={{ height: viewportHeight }}
        onScroll={(event) => setScrollTop(event.currentTarget.scrollTop)}
      >
        <div style={{ height: totalHeight, position: "relative" }}>
          {visibleItems.map((item, offset) => {
            const index = startIndex + offset;
            return (
              <PairRow
                key={item.pair}
                item={item}
                fiat={fiat}
                isFavorite={Boolean(favoritesMap[item.pair])}
                onOpenDetails={onOpenDetails}
                onToggleFavorite={onToggleFavorite}
                onOpenChart={onOpenChart}
                onCopyPair={onCopyPair}
                style={{
                  position: "absolute",
                  top: index * ROW_HEIGHT,
                  left: 0,
                  right: 0,
                  height: ROW_HEIGHT,
                }}
              />
            );
          })}
        </div>
      </div>
    </section>
  );
}

export default PairList;
