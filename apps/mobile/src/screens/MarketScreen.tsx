import { Ionicons } from "@expo/vector-icons";
import { useEffect, useMemo, useState } from "react";
import {
  Modal,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import { useAuth } from "../context/AuthContext";
import { colors, fonts } from "../theme/colors";

type MarketScreenProps = {
  fontsReady: boolean;
  onBack: () => void;
  onOpenTrade?: () => void;
  onOpenFutures?: () => void;
  onOpenP2P?: () => void;
  onOpenCrypto?: () => void;
};

type RawMarketPayload = Record<string, unknown>;

type MarketPair = {
  pair: string;
  base: string;
  quote: string;
  last: number;
  change24h: number;
  volume: string;
  favorite: boolean;
  source: string;
  fiat?: number;
};

const topTabs = ["Favorites", "Market", "Discover", "..."] as const;
const marketTabs = ["Crypto", "Spot", "Futures", "P2P"] as const;
const quickChips = ["XRP", "USDT", "BTC"] as const;
const rowHeights = [36, 48, 42, 62, 54, 74, 66];

const fallbackPairs: MarketPair[] = [
  { pair: "BTC/USDT", base: "BTC", quote: "USDT", last: 102840, change24h: 2.84, volume: "1.7B", favorite: true, source: "fallback" },
  { pair: "ETH/USDT", base: "ETH", quote: "USDT", last: 5418.2, change24h: 1.37, volume: "973.4M", favorite: false, source: "fallback" },
  { pair: "XRP/USDT", base: "XRP", quote: "USDT", last: 2.92, change24h: 4.61, volume: "181.1M", favorite: true, source: "fallback" },
  { pair: "SOL/USDT", base: "SOL", quote: "USDT", last: 238.7, change24h: -0.42, volume: "642.2M", favorite: false, source: "fallback" },
  { pair: "EXA/USDT", base: "EXA", quote: "USDT", last: 0.84, change24h: 8.2, volume: "41.8M", favorite: false, source: "fallback" },
  { pair: "ONDO/USDT", base: "ONDO", quote: "USDT", last: 1.03, change24h: 7.8, volume: "96.7M", favorite: false, source: "fallback" },
  { pair: "PEPE/USDT", base: "PEPE", quote: "USDT", last: 0.000012, change24h: -4.92, volume: "314.1M", favorite: false, source: "fallback" },
];

function compactVolume(value: unknown) {
  const numericValue = Number(value);
  if (!Number.isFinite(numericValue)) return "0";
  if (numericValue >= 1_000_000_000) return `${(numericValue / 1_000_000_000).toFixed(1)}B`;
  if (numericValue >= 1_000_000) return `${(numericValue / 1_000_000).toFixed(1)}M`;
  if (numericValue >= 1_000) return `${(numericValue / 1_000).toFixed(1)}K`;
  return numericValue.toLocaleString(undefined, { maximumFractionDigits: 2 });
}

function normalizeMarket(item: RawMarketPayload, favorite = false): MarketPair {
  const symbol = String(item.symbol || item.pair || "").toUpperCase();
  const [symbolBase, symbolQuote] = symbol.includes("/") ? symbol.split("/") : symbol.split("-");
  const base = String(item.base || item.base_currency || symbolBase || "").toUpperCase();
  const quote = String(item.quote || item.quote_currency || symbolQuote || "USDT").toUpperCase();
  const pair = String(item.pair || (base && quote ? `${base}/${quote}` : symbol));
  const last = Number(item.last ?? item.last_price ?? item.price ?? 0);
  const change24h = Number(item.change24h ?? item.price_change_percent ?? item.change_24h ?? 0);

  return {
    pair,
    base,
    quote,
    last: Number.isFinite(last) ? last : 0,
    change24h: Number.isFinite(change24h) ? change24h : 0,
    volume: typeof item.volume === "string" ? item.volume : compactVolume(item.volume),
    favorite: Boolean(item.favorite ?? favorite),
    source: String(item.source || "api"),
    fiat: Number.isFinite(last) ? last : 0,
  };
}

function parseVolumeToNumber(value: string) {
  if (!value) return 0;
  const cleaned = String(value).toUpperCase();
  const amount = parseFloat(cleaned.replace(/[^\d.]/g, ""));
  if (cleaned.endsWith("B")) return amount * 1_000_000_000;
  if (cleaned.endsWith("M")) return amount * 1_000_000;
  if (cleaned.endsWith("K")) return amount * 1_000;
  return amount;
}

function formatPrice(value: number) {
  if (value >= 1000) return value.toLocaleString(undefined, { maximumFractionDigits: 2 });
  if (value >= 1) return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 });
  return value.toLocaleString(undefined, { minimumFractionDigits: 4, maximumFractionDigits: 6 });
}

function sparkHeights(pair: string) {
  const seed = pair.split("").reduce((sum, char) => sum + char.charCodeAt(0), 0);
  return rowHeights.map((value, index) => Math.max(18, Math.min(86, value + ((seed + index * 11) % 10) - 5)));
}

export default function MarketScreen({
  fontsReady,
  onBack,
  onOpenTrade,
  onOpenFutures,
  onOpenP2P,
  onOpenCrypto,
}: MarketScreenProps) {
  const insets = useSafeAreaInsets();
  const { request } = useAuth();
  const [loading, setLoading] = useState(true);
  const [offline, setOffline] = useState(false);
  const [pairs, setPairs] = useState<MarketPair[]>(fallbackPairs);
  const [fiat, setFiat] = useState<"USD" | "NGN">("USD");
  const [searchTerm, setSearchTerm] = useState("");
  const [activeChip, setActiveChip] = useState<(typeof quickChips)[number] | "">("");
  const [sortBy, setSortBy] = useState<"last" | "change24h" | "volume">("last");
  const [sortDirection, setSortDirection] = useState<"asc" | "desc">("desc");
  const [favoritesOnly, setFavoritesOnly] = useState(false);
  const [activeSubTab, setActiveSubTab] = useState<(typeof marketTabs)[number]>("Spot");
  const [selectedPair, setSelectedPair] = useState<MarketPair | null>(null);
  const [chartPair, setChartPair] = useState<MarketPair | null>(null);

  const handleSubTabChange = (tab: (typeof marketTabs)[number]) => {
    setActiveSubTab(tab);

    if (tab === "Futures") {
      onOpenFutures?.();
      return;
    }

    if (tab === "P2P") {
      onOpenP2P?.();
      return;
    }

    if (tab === "Crypto") {
      onOpenCrypto?.();
    }
  };

  const loadMarkets = async () => {
    setLoading(true);
    try {
      const payload = await request<{ data?: RawMarketPayload[] }>("/api/trade/markets", { method: "GET" });
      const data = Array.isArray(payload.data) ? payload.data : [];
      if (data.length) {
        setPairs((previous) => {
          const favorites = new Map(previous.map((item) => [item.pair, item.favorite]));
          return data.map((item) => {
            const normalized = normalizeMarket(item);
            return normalizeMarket(item, favorites.get(normalized.pair));
          });
        });
        setOffline(false);
      } else {
        setOffline(true);
      }
    } catch {
      setOffline(true);
      setPairs((previous) => (previous.length ? previous : fallbackPairs));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void loadMarkets();
  }, []);

  const filteredPairs = useMemo(() => {
    const normalizedSearch = searchTerm.trim().toLowerCase();

    return [...pairs]
      .filter((item) => {
        if (favoritesOnly && !item.favorite) return false;
        if (activeChip && !item.pair.includes(activeChip)) return false;
        if (!normalizedSearch) return true;
        return (
          item.pair.toLowerCase().includes(normalizedSearch) ||
          item.base.toLowerCase().includes(normalizedSearch) ||
          item.quote.toLowerCase().includes(normalizedSearch)
        );
      })
      .sort((left, right) => {
        const direction = sortDirection === "asc" ? 1 : -1;
        if (sortBy === "change24h") return (left.change24h - right.change24h) * direction;
        if (sortBy === "volume") return (parseVolumeToNumber(left.volume) - parseVolumeToNumber(right.volume)) * direction;
        return (left.last - right.last) * direction;
      });
  }, [activeChip, favoritesOnly, pairs, searchTerm, sortBy, sortDirection]);

  const toggleFavorite = (pairKey: string) => {
    setPairs((previous) => previous.map((item) => (item.pair === pairKey ? { ...item, favorite: !item.favorite } : item)));
  };

  if (!fontsReady) {
    return <View style={styles.fill} />;
  }

  return (
    <View style={styles.screen}>
      <View style={[styles.headerShell, { paddingTop: insets.top + 16 }]}> 
        <View style={styles.headerRow}>
          <Text style={styles.headerTitle}>Market</Text>
          <View style={styles.headerActions}>
            <AnimatedPressable
              style={styles.smallActionButton}
              onPress={() => setFiat((previous) => (previous === "USD" ? "NGN" : "USD"))}
            >
              <Text style={styles.smallActionText}>{fiat}</Text>
            </AnimatedPressable>
            <AnimatedPressable style={styles.iconActionButton} onPress={() => setChartPair(filteredPairs[0] ?? null)}>
              <Ionicons name="bar-chart-outline" size={16} color="#9aa4b2" />
            </AnimatedPressable>
            <AnimatedPressable style={styles.iconActionButton}>
              <Ionicons name="options-outline" size={16} color="#9aa4b2" />
            </AnimatedPressable>
          </View>
        </View>

        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.topHeaderTabsRow}>
          {topTabs.map((tab) => (
            <AnimatedPressable key={tab} style={styles.topHeaderTabButton}>
              <Text style={[styles.topHeaderTabText, tab === "Market" ? styles.topHeaderTabTextActive : null]}>{tab}</Text>
              {tab === "Market" ? <View style={styles.topHeaderUnderline} /> : null}
            </AnimatedPressable>
          ))}
        </ScrollView>

        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.marketTabsRow}>
          {marketTabs.map((tab) => (
            <AnimatedPressable
              key={tab}
              onPress={() => handleSubTabChange(tab)}
              style={[styles.marketTabChip, activeSubTab === tab ? styles.marketTabChipActive : null]}
            >
              <Text style={[styles.marketTabChipText, activeSubTab === tab ? styles.marketTabChipTextActive : null]}>{tab}</Text>
            </AnimatedPressable>
          ))}
        </ScrollView>
      </View>

      {offline ? (
        <View style={styles.offlineBanner}>
          <Ionicons name="cloud-offline-outline" size={14} color="#ffde7a" />
          <Text style={styles.offlineBannerText}>Offline - using cached data</Text>
        </View>
      ) : null}

      <View style={styles.filterShell}>
        <View style={styles.searchWrap}>
          <Ionicons name="search-outline" size={16} color="#9aa4b2" style={styles.searchIcon} />
          <TextInput
            value={searchTerm}
            onChangeText={setSearchTerm}
            placeholder="Search symbol or name (e.g., XRP, BTC)"
            placeholderTextColor="#9aa4b2"
            style={styles.searchInput}
          />
        </View>

        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.quickChipRow}>
          {quickChips.map((chip) => (
            <AnimatedPressable
              key={chip}
              onPress={() => setActiveChip((previous) => (previous === chip ? "" : chip))}
              style={[styles.quickChip, activeChip === chip ? styles.quickChipActive : null]}
            >
              <Text style={[styles.quickChipText, activeChip === chip ? styles.quickChipTextActive : null]}>{chip}</Text>
            </AnimatedPressable>
          ))}

          <AnimatedPressable
            onPress={() => setFavoritesOnly((previous) => !previous)}
            style={[styles.favoritesChip, favoritesOnly ? styles.favoritesChipActive : null]}
          >
            <Ionicons name={favoritesOnly ? "star" : "star-outline"} size={13} color={favoritesOnly ? "#ffffff" : "#9aa4b2"} />
            <Text style={[styles.favoritesChipText, favoritesOnly ? styles.favoritesChipTextActive : null]}>Favorites</Text>
          </AnimatedPressable>
        </ScrollView>

        <View style={styles.sortRow}>
          <AnimatedPressable
            onPress={() => {
              const options: Array<typeof sortBy> = ["last", "change24h", "volume"];
              const currentIndex = options.indexOf(sortBy);
              setSortBy(options[(currentIndex + 1) % options.length]);
            }}
            style={styles.sortButton}
          >
            <Text style={styles.sortButtonText}>{sortBy === "last" ? "Last Price" : sortBy === "change24h" ? "24h Change" : "Volume"}</Text>
            <Ionicons name="chevron-down-outline" size={14} color="#9aa4b2" />
          </AnimatedPressable>

          <AnimatedPressable
            onPress={() => setSortDirection((previous) => (previous === "desc" ? "asc" : "desc"))}
            style={styles.sortDirectionButton}
          >
            <Text style={styles.sortDirectionText}>{sortDirection === "desc" ? "Descending" : "Ascending"}</Text>
          </AnimatedPressable>
        </View>
      </View>

      <ScrollView
        contentContainerStyle={{ paddingBottom: insets.bottom + 92 }}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={loadMarkets} tintColor={colors.auric300} />}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.listShell}>
          {loading ? (
            <>
              <View style={styles.loadingBanner}>
                <Ionicons name="refresh-outline" size={14} color="#ffb900" />
                <Text style={styles.loadingBannerText}>Loading markets...</Text>
              </View>
              {Array.from({ length: 6 }).map((_, index) => <MarketSkeleton key={index} />)}
            </>
          ) : filteredPairs.length ? (
            filteredPairs.map((item) => (
              <PairRow
                key={item.pair}
                item={item}
                fiat={fiat}
                onOpenDetails={setSelectedPair}
                onToggleFavorite={toggleFavorite}
                onOpenChart={setChartPair}
              />
            ))
          ) : (
            <View style={styles.emptyState}>
              <Text style={styles.emptyStateText}>No markets found. Try clearing filters.</Text>
            </View>
          )}
        </View>
      </ScrollView>

      <View style={[styles.bottomNavShell, { paddingBottom: insets.bottom }]}> 
        <View style={styles.bottomNav}>
          <BottomNavItem label="Home" icon="home-outline" active={false} onPress={onBack} />
          <BottomNavItem label="Markets" icon="bar-chart-outline" active />
          <BottomNavItem label="Trade" icon="diamond-outline" active={false} onPress={onOpenTrade} />
          <BottomNavItem label="Futures" icon="stats-chart-outline" active={false} onPress={onOpenFutures} />
          <BottomNavItem label="Assets" icon="wallet-outline" active={false} />
        </View>
      </View>

      <Modal visible={Boolean(selectedPair)} transparent animationType="fade" onRequestClose={() => setSelectedPair(null)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.tradeModal}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>{selectedPair?.pair}</Text>
              <AnimatedPressable onPress={() => setSelectedPair(null)} style={styles.modalCloseButton}>
                <Ionicons name="close-outline" size={16} color="#9aa4b2" />
              </AnimatedPressable>
            </View>
            <Text style={styles.modalDescription}>Choose an action for this market.</Text>
            <View style={styles.modalActionRow}>
              <AnimatedPressable
                style={styles.modalPrimaryAction}
                onPress={() => {
                  setSelectedPair(null);
                  onOpenTrade?.();
                }}
              >
                <Text style={styles.modalPrimaryActionText}>Trade</Text>
              </AnimatedPressable>
              <AnimatedPressable style={styles.modalBuyAction}><Text style={styles.modalBuyText}>Buy</Text></AnimatedPressable>
              <AnimatedPressable style={styles.modalSellAction}><Text style={styles.modalSellText}>Sell</Text></AnimatedPressable>
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={Boolean(chartPair)} transparent animationType="fade" onRequestClose={() => setChartPair(null)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.chartModal}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>{chartPair?.pair} Mini Chart</Text>
              <AnimatedPressable onPress={() => setChartPair(null)} style={styles.modalCloseButton}>
                <Ionicons name="close-outline" size={16} color="#9aa4b2" />
              </AnimatedPressable>
            </View>
            <View style={styles.chartCanvas}>
              <View style={styles.chartBarsRow}>
                {(chartPair ? sparkHeights(chartPair.pair) : rowHeights).map((height, index) => (
                  <View key={index} style={[styles.chartBar, { height: `${height}%` }]} />
                ))}
              </View>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

function PairRow({
  item,
  fiat,
  onOpenDetails,
  onToggleFavorite,
  onOpenChart,
}: {
  item: MarketPair;
  fiat: "USD" | "NGN";
  onOpenDetails: (pair: MarketPair) => void;
  onToggleFavorite: (pairKey: string) => void;
  onOpenChart: (pair: MarketPair) => void;
}) {
  const changePositive = item.change24h >= 0;
  const fiatSymbol = fiat === "NGN" ? "NGN " : "$";

  return (
    <View style={styles.rowOuter}>
      <AnimatedPressable onPress={() => onOpenDetails(item)} style={styles.rowCard}>
        <View style={styles.rowTop}>
          <View style={styles.rowIdentity}>
            <View style={styles.rowCoinBadge}>
              <Text style={styles.rowCoinBadgeText}>{item.base.slice(0, 2)}</Text>
            </View>
            <View style={styles.rowIdentityCopy}>
              <Text style={styles.rowPair}>{item.pair}</Text>
              <Text style={styles.rowVolume}>Vol {item.volume}</Text>
            </View>
          </View>

          <View style={styles.rowPrices}>
            <Text style={styles.rowPrice}>{formatPrice(item.last)}</Text>
            <Text style={styles.rowFiat}>{fiatSymbol}{formatPrice(item.fiat ?? item.last)}</Text>
          </View>
        </View>

        <View style={styles.rowBottom}>
          <View style={styles.rowLeftActions}>
            <AnimatedPressable
              onPress={(event) => {
                event.stopPropagation();
                onToggleFavorite(item.pair);
              }}
              style={styles.favoriteIconButton}
            >
              <Ionicons name={item.favorite ? "star" : "star-outline"} size={15} color={item.favorite ? "#ffb900" : "#9aa4b2"} />
            </AnimatedPressable>
          </View>

          <View style={styles.rowRightActions}>
            <Text style={[styles.rowChangePill, changePositive ? styles.rowChangePositive : styles.rowChangeNegative]}>
              {changePositive ? "+" : ""}{item.change24h.toFixed(2)}%
            </Text>
            <AnimatedPressable
              onPress={(event) => {
                event.stopPropagation();
                onOpenChart(item);
              }}
              style={styles.chartIconButton}
            >
              <Ionicons name="bar-chart-outline" size={14} color="#9aa4b2" />
            </AnimatedPressable>
          </View>
        </View>
      </AnimatedPressable>
    </View>
  );
}

function BottomNavItem({ label, icon, active = false, onPress }: { label: string; icon: keyof typeof Ionicons.glyphMap; active?: boolean; onPress?: () => void }) {
  return (
    <AnimatedPressable onPress={onPress} style={styles.bottomNavItem}>
      <View style={[styles.bottomNavIconWrap, active ? styles.bottomNavIconWrapActive : null]}>
        <Ionicons name={icon} size={16} color={active ? "#ffffff" : "#9aa4b2"} />
      </View>
      <Text style={[styles.bottomNavLabel, active ? styles.bottomNavLabelActive : null]}>{label}</Text>
    </AnimatedPressable>
  );
}

function MarketSkeleton() {
  return (
    <View style={styles.rowOuter}>
      <View style={styles.rowCard}>
        <View style={styles.skeletonPulse}>
          <View style={styles.rowTop}>
            <View style={styles.skeletonIdentity}>
              <View style={styles.skeletonBadge} />
              <View>
                <View style={styles.skeletonWide} />
                <View style={styles.skeletonNarrow} />
              </View>
            </View>
            <View>
              <View style={styles.skeletonWide} />
              <View style={[styles.skeletonNarrow, { alignSelf: "flex-end" }]} />
            </View>
          </View>
          <View style={styles.skeletonBottomRow}>
            <View style={styles.skeletonSmallButton} />
            <View style={styles.skeletonPill} />
          </View>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  screen: { flex: 1, backgroundColor: "#0b0f18" },
  headerShell: {
    borderBottomWidth: 1,
    borderBottomColor: "rgba(255,255,255,0.1)",
    backgroundColor: "rgba(11,15,24,0.95)",
    paddingHorizontal: 16,
    paddingBottom: 12,
  },
  headerRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  headerTitle: { color: "#ffffff", fontFamily: fonts.display, fontSize: 22 },
  headerActions: { flexDirection: "row", alignItems: "center", gap: 8 },
  smallActionButton: {
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.15)",
    backgroundColor: "#0f1720",
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  smallActionText: { color: "#9aa4b2", fontFamily: fonts.semibold, fontSize: 11 },
  iconActionButton: {
    width: 34,
    height: 34,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.15)",
    backgroundColor: "#0f1720",
    alignItems: "center",
    justifyContent: "center",
  },
  topHeaderTabsRow: { gap: 20, paddingTop: 14, paddingBottom: 8 },
  topHeaderTabButton: { position: "relative", paddingBottom: 6 },
  topHeaderTabText: { color: "#9aa4b2", fontFamily: fonts.medium, fontSize: 14 },
  topHeaderTabTextActive: { color: "#ffffff" },
  topHeaderUnderline: { position: "absolute", left: 0, right: 0, bottom: 0, height: 2, borderRadius: 999, backgroundColor: colors.auric500 },
  marketTabsRow: { gap: 8, paddingTop: 4 },
  marketTabChip: {
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.15)",
    backgroundColor: "#0f1720",
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  marketTabChipActive: { borderColor: "transparent", backgroundColor: "#6b2cff" },
  marketTabChipText: { color: "#9aa4b2", fontFamily: fonts.medium, fontSize: 11 },
  marketTabChipTextActive: { color: "#ffffff" },
  offlineBanner: {
    marginHorizontal: 16,
    marginTop: 12,
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "rgba(255,185,0,0.3)",
    backgroundColor: "rgba(255,185,0,0.1)",
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  offlineBannerText: { color: "#ffde7a", fontFamily: fonts.body, fontSize: 11 },
  filterShell: {
    borderBottomWidth: 1,
    borderBottomColor: "rgba(255,255,255,0.1)",
    backgroundColor: "#0b0f18",
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 12,
  },
  searchWrap: { position: "relative" },
  searchIcon: { position: "absolute", left: 12, top: 13, zIndex: 1 },
  searchInput: {
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.15)",
    backgroundColor: "#0f1720",
    paddingVertical: 11,
    paddingLeft: 38,
    paddingRight: 12,
    color: "#ffffff",
    fontFamily: fonts.body,
    fontSize: 13,
  },
  quickChipRow: { gap: 8, paddingTop: 12, paddingBottom: 4, alignItems: "center" },
  quickChip: {
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.15)",
    backgroundColor: "#0f1720",
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  quickChipActive: { borderColor: "transparent", backgroundColor: "#6b2cff" },
  quickChipText: { color: "#9aa4b2", fontFamily: fonts.semibold, fontSize: 11 },
  quickChipTextActive: { color: "#ffffff" },
  favoritesChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 4,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.15)",
    backgroundColor: "#0f1720",
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  favoritesChipActive: { borderColor: "transparent", backgroundColor: "#6b2cff" },
  favoritesChipText: { color: "#9aa4b2", fontFamily: fonts.semibold, fontSize: 11 },
  favoritesChipTextActive: { color: "#ffffff" },
  sortRow: { flexDirection: "row", alignItems: "center", gap: 8, paddingTop: 12 },
  sortButton: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.15)",
    backgroundColor: "#0f1720",
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  sortButtonText: { color: "#ffffff", fontFamily: fonts.medium, fontSize: 11 },
  sortDirectionButton: {
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.15)",
    backgroundColor: "#0f1720",
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  sortDirectionText: { color: "#9aa4b2", fontFamily: fonts.medium, fontSize: 11 },
  listShell: { paddingTop: 8, paddingBottom: 8 },
  loadingBanner: { flexDirection: "row", alignItems: "center", gap: 8, marginHorizontal: 16, marginBottom: 8 },
  loadingBannerText: { color: "#9aa4b2", fontFamily: fonts.body, fontSize: 11 },
  rowOuter: { paddingHorizontal: 16, paddingVertical: 4 },
  rowCard: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.10)",
    backgroundColor: "#0f1720",
    padding: 12,
  },
  rowTop: { flexDirection: "row", alignItems: "flex-start", justifyContent: "space-between", gap: 12 },
  rowIdentity: { flexDirection: "row", alignItems: "center", gap: 8, flex: 1 },
  rowCoinBadge: { width: 36, height: 36, borderRadius: 18, backgroundColor: "#1b2740", alignItems: "center", justifyContent: "center" },
  rowCoinBadgeText: { color: "#ffb900", fontFamily: fonts.semibold, fontSize: 11 },
  rowIdentityCopy: { flex: 1 },
  rowPair: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 14 },
  rowVolume: { color: "#9aa4b2", fontFamily: fonts.body, fontSize: 11, marginTop: 2 },
  rowPrices: { alignItems: "flex-end" },
  rowPrice: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 18 },
  rowFiat: { color: "#9aa4b2", fontFamily: fonts.body, fontSize: 11, marginTop: 2 },
  rowBottom: { marginTop: 12, flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  rowLeftActions: { flexDirection: "row", alignItems: "center", gap: 6 },
  favoriteIconButton: { width: 24, height: 24, alignItems: "center", justifyContent: "center" },
  rowRightActions: { flexDirection: "row", alignItems: "center", gap: 8 },
  rowChangePill: {
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
    fontFamily: fonts.semibold,
    fontSize: 11,
    overflow: "hidden",
  },
  rowChangePositive: { color: "#6ef3a9", backgroundColor: "rgba(24,192,108,0.2)" },
  rowChangeNegative: { color: "#ff93a0", backgroundColor: "rgba(255,91,107,0.2)" },
  chartIconButton: { width: 24, height: 24, alignItems: "center", justifyContent: "center" },
  emptyState: {
    marginHorizontal: 16,
    marginTop: 8,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.10)",
    backgroundColor: "#0f1720",
    padding: 24,
  },
  emptyStateText: { color: "#9aa4b2", fontFamily: fonts.body, fontSize: 13, textAlign: "center" },
  bottomNavShell: { position: "absolute", left: 0, right: 0, bottom: 0, backgroundColor: "transparent" },
  bottomNav: {
    flexDirection: "row",
    borderTopWidth: 1,
    borderTopColor: "rgba(255,255,255,0.10)",
    backgroundColor: "rgba(15,23,32,0.95)",
    paddingHorizontal: 8,
    paddingTop: 8,
  },
  bottomNavItem: { flex: 1, alignItems: "center", gap: 4, paddingVertical: 6 },
  bottomNavIconWrap: { width: 28, height: 28, borderRadius: 14, backgroundColor: "#111d2b", alignItems: "center", justifyContent: "center" },
  bottomNavIconWrapActive: { backgroundColor: "#6b2cff" },
  bottomNavLabel: { color: "#9aa4b2", fontFamily: fonts.medium, fontSize: 10 },
  bottomNavLabelActive: { color: "#ffffff" },
  modalBackdrop: { flex: 1, justifyContent: "flex-end", backgroundColor: "rgba(0,0,0,0.60)", padding: 16 },
  tradeModal: { width: "100%", maxWidth: 380, alignSelf: "center", borderRadius: 18, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", backgroundColor: "#0f1720", padding: 16 },
  chartModal: { width: "100%", maxWidth: 380, alignSelf: "center", borderRadius: 18, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", backgroundColor: "#0f1720", padding: 16 },
  modalHeader: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 8 },
  modalTitle: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 16 },
  modalCloseButton: { width: 28, height: 28, borderRadius: 8, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", alignItems: "center", justifyContent: "center" },
  modalDescription: { color: "#9aa4b2", fontFamily: fonts.body, fontSize: 13, marginTop: 8 },
  modalActionRow: { flexDirection: "row", gap: 8, marginTop: 14 },
  modalPrimaryAction: { flex: 1, borderRadius: 10, backgroundColor: "#6b2cff", alignItems: "center", paddingVertical: 11 },
  modalPrimaryActionText: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 12 },
  modalBuyAction: { flex: 1, borderRadius: 10, borderWidth: 1, borderColor: "rgba(24,192,108,0.35)", backgroundColor: "rgba(24,192,108,0.10)", alignItems: "center", paddingVertical: 11 },
  modalSellAction: { flex: 1, borderRadius: 10, borderWidth: 1, borderColor: "rgba(255,91,107,0.35)", backgroundColor: "rgba(255,91,107,0.10)", alignItems: "center", paddingVertical: 11 },
  modalBuyText: { color: "#79f2b4", fontFamily: fonts.semibold, fontSize: 12 },
  modalSellText: { color: "#ff9aa8", fontFamily: fonts.semibold, fontSize: 12 },
  chartCanvas: { marginTop: 14, height: 144, borderRadius: 12, borderWidth: 1, borderColor: "rgba(255,255,255,0.10)", backgroundColor: "#111d2b", padding: 12 },
  chartBarsRow: { flex: 1, flexDirection: "row", alignItems: "flex-end", gap: 4 },
  chartBar: { flex: 1, borderTopLeftRadius: 999, borderTopRightRadius: 999, backgroundColor: colors.auric500 },
  skeletonPulse: { opacity: 0.7 },
  skeletonIdentity: { flexDirection: "row", alignItems: "center", gap: 8 },
  skeletonBadge: { width: 36, height: 36, borderRadius: 18, backgroundColor: "rgba(255,255,255,0.08)" },
  skeletonWide: { width: 82, height: 10, borderRadius: 999, backgroundColor: "rgba(255,255,255,0.08)" },
  skeletonNarrow: { width: 56, height: 8, borderRadius: 999, backgroundColor: "rgba(255,255,255,0.08)", marginTop: 6 },
  skeletonBottomRow: { marginTop: 12, flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  skeletonSmallButton: { width: 24, height: 24, borderRadius: 12, backgroundColor: "rgba(255,255,255,0.08)" },
  skeletonPill: { width: 72, height: 28, borderRadius: 999, backgroundColor: "rgba(255,255,255,0.08)" },
});

