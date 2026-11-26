import { Ionicons } from "@expo/vector-icons";
import { useEffect, useRef, useState } from "react";
import {
  ActivityIndicator,
  BackHandler,
  Image,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import {
  authorizeAndSubmitPayment,
  type PaymentAuthorizationStage,
} from "../services/exaPayPaymentAuthorizationAdapter";
import {
  calculatePaymentTotalMinor,
  formatPaymentAmount,
  validatePaymentDraft,
  type ExaPayPaymentDraft,
  type ExaPayPaymentResult,
} from "../services/exaPayPaymentReviewModel";
import { colors, fonts } from "../theme/colors";

type Props = {
  fontsReady: boolean;
  draft: ExaPayPaymentDraft;
  onBack: () => void;
  onCancel: () => void;
  onResult: (draft: ExaPayPaymentDraft, result: ExaPayPaymentResult) => void;
};

function DetailRow({ label, value, last = false }: { label: string; value: string; last?: boolean }) {
  return (
    <View style={[styles.detailRow, last ? styles.detailLast : null]}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue} numberOfLines={2}>{value}</Text>
    </View>
  );
}

function AuthorizationSheet({
  visible,
  stage,
  onCancel,
  bottomInset,
}: {
  visible: boolean;
  stage: PaymentAuthorizationStage | null;
  onCancel: () => void;
  bottomInset: number;
}) {
  const stages: PaymentAuthorizationStage[] = [
    "validating-payment",
    "requesting-simulation-authorization",
    "committing-offline",
  ];
  const activeIndex = stage ? stages.indexOf(stage) : -1;

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onCancel}>
      <View style={styles.progressBackdrop}>
        <View style={[styles.progressCard, { marginBottom: bottomInset }]}>
          <View style={styles.progressHeader}>
            <View style={styles.progressSpinner}><ActivityIndicator size="small" color={colors.auric300} /></View>
            <View style={styles.progressCopy}>
              <Text style={styles.progressTitle}>Authorizing payment</Text>
              <Text style={styles.progressSubtitle}>Local simulation · no real funds</Text>
            </View>
          </View>
          <View style={styles.progressSteps}>
            {stages.map((item, index) => {
              const complete = index < activeIndex;
              const current = index === activeIndex;
              return (
                <View key={item} style={styles.progressStep}>
                  <View style={[
                    styles.progressDot,
                    complete ? styles.progressDotComplete : null,
                    current ? styles.progressDotActive : null,
                  ]}>
                    {complete ? <Ionicons name="checkmark" size={12} color="#11140f" /> : null}
                  </View>
                  <Text style={[styles.progressStepText, current ? styles.progressStepTextActive : null]}>
                    {item === "validating-payment"
                      ? "Validating payment details"
                      : item === "requesting-simulation-authorization"
                        ? "Requesting simulation authorization"
                        : "Preparing offline commitment"}
                  </Text>
                </View>
              );
            })}
          </View>
          <AnimatedPressable style={styles.progressCancel} onPress={onCancel}>
            <Text style={styles.progressCancelText}>Cancel authorization</Text>
          </AnimatedPressable>
        </View>
      </View>
    </Modal>
  );
}

function PaymentAmountCard({ draft, fontsReady }: { draft: ExaPayPaymentDraft; fontsReady: boolean }) {
  return (
    <View style={styles.amountCard}>
      <View style={styles.amountIcon}>
        <Ionicons name="arrow-up-outline" size={21} color="#cbd3df" />
      </View>
      <Text style={[styles.amount, !fontsReady ? styles.systemFont : null]}>
        {formatPaymentAmount(draft.amountMinor, "").replace(/\s$/, "")}
      </Text>
      <Text style={styles.assetLabel}>{draft.asset.symbol}{draft.asset.network ? ` · ${draft.asset.network}` : ""}</Text>
      <View style={styles.simulationBadge}>
        <View style={styles.simulationDot} />
        <Text style={styles.simulationBadgeText}>Simulation Mode</Text>
      </View>
    </View>
  );
}

function PaymentParticipantCard({ draft }: { draft: ExaPayPaymentDraft }) {
  return (
    <View style={styles.participantCard}>
      <Text style={styles.cardEyebrow}>FROM</Text>
      <View style={styles.participantRow}>
        <View style={styles.sourceIcon}>
          <Ionicons name="wallet-outline" size={20} color="#62c7e8" />
        </View>
        <View style={styles.participantCopy}>
          <Text style={styles.participantName}>{draft.sourceAccount.label}</Text>
          <Text style={styles.participantDetail}>
            {draft.sourceAccount.identifier ?? "ExaEarn wallet · simulation"}
          </Text>
        </View>
      </View>

      <View style={styles.participantDivider} />

      <Text style={styles.cardEyebrow}>TO</Text>
      <View style={styles.participantRow}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{draft.recipient.initials}</Text>
        </View>
        <View style={styles.participantCopy}>
          <Text style={styles.participantName}>{draft.recipient.displayName}</Text>
          <Text style={styles.participantDetail}>
            {draft.recipient.identifier
              ?? (draft.recipient.verification === "simulation-verified"
                ? "Verified in simulation"
                : "Identity verification is not provided")}
          </Text>
        </View>
      </View>
    </View>
  );
}

function PaymentDetailsCard({
  draft,
  totalMinor,
  message,
  onMessageChange,
  isSubmitting,
}: {
  draft: ExaPayPaymentDraft;
  totalMinor: number | null;
  message: string;
  onMessageChange: (value: string) => void;
  isSubmitting: boolean;
}) {
  return (
    <View style={styles.detailsCard}>
      <DetailRow
        label={draft.channel === "online" ? "Network/payment fee" : "Payment fee"}
        value={draft.feeMinor === null ? "Not quoted" : formatPaymentAmount(draft.feeMinor, draft.asset.symbol)}
      />
      <DetailRow
        label="Total deducted"
        value={totalMinor === null ? "Unavailable until fee is quoted" : formatPaymentAmount(totalMinor, draft.asset.symbol)}
      />
      <DetailRow
        label="Processing"
        value={
          draft.channel === "online"
            ? "Backend authorization and settlement required"
            : "Local commit · reconciliation required"
        }
      />
      {draft.asset.network ? <DetailRow label="Network / protocol" value={draft.asset.network} /> : null}
      <View style={styles.messageRow}>
        <Text style={styles.detailLabel}>Payment message</Text>
        <TextInput
          value={message}
          onChangeText={onMessageChange}
          placeholder="Optional"
          placeholderTextColor="#697586"
          style={styles.messageInput}
          maxLength={100}
          editable={!isSubmitting}
          accessibilityLabel="Optional payment message"
        />
      </View>
      <DetailRow label="Reference" value={draft.reference} last />
    </View>
  );
}

function PaymentConfirmationButton({
  canConfirm,
  isSubmitting,
  isOffline,
  onPress,
}: {
  canConfirm: boolean;
  isSubmitting: boolean;
  isOffline: boolean;
  onPress: () => void;
}) {
  return (
    <AnimatedPressable
      style={[styles.confirmButton, !canConfirm ? styles.confirmDisabled : null]}
      onPress={onPress}
      disabled={!canConfirm}
      accessibilityRole="button"
      accessibilityLabel={isOffline ? "Confirm offline payment in simulation" : "Confirm online payment"}
    >
      {isSubmitting ? (
        <ActivityIndicator size="small" color="#17140c" />
      ) : (
        <Ionicons name="checkmark" size={18} color={canConfirm ? "#17140c" : "#7f8792"} />
      )}
      <Text style={[styles.confirmText, !canConfirm ? styles.confirmTextDisabled : null]}>
        {isSubmitting ? "Confirming…" : "Confirm"}
      </Text>
    </AnimatedPressable>
  );
}

export default function ExaPayPaymentReviewScreen({
  fontsReady,
  draft,
  onBack,
  onCancel,
  onResult,
}: Props) {
  const insets = useSafeAreaInsets();
  const [message, setMessage] = useState(draft.message);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [stage, setStage] = useState<PaymentAuthorizationStage | null>(null);
  const [error, setError] = useState("");
  const [now, setNow] = useState(Date.now);
  const submissionLock = useRef(false);
  const cancellationRequested = useRef(false);

  const validation = validatePaymentDraft(draft, now);
  const totalMinor = calculatePaymentTotalMinor(draft);
  const isOffline = draft.channel === "bluetooth" || draft.channel === "qr";

  useEffect(() => {
    if (draft.expiresAt === null) return;
    const delay = Math.max(0, draft.expiresAt - Date.now());
    const timeout = setTimeout(() => setNow(Date.now()), Math.min(delay, 2_147_000_000));
    return () => clearTimeout(timeout);
  }, [draft.expiresAt, now]);

  useEffect(() => {
    const subscription = BackHandler.addEventListener("hardwareBackPress", () => {
      if (isSubmitting) {
        cancellationRequested.current = true;
        return true;
      }
      onBack();
      return true;
    });
    return () => subscription.remove();
  }, [isSubmitting, onBack]);

  async function confirmPayment() {
    if (submissionLock.current) return;
    const latestValidation = validatePaymentDraft(draft);
    if (!latestValidation.valid) {
      setError(
        latestValidation.reason === "expired"
          ? "This payment request has expired."
          : "This payment draft is invalid. Review it before continuing.",
      );
      return;
    }

    submissionLock.current = true;
    cancellationRequested.current = false;
    setError("");
    setIsSubmitting(true);
    setStage("validating-payment");
    try {
      const effectiveDraft = { ...draft, message: message.trim() };
      const result = await authorizeAndSubmitPayment(effectiveDraft, {
        onStage: (nextStage) => setStage(nextStage),
        shouldCancel: () => cancellationRequested.current,
      });
      onResult(effectiveDraft, result);
    } catch {
      onResult(draft, {
        channel: draft.channel,
        outcome: "recovery-required",
        reference: draft.reference,
        amountMinor: draft.amountMinor,
        simulation: true,
        onlineSettlement: false,
        message: "The authorization outcome is uncertain. Stop and reconcile before retrying.",
      });
    } finally {
      submissionLock.current = false;
      setIsSubmitting(false);
      setStage(null);
    }
  }

  const canConfirm = validation.valid && !isSubmitting && draft.channel !== "online";

  return (
    <View style={styles.fill}>
      <KeyboardAvoidingView style={styles.fill} behavior={Platform.OS === "ios" ? "padding" : undefined}>
        <ScrollView
          contentContainerStyle={[
            styles.scrollContent,
            {
              paddingTop: insets.top + 8,
              paddingBottom: Math.max(insets.bottom, 16) + 96,
            },
          ]}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode={Platform.OS === "ios" ? "interactive" : "on-drag"}
          showsVerticalScrollIndicator={false}
        >
          <View style={[styles.shell, { width: "100%", maxWidth: 520 }]}>
            <View style={styles.header}>
              <AnimatedPressable style={styles.backButton} onPress={onBack} disabled={isSubmitting} accessibilityLabel="Back">
                <Ionicons name="arrow-back" size={20} color="#dce3ed" />
              </AnimatedPressable>
              <Text style={[styles.headerTitle, !fontsReady ? styles.systemFont : null]} accessibilityRole="header">
                Review and confirm
              </Text>
              <View style={styles.brandIdentifier}>
                <Image
                  source={require("../../../web/src/assets/images/exaearn-logo.png")}
                  style={styles.brandLogo}
                  accessibilityLabel="ExaPay"
                />
                <Text style={styles.brandCaption}>EXAPAY</Text>
              </View>
            </View>

            <PaymentAmountCard draft={draft} fontsReady={fontsReady} />
            <PaymentParticipantCard draft={draft} />
            <PaymentDetailsCard
              draft={draft}
              totalMinor={totalMinor}
              message={message}
              onMessageChange={setMessage}
              isSubmitting={isSubmitting}
            />

            {draft.channel === "online" ? (
              <View style={styles.safetyNotice}>
                <Ionicons name="information-circle-outline" size={17} color="#e8b866" />
                <Text style={styles.safetyText}>
                  Online transfer is unavailable in this build. No live fee quote or backend payment service is connected, so confirmation is disabled.
                </Text>
              </View>
            ) : null}

            {isOffline ? (
              <View style={styles.offlineNotice}>
                <View style={styles.offlineIcon}><Ionicons name="radio-outline" size={18} color="#b5a2ed" /></View>
                <View style={styles.offlineCopy}>
                  <Text style={styles.offlineTitle}>Offline payment status</Text>
                  <Text style={styles.offlineText}>A successful local commit is pending reconciliation and is not online settlement.</Text>
                </View>
              </View>
            ) : null}

            {!validation.valid || error ? (
              <View style={styles.errorCard} accessibilityRole="alert">
                <Ionicons name="alert-circle-outline" size={17} color="#f08c8c" />
                <Text style={styles.errorText}>
                  {error || "This payment draft is invalid or expired and cannot be confirmed."}
                </Text>
              </View>
            ) : null}
          </View>
        </ScrollView>

        <View style={[styles.footer, { paddingBottom: Math.max(insets.bottom, 14) + 10 }]}>
          <PaymentConfirmationButton
            canConfirm={canConfirm}
            isSubmitting={isSubmitting}
            isOffline={isOffline}
            onPress={() => void confirmPayment()}
          />
          <AnimatedPressable
            style={styles.cancelButton}
            onPress={onCancel}
            disabled={isSubmitting}
            accessibilityLabel="Cancel payment"
          >
            <Text style={styles.cancelText}>Cancel</Text>
          </AnimatedPressable>
        </View>
      </KeyboardAvoidingView>

      <AuthorizationSheet
        visible={isSubmitting}
        stage={stage}
        onCancel={() => { cancellationRequested.current = true; }}
        bottomInset={insets.bottom}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: "#090b12" },
  scrollContent: { alignItems: "center", paddingHorizontal: 16 },
  shell: { alignSelf: "center" },
  header: { minHeight: 50, flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginBottom: 18 },
  backButton: { width: 44, height: 44, alignItems: "center", justifyContent: "center", borderRadius: 13, borderWidth: 1, borderColor: "rgba(224,232,241,0.1)", backgroundColor: "#111720" },
  headerTitle: { color: "#eef2f7", fontFamily: fonts.display, fontSize: 17 },
  systemFont: { fontFamily: undefined },
  brandIdentifier: { minWidth: 68, height: 44, flexDirection: "row", alignItems: "center", justifyContent: "flex-end", gap: 5 },
  brandLogo: { width: 26, height: 26, borderRadius: 8, resizeMode: "contain" },
  brandCaption: { color: "#cdb66e", fontFamily: fonts.semibold, fontSize: 8, letterSpacing: 0.6 },
  amountCard: { minHeight: 188, alignItems: "center", justifyContent: "center", borderRadius: 17, borderWidth: 1, borderColor: "rgba(224,232,241,0.09)", backgroundColor: "#111720", marginBottom: 12 },
  amountIcon: { width: 42, height: 42, alignItems: "center", justifyContent: "center", borderRadius: 13, borderWidth: 1, borderColor: "rgba(224,232,241,0.12)", backgroundColor: "rgba(224,232,241,0.04)", marginBottom: 12 },
  amount: { color: "#f2f5f8", fontFamily: fonts.display, fontSize: 34, letterSpacing: -1 },
  assetLabel: { marginTop: 5, color: "#929dad", fontFamily: fonts.medium, fontSize: 11 },
  simulationBadge: { minHeight: 24, flexDirection: "row", alignItems: "center", gap: 6, borderRadius: 99, borderWidth: 1, borderColor: "rgba(232,184,102,0.18)", backgroundColor: "rgba(232,184,102,0.05)", marginTop: 12, paddingHorizontal: 8 },
  simulationDot: { width: 6, height: 6, borderRadius: 3, backgroundColor: "#e8b866" },
  simulationBadgeText: { color: "#dac28e", fontFamily: fonts.semibold, fontSize: 9 },
  participantCard: { borderRadius: 15, borderWidth: 1, borderColor: "rgba(224,232,241,0.09)", backgroundColor: "#111720", paddingHorizontal: 13, paddingVertical: 12, marginBottom: 12 },
  cardEyebrow: { color: "#7f8a99", fontFamily: fonts.semibold, fontSize: 8, letterSpacing: 1.2, marginBottom: 7 },
  participantRow: { minHeight: 58, flexDirection: "row", alignItems: "center", gap: 10 },
  sourceIcon: { width: 38, height: 38, alignItems: "center", justifyContent: "center", borderRadius: 11, backgroundColor: "rgba(98,199,232,0.09)" },
  avatar: { width: 38, height: 38, alignItems: "center", justifyContent: "center", borderRadius: 19, backgroundColor: "#242a35", borderWidth: 1, borderColor: "rgba(224,232,241,0.11)" },
  avatarText: { color: "#e3e8ef", fontFamily: fonts.semibold, fontSize: 11 },
  participantCopy: { flex: 1 },
  participantName: { color: "#edf1f6", fontFamily: fonts.semibold, fontSize: 12 },
  participantDetail: { color: "#929dad", fontFamily: fonts.body, fontSize: 9, marginTop: 4 },
  participantDivider: { height: 1, backgroundColor: "rgba(255,255,255,0.06)", marginVertical: 8 },
  detailsCard: { borderRadius: 15, borderWidth: 1, borderColor: "rgba(224,232,241,0.09)", backgroundColor: "#111720", paddingHorizontal: 13, marginBottom: 12 },
  detailRow: { minHeight: 48, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 12, borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.06)" },
  detailLast: { borderBottomWidth: 0 },
  detailLabel: { color: "#929dad", fontFamily: fonts.body, fontSize: 10 },
  detailValue: { flexShrink: 1, maxWidth: "58%", color: "#dde4ec", fontFamily: fonts.medium, fontSize: 10, textAlign: "right" },
  messageRow: { minHeight: 58, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 10, borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.06)" },
  messageInput: { width: "58%", minHeight: 44, borderRadius: 9, borderWidth: 1, borderColor: "rgba(224,232,241,0.08)", backgroundColor: "#0d131b", color: "#dde4ec", paddingHorizontal: 9, fontFamily: fonts.body, fontSize: 10, textAlign: "right" },
  safetyNotice: { flexDirection: "row", alignItems: "flex-start", gap: 8, borderRadius: 12, borderWidth: 1, borderColor: "rgba(232,184,102,0.16)", backgroundColor: "rgba(232,184,102,0.05)", padding: 11, marginBottom: 12 },
  safetyText: { flex: 1, color: "#cdbb98", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  offlineNotice: { flexDirection: "row", alignItems: "flex-start", gap: 9, borderRadius: 12, borderWidth: 1, borderColor: "rgba(170,145,240,0.15)", backgroundColor: "rgba(170,145,240,0.045)", padding: 11, marginBottom: 12 },
  offlineIcon: { width: 32, height: 32, alignItems: "center", justifyContent: "center", borderRadius: 10, backgroundColor: "rgba(170,145,240,0.09)" },
  offlineCopy: { flex: 1 },
  offlineTitle: { color: "#dcd5ed", fontFamily: fonts.semibold, fontSize: 10 },
  offlineText: { color: "#a9a3b7", fontFamily: fonts.body, fontSize: 9, lineHeight: 14, marginTop: 3 },
  errorCard: { flexDirection: "row", alignItems: "flex-start", gap: 8, borderRadius: 11, borderWidth: 1, borderColor: "rgba(240,140,140,0.18)", backgroundColor: "rgba(240,140,140,0.055)", padding: 10 },
  errorText: { flex: 1, color: "#e4aaaa", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  footer: { position: "absolute", left: 0, right: 0, bottom: 0, alignItems: "center", borderTopWidth: 1, borderTopColor: "rgba(255,255,255,0.07)", backgroundColor: "#090b12", paddingHorizontal: 16, paddingTop: 10 },
  confirmButton: { width: "100%", maxWidth: 520, minHeight: 50, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 8, borderRadius: 13, backgroundColor: colors.auric300 },
  confirmDisabled: { backgroundColor: "#232a33" },
  confirmText: { color: "#17140c", fontFamily: fonts.semibold, fontSize: 12 },
  confirmTextDisabled: { color: "#7f8792" },
  cancelButton: { minHeight: 36, alignItems: "center", justifyContent: "center", marginTop: 3 },
  cancelText: { color: "#98a3b1", fontFamily: fonts.medium, fontSize: 10 },
  progressBackdrop: { flex: 1, alignItems: "center", justifyContent: "center", backgroundColor: "rgba(0,0,0,0.68)", padding: 20 },
  progressCard: { width: "100%", maxWidth: 430, borderRadius: 17, borderWidth: 1, borderColor: "rgba(224,232,241,0.12)", backgroundColor: "#111720", padding: 16 },
  progressHeader: { flexDirection: "row", alignItems: "center", gap: 10 },
  progressSpinner: { width: 34, height: 34, alignItems: "center", justifyContent: "center", borderRadius: 10, backgroundColor: "rgba(232,184,102,0.08)" },
  progressCopy: { flex: 1 },
  progressTitle: { color: "#edf1f6", fontFamily: fonts.semibold, fontSize: 12 },
  progressSubtitle: { color: "#929dad", fontFamily: fonts.body, fontSize: 9, marginTop: 3 },
  progressSteps: { gap: 10, marginTop: 16 },
  progressStep: { minHeight: 25, flexDirection: "row", alignItems: "center", gap: 9 },
  progressDot: { width: 17, height: 17, alignItems: "center", justifyContent: "center", borderRadius: 9, borderWidth: 1, borderColor: "rgba(255,255,255,0.18)" },
  progressDotComplete: { backgroundColor: colors.auric300, borderColor: colors.auric300 },
  progressDotActive: { borderColor: colors.auric300 },
  progressStepText: { color: "#8490a0", fontFamily: fonts.body, fontSize: 9 },
  progressStepTextActive: { color: "#e6d39a", fontFamily: fonts.semibold },
  progressCancel: { minHeight: 44, alignItems: "center", justifyContent: "center", borderRadius: 10, borderWidth: 1, borderColor: "rgba(224,232,241,0.12)", marginTop: 15 },
  progressCancelText: { color: "#b7c0cc", fontFamily: fonts.medium, fontSize: 10 },
});
