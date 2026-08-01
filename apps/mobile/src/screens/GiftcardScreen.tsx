import { Ionicons } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { useCallback, useEffect, useMemo, useState } from "react";
import { Modal, ScrollView, StyleSheet, Text, TextInput, View } from "react-native";
import Animated, { FadeInDown, FadeInUp } from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import { useAuth } from "../context/AuthContext";
import {
  fetchGiftcardInventory,
  fetchGiftcardOrders,
  fetchGiftcardRate,
  formatLockTime,
  formatNaira,
  GiftcardInventoryItem,
  GiftcardOrder,
  GiftcardRate,
  normalizeBrand,
  submitGiftcardBuy,
  submitGiftcardSell,
} from "../services/giftcardApi";
import { colors, fonts } from "../theme/colors";

type GiftcardScreenProps = {
  fontsReady: boolean;
  onBack: () => void;
};

const providers = ["Amazon", "Steam", "iTunes", "Google Play"];
const currencies = ["NGN", "ZAR", "GHS", "KES", "USD", "EUR", "GBP", "CAD", "AUD", "USDT", "USDC"];
const categories = ["Shopping", "Gaming", "Entertainment", "Lifestyle"];
const countries = ["United States", "United Kingdom", "Nigeria", "Canada"];
const denominations = ["25", "50", "100", "200"];
const paymentMethods = [
  { id: "balance", label: "Platform Balance", detail: "Available: 1,240.00 USDT", icon: "wallet-outline" as const },
  { id: "crypto", label: "Crypto Wallet", detail: "Connected: 0x4a...0c51", icon: "logo-bitcoin" as const },
];
const paymentLabels: Record<string, string> = {
  balance: "Platform Balance",
  crypto: "Crypto Wallet",
};

function toNumber(value: unknown) {
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : 0;
}

function SummaryBox({
  title,
  amount,
  rate,
  loading,
  mode,
  currency,
  provider,
  denomination,
  paymentLabel,
}: {
  title: string;
  amount: number;
  rate: GiftcardRate | null;
  loading: boolean;
  mode: "sell" | "buy";
  currency: string;
  provider: string;
  denomination: string;
  paymentLabel: string;
}) {
  const fee = 0;
  const total = mode === "sell" ? toNumber(rate?.payout) : toNumber(rate?.price);
  const rateText = mode === "sell" ? rate?.buy_rate : rate?.sell_rate;
  const rows =
    mode === "sell"
      ? [
          ["Entered Value", `${amount.toFixed(2)} ${currency}`],
          ["Live Rate", loading ? "Fetching best rate..." : rateText ? `${formatNaira(rateText)}/$` : "-"],
          ["Demand", rate?.demand_level || "Medium"],
          ["Inventory Signal", rate?.inventory_status || "Available"],
          ["Processing Fee", `${fee.toFixed(2)} ${currency}`],
          ["Final Receivable", `${total.toFixed(2)} EXA`],
        ]
      : [
          ["Selected Giftcard", provider],
          ["Denomination", denomination ? `$${denomination}` : "-"],
          ["Inventory Status", rate?.inventory_status || "Unknown"],
          ["Demand", rate?.demand_level || "Medium"],
          ["Live Sell Rate", loading ? "Updating price..." : rateText ? `${formatNaira(rateText)}/$` : "-"],
          ["Fees", formatNaira(fee)],
          ["Payment Source", paymentLabel],
          ["Final Payable", formatNaira(total)],
        ];

  return (
    <View style={styles.summaryCard}>
      <View style={styles.summaryIcon}>
        <Ionicons name="receipt-outline" size={20} color={colors.auric300} />
      </View>
      <Text style={styles.sectionTitle}>{title}</Text>
      <View style={styles.summaryRows}>
        {rows.map(([label, value], index) => (
          <View key={label} style={styles.summaryRow}>
            <Text style={styles.mutedText}>{label}</Text>
            <Text style={index === rows.length - 1 ? styles.goldValue : styles.valueText}>{value}</Text>
          </View>
        ))}
      </View>
      <View style={styles.rateChip}>
        <Ionicons name="pulse-outline" size={13} color="#86efac" />
        <Text style={styles.rateChipText}>{rateText ? `${formatNaira(rateText)}/$ live` : "Live rate pending"}</Text>
      </View>
      {rate?.market_feedback || rate?.demand_level || rate?.inventory_status ? (
        <Text style={styles.feedbackText}>
          {rate.market_feedback || `${rate.demand_level || "Normal"} demand - ${rate.inventory_status || "Available"}`}
        </Text>
      ) : null}
    </View>
  );
}

function SelectPills({
  items,
  value,
  onChange,
}: {
  items: string[];
  value: string;
  onChange: (value: string) => void;
}) {
  return (
    <View style={styles.pillWrap}>
      {items.map((item) => (
        <AnimatedPressable key={item} style={[styles.selectPill, value === item ? styles.selectPillActive : null]} onPress={() => onChange(item)}>
          <Text style={[styles.selectPillText, value === item ? styles.selectPillTextActive : null]}>{item}</Text>
        </AnimatedPressable>
      ))}
    </View>
  );
}

export default function GiftcardScreen({ fontsReady, onBack }: GiftcardScreenProps) {
  const insets = useSafeAreaInsets();
  const { request } = useAuth();
  const [mode, setMode] = useState<"sell" | "buy">("sell");
  const [provider, setProvider] = useState(providers[0]);
  const [code, setCode] = useState("");
  const [pin, setPin] = useState("");
  const [amount, setAmount] = useState("100");
  const [currency, setCurrency] = useState("USD");
  const [category, setCategory] = useState(categories[0]);
  const [country, setCountry] = useState(countries[0]);
  const [quantity, setQuantity] = useState("1");
  const [paymentMethod, setPaymentMethod] = useState("balance");
  const [inventory, setInventory] = useState<GiftcardInventoryItem[]>([]);
  const [rate, setRate] = useState<GiftcardRate | null>(null);
  const [lockedRate, setLockedRate] = useState<GiftcardRate | null>(null);
  const [lockSeconds, setLockSeconds] = useState(0);
  const [latestOrder, setLatestOrder] = useState<GiftcardOrder | null>(null);
  const [rateLoading, setRateLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [touched, setTouched] = useState({ code: false, amount: false });

  const amountValue = toNumber(amount);
  const quantityValue = Math.max(1, Math.floor(toNumber(quantity) || 1));
  const providerOptions = useMemo(() => {
    const unique = [...new Set(inventory.map((item) => item.card_type || item.brand).filter(Boolean) as string[])];
    return unique.length ? unique : providers;
  }, [inventory]);
  const denominationOptions = useMemo(() => {
    const matching = inventory
      .filter((item) => normalizeBrand(String(item.card_type || item.brand)) === normalizeBrand(provider))
      .map((item) => String(Number(item.amount || 0).toFixed(0)))
      .filter((value) => value !== "0");
    const unique = [...new Set(matching)];
    return unique.length ? unique : denominations;
  }, [inventory, provider]);
  const selectedInventory = useMemo(
    () =>
      inventory.find((item) => normalizeBrand(String(item.card_type || item.brand)) === normalizeBrand(provider) && toNumber(item.amount) === amountValue) ??
      null,
    [amountValue, inventory, provider],
  );
  const errors = useMemo(
    () => ({
      code: mode === "sell" && code.trim().length < 8 ? "Enter a valid giftcard code (min 8 characters)." : "",
      amount: amountValue <= 0 ? "Amount must be greater than zero." : "",
      inventory: mode === "buy" && inventory.length > 0 && !selectedInventory ? "Selected denomination is out of stock." : "",
    }),
    [amountValue, code, inventory.length, mode, selectedInventory],
  );
  const canSubmit = mode === "sell" ? !errors.code && !errors.amount : Boolean(provider && !errors.amount && !errors.inventory && quantityValue > 0);
  const activeRate = lockedRate || rate;

  const refreshRate = useCallback(async () => {
    if (!provider || amountValue <= 0) {
      setRate(null);
      return;
    }

    setRateLoading(true);
    try {
      const nextRate = await fetchGiftcardRate(request, provider, amountValue);
      setRate(nextRate);
    } catch (requestError) {
      setRate(null);
      setError(requestError instanceof Error ? requestError.message : "Unable to fetch live giftcard rate.");
    } finally {
      setRateLoading(false);
    }
  }, [amountValue, provider, request]);

  useEffect(() => {
    let active = true;
    const loadInventory = async () => {
      try {
        const items = await fetchGiftcardInventory(request);
        if (active) setInventory(items);
      } catch {
        if (active) setInventory([]);
      }
    };
    void loadInventory();
    return () => {
      active = false;
    };
  }, [request]);

  useEffect(() => {
    const timer = setTimeout(() => {
      void refreshRate();
    }, 350);
    return () => clearTimeout(timer);
  }, [refreshRate]);

  useEffect(() => {
    if (mode === "buy" && !denominationOptions.includes(amount)) {
      setAmount(denominationOptions[0] || "100");
    }
  }, [amount, denominationOptions, mode]);

  useEffect(() => {
    if (!providerOptions.includes(provider)) {
      setProvider(providerOptions[0] || providers[0]);
    }
  }, [provider, providerOptions]);

  useEffect(() => {
    if (lockedRate) return undefined;
    const interval = setInterval(() => {
      void refreshRate();
    }, 30000);
    return () => clearInterval(interval);
  }, [lockedRate, refreshRate]);

  useEffect(() => {
    if (!lockedRate || lockSeconds <= 0) return undefined;
    const interval = setInterval(() => {
      setLockSeconds((current) => {
        if (current <= 1) {
          setLockedRate(null);
          setConfirmOpen(false);
          void refreshRate();
          return 0;
        }
        return current - 1;
      });
    }, 1000);
    return () => clearInterval(interval);
  }, [lockSeconds, lockedRate, refreshRate]);

  const openConfirmation = async () => {
    setTouched({ code: true, amount: true });
    setError("");
    setMessage("");
    if (!canSubmit) return;

    const nextRate = rate || (await fetchGiftcardRate(request, provider, amountValue));
    setLockedRate(nextRate);
    setLockSeconds(300);
    setConfirmOpen(true);
  };

  const submit = async () => {
    if (!canSubmit || submitting || !lockedRate || lockSeconds <= 0) {
      setError("Rate expired. Please refresh pricing before submission.");
      setConfirmOpen(false);
      setLockedRate(null);
      return;
    }

    setSubmitting(true);
    setMessage("");
    setError("");
    try {
      const payload =
        mode === "sell"
          ? await submitGiftcardSell(request, {
              brand: provider,
              card_value: amountValue,
              currency,
              card_code: code.trim(),
              card_pin: pin.trim() || undefined,
            })
          : await submitGiftcardBuy(request, {
              brand: provider,
              card_value: amountValue,
              quantity: quantityValue,
              currency,
              payment_wallet_currency: currency,
              giftcard_id: selectedInventory?.id,
              payment_method: paymentMethod,
            });
      setMessage(String(payload.message || (mode === "sell" ? "Giftcard submitted for fraud analysis." : "Giftcard purchase submitted.")));
      if (mode === "sell") {
        setCode("");
        setPin("");
      } else {
        try {
          const orders = await fetchGiftcardOrders(request, 1);
          setLatestOrder(orders[0] || null);
        } catch {
          setLatestOrder(null);
        }
      }
      setConfirmOpen(false);
      setLockedRate(null);
      setLockSeconds(0);
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Unable to complete giftcard request.");
    } finally {
      setSubmitting(false);
    }
  };

  if (!fontsReady) {
    return <LinearGradient colors={[colors.cosmic950, "#120b22"]} style={styles.fill} />;
  }

  return (
    <LinearGradient colors={["#07050f", "#0e0a1b", "#120b22"]} style={styles.fill}>
      <ScrollView contentContainerStyle={[styles.content, { paddingTop: insets.top + 12, paddingBottom: insets.bottom + 28 }]} showsVerticalScrollIndicator={false}>
        <Animated.View entering={FadeInDown.duration(420)} style={styles.shell}>
          <View style={styles.heroCard}>
            <AnimatedPressable style={styles.backButton} onPress={onBack}>
              <Ionicons name="arrow-back" size={17} color={colors.auric300} />
              <Text style={styles.backText}>Back</Text>
            </AnimatedPressable>
            <View style={styles.heroRow}>
              <View className="min-w-0 flex-1">
                <Text style={styles.kicker}>Giftcard Desk</Text>
                <Text style={styles.heroTitle}>{mode === "sell" ? "Giftcard Conversion" : "Buy Giftcards"}</Text>
                <Text style={styles.heroCopy}>
                  {mode === "sell"
                    ? "Redeem or convert supported giftcards securely within the ExaEarn ecosystem."
                    : "Purchase digital giftcards securely from current ExaEarn inventory."}
                </Text>
              </View>
              <View style={styles.heroIcon}>
                <Ionicons name={mode === "sell" ? "swap-horizontal" : "gift"} size={28} color={colors.auric300} />
              </View>
            </View>
          </View>

          <View style={styles.modeTabs}>
            {(["sell", "buy"] as const).map((item) => (
              <AnimatedPressable key={item} style={[styles.modeTab, mode === item ? styles.modeTabActive : null]} onPress={() => setMode(item)}>
                <Text style={[styles.modeTabText, mode === item ? styles.modeTabTextActive : null]}>{item === "sell" ? "Sell Giftcard" : "Buy Giftcard"}</Text>
              </AnimatedPressable>
            ))}
          </View>

          <Animated.View entering={FadeInUp.duration(420)} style={styles.formGrid}>
            <View style={styles.formCard}>
              <Text style={styles.sectionTitle}>{mode === "sell" ? "Giftcard Input Panel" : "Giftcard Selector"}</Text>
              <Text style={styles.label}>Giftcard Type / Provider</Text>
              <SelectPills items={providerOptions} value={provider} onChange={setProvider} />

              {mode === "buy" ? (
                <>
                  <Text style={styles.label}>Category</Text>
                  <SelectPills items={categories} value={category} onChange={setCategory} />
                  <Text style={styles.label}>Country</Text>
                  <SelectPills items={countries} value={country} onChange={setCountry} />
                </>
              ) : null}

              {mode === "sell" ? (
                <>
                  <Text style={styles.label}>Giftcard Code / Number</Text>
                  <TextInput
                    value={code}
                    onChangeText={setCode}
                    onBlur={() => setTouched((previous) => ({ ...previous, code: true }))}
                    placeholder="Enter card code"
                    placeholderTextColor="rgba(226,232,240,0.42)"
                    style={styles.input}
                    autoCapitalize="characters"
                  />
                  {touched.code && errors.code ? <Text style={styles.errorText}>{errors.code}</Text> : null}
                  <Text style={styles.label}>PIN (optional)</Text>
                  <TextInput value={pin} onChangeText={setPin} placeholder="Enter PIN" placeholderTextColor="rgba(226,232,240,0.42)" style={styles.input} />
                </>
              ) : null}

              <View style={styles.twoColumn}>
                <View style={styles.columnField}>
                  <Text style={styles.label}>Amount / Value</Text>
                  {mode === "buy" ? (
                    <SelectPills items={denominationOptions} value={amount} onChange={setAmount} />
                  ) : (
                    <TextInput
                      value={amount}
                      onChangeText={setAmount}
                      onBlur={() => setTouched((previous) => ({ ...previous, amount: true }))}
                      keyboardType="decimal-pad"
                      placeholder="100"
                      placeholderTextColor="rgba(226,232,240,0.42)"
                      style={styles.input}
                    />
                  )}
                  {touched.amount && errors.amount ? <Text style={styles.errorText}>{errors.amount}</Text> : null}
                </View>
                <View style={styles.columnField}>
                  <Text style={styles.label}>Currency</Text>
                  <SelectPills items={currencies} value={currency} onChange={setCurrency} />
                </View>
              </View>

              {mode === "buy" ? (
                <>
                  <Text style={styles.label}>Quantity</Text>
                  <TextInput
                    value={quantity}
                    onChangeText={setQuantity}
                    keyboardType="number-pad"
                    placeholder="1"
                    placeholderTextColor="rgba(226,232,240,0.42)"
                    style={styles.input}
                  />
                </>
              ) : null}

              {mode === "buy" ? (
                <View style={styles.paymentPanel}>
                  <Text style={styles.sectionTitle}>Payment Panel</Text>
                  <Text style={styles.mutedText}>Choose your payment source for this purchase.</Text>
                  <View style={styles.paymentGrid}>
                    {paymentMethods.map((method) => {
                      const active = paymentMethod === method.id;
                      return (
                        <AnimatedPressable key={method.id} style={[styles.paymentMethod, active ? styles.paymentMethodActive : null]} onPress={() => setPaymentMethod(method.id)}>
                          <View style={styles.paymentMethodTop}>
                            <View style={styles.paymentIcon}>
                              <Ionicons name={method.icon} size={15} color={colors.auric300} />
                            </View>
                            <Text style={styles.valueText}>{method.label}</Text>
                            {active ? <Text style={styles.selectedText}>Selected</Text> : null}
                          </View>
                          <Text style={styles.feedbackText}>{method.detail}</Text>
                        </AnimatedPressable>
                      );
                    })}
                  </View>
                </View>
              ) : null}

              <View style={styles.securityBox}>
                <Text style={styles.securityLine}>
                  <Ionicons name="shield-checkmark" size={14} color="#86efac" /> Live pricing is refreshed automatically before submission.
                </Text>
                <Text style={styles.securityLine}>
                  <Ionicons name="lock-closed" size={14} color="#86efac" /> Giftcard details are handled through the same secure backend as web.
                </Text>
              </View>

              <AnimatedPressable style={[styles.primaryButton, !canSubmit ? styles.disabledButton : null]} disabled={!canSubmit} onPress={openConfirmation}>
                <Ionicons name={mode === "sell" ? "gift-outline" : "bag-check-outline"} size={18} color="#090b12" />
                <Text style={styles.primaryButtonText}>{rateLoading ? "Fetching best rate..." : mode === "sell" ? "Convert / Redeem Giftcard" : "Buy Giftcard"}</Text>
              </AnimatedPressable>

              {errors.inventory ? <Text style={styles.warningText}>{errors.inventory}</Text> : null}
              {message ? <Text style={styles.successText}>{message}</Text> : null}
              {error ? <Text style={styles.errorText}>{error}</Text> : null}
              {latestOrder ? (
                <View style={styles.latestOrder}>
                  <Text style={styles.valueText}>Latest Order Status</Text>
                  <Text style={styles.feedbackText}>Reference: {latestOrder.reference || latestOrder.id || "-"}</Text>
                  <Text style={styles.feedbackText}>Status: {latestOrder.status || "-"}</Text>
                  <Text style={styles.feedbackText}>Risk Level: {latestOrder.risk_level || "-"}</Text>
                  {latestOrder.metadata?.delivery?.masked_code ? <Text style={styles.feedbackText}>Delivery: {latestOrder.metadata.delivery.masked_code}</Text> : null}
                </View>
              ) : null}
            </View>

            <SummaryBox
              title="Transaction Summary"
              amount={amountValue}
              rate={activeRate}
              loading={rateLoading}
              mode={mode}
              currency={currency}
              provider={`${provider}${mode === "buy" ? ` (${category})` : ""}`}
              denomination={amount}
              paymentLabel={paymentLabels[paymentMethod]}
            />
          </Animated.View>

          <View style={styles.supportedCard}>
            <Text style={styles.sectionTitle}>Supported Giftcards</Text>
            <Text style={styles.mutedText}>{mode === "sell" ? "Providers currently available for secure conversion." : "Trusted partners available from current inventory."}</Text>
            <View style={styles.providerGrid}>
              {providerOptions.map((item) => (
                <View key={item} style={styles.providerBadge}>
                  <Ionicons name={item === "Steam" ? "game-controller-outline" : item === "Google Play" ? "globe-outline" : "card-outline"} size={14} color={colors.auric300} />
                  <Text style={styles.providerText}>{item}</Text>
                </View>
              ))}
            </View>
          </View>
        </Animated.View>
      </ScrollView>

      <Modal visible={confirmOpen} transparent animationType="fade" onRequestClose={() => setConfirmOpen(false)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.confirmCard}>
            <Text style={styles.heroTitle}>{mode === "sell" ? "Confirm Conversion" : "Confirm Purchase"}</Text>
            <Text style={styles.confirmText}>Brand: {provider}</Text>
            <Text style={styles.confirmText}>Value: ${amountValue.toFixed(2)}</Text>
            {mode === "buy" ? <Text style={styles.confirmText}>Quantity: {quantityValue}</Text> : null}
            <Text style={styles.confirmText}>
              Rate Used: {formatNaira(mode === "sell" ? lockedRate?.buy_rate : lockedRate?.sell_rate)}/$
            </Text>
            <Text style={styles.confirmText}>{mode === "sell" ? "You Receive" : "Price"}: {formatNaira(mode === "sell" ? lockedRate?.payout : lockedRate?.price)}</Text>
            <Text style={styles.lockText}>Rate locked for {formatLockTime(lockSeconds)}</Text>
            <View style={styles.confirmActions}>
              <AnimatedPressable style={styles.secondaryButton} onPress={() => setConfirmOpen(false)}>
                <Text style={styles.secondaryButtonText}>Cancel</Text>
              </AnimatedPressable>
              <AnimatedPressable style={styles.primaryButtonSmall} onPress={submit} disabled={submitting}>
                <Text style={styles.primaryButtonText}>{submitting ? "Submitting..." : "Confirm"}</Text>
              </AnimatedPressable>
            </View>
          </View>
        </View>
      </Modal>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  content: {
    paddingHorizontal: 10,
    width: "100%",
    maxWidth: 720,
    alignSelf: "center",
  },
  shell: {
    borderRadius: 24,
    borderWidth: 1,
    borderColor: "rgba(163,119,255,0.24)",
    backgroundColor: "rgba(9,8,19,0.82)",
    padding: 10,
    shadowColor: "#7f46d4",
    shadowOffset: { width: 0, height: 20 },
    shadowOpacity: 0.2,
    shadowRadius: 34,
  },
  heroCard: {
    borderRadius: 20,
    borderWidth: 1,
    borderColor: "rgba(244,207,126,0.24)",
    backgroundColor: "rgba(28,19,53,0.86)",
    padding: 14,
  },
  backButton: {
    alignSelf: "flex-start",
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "rgba(212,175,55,0.28)",
    paddingHorizontal: 10,
    paddingVertical: 7,
    marginBottom: 12,
  },
  backText: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 11 },
  heroRow: { flexDirection: "row", alignItems: "center", gap: 12 },
  kicker: { color: "rgba(212,175,55,0.8)", fontFamily: fonts.semibold, fontSize: 10, textTransform: "uppercase", letterSpacing: 1.5 },
  heroTitle: { color: "#f8fafc", fontFamily: fonts.display, fontSize: 24, lineHeight: 29 },
  heroCopy: { color: "rgba(237,233,254,0.76)", fontFamily: fonts.body, fontSize: 12, lineHeight: 18, marginTop: 6 },
  heroIcon: {
    width: 54,
    height: 54,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "rgba(212,175,55,0.6)",
    backgroundColor: "rgba(12,9,24,0.72)",
  },
  modeTabs: { flexDirection: "row", gap: 8, marginTop: 10 },
  modeTab: { flex: 1, alignItems: "center", borderRadius: 14, borderWidth: 1, borderColor: "rgba(255,255,255,0.1)", paddingVertical: 11 },
  modeTabActive: { borderColor: "rgba(212,175,55,0.42)", backgroundColor: "rgba(212,175,55,0.14)" },
  modeTabText: { color: "rgba(226,232,240,0.66)", fontFamily: fonts.semibold, fontSize: 11 },
  modeTabTextActive: { color: colors.auric300 },
  formGrid: { gap: 10, marginTop: 10 },
  formCard: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "rgba(244,207,126,0.24)",
    backgroundColor: "rgba(28,19,53,0.82)",
    padding: 12,
  },
  sectionTitle: { color: "#f8fafc", fontFamily: fonts.display, fontSize: 17 },
  label: { color: "rgba(237,233,254,0.84)", fontFamily: fonts.semibold, fontSize: 11, marginTop: 14, marginBottom: 7 },
  input: {
    minHeight: 46,
    borderRadius: 13,
    borderWidth: 1,
    borderColor: "rgba(163,119,255,0.38)",
    backgroundColor: "rgba(14,10,28,0.88)",
    color: "#f8fafc",
    fontFamily: fonts.body,
    fontSize: 14,
    paddingHorizontal: 12,
  },
  pillWrap: { flexDirection: "row", flexWrap: "wrap", gap: 7 },
  selectPill: { borderRadius: 999, borderWidth: 1, borderColor: "rgba(163,119,255,0.24)", backgroundColor: "rgba(14,10,28,0.72)", paddingHorizontal: 10, paddingVertical: 8 },
  selectPillActive: { borderColor: "rgba(212,175,55,0.56)", backgroundColor: "rgba(212,175,55,0.14)" },
  selectPillText: { color: "rgba(226,232,240,0.74)", fontFamily: fonts.semibold, fontSize: 10 },
  selectPillTextActive: { color: colors.auric300 },
  twoColumn: { gap: 8 },
  columnField: { minWidth: 0 },
  securityBox: { gap: 8, borderRadius: 14, borderWidth: 1, borderColor: "rgba(16,185,129,0.2)", backgroundColor: "rgba(16,185,129,0.06)", padding: 10, marginTop: 14 },
  securityLine: { color: "rgba(209,250,229,0.86)", fontFamily: fonts.body, fontSize: 11, lineHeight: 16 },
  paymentPanel: {
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "rgba(244,207,126,0.18)",
    backgroundColor: "rgba(14,10,28,0.5)",
    padding: 10,
    marginTop: 14,
  },
  paymentGrid: { gap: 8, marginTop: 10 },
  paymentMethod: {
    borderRadius: 13,
    borderWidth: 1,
    borderColor: "rgba(163,119,255,0.25)",
    backgroundColor: "rgba(14,10,28,0.72)",
    padding: 10,
  },
  paymentMethodActive: {
    borderColor: "rgba(212,175,55,0.75)",
    backgroundColor: "rgba(212,175,55,0.1)",
  },
  paymentMethodTop: { flexDirection: "row", alignItems: "center", gap: 8 },
  paymentIcon: {
    width: 28,
    height: 28,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "rgba(212,175,55,0.45)",
    backgroundColor: "rgba(12,9,24,0.8)",
  },
  selectedText: { marginLeft: "auto", color: colors.auric300, fontFamily: fonts.semibold, fontSize: 10 },
  primaryButton: { minHeight: 48, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 8, borderRadius: 14, backgroundColor: colors.auric500, marginTop: 14, paddingHorizontal: 12 },
  primaryButtonSmall: { flex: 1, minHeight: 44, alignItems: "center", justifyContent: "center", borderRadius: 12, backgroundColor: colors.auric500 },
  primaryButtonText: { color: "#090b12", fontFamily: fonts.semibold, fontSize: 12 },
  disabledButton: { opacity: 0.5 },
  summaryCard: { borderRadius: 18, borderWidth: 1, borderColor: "rgba(244,207,126,0.24)", backgroundColor: "rgba(28,19,53,0.82)", padding: 12 },
  summaryIcon: { width: 42, height: 42, alignItems: "center", justifyContent: "center", borderRadius: 14, borderWidth: 1, borderColor: "rgba(212,175,55,0.34)", marginBottom: 10 },
  summaryRows: { gap: 9, marginTop: 12 },
  summaryRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  mutedText: { color: "rgba(226,232,240,0.62)", fontFamily: fonts.body, fontSize: 11 },
  valueText: { color: "#f8fafc", fontFamily: fonts.semibold, fontSize: 12 },
  goldValue: { color: colors.auric300, fontFamily: fonts.display, fontSize: 15 },
  rateChip: { flexDirection: "row", alignItems: "center", gap: 6, alignSelf: "flex-start", borderRadius: 999, backgroundColor: "rgba(16,185,129,0.12)", paddingHorizontal: 9, paddingVertical: 6, marginTop: 12 },
  rateChipText: { color: "#bbf7d0", fontFamily: fonts.semibold, fontSize: 10 },
  feedbackText: { color: "rgba(237,233,254,0.66)", fontFamily: fonts.body, fontSize: 11, lineHeight: 16, marginTop: 10 },
  supportedCard: { borderRadius: 18, borderWidth: 1, borderColor: "rgba(244,207,126,0.24)", backgroundColor: "rgba(28,19,53,0.82)", padding: 12, marginTop: 10 },
  providerGrid: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginTop: 12 },
  providerBadge: { flexDirection: "row", alignItems: "center", gap: 7, borderRadius: 999, borderWidth: 1, borderColor: "rgba(163,119,255,0.2)", backgroundColor: "rgba(14,10,28,0.72)", paddingHorizontal: 10, paddingVertical: 8 },
  providerText: { color: "rgba(237,233,254,0.86)", fontFamily: fonts.semibold, fontSize: 11 },
  warningText: { color: "#fde68a", fontFamily: fonts.body, fontSize: 11, marginTop: 10 },
  successText: { color: "#86efac", fontFamily: fonts.semibold, fontSize: 11, marginTop: 10 },
  errorText: { color: "#fda4af", fontFamily: fonts.semibold, fontSize: 11, marginTop: 10 },
  latestOrder: {
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "rgba(163,119,255,0.2)",
    backgroundColor: "rgba(14,10,28,0.55)",
    padding: 10,
    marginTop: 12,
  },
  modalBackdrop: { flex: 1, alignItems: "center", justifyContent: "center", backgroundColor: "rgba(0,0,0,0.72)", padding: 16 },
  confirmCard: { width: "100%", maxWidth: 430, borderRadius: 20, borderWidth: 1, borderColor: "rgba(244,207,126,0.28)", backgroundColor: "#120b22", padding: 16 },
  confirmText: { color: "rgba(237,233,254,0.84)", fontFamily: fonts.body, fontSize: 12, marginTop: 10 },
  lockText: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 12, marginTop: 12 },
  confirmActions: { flexDirection: "row", gap: 10, marginTop: 18 },
  secondaryButton: { flex: 1, minHeight: 44, alignItems: "center", justifyContent: "center", borderRadius: 12, borderWidth: 1, borderColor: "rgba(163,119,255,0.32)" },
  secondaryButtonText: { color: "rgba(237,233,254,0.86)", fontFamily: fonts.semibold, fontSize: 12 },
});
