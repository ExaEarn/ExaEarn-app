import { Ionicons } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { useEffect, useMemo, useState } from "react";
import type { ReactNode } from "react";
import { ActivityIndicator, AppState, ScrollView, StyleSheet, Text, TextInput, View, useWindowDimensions } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import { useAuth } from "../context/AuthContext";
import {
  ExaCard,
  ExaCardActivity,
  ExaCardProduct,
  ExaCardQuote,
  fetchExaCardProducts,
  fetchExaCardRealtimeReplay,
  fetchExaCardTransactions,
  fetchExaCards,
  fundExaCard,
  issueExaCard,
  quoteExaCardFunding,
  unloadExaCard,
  updateExaCardControls,
} from "../services/exaCardApi";
import { colors, fonts } from "../theme/colors";

type ExaCardScreenProps = {
  fontsReady: boolean;
  onBack: () => void;
};

function money(value: unknown, currency = "USD") {
  const numeric = Number(value || 0);
  return `${currency} ${Number.isFinite(numeric) ? numeric.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "0.00"}`;
}

export default function ExaCardScreen({ fontsReady, onBack }: ExaCardScreenProps) {
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const { request } = useAuth();
  const [products, setProducts] = useState<ExaCardProduct[]>([]);
  const [provider, setProvider] = useState<Record<string, unknown> | null>(null);
  const [cards, setCards] = useState<ExaCard[]>([]);
  const [selectedProduct, setSelectedProduct] = useState("USD_VIRTUAL");
  const [selectedCardUuid, setSelectedCardUuid] = useState("");
  const [sourceAsset, setSourceAsset] = useState("USD");
  const [amount, setAmount] = useState("50");
  const [unloadAmount, setUnloadAmount] = useState("25");
  const [quote, setQuote] = useState<ExaCardQuote | null>(null);
  const [activity, setActivity] = useState<ExaCardActivity[]>([]);
  const [busy, setBusy] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [realtime, setRealtime] = useState({ status: "connecting", sequence: 0 });

  const selectedCard = useMemo(() => cards.find((card) => card.card_uuid === selectedCardUuid) ?? cards[0], [cards, selectedCardUuid]);
  const shellWidth = Math.min(width - 16, width >= 1024 ? 460 : 430);

  async function load() {
    setBusy("loading");
    setError("");
    try {
      const [productPayload, cardPayload] = await Promise.all([fetchExaCardProducts(request), fetchExaCards(request)]);
      setProducts(productPayload.products ?? []);
      setProvider(productPayload.provider ?? null);
      setCards(cardPayload);
      if (!selectedCardUuid && cardPayload[0]) setSelectedCardUuid(cardPayload[0].card_uuid);
    } catch (loadError) {
      setError(loadError instanceof Error ? loadError.message : "Unable to load ExaCard.");
    } finally {
      setBusy("");
    }
  }

  useEffect(() => {
    load();
  }, []);

  useEffect(() => {
    let active = true;
    let timer: ReturnType<typeof setTimeout> | undefined;

    async function poll() {
      try {
        const replay = await fetchExaCardRealtimeReplay(request, realtime.sequence);
        if (!active) return;
        const events = replay.events ?? [];
        if (replay.reconcile_required) {
          await load();
        }
        events.forEach((event) => {
          const card = event.payload?.card;
          if (card?.card_uuid) {
            setCards((prev) => {
              const exists = prev.some((item) => item.card_uuid === card.card_uuid);
              return exists ? prev.map((item) => (item.card_uuid === card.card_uuid ? card : item)) : [card, ...prev];
            });
            setSelectedCardUuid((current) => current || card.card_uuid);
          }
        });
        setRealtime({ status: replay.reconcile_required ? "reconciled" : "live", sequence: replay.latest_sequence ?? realtime.sequence });
      } catch {
        if (active) setRealtime((current) => ({ ...current, status: "degraded" }));
      } finally {
        if (active) timer = setTimeout(poll, 12000);
      }
    }

    poll();
    const subscription = AppState.addEventListener("change", (state) => {
      if (state === "active") {
        load();
        poll();
      }
    });

    return () => {
      active = false;
      if (timer) clearTimeout(timer);
      subscription.remove();
    };
  }, [request, realtime.sequence]);

  useEffect(() => {
    if (!selectedCard?.card_uuid) {
      setActivity([]);
      return;
    }
    let mounted = true;
    fetchExaCardTransactions(request, selectedCard.card_uuid)
      .then((rows) => {
        if (mounted) setActivity(rows);
      })
      .catch(() => {
        if (mounted) setActivity([]);
      });
    return () => {
      mounted = false;
    };
  }, [request, selectedCard?.card_uuid]);

  async function run(name: string, action: () => Promise<void>, success: string) {
    setBusy(name);
    setMessage("");
    setError("");
    try {
      await action();
      setMessage(success);
      await load();
    } catch (actionError) {
      setError(actionError instanceof Error ? actionError.message : "Card action failed.");
    } finally {
      setBusy("");
    }
  }

  return (
    <LinearGradient colors={[colors.cosmic950, "#090b12", colors.cosmic900]} style={styles.fill}>
      <ScrollView contentContainerStyle={[styles.scroll, { paddingTop: insets.top + 10, paddingBottom: insets.bottom + 28 }]} showsVerticalScrollIndicator={false}>
        <View style={[styles.shell, { width: shellWidth }]}>
          <View style={styles.hero}>
            <AnimatedPressable onPress={onBack} style={styles.back}>
              <Ionicons name="chevron-back" size={17} color={colors.auric300} />
              <Text style={styles.backText}>Back</Text>
            </AnimatedPressable>
            <Text style={styles.eyebrow}>ExaCard</Text>
            <Text style={[styles.title, !fontsReady ? { fontFamily: undefined } : null]}>Spend from ExaEarn, safely</Text>
            <Text style={styles.subtitle}>Issue virtual cards, fund through the canonical ledger, control usage, and review provider activity.</Text>
            <View style={styles.providerPill}>
              <Ionicons name="shield-checkmark-outline" size={14} color="#86efac" />
              <Text style={styles.providerText}>{String(provider?.status ?? "Loading")} provider mode</Text>
            </View>
            <View style={[styles.realtimePill, realtime.status === "degraded" ? styles.realtimePillWarn : null]}>
              <Ionicons name={realtime.status === "degraded" ? "warning-outline" : "radio-outline"} size={14} color={realtime.status === "degraded" ? "#fde68a" : "#86efac"} />
              <Text style={styles.providerText}>Realtime {realtime.status} - seq {realtime.sequence}</Text>
            </View>
          </View>

          <Panel title="Card products">
            <View style={styles.productGrid}>
              {products.map((product) => {
                const selected = selectedProduct === product.product_code;
                return (
                  <AnimatedPressable key={product.product_code} onPress={() => setSelectedProduct(product.product_code)} style={[styles.product, selected ? styles.productActive : null]}>
                    <Ionicons name="card-outline" size={20} color={selected ? colors.auric300 : colors.muted} />
                    <Text style={styles.productTitle}>{product.product_code.replace("_", " ")}</Text>
                    <Text style={styles.productMeta}>{product.currency} {product.type}</Text>
                  </AnimatedPressable>
                );
              })}
            </View>
            <AnimatedPressable onPress={() => run("issue", () => issueExaCard(request, selectedProduct).then(() => undefined), "Card issued successfully.")} style={[styles.primary, busy === "issue" ? styles.dim : null]}>
              {busy === "issue" ? <ActivityIndicator color={colors.cosmic950} /> : <Text style={styles.primaryText}>Issue selected card</Text>}
            </AnimatedPressable>
          </Panel>

          <Panel title="Your cards">
            {cards.length ? cards.map((card) => (
              <AnimatedPressable key={card.card_uuid} onPress={() => setSelectedCardUuid(card.card_uuid)} style={[styles.cardRow, selectedCard?.card_uuid === card.card_uuid ? styles.cardRowActive : null]}>
                <View style={styles.cardIcon}>
                  <Ionicons name="card" size={20} color={colors.auric300} />
                </View>
                <View style={styles.flex}>
                  <Text style={styles.cardTitle}>{card.network || "CARD"} **** {card.last_four || "----"}</Text>
                  <Text style={styles.muted}>{money(card.balance?.available, card.currency)} available</Text>
                </View>
                <Text style={styles.status}>{card.status}</Text>
              </AnimatedPressable>
            )) : <Text style={styles.muted}>No ExaCard has been issued yet.</Text>}
          </Panel>

          <Panel title="Fund card">
            <TextInput value={sourceAsset} onChangeText={(text) => setSourceAsset(text.toUpperCase())} style={styles.input} placeholder="Source asset" placeholderTextColor="rgba(245,240,255,0.38)" />
            <TextInput value={amount} onChangeText={setAmount} keyboardType="decimal-pad" style={styles.input} placeholder="Amount" placeholderTextColor="rgba(245,240,255,0.38)" />
            <View style={styles.row}>
              <AnimatedPressable disabled={!selectedCard} onPress={() => selectedCard ? run("quote", async () => setQuote(await quoteExaCardFunding(request, selectedCard.card_uuid, sourceAsset, amount)), "Funding quote created.") : undefined} style={[styles.secondary, !selectedCard ? styles.dim : null]}>
                <Text style={styles.secondaryText}>Create quote</Text>
              </AnimatedPressable>
              <AnimatedPressable disabled={!quote} onPress={() => quote ? run("fund", async () => { await fundExaCard(request, quote.quote_uuid); setQuote(null); }, "Funding submitted.") : undefined} style={[styles.primarySmall, !quote ? styles.dim : null]}>
                <Text style={styles.primaryText}>Confirm</Text>
              </AnimatedPressable>
            </View>
            {quote ? (
              <View style={styles.summary}>
                <SummaryRow label="Card receives" value={money(quote.card_amount, quote.card_currency)} />
                <SummaryRow label="Total debit" value={money(quote.total_debit, quote.source_asset)} />
              </View>
            ) : null}
          </Panel>

          <Panel title="Controls and unload">
            <View style={styles.row}>
              <AnimatedPressable disabled={!selectedCard} onPress={() => selectedCard ? run("online", () => updateExaCardControls(request, selectedCard.card_uuid, { online: !selectedCard.controls?.online }).then(() => undefined), "Card controls updated.") : undefined} style={styles.secondary}>
                <Text style={styles.secondaryText}>Online {selectedCard?.controls?.online ? "On" : "Off"}</Text>
              </AnimatedPressable>
              <AnimatedPressable disabled={!selectedCard} onPress={() => selectedCard ? run("freeze", () => updateExaCardControls(request, selectedCard.card_uuid, { international: false }).then(() => undefined), "International use disabled.") : undefined} style={styles.secondary}>
                <Text style={styles.secondaryText}>Restrict intl</Text>
              </AnimatedPressable>
            </View>
            <TextInput value={unloadAmount} onChangeText={setUnloadAmount} keyboardType="decimal-pad" style={styles.input} placeholder="Unload amount" placeholderTextColor="rgba(245,240,255,0.38)" />
            <AnimatedPressable disabled={!selectedCard} onPress={() => selectedCard ? run("unload", () => unloadExaCard(request, selectedCard.card_uuid, unloadAmount).then(() => undefined), "Unload submitted.") : undefined} style={[styles.primary, !selectedCard ? styles.dim : null]}>
              <Text style={styles.primaryText}>Unload to funding wallet</Text>
            </AnimatedPressable>
          </Panel>

          <Panel title="Recent activity">
            {activity.length ? activity.slice(0, 8).map((item) => (
              <View key={item.transaction_uuid || item.authorization_uuid} style={styles.activityRow}>
                <View>
                  <Text style={styles.cardTitle}>{item.merchant || item.type || "Card activity"}</Text>
                  <Text style={styles.muted}>{item.status}</Text>
                </View>
                <Text style={styles.value}>{money(item.billing_amount || item.amount, item.billing_currency || item.currency)}</Text>
              </View>
            )) : <Text style={styles.muted}>Transactions will appear after provider webhooks are processed.</Text>}
          </Panel>

          {message ? <Text style={styles.success}>{message}</Text> : null}
          {error ? <Text style={styles.error}>{error}</Text> : null}
          {busy === "loading" ? <ActivityIndicator color={colors.auric300} /> : null}
        </View>
      </ScrollView>
    </LinearGradient>
  );
}

function Panel({ title, children }: { title: string; children: ReactNode }) {
  return (
    <View style={styles.panel}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {children}
    </View>
  );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.summaryRow}>
      <Text style={styles.muted}>{label}</Text>
      <Text style={styles.value}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  scroll: { alignItems: "center", paddingHorizontal: 8 },
  shell: { gap: 10 },
  hero: { borderRadius: 26, borderWidth: 1, borderColor: "rgba(249,226,173,0.2)", backgroundColor: "rgba(7,10,18,0.92)", padding: 16 },
  back: { alignSelf: "flex-start", flexDirection: "row", alignItems: "center", gap: 6, borderRadius: 999, borderWidth: 1, borderColor: "rgba(255,255,255,0.1)", paddingHorizontal: 11, paddingVertical: 7 },
  backText: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 11 },
  eyebrow: { marginTop: 14, color: "rgba(249,226,173,0.8)", fontFamily: fonts.semibold, fontSize: 10, letterSpacing: 1.6, textTransform: "uppercase" },
  title: { marginTop: 4, color: colors.violetText, fontFamily: fonts.display, fontSize: 28, lineHeight: 33 },
  subtitle: { marginTop: 8, color: colors.muted, fontFamily: fonts.body, fontSize: 13, lineHeight: 20 },
  providerPill: { marginTop: 14, alignSelf: "flex-start", flexDirection: "row", alignItems: "center", gap: 6, borderRadius: 999, borderWidth: 1, borderColor: "rgba(134,239,172,0.22)", backgroundColor: "rgba(134,239,172,0.08)", paddingHorizontal: 10, paddingVertical: 6 },
  realtimePill: { marginTop: 8, alignSelf: "flex-start", flexDirection: "row", alignItems: "center", gap: 6, borderRadius: 999, borderWidth: 1, borderColor: "rgba(134,239,172,0.22)", backgroundColor: "rgba(134,239,172,0.08)", paddingHorizontal: 10, paddingVertical: 6 },
  realtimePillWarn: { borderColor: "rgba(253,230,138,0.25)", backgroundColor: "rgba(253,230,138,0.1)" },
  providerText: { color: "rgba(220,252,231,0.9)", fontFamily: fonts.semibold, fontSize: 10 },
  panel: { borderRadius: 22, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "rgba(12,16,25,0.9)", padding: 14 },
  sectionTitle: { color: colors.violetText, fontFamily: fonts.display, fontSize: 17 },
  productGrid: { marginTop: 12, flexDirection: "row", flexWrap: "wrap", gap: 8 },
  product: { width: "48.5%", borderRadius: 18, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "rgba(255,255,255,0.04)", padding: 12 },
  productActive: { borderColor: "rgba(249,226,173,0.45)", backgroundColor: "rgba(249,226,173,0.1)" },
  productTitle: { marginTop: 8, color: colors.violetText, fontFamily: fonts.semibold, fontSize: 12 },
  productMeta: { marginTop: 3, color: colors.muted, fontFamily: fonts.body, fontSize: 10, textTransform: "uppercase" },
  primary: { minHeight: 48, marginTop: 12, alignItems: "center", justifyContent: "center", borderRadius: 16, backgroundColor: colors.auric500 },
  primarySmall: { flex: 1, minHeight: 46, alignItems: "center", justifyContent: "center", borderRadius: 14, backgroundColor: colors.auric500 },
  primaryText: { color: colors.cosmic950, fontFamily: fonts.semibold, fontSize: 12 },
  secondary: { flex: 1, minHeight: 46, alignItems: "center", justifyContent: "center", borderRadius: 14, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "rgba(255,255,255,0.04)", paddingHorizontal: 12 },
  secondaryText: { color: "rgba(245,240,255,0.86)", fontFamily: fonts.semibold, fontSize: 12 },
  cardRow: { marginTop: 10, flexDirection: "row", alignItems: "center", gap: 10, borderRadius: 18, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "rgba(255,255,255,0.04)", padding: 12 },
  cardRowActive: { borderColor: "rgba(249,226,173,0.42)", backgroundColor: "rgba(249,226,173,0.09)" },
  cardIcon: { width: 42, height: 42, borderRadius: 14, alignItems: "center", justifyContent: "center", backgroundColor: "rgba(249,226,173,0.1)" },
  cardTitle: { color: colors.violetText, fontFamily: fonts.semibold, fontSize: 12 },
  muted: { marginTop: 3, color: colors.muted, fontFamily: fonts.body, fontSize: 11 },
  status: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 10 },
  flex: { flex: 1 },
  input: { marginTop: 10, minHeight: 48, borderRadius: 15, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "rgba(255,255,255,0.04)", paddingHorizontal: 13, color: colors.violetText, fontFamily: fonts.body, fontSize: 13 },
  row: { marginTop: 10, flexDirection: "row", gap: 8 },
  summary: { marginTop: 10, borderRadius: 16, borderWidth: 1, borderColor: "rgba(249,226,173,0.16)", backgroundColor: "rgba(249,226,173,0.07)", padding: 10 },
  summaryRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 10, paddingVertical: 4 },
  value: { color: colors.violetText, fontFamily: fonts.semibold, fontSize: 12 },
  activityRow: { marginTop: 10, flexDirection: "row", justifyContent: "space-between", gap: 10, borderRadius: 16, borderWidth: 1, borderColor: "rgba(255,255,255,0.07)", backgroundColor: "rgba(255,255,255,0.035)", padding: 12 },
  success: { color: "#86efac", fontFamily: fonts.body, fontSize: 12, lineHeight: 18 },
  error: { color: colors.danger, fontFamily: fonts.body, fontSize: 12, lineHeight: 18 },
  dim: { opacity: 0.5 },
});
