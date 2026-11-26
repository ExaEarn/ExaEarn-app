import { Ionicons } from "@expo/vector-icons";
import { useMemo, useState } from "react";
import {
  Image,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import {
  createPaymentReference,
  parsePaymentAmount,
  parseSimulatedQrRequest,
  type ExaPayPaymentDraft,
} from "../services/exaPayPaymentReviewModel";
import { colors, fonts } from "../theme/colors";

type Props = {
  fontsReady: boolean;
  mode: "online" | "qr";
  initialDraft?: ExaPayPaymentDraft | null;
  onBack: () => void;
  onContinue: (draft: ExaPayPaymentDraft) => void;
};

function initialsFor(value: string) {
  return value
    .replace(/^@/, "")
    .split(/[._\s-]+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? "")
    .join("") || "EX";
}

export default function ExaPayTransferDraftScreen({
  fontsReady,
  mode,
  initialDraft,
  onBack,
  onContinue,
}: Props) {
  const insets = useSafeAreaInsets();
  const initialQr = mode === "qr" && initialDraft?.channel === "qr"
    ? {
        username: initialDraft.recipient.username ?? "",
        amountMinor: initialDraft.amountMinor,
        reference: initialDraft.reference,
        message: initialDraft.message,
        expiresAt: initialDraft.expiresAt,
      }
    : null;
  const [recipient, setRecipient] = useState(
    mode === "online" && initialDraft?.channel === "online"
      ? initialDraft.recipient.username ?? ""
      : "",
  );
  const [amount, setAmount] = useState(initialDraft ? formatMinorForInput(initialDraft.amountMinor) : "");
  const [message, setMessage] = useState(initialDraft?.message ?? "");
  const [qrPayload, setQrPayload] = useState("");
  const [formError, setFormError] = useState("");
  const [validatedQr, setValidatedQr] = useState<ReturnType<typeof parseSimulatedQrRequest>>(initialQr);
  const isQr = mode === "qr";
  const parsedAmount = useMemo(() => parsePaymentAmount(amount), [amount]);

  function continueOnlineTransfer() {
    const username = recipient.trim().replace(/^@/, "");
    if (!/^[A-Za-z0-9._-]{3,30}$/.test(username)) {
      setFormError("Enter a valid ExaEarn username. Recipient identity will still require server verification.");
      return;
    }
    if (!parsedAmount.valid) {
      setFormError(
        parsedAmount.reason === "precision"
          ? "Use no more than 2 decimal places."
          : parsedAmount.reason === "maximum"
            ? "Enter a smaller amount."
            : "Enter an amount greater than zero.",
      );
      return;
    }
    const reference = createPaymentReference("PAY");
    onContinue({
      idempotencyKey: createPaymentReference("IDEM"),
      reference,
      channel: "online",
      sourceAccount: { label: "ExaEarn Wallet" },
      recipient: {
        id: `entered:${username.toLowerCase()}`,
        displayName: `@${username}`,
        username,
        initials: initialsFor(username),
        verification: "user-entered",
      },
      amountMinor: parsedAmount.amountMinor,
      feeMinor: null,
      asset: { symbol: "simUSDT", precision: 2 },
      message: message.trim(),
      createdAt: Date.now(),
      expiresAt: null,
      simulation: true,
    });
  }

  function validateQrRequest() {
    setFormError("");
    const request = parseSimulatedQrRequest(qrPayload);
    if (!request) {
      setValidatedQr(null);
      setFormError("This sample request is malformed or expired. No QR camera or live payment service is connected.");
      return;
    }
    setValidatedQr(request);
  }

  function continueQrPayment() {
    if (!validatedQr) {
      setFormError("Validate the sample payment request before continuing.");
      return;
    }
    if (validatedQr.expiresAt !== null && Date.now() >= validatedQr.expiresAt) {
      setValidatedQr(null);
      setFormError("This QR request has expired. Ask the sender for a new request.");
      return;
    }

    const reference = validatedQr.reference;
    const username = validatedQr.username;
    const simulatedRecipient = {
      id: `sim-qr-${reference}`,
      name: `@${username}`,
      product: "QR request · parsed locally",
      shortId: reference,
      available: true,
    };

    onContinue({
      idempotencyKey: `IDEM-${reference}`,
      reference,
      channel: "qr",
      sourceAccount: { label: "ExaEarn Offline Wallet" },
      recipient: {
        id: `qr:${reference}`,
        displayName: `@${username}`,
        username,
        identifier: reference,
        initials: initialsFor(username),
        verification: "qr-parsed",
      },
      amountMinor: validatedQr.amountMinor,
      feeMinor: 0,
      asset: { symbol: "simUSDT", precision: 2, network: "QR · simulation" },
      message: validatedQr.message,
      createdAt: Date.now(),
      expiresAt: validatedQr.expiresAt,
      simulation: true,
      offlineTransfer: {
        recipient: simulatedRecipient,
        recipientVerifiedForSimulation: true,
        method: "qr",
        scenario: "success",
      },
    });
  }

  function loadSampleQrRequest() {
    const reference = createPaymentReference("QR");
    const expiresAt = Date.now() + 5 * 60 * 1000;
    setQrPayload(`exaearn://pay?to=monica&amount=10.00&ref=${reference}&expires=${expiresAt}`);
    setValidatedQr(null);
    setFormError("");
  }

  return (
    <View style={styles.fill}>
      <KeyboardAvoidingView style={styles.fill} behavior={Platform.OS === "ios" ? "padding" : undefined}>
        <ScrollView
          contentContainerStyle={[styles.scrollContent, { paddingTop: insets.top + 10, paddingBottom: 20 }]}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode={Platform.OS === "ios" ? "interactive" : "on-drag"}
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.shell}>
            <View style={styles.header}>
              <AnimatedPressable style={styles.backButton} onPress={onBack} accessibilityLabel="Back to ExaPay">
                <Ionicons name="arrow-back" size={20} color="#dce3ed" />
              </AnimatedPressable>
              <View style={styles.brand}>
                <Image
                  source={require("../../../web/src/assets/images/exaearn-logo.png")}
                  style={styles.logo}
                  accessibilityLabel="ExaEarn"
                />
                <View><Text style={styles.brandName}>ExaEarn</Text><Text style={styles.brandCaption}>EXAPAY</Text></View>
              </View>
              <View style={styles.simulation}><View style={styles.simDot} /><Text style={styles.simText}>Simulation</Text></View>
            </View>

            <View style={styles.titleRow}>
              <View style={styles.titleIcon}>
                <Ionicons name={isQr ? "qr-code-outline" : "arrow-up-outline"} size={20} color={isQr ? "#b5a2ed" : colors.auric300} />
              </View>
              <View style={styles.titleCopy}>
                <Text style={[styles.title, !fontsReady ? styles.systemFont : null]}>
                  {isQr ? "QR Payment" : "Online Transfer"}
                </Text>
                <Text style={styles.subtitle}>
                  {isQr ? "Validate a payment request before reviewing its details." : "Enter the recipient and amount for a simulated transfer."}
                </Text>
              </View>
            </View>

            {isQr ? (
              <>
                <View style={styles.notice}>
                  <Ionicons name="information-circle-outline" size={18} color="#e8b866" />
                  <Text style={styles.noticeText}>
                    Camera scanning is not configured in this build. You can paste a request or load a clearly marked sample. Parsing does not verify the recipient identity.
                  </Text>
                </View>
                <View style={styles.card}>
                  <Text style={styles.label}>Payment request payload</Text>
                  <TextInput
                    value={qrPayload}
                    onChangeText={(value) => {
                      setQrPayload(value);
                      setValidatedQr(null);
                      setFormError("");
                    }}
                    multiline
                    autoCapitalize="none"
                    autoCorrect={false}
                    placeholder="exaearn://pay?to=...&amount=...&ref=..."
                    placeholderTextColor="#697586"
                    style={styles.payloadInput}
                    accessibilityLabel="QR payment request payload"
                  />
                  <View style={styles.qrActions}>
                    <AnimatedPressable style={styles.secondaryButton} onPress={loadSampleQrRequest}>
                      <Ionicons name="flask-outline" size={16} color="#c5b5ef" />
                      <Text style={styles.secondaryButtonText}>Load sample request</Text>
                    </AnimatedPressable>
                    <AnimatedPressable style={styles.validateButton} onPress={validateQrRequest}>
                      <Ionicons name="checkmark-circle-outline" size={17} color="#17140c" />
                      <Text style={styles.validateButtonText}>Validate</Text>
                    </AnimatedPressable>
                  </View>
                  {validatedQr ? (
                    <View style={styles.validatedCard} accessibilityRole="summary">
                      <Ionicons name="checkmark-circle-outline" size={19} color="#72d3a0" />
                      <View style={styles.validatedCopy}>
                        <Text style={styles.validatedTitle}>Request syntax validated · simulation</Text>
                        <Text style={styles.validatedDescription}>
                          @{validatedQr.username} · {formatAmount(validatedQr.amountMinor)} simUSDT
                        </Text>
                        <Text style={styles.unverifiedNote}>Recipient identity is not verified.</Text>
                      </View>
                    </View>
                  ) : null}
                </View>
              </>
            ) : (
              <View style={styles.card}>
                <Field
                  label="Recipient ExaEarn username"
                  value={recipient}
                  onChangeText={(value) => { setRecipient(value); setFormError(""); }}
                  placeholder="@username"
                  accessibilityLabel="Recipient username"
                  autoCapitalize="none"
                />
                <Field
                  label="Payment amount · simUSDT"
                  value={amount}
                  onChangeText={(value) => { setAmount(value); setFormError(""); }}
                  placeholder="0.00"
                  accessibilityLabel="Payment amount in simulated USDT"
                  keyboardType="decimal-pad"
                />
                <Field
                  label="Message · optional"
                  value={message}
                  onChangeText={setMessage}
                  placeholder="Add a note"
                  accessibilityLabel="Optional payment message"
                  maxLength={100}
                />
                <Text style={styles.helper}>
                  Recipient identity and transfer fees require an online payment service. Neither is verified or quoted in this simulation.
                </Text>
              </View>
            )}

            {formError ? (
              <View style={styles.error} accessibilityRole="alert">
                <Ionicons name="alert-circle-outline" size={17} color="#f08c8c" />
                <Text style={styles.errorText}>{formError}</Text>
              </View>
            ) : null}
          </View>
        </ScrollView>

        <View style={[styles.footer, { paddingBottom: Math.max(insets.bottom, 14) + 10 }]}>
          <AnimatedPressable
            style={styles.primaryButton}
            onPress={isQr ? continueQrPayment : continueOnlineTransfer}
            accessibilityLabel={isQr ? "Review validated QR payment" : "Review online transfer"}
          >
            <Text style={styles.primaryButtonText}>Review Payment</Text>
            <Ionicons name="arrow-forward" size={17} color="#17140c" />
          </AnimatedPressable>
          <Text style={styles.footerNote}>Simulation only · No real funds will move</Text>
        </View>
      </KeyboardAvoidingView>
    </View>
  );
}

function formatMinorForInput(amountMinor: number) {
  return `${Math.floor(amountMinor / 100)}.${String(amountMinor % 100).padStart(2, "0")}`;
}

function Field({
  label,
  value,
  onChangeText,
  placeholder,
  accessibilityLabel,
  keyboardType = "default",
  autoCapitalize = "sentences",
  maxLength,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  accessibilityLabel: string;
  keyboardType?: "default" | "decimal-pad";
  autoCapitalize?: "none" | "sentences";
  maxLength?: number;
}) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor="#697586"
        style={styles.input}
        accessibilityLabel={accessibilityLabel}
        keyboardType={keyboardType}
        autoCapitalize={autoCapitalize}
        autoCorrect={false}
        maxLength={maxLength}
      />
    </View>
  );
}

function formatAmount(amountMinor: number) {
  return `${Math.floor(amountMinor / 100)}.${String(amountMinor % 100).padStart(2, "0")}`;
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: "#090b12" },
  scrollContent: { alignItems: "center" },
  shell: { width: "100%", maxWidth: 520, paddingHorizontal: 16 },
  header: { minHeight: 50, flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginBottom: 25 },
  backButton: { width: 44, height: 44, alignItems: "center", justifyContent: "center", borderRadius: 13, borderWidth: 1, borderColor: "rgba(224,232,241,0.1)", backgroundColor: "#111720" },
  brand: { flexDirection: "row", alignItems: "center", gap: 8 },
  logo: { width: 34, height: 34, borderRadius: 10, resizeMode: "contain" },
  brandName: { color: "#eef2f7", fontFamily: fonts.semibold, fontSize: 12 },
  brandCaption: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 8, letterSpacing: 1.4 },
  simulation: { minHeight: 28, flexDirection: "row", alignItems: "center", gap: 6, borderRadius: 99, borderWidth: 1, borderColor: "rgba(232,184,102,0.2)", backgroundColor: "rgba(232,184,102,0.06)", paddingHorizontal: 9 },
  simDot: { width: 6, height: 6, borderRadius: 3, backgroundColor: "#e8b866" },
  simText: { color: "#e7c68e", fontFamily: fonts.semibold, fontSize: 9 },
  titleRow: { flexDirection: "row", alignItems: "center", gap: 12, marginBottom: 20 },
  titleIcon: { width: 46, height: 46, alignItems: "center", justifyContent: "center", borderRadius: 14, borderWidth: 1, borderColor: "rgba(170,145,240,0.17)", backgroundColor: "rgba(170,145,240,0.08)" },
  titleCopy: { flex: 1 },
  title: { color: "#eff2f7", fontFamily: fonts.display, fontSize: 22 },
  systemFont: { fontFamily: undefined },
  subtitle: { marginTop: 4, color: "#9da8b7", fontFamily: fonts.body, fontSize: 11, lineHeight: 16 },
  notice: { flexDirection: "row", alignItems: "flex-start", gap: 9, borderRadius: 12, borderWidth: 1, borderColor: "rgba(232,184,102,0.17)", backgroundColor: "rgba(232,184,102,0.045)", padding: 11, marginBottom: 13 },
  noticeText: { flex: 1, color: "#cdbb98", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  card: { borderRadius: 16, borderWidth: 1, borderColor: "rgba(224,232,241,0.09)", backgroundColor: "#111720", padding: 14 },
  field: { marginBottom: 14 },
  label: { marginBottom: 7, color: "#c1cad5", fontFamily: fonts.medium, fontSize: 11 },
  input: { minHeight: 48, borderRadius: 11, borderWidth: 1, borderColor: "rgba(224,232,241,0.1)", backgroundColor: "#0d131b", color: "#eff2f7", paddingHorizontal: 12, fontFamily: fonts.body, fontSize: 13 },
  payloadInput: { minHeight: 104, borderRadius: 11, borderWidth: 1, borderColor: "rgba(224,232,241,0.1)", backgroundColor: "#0d131b", color: "#eff2f7", padding: 12, fontFamily: fonts.body, fontSize: 12, textAlignVertical: "top" },
  helper: { color: "#8995a5", fontFamily: fonts.body, fontSize: 10, lineHeight: 15, marginTop: 2 },
  qrActions: { flexDirection: "row", gap: 8, marginTop: 10 },
  secondaryButton: { minHeight: 44, flex: 1, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 6, borderRadius: 11, borderWidth: 1, borderColor: "rgba(170,145,240,0.2)", backgroundColor: "rgba(170,145,240,0.06)", paddingHorizontal: 8 },
  secondaryButtonText: { color: "#c5b5ef", fontFamily: fonts.semibold, fontSize: 10 },
  validateButton: { minHeight: 44, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 6, borderRadius: 11, backgroundColor: colors.auric300, paddingHorizontal: 12 },
  validateButtonText: { color: "#17140c", fontFamily: fonts.semibold, fontSize: 10 },
  validatedCard: { flexDirection: "row", alignItems: "flex-start", gap: 8, borderRadius: 11, borderWidth: 1, borderColor: "rgba(114,211,160,0.17)", backgroundColor: "rgba(114,211,160,0.05)", padding: 10, marginTop: 11 },
  validatedCopy: { flex: 1, gap: 4 },
  validatedTitle: { color: "#9ae0b7", fontFamily: fonts.semibold, fontSize: 10 },
  validatedDescription: { color: "#dce3ed", fontFamily: fonts.body, fontSize: 11 },
  unverifiedNote: { color: "#a7adb7", fontFamily: fonts.body, fontSize: 9 },
  error: { flexDirection: "row", alignItems: "flex-start", gap: 8, borderRadius: 11, borderWidth: 1, borderColor: "rgba(240,140,140,0.18)", backgroundColor: "rgba(240,140,140,0.055)", padding: 10, marginTop: 12 },
  errorText: { flex: 1, color: "#e4aaaa", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  footer: { width: "100%", alignItems: "center", borderTopWidth: 1, borderTopColor: "rgba(255,255,255,0.07)", backgroundColor: "#090b12", paddingHorizontal: 16, paddingTop: 12 },
  primaryButton: { width: "100%", maxWidth: 520, minHeight: 50, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 9, borderRadius: 13, backgroundColor: colors.auric300 },
  primaryButtonText: { color: "#17140c", fontFamily: fonts.semibold, fontSize: 12 },
  footerNote: { marginTop: 7, color: "#778391", fontFamily: fonts.body, fontSize: 9 },
});
