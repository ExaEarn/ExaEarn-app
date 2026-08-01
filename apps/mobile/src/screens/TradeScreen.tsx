import { Ionicons } from "@expo/vector-icons";
import { useEffect, useMemo, useState } from "react";
import { Modal, ScrollView, StyleSheet, Text, TextInput, View, useWindowDimensions } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import { colors, fonts } from "../theme/colors";

type TradeScreenProps = { fontsReady: boolean; onBack: () => void };
type TradePair = { pair: string; base: string; quote: string; name: string; seedPrice: number; colorA: string };
type OrderBookEntry = { price: number; amount: number; depth: number };

const pairs: TradePair[] = [
  { pair: "XRP/USDT", base: "XRP", quote: "USDT", name: "XRP Ledger", seedPrice: 1.3572, colorA: "#3b82f6" },
  { pair: "BTC/USDT", base: "BTC", quote: "USDT", name: "Bitcoin", seedPrice: 68320.2, colorA: "#f59e0b" },
  { pair: "ETH/USDT", base: "ETH", quote: "USDT", name: "Ethereum", seedPrice: 1964.8, colorA: "#6366f1" },
  { pair: "SOL/USDT", base: "SOL", quote: "USDT", name: "Solana", seedPrice: 154.7, colorA: "#a855f7" },
];
const modes = ["Spot", "Margin", "Futures"] as const;
const orderTypes = ["Market", "Limit", "Stop-Limit"] as const;
const percentages = [25, 50, 75, 100] as const;
const chartBars = [28, 39, 44, 30, 52, 47, 62, 57, 50, 68, 61, 74];

type Mode = (typeof modes)[number];
type OrderType = (typeof orderTypes)[number];

function generateOrders(startPrice: number, isBid: boolean, tick: number): OrderBookEntry[] {
  return Array.from({ length: 8 }).map((_, index) => {
    const step = 0.0002 * (index + 1);
    const price = isBid ? startPrice - step : startPrice + step;
    const amount = 400 + (((index + 1) * 91 + tick * 13) % 680) + Math.random() * 8;
    return { price: Number(price.toFixed(4)), amount, depth: 18 + ((index * 12 + tick * 3) % 72) };
  });
}

function PairBadge({ pair, compact = false }: { pair: TradePair; compact?: boolean }) {
  return <View style={[styles.badge, compact ? styles.badgeCompact : null, { backgroundColor: pair.colorA }]}><Text style={[styles.badgeText, compact ? styles.badgeTextCompact : null]}>{pair.base[0]}</Text></View>;
}

function InfoCell({ label, value, positive = false }: { label: string; value: string; positive?: boolean }) {
  return <View style={styles.infoCell}><Text style={styles.infoLabel}>{label}</Text><Text style={[styles.infoValue, positive ? styles.infoPositive : null]}>{value}</Text></View>;
}

function BookRow({ item, side }: { item: OrderBookEntry; side: "buy" | "sell" }) {
  const isBuy = side === "buy";
  return (
    <View style={styles.bookRowShell}>
      <View style={[styles.bookRowBar, { width: `${Math.min(100, item.depth)}%`, backgroundColor: isBuy ? "rgba(24,192,108,0.2)" : "rgba(255,91,107,0.2)" }]} />
      <View style={styles.bookRowContent}>
        <Text style={[styles.bookPrice, isBuy ? styles.bookBuy : styles.bookSell]}>{item.price.toFixed(4)}</Text>
        <Text style={styles.bookAmount}>{item.amount.toFixed(3)}</Text>
      </View>
    </View>
  );
}

function BottomNavItem({ label, icon, active = false, onPress }: { label: string; icon: keyof typeof Ionicons.glyphMap; active?: boolean; onPress?: () => void }) {
  return <AnimatedPressable onPress={onPress} style={styles.bottomNavItem}><View style={[styles.bottomNavIconWrap, active ? styles.bottomNavIconWrapActive : null]}><Ionicons name={icon} size={16} color={active ? "#ffffff" : "#9aa4b2"} /></View><Text style={[styles.bottomNavLabel, active ? styles.bottomNavLabelActive : null]}>{label}</Text></AnimatedPressable>;
}

export default function TradeScreen({ fontsReady, onBack }: TradeScreenProps) {
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const [pair, setPair] = useState("XRP/USDT");
  const [showPairMenu, setShowPairMenu] = useState(false);
  const [mode, setMode] = useState<Mode>("Spot");
  const [leverage, setLeverage] = useState(20);
  const [marginType, setMarginType] = useState<"Cross" | "Isolated">("Cross");
  const [side, setSide] = useState<"buy" | "sell">("buy");
  const [orderType, setOrderType] = useState<OrderType>("Market");
  const [price, setPrice] = useState("1.3572");
  const [amount, setAmount] = useState("");
  const [selectedPct, setSelectedPct] = useState(0);
  const [showConfirm, setShowConfirm] = useState(false);
  const [showChartModal, setShowChartModal] = useState(false);
  const [pricePulse, setPricePulse] = useState(false);
  const [bookTick, setBookTick] = useState(0);
  const [midPrice, setMidPrice] = useState(1.3572);
  const [change24h, setChange24h] = useState(-2.6);
  const [availableBalance] = useState(3240.25);
  const selectedPair = useMemo(() => pairs.find((item) => item.pair === pair) || pairs[0], [pair]);
  const askOrders = useMemo(() => generateOrders(midPrice + 0.0006, false, bookTick), [midPrice, bookTick]);
  const bidOrders = useMemo(() => generateOrders(midPrice - 0.0006, true, bookTick), [midPrice, bookTick]);
  const twoColumnLayout = width >= 1100;
  const estimatedCost = useMemo(() => (parseFloat(price || "0") * parseFloat(amount || "0")) || 0, [price, amount]);
  const fee = useMemo(() => estimatedCost * 0.001, [estimatedCost]);
  const liquidationPrice = useMemo(() => {
    if (mode !== "Futures") return null;
    const numericPrice = parseFloat(price || "0");
    if (!numericPrice) return null;
    return numericPrice * (side === "buy" ? 0.88 : 1.12);
  }, [mode, side, price]);

  useEffect(() => {
    const timer = setInterval(() => {
      setMidPrice((previous) => Number(Math.max(0.0001, previous + (Math.random() - 0.5) * 0.004).toFixed(4)));
      setChange24h((previous) => Number((previous + (Math.random() - 0.5) * 0.16).toFixed(2)));
      setPricePulse(true);
      setTimeout(() => setPricePulse(false), 260);
      setBookTick((previous) => previous + 1);
    }, 1800);
    return () => clearInterval(timer);
  }, []);

  useEffect(() => {
    const nextPrice = selectedPair.seedPrice;
    setMidPrice(nextPrice);
    if (orderType === "Market") setPrice(nextPrice.toFixed(4));
  }, [selectedPair, orderType]);

  useEffect(() => {
    if (orderType === "Market") setPrice(midPrice.toFixed(4));
  }, [midPrice, orderType]);

  if (!fontsReady) return <View style={styles.fill} />;
  return (
    <View style={styles.screen}>
      <ScrollView contentContainerStyle={{ paddingBottom: insets.bottom + 92 }} showsVerticalScrollIndicator={false}>
        <View style={[styles.shell, { paddingTop: insets.top + 16 }]}> 
          <View style={styles.headerShell}>
            <View style={styles.headerRow}>
              <AnimatedPressable onPress={onBack} style={styles.backButton}><Text style={styles.backButtonText}>Back</Text></AnimatedPressable>
              <View style={styles.headerCenter}>
                <AnimatedPressable onPress={() => setShowPairMenu(true)} style={styles.pairSelectorButton}><PairBadge pair={selectedPair} compact /><Text style={styles.pairSelectorText}>{selectedPair.pair}</Text><Ionicons name="chevron-down-outline" size={15} color="#9aa4b2" /></AnimatedPressable>
              </View>
              <View style={styles.headerIconRow}>
                <AnimatedPressable style={styles.headerIconButton}><Ionicons name="analytics-outline" size={16} color="#9aa4b2" /></AnimatedPressable>
                <AnimatedPressable style={styles.headerIconButton}><Ionicons name="bar-chart-outline" size={16} color="#9aa4b2" /></AnimatedPressable>
                <AnimatedPressable style={styles.headerIconButton}><Ionicons name="options-outline" size={16} color="#9aa4b2" /></AnimatedPressable>
              </View>
            </View>
            <View style={styles.headerPriceRow}>
              <View>
                <Text style={[styles.livePriceText, pricePulse ? styles.livePricePulse : null]}>{midPrice.toFixed(4)}</Text>
                <Text style={[styles.changeText, change24h >= 0 ? styles.changePositive : styles.changeNegative]}>{change24h >= 0 ? "+" : ""}{change24h.toFixed(2)}% (24h)</Text>
              </View>
              <AnimatedPressable onPress={() => setShowChartModal(true)} style={styles.expandChartButton}><Text style={styles.expandChartButtonText}>Expand Chart</Text></AnimatedPressable>
            </View>
          </View>

          <View style={styles.chartCard}><View style={styles.chartCanvas}><View style={styles.chartBarsRow}>{chartBars.map((height, index) => <View key={index} style={[styles.chartBar, { height: `${height}%` }]} />)}</View></View></View>

          <View style={styles.modeCard}>
            <View style={styles.segmentShell}>{modes.map((item) => <AnimatedPressable key={item} onPress={() => setMode(item)} style={[styles.segmentButton, mode === item ? styles.segmentButtonActive : null]}><Text style={[styles.segmentButtonText, mode === item ? styles.segmentButtonTextActive : null]}>{item}</Text></AnimatedPressable>)}</View>
            {mode === "Futures" ? <View style={styles.futuresToolbar}><View style={styles.leverageRow}><AnimatedPressable onPress={() => setLeverage((value) => Math.max(1, value - 1))} style={styles.miniIconButton}><Text style={styles.miniIconButtonText}>-</Text></AnimatedPressable><View style={styles.leverageBadge}><Text style={styles.leverageBadgeText}>{leverage}x</Text></View><AnimatedPressable onPress={() => setLeverage((value) => Math.min(125, value + 1))} style={styles.miniIconButton}><Text style={styles.miniIconButtonText}>+</Text></AnimatedPressable></View><View style={styles.marginTypeShell}>{(["Cross", "Isolated"] as const).map((item) => <AnimatedPressable key={item} onPress={() => setMarginType(item)} style={[styles.marginTypeButton, marginType === item ? styles.marginTypeButtonActive : null]}><Text style={[styles.marginTypeText, marginType === item ? styles.marginTypeTextActive : null]}>{item}</Text></AnimatedPressable>)}</View></View> : null}
          </View>

          <View style={[styles.mainGrid, twoColumnLayout ? styles.mainGridDesktop : null]}>
            <View style={styles.leftColumn}>
              <View style={styles.orderCard}>
                <View style={styles.buySellShell}><AnimatedPressable onPress={() => setSide("buy")} style={[styles.buySellButton, side === "buy" ? styles.buySellButtonBuyActive : null]}><Text style={styles.buySellText}>Buy</Text></AnimatedPressable><AnimatedPressable onPress={() => setSide("sell")} style={[styles.buySellButton, side === "sell" ? styles.buySellButtonSellActive : null]}><Text style={styles.buySellText}>Sell</Text></AnimatedPressable></View>
                <View style={styles.fieldBlock}><Text style={styles.fieldLabel}>Order Type</Text><View style={styles.inlineOptionsWrap}>{orderTypes.map((item) => <AnimatedPressable key={item} onPress={() => setOrderType(item)} style={[styles.inlineOption, orderType === item ? styles.inlineOptionActive : null]}><Text style={[styles.inlineOptionText, orderType === item ? styles.inlineOptionTextActive : null]}>{item}</Text></AnimatedPressable>)}</View></View>
                <View style={styles.fieldBlock}><Text style={styles.fieldLabel}>Price</Text><TextInput value={price} onChangeText={setPrice} editable={orderType !== "Market"} style={[styles.tradeInput, orderType === "Market" ? styles.tradeInputDisabled : null]} placeholderTextColor="#64748b" /></View>
                <View style={styles.fieldBlock}><Text style={styles.fieldLabel}>Amount</Text><TextInput value={amount} onChangeText={setAmount} placeholder="0.00" placeholderTextColor="#64748b" style={styles.tradeInput} keyboardType="decimal-pad" /></View>
                <View style={styles.percentagesRow}>{percentages.map((percent) => <AnimatedPressable key={percent} onPress={() => { setSelectedPct(percent); const numericPrice = parseFloat(price || "0"); const maxAmount = numericPrice ? availableBalance / numericPrice : 0; setAmount(((maxAmount * percent) / 100).toFixed(4)); }} style={[styles.percentageButton, selectedPct === percent ? styles.percentageButtonActive : null]}><Text style={[styles.percentageButtonText, selectedPct === percent ? styles.percentageButtonTextActive : null]}>{percent}%</Text></AnimatedPressable>)}</View>
                <View style={styles.summaryCard}><Text style={styles.summaryLine}>Available Balance: {availableBalance.toFixed(2)} USDT</Text><Text style={styles.summaryLine}>Estimated Cost: {estimatedCost.toFixed(4)} USDT</Text><Text style={styles.summaryLine}>Fee Preview: {fee.toFixed(4)} USDT</Text>{liquidationPrice ? <Text style={styles.summaryWarning}>Liquidation Price: {liquidationPrice.toFixed(4)}</Text> : null}</View>
                <AnimatedPressable onPress={() => setShowConfirm(true)} style={[styles.submitButton, side === "buy" ? styles.submitButtonBuy : styles.submitButtonSell]}><Text style={[styles.submitButtonText, side === "buy" ? styles.submitButtonTextBuy : styles.submitButtonTextSell]}>{side === "buy" ? "Buy / Long" : "Sell / Short"}</Text></AnimatedPressable>
              </View>

              <View style={styles.positionCard}><Text style={styles.sectionTitle}>Open Position</Text><View style={styles.positionGrid}><InfoCell label="Entry Price" value="1.3400" /><InfoCell label="Mark Price" value={midPrice.toFixed(4)} /><InfoCell label="Unrealized PNL" value="+43.21 USDT" positive /><InfoCell label="Margin Used" value="122.40 USDT" /><InfoCell label="Liquidation" value="1.1750" /><AnimatedPressable style={styles.closePositionButton}><Text style={styles.closePositionButtonText}>Close Position</Text></AnimatedPressable></View></View>
            </View>
            <View style={styles.rightColumn}><View style={styles.orderBookCard}><Text style={styles.sectionTitle}>Order Book</Text><View style={styles.orderBookList}>{askOrders.map((item, index) => <BookRow key={`ask-${index}`} item={item} side="sell" />)}</View><View style={styles.midPricePill}><Text style={styles.midPricePillText}>{midPrice.toFixed(4)}</Text></View><View style={styles.orderBookList}>{bidOrders.map((item, index) => <BookRow key={`bid-${index}`} item={item} side="buy" />)}</View></View></View>
          </View>

          <Text style={styles.riskText}>Trading involves risk. Ensure you understand leverage and liquidation before placing an order.</Text>
        </View>
      </ScrollView>

      <View style={[styles.bottomNavShell, { paddingBottom: insets.bottom }]}><View style={styles.bottomNav}><BottomNavItem label="Home" icon="home-outline" onPress={onBack} /><BottomNavItem label="Markets" icon="bar-chart-outline" /><BottomNavItem label="Trade" icon="diamond-outline" active /><BottomNavItem label="Futures" icon="stats-chart-outline" /><BottomNavItem label="Assets" icon="wallet-outline" /></View></View>

      <Modal visible={showPairMenu} transparent animationType="fade" onRequestClose={() => setShowPairMenu(false)}><View style={styles.modalBackdrop}><View style={styles.modalCard}><View style={styles.modalHeaderRow}><Text style={styles.modalTitle}>Select Pair</Text><AnimatedPressable onPress={() => setShowPairMenu(false)} style={styles.modalCloseButton}><Ionicons name="close-outline" size={16} color="#9aa4b2" /></AnimatedPressable></View><View style={styles.pairMenuList}>{pairs.map((item) => <AnimatedPressable key={item.pair} onPress={() => { setPair(item.pair); setShowPairMenu(false); }} style={[styles.pairMenuItem, item.pair === pair ? styles.pairMenuItemActive : null]}><View style={styles.pairMenuIdentity}><PairBadge pair={item} /><View><Text style={styles.pairMenuPair}>{item.pair}</Text><Text style={styles.pairMenuName}>{item.name}</Text></View></View><Text style={styles.pairMenuBase}>{item.base}</Text></AnimatedPressable>)}</View></View></View></Modal>
      <Modal visible={showConfirm} transparent animationType="fade" onRequestClose={() => setShowConfirm(false)}><View style={styles.modalBackdrop}><View style={styles.confirmCard}><Text style={styles.modalTitle}>Confirm Order</Text><View style={styles.confirmLines}><Text style={styles.confirmLine}>Pair: {pair}</Text><Text style={styles.confirmLine}>Side: {side === "buy" ? "Buy / Long" : "Sell / Short"}</Text><Text style={styles.confirmLine}>Type: {orderType}</Text><Text style={styles.confirmLine}>Price: {price}</Text><Text style={styles.confirmLine}>Amount: {amount || "0"}</Text><Text style={styles.confirmLine}>Fee: {fee.toFixed(4)} USDT</Text><Text style={styles.confirmTotal}>Total: {(estimatedCost + fee).toFixed(4)} USDT</Text></View><View style={styles.confirmActions}><AnimatedPressable onPress={() => setShowConfirm(false)} style={styles.confirmCancelButton}><Text style={styles.confirmCancelText}>Cancel</Text></AnimatedPressable><AnimatedPressable onPress={() => setShowConfirm(false)} style={styles.confirmPrimaryButton}><Text style={styles.confirmPrimaryText}>Confirm</Text></AnimatedPressable></View></View></View></Modal>
      <Modal visible={showChartModal} transparent animationType="fade" onRequestClose={() => setShowChartModal(false)}><View style={styles.chartModalBackdrop}><View style={styles.chartModalCard}><View style={styles.modalHeaderRow}><Text style={styles.modalTitle}>{pair} Advanced Chart</Text><AnimatedPressable onPress={() => setShowChartModal(false)} style={styles.modalCloseButton}><Text style={styles.chartCloseText}>Close</Text></AnimatedPressable></View><View style={styles.advancedChartCanvas}><View style={styles.advancedBarsRow}>{Array.from({ length: 42 }).map((_, index) => { const height = 18 + ((index * 19 + bookTick * 7) % 66); return <View key={index} style={[styles.advancedChartBar, { height: `${height}%` }]} />; })}</View></View></View></View></Modal>
    </View>
  );
}
const styles = StyleSheet.create({
  fill: { flex: 1 },
  screen: { flex: 1, backgroundColor: "#0B0F1A" },
  shell: { width: "100%", maxWidth: 1200, alignSelf: "center", paddingHorizontal: 16 },
  headerShell: { borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.10)", backgroundColor: "rgba(11,15,26,0.95)", paddingBottom: 12 },
  headerRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 8 },
  backButton: { borderRadius: 10, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", backgroundColor: "#111827", paddingHorizontal: 10, paddingVertical: 7 },
  backButtonText: { color: "#9aa4b2", fontFamily: fonts.medium, fontSize: 11 },
  headerCenter: { flex: 1, alignItems: "center" },
  pairSelectorButton: { flexDirection: "row", alignItems: "center", gap: 8, borderRadius: 10, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", backgroundColor: "#111827", paddingHorizontal: 10, paddingVertical: 7 },
  pairSelectorText: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 13 },
  headerIconRow: { flexDirection: "row", alignItems: "center", gap: 6 },
  headerIconButton: { width: 34, height: 34, borderRadius: 10, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", backgroundColor: "#111827", alignItems: "center", justifyContent: "center" },
  headerPriceRow: { marginTop: 14, flexDirection: "row", alignItems: "flex-end", justifyContent: "space-between", gap: 12 },
  livePriceText: { color: "#ffffff", fontFamily: fonts.display, fontSize: 28 },
  livePricePulse: { textShadowColor: "rgba(255,185,0,0.35)", textShadowOffset: { width: 0, height: 0 }, textShadowRadius: 12 },
  changeText: { marginTop: 3, fontFamily: fonts.medium, fontSize: 12 },
  changePositive: { color: "#18c06c" },
  changeNegative: { color: "#ff5b6b" },
  expandChartButton: { borderRadius: 10, backgroundColor: "#6b2cff", paddingHorizontal: 12, paddingVertical: 8 },
  expandChartButtonText: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 11 },
  chartCard: { marginTop: 16, borderRadius: 18, borderWidth: 1, borderColor: "rgba(255,255,255,0.10)", backgroundColor: "#111827", padding: 12 },
  chartCanvas: { height: 110, borderRadius: 14, backgroundColor: "#0d1424", padding: 10 },
  chartBarsRow: { flex: 1, flexDirection: "row", alignItems: "flex-end", gap: 4 },
  chartBar: { flex: 1, borderTopLeftRadius: 999, borderTopRightRadius: 999, backgroundColor: colors.auric500 },
  modeCard: { marginTop: 16, borderRadius: 18, borderWidth: 1, borderColor: "rgba(255,255,255,0.10)", backgroundColor: "#111827", padding: 12 },
  segmentShell: { flexDirection: "row", gap: 8, borderRadius: 14, backgroundColor: "#0d1424", padding: 6 },
  segmentButton: { flex: 1, borderRadius: 12, paddingVertical: 10, alignItems: "center", justifyContent: "center" },
  segmentButtonActive: { backgroundColor: "#6b2cff" },
  segmentButtonText: { color: "#9aa4b2", fontFamily: fonts.semibold, fontSize: 12 },
  segmentButtonTextActive: { color: "#ffffff" },
  futuresToolbar: { marginTop: 12, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 10, flexWrap: "wrap" },
  leverageRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  miniIconButton: { width: 28, height: 28, borderRadius: 8, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", backgroundColor: "#0d1424", alignItems: "center", justifyContent: "center" },
  miniIconButtonText: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 13 },
  leverageBadge: { borderRadius: 10, backgroundColor: "#0d1424", paddingHorizontal: 12, paddingVertical: 7 },
  leverageBadgeText: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 12 },
  marginTypeShell: { flexDirection: "row", gap: 6, borderRadius: 12, backgroundColor: "#0d1424", padding: 4 },
  marginTypeButton: { borderRadius: 9, paddingHorizontal: 12, paddingVertical: 7 },
  marginTypeButtonActive: { backgroundColor: "rgba(107,44,255,0.30)" },
  marginTypeText: { color: "#9aa4b2", fontFamily: fonts.medium, fontSize: 11 },
  marginTypeTextActive: { color: "#ffffff" },
  mainGrid: { marginTop: 16, gap: 16 },
  mainGridDesktop: { flexDirection: "row", alignItems: "flex-start" },
  leftColumn: { flex: 1.12, gap: 16 },
  rightColumn: { flex: 0.88 },
  orderCard: { borderRadius: 18, borderWidth: 1, borderColor: "rgba(255,255,255,0.10)", backgroundColor: "#111827", padding: 12 },
  buySellShell: { flexDirection: "row", gap: 8, borderRadius: 14, backgroundColor: "#0d1424", padding: 6 },
  buySellButton: { flex: 1, borderRadius: 12, paddingVertical: 11, alignItems: "center" },
  buySellButtonBuyActive: { backgroundColor: "#18c06c" },
  buySellButtonSellActive: { backgroundColor: "#ff5b6b" },
  buySellText: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 14 },
  fieldBlock: { marginTop: 14 },
  fieldLabel: { marginBottom: 6, color: "#9aa4b2", fontFamily: fonts.medium, fontSize: 11 },
  inlineOptionsWrap: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  inlineOption: { borderRadius: 12, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", backgroundColor: "#0d1424", paddingHorizontal: 12, paddingVertical: 9 },
  inlineOptionActive: { borderColor: "transparent", backgroundColor: "#6b2cff" },
  inlineOptionText: { color: "#9aa4b2", fontFamily: fonts.medium, fontSize: 12 },
  inlineOptionTextActive: { color: "#ffffff" },
  tradeInput: { borderRadius: 14, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", backgroundColor: "#0d1424", paddingHorizontal: 14, paddingVertical: 12, color: "#ffffff", fontFamily: fonts.body, fontSize: 14 },
  tradeInputDisabled: { opacity: 0.72 },
  percentagesRow: { marginTop: 14, flexDirection: "row", gap: 8 },
  percentageButton: { flex: 1, borderRadius: 12, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", backgroundColor: "#0d1424", paddingVertical: 9, alignItems: "center" },
  percentageButtonActive: { borderColor: "transparent", backgroundColor: "#6b2cff" },
  percentageButtonText: { color: "#9aa4b2", fontFamily: fonts.semibold, fontSize: 11 },
  percentageButtonTextActive: { color: "#ffffff" },
  summaryCard: { marginTop: 14, borderRadius: 14, borderWidth: 1, borderColor: "rgba(255,255,255,0.10)", backgroundColor: "#0d1424", padding: 12, gap: 4 },
  summaryLine: { color: "#9aa4b2", fontFamily: fonts.body, fontSize: 12 },
  summaryWarning: { color: "#ffb900", fontFamily: fonts.medium, fontSize: 12, marginTop: 2 },
  submitButton: { marginTop: 16, borderRadius: 14, paddingVertical: 14, alignItems: "center" },
  submitButtonBuy: { backgroundColor: "#18c06c" },
  submitButtonSell: { backgroundColor: "#ff5b6b" },
  submitButtonText: { fontFamily: fonts.semibold, fontSize: 14 },
  submitButtonTextBuy: { color: "#0B0F1A" },
  submitButtonTextSell: { color: "#ffffff" },
  positionCard: { borderRadius: 18, borderWidth: 1, borderColor: "rgba(255,255,255,0.10)", backgroundColor: "#111827", padding: 12 },
  sectionTitle: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 14 },
  positionGrid: { marginTop: 10, flexDirection: "row", flexWrap: "wrap", gap: 10 },
  infoCell: { minWidth: 140, flexGrow: 1, borderRadius: 12, borderWidth: 1, borderColor: "rgba(255,255,255,0.10)", backgroundColor: "#0d1424", paddingHorizontal: 10, paddingVertical: 9 },
  infoLabel: { color: "#9aa4b2", fontFamily: fonts.body, fontSize: 11 },
  infoValue: { marginTop: 3, color: "#ffffff", fontFamily: fonts.semibold, fontSize: 12 },
  infoPositive: { color: "#18c06c" },
  closePositionButton: { minWidth: 140, flexGrow: 1, borderRadius: 12, borderWidth: 1, borderColor: "rgba(255,91,107,0.35)", backgroundColor: "rgba(255,91,107,0.15)", alignItems: "center", justifyContent: "center", paddingVertical: 11 },
  closePositionButtonText: { color: "#ff9aa8", fontFamily: fonts.semibold, fontSize: 12 },
  orderBookCard: { borderRadius: 18, borderWidth: 1, borderColor: "rgba(255,255,255,0.10)", backgroundColor: "#111827", padding: 12 },
  orderBookList: { marginTop: 10, gap: 6 },
  bookRowShell: { overflow: "hidden", borderRadius: 10, borderWidth: 1, borderColor: "rgba(255,255,255,0.05)", paddingHorizontal: 10, paddingVertical: 8 },
  bookRowBar: { position: "absolute", right: 0, top: 0, bottom: 0 },
  bookRowContent: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", zIndex: 1 },
  bookPrice: { fontFamily: fonts.medium, fontSize: 12 },
  bookBuy: { color: "#18c06c" },
  bookSell: { color: "#ff7b89" },
  bookAmount: { color: "#9aa4b2", fontFamily: fonts.body, fontSize: 12 },
  midPricePill: { marginVertical: 10, borderRadius: 12, borderWidth: 1, borderColor: "rgba(255,185,0,0.30)", backgroundColor: "rgba(255,185,0,0.10)", paddingVertical: 8, alignItems: "center" },
  midPricePillText: { color: "#ffde7a", fontFamily: fonts.semibold, fontSize: 14 },
  riskText: { marginTop: 16, color: "#9aa4b2", fontFamily: fonts.body, fontSize: 12, textAlign: "center" },
  badge: { width: 24, height: 24, borderRadius: 12, alignItems: "center", justifyContent: "center" },
  badgeCompact: { width: 20, height: 20, borderRadius: 10 },
  badgeText: { color: "#0B0F1A", fontFamily: fonts.semibold, fontSize: 11 },
  badgeTextCompact: { fontSize: 10 },
  bottomNavShell: { position: "absolute", left: 0, right: 0, bottom: 0 },
  bottomNav: { flexDirection: "row", borderTopWidth: 1, borderTopColor: "rgba(255,255,255,0.10)", backgroundColor: "rgba(17,24,39,0.96)", paddingHorizontal: 8, paddingTop: 8 },
  bottomNavItem: { flex: 1, alignItems: "center", gap: 4, paddingVertical: 6 },
  bottomNavIconWrap: { width: 28, height: 28, borderRadius: 14, backgroundColor: "#0d1424", alignItems: "center", justifyContent: "center" },
  bottomNavIconWrapActive: { backgroundColor: "#6b2cff" },
  bottomNavLabel: { color: "#9aa4b2", fontFamily: fonts.medium, fontSize: 10 },
  bottomNavLabelActive: { color: "#ffffff" },
  modalBackdrop: { flex: 1, justifyContent: "center", backgroundColor: "rgba(0,0,0,0.62)", padding: 16 },
  chartModalBackdrop: { flex: 1, justifyContent: "center", backgroundColor: "rgba(0,0,0,0.72)", padding: 12 },
  modalCard: { width: "100%", maxWidth: 420, alignSelf: "center", borderRadius: 18, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", backgroundColor: "#111827", padding: 16 },
  confirmCard: { width: "100%", maxWidth: 380, alignSelf: "center", borderRadius: 18, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", backgroundColor: "#111827", padding: 16 },
  chartModalCard: { width: "100%", maxWidth: 980, height: "78%", alignSelf: "center", borderRadius: 18, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", backgroundColor: "#111827", padding: 16 },
  modalHeaderRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 8 },
  modalTitle: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 16 },
  modalCloseButton: { minWidth: 32, height: 32, borderRadius: 8, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", alignItems: "center", justifyContent: "center", paddingHorizontal: 10 },
  chartCloseText: { color: "#9aa4b2", fontFamily: fonts.medium, fontSize: 11 },
  pairMenuList: { marginTop: 12, gap: 8 },
  pairMenuItem: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", borderRadius: 12, paddingHorizontal: 10, paddingVertical: 10 },
  pairMenuItemActive: { backgroundColor: "rgba(107,44,255,0.18)" },
  pairMenuIdentity: { flexDirection: "row", alignItems: "center", gap: 10 },
  pairMenuPair: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 13 },
  pairMenuName: { color: "#9aa4b2", fontFamily: fonts.body, fontSize: 11, marginTop: 2 },
  pairMenuBase: { color: "#9aa4b2", fontFamily: fonts.medium, fontSize: 11 },
  confirmLines: { marginTop: 12, gap: 4 },
  confirmLine: { color: "#9aa4b2", fontFamily: fonts.body, fontSize: 13 },
  confirmTotal: { color: "#ffde7a", fontFamily: fonts.semibold, fontSize: 13, marginTop: 4 },
  confirmActions: { marginTop: 16, flexDirection: "row", gap: 8 },
  confirmCancelButton: { flex: 1, borderRadius: 12, borderWidth: 1, borderColor: "rgba(255,255,255,0.15)", backgroundColor: "#0d1424", alignItems: "center", paddingVertical: 12 },
  confirmCancelText: { color: "#ffffff", fontFamily: fonts.medium, fontSize: 13 },
  confirmPrimaryButton: { flex: 1, borderRadius: 12, backgroundColor: "#6b2cff", alignItems: "center", paddingVertical: 12 },
  confirmPrimaryText: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 13 },
  advancedChartCanvas: { marginTop: 14, flex: 1, borderRadius: 14, borderWidth: 1, borderColor: "rgba(255,255,255,0.10)", backgroundColor: "#0d1424", padding: 12 },
  advancedBarsRow: { flex: 1, flexDirection: "row", alignItems: "flex-end", gap: 3 },
  advancedChartBar: { flex: 1, borderTopLeftRadius: 999, borderTopRightRadius: 999, backgroundColor: colors.auric500 },
});
