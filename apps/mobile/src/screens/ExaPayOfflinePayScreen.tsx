import { Ionicons } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
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
import { useAuth } from "../context/AuthContext";

import { AnimatedPressable } from "../components/AnimatedPressable";
import {
  createPaymentReference,
  type ExaPayPaymentDraft,
} from "../services/exaPayPaymentReviewModel";
import { colors, fonts } from "../theme/colors";
import {
  createExaPayOfflineSimulator,
  createSimulatedTransferReference,
  formatSimulatedAmount,
  MAX_SIMULATED_PAYMENT_MINOR,
  parseSimulatedAmount,
  type OfflinePaymentMethod,
  type OfflinePaymentOutcome,
  type OfflinePaymentScenario,
  type OfflinePaymentStage,
  type SimulatedRecipientDevice,
} from "../services/exaPayOfflineSimulator";

type ExaPayOfflinePayScreenProps = {
  fontsReady: boolean;
  initialMethod: OfflinePaymentMethod;
  initialDraft?: ExaPayPaymentDraft | null;
  onBack: () => void;
  onReview: (draft: ExaPayPaymentDraft) => void;
};

type SheetName = "recipient" | "deviceInfo" | "scenario" | null;

const paymentMethods: { id: OfflinePaymentMethod; label: string; icon: keyof typeof Ionicons.glyphMap }[] = [
  { id: "bluetooth", label: "Bluetooth", icon: "bluetooth-outline" },
  { id: "qr", label: "QR", icon: "qr-code-outline" },
  { id: "nearby", label: "Nearby Devices", icon: "radio-outline" },
];

const stageLabels: Record<OfflinePaymentStage, string> = {
  connecting: "Connecting",
  authenticating: "Authenticating",
  "preparing-transfer": "Preparing transfer",
  "awaiting-recipient": "Awaiting recipient",
  "committing-offline": "Committing offline",
};

const outcomeCopy: Record<OfflinePaymentOutcome, { title: string; description: string; tone: "success" | "warning" | "danger" | "neutral" }> = {
  "offline-committed-simulation": {
    title: "Offline committed — Simulation",
    description: "The simulator recorded this transfer locally. This is not real-money offline finality; reconciliation is required before value is available online.",
    tone: "success",
  },
  "insufficient-balance": {
    title: "Not enough simulated value",
    description: "The available sample balance changed before commitment. No transfer was recorded.",
    tone: "warning",
  },
  "invalid-amount": {
    title: "Invalid simulated amount",
    description: "The simulator rejected the amount. No transfer was recorded.",
    tone: "warning",
  },
  "recipient-unverified": {
    title: "Recipient is not verified",
    description: "The sample recipient did not pass the explicit simulation verification step.",
    tone: "warning",
  },
  cancelled: {
    title: "Transfer cancelled",
    description: "The simulated transfer was cancelled before offline commitment. No simulated balance was deducted.",
    tone: "neutral",
  },
  "recipient-rejected": {
    title: "Recipient declined",
    description: "The simulated recipient did not accept the transfer. No simulated balance was deducted.",
    tone: "warning",
  },
  "authentication-failed": {
    title: "Device authentication failed",
    description: "The simulator could not authenticate this device session. No transfer was committed.",
    tone: "danger",
  },
  "secure-storage-unavailable": {
    title: "Secure storage unavailable",
    description: "This device cannot provide the encrypted local storage required for the simulation journal. No offline transfer was started.",
    tone: "warning",
  },
  interrupted: {
    title: "Connection interrupted",
    description: "The simulated connection ended before commitment. No transfer was marked as successful.",
    tone: "warning",
  },
  "timed-out": {
    title: "Recipient timed out",
    description: "The simulated recipient did not respond in time. No transfer was committed.",
    tone: "warning",
  },
  "recovery-required": {
    title: "Recovery required",
    description: "The transfer state is uncertain at the commit step. Do not retry; reconciliation is required before another transfer.",
    tone: "danger",
  },
};

const simulationScenarios: { id: OfflinePaymentScenario; label: string; detail: string }[] = [
  { id: "success", label: "Complete successfully", detail: "Simulation only" },
  { id: "recipient-rejected", label: "Recipient rejects", detail: "No value committed" },
  { id: "authentication-failed", label: "Authentication fails", detail: "No value committed" },
  { id: "connection-interrupted", label: "Connection interrupted", detail: "Before commitment" },
  { id: "timeout", label: "Recipient times out", detail: "No value committed" },
  { id: "recovery-required", label: "Commit becomes uncertain", detail: "Recovery required" },
];

function amountErrorMessage(reason: string, balanceMinor: number) {
  const messages: Record<string, string> = {
    empty: "Enter an amount to continue.",
    invalid: "Enter a valid amount using numbers and a decimal point.",
    negative: "Amount must be greater than zero.",
    zero: "Amount must be greater than zero.",
    precision: "Use no more than 2 decimal places for simUSDT.",
    maximum: "The simulation limit is 100.00 simUSDT per transfer.",
    insufficient: `Available offline balance is ${formatSimulatedAmount(balanceMinor)} simUSDT.`,
  };
  return messages[reason] ?? "Check the amount and try again.";
}

function BottomSheet({
  visible,
  title,
  onClose,
  insetsBottom,
  children,
}: {
  visible: boolean;
  title: string;
  onClose: () => void;
  insetsBottom: number;
  children: ReactNode;
}) {
  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
      <View style={styles.sheetBackdrop}>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Close sheet"
          style={StyleSheet.absoluteFill}
          onPress={onClose}
        />
        <View style={[styles.sheet, { paddingBottom: Math.max(insetsBottom, 16) + 16 }]} accessibilityViewIsModal>
          <View style={styles.sheetHandle} />
          <View style={styles.sheetHeader}>
            <Text style={styles.sheetTitle}>{title}</Text>
            <AnimatedPressable style={styles.sheetClose} onPress={onClose} accessibilityLabel="Close">
              <Ionicons name="close" size={19} color="#c6ced9" />
            </AnimatedPressable>
          </View>
          {children}
        </View>
      </View>
    </Modal>
  );
}

function MethodSelector({
  selected,
  onSelect,
}: {
  selected: OfflinePaymentMethod;
  onSelect: (method: OfflinePaymentMethod) => void;
}) {
  return (
    <View style={styles.methodSelector} accessibilityRole="tablist">
      {paymentMethods.map((method) => (
        <Pressable
          key={method.id}
          accessibilityRole="tab"
          accessibilityState={{ selected: selected === method.id }}
          onPress={() => onSelect(method.id)}
          style={[styles.methodTab, selected === method.id ? styles.methodTabSelected : null]}
        >
          <Ionicons
            name={method.icon}
            size={17}
            color={selected === method.id ? colors.auric300 : "#939eac"}
          />
          <Text style={[styles.methodLabel, selected === method.id ? styles.methodLabelSelected : null]}>
            {method.label}
          </Text>
        </Pressable>
      ))}
    </View>
  );
}

function DeviceStatus({ label, tone }: { label: string; tone: "pending" | "simulation" | "unavailable" }) {
  const dotColor = tone === "simulation" ? "#a991f0" : tone === "unavailable" ? "#e38c8c" : "#e8b866";
  return (
    <View style={[styles.deviceStatus, tone === "simulation" ? styles.deviceStatusSimulation : null]}>
      <View style={[styles.deviceStatusDot, { backgroundColor: dotColor }]} />
      <Text style={styles.deviceStatusText}>{label}</Text>
    </View>
  );
}

function DetailRow({ label, value, last = false }: { label: string; value: string; last?: boolean }) {
  return (
    <View style={[styles.detailRow, last ? styles.detailRowLast : null]}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue}>{value}</Text>
    </View>
  );
}

function ProgressSheet({
  visible,
  stage,
  onCancel,
  insetsBottom,
}: {
  visible: boolean;
  stage: OfflinePaymentStage | null;
  onCancel: () => void;
  insetsBottom: number;
}) {
  const stageOrder: OfflinePaymentStage[] = [
    "connecting",
    "authenticating",
    "preparing-transfer",
    "awaiting-recipient",
    "committing-offline",
  ];
  const activeIndex = stage ? stageOrder.indexOf(stage) : -1;

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onCancel}>
      <View style={styles.progressBackdrop}>
        <View style={[styles.progressCard, { marginBottom: Math.max(insetsBottom, 12) }]}>
          <View style={styles.progressHeading}>
            <View style={styles.progressSpinner}>
              <ActivityIndicator size="small" color={colors.auric300} />
            </View>
            <View style={styles.progressHeadingCopy}>
              <Text style={styles.progressTitle}>Offline Pay simulation</Text>
              <Text style={styles.progressSubtitle}>No Bluetooth radio or real funds are in use.</Text>
            </View>
          </View>
          <View style={styles.progressSteps}>
            {stageOrder.map((item, index) => {
              const complete = index < activeIndex;
              const current = index === activeIndex;
              return (
                <View key={item} style={styles.progressStep}>
                  <View style={[
                    styles.progressStepIcon,
                    complete ? styles.progressStepDone : null,
                    current ? styles.progressStepCurrent : null,
                  ]}>
                    {complete ? (
                      <Ionicons name="checkmark" size={13} color="#08090b" />
                    ) : current ? (
                      <ActivityIndicator size="small" color={colors.auric300} />
                    ) : (
                      <View style={styles.progressStepEmpty} />
                    )}
                  </View>
                  <Text style={[styles.progressStepText, current ? styles.progressStepTextCurrent : null]}>
                    {stageLabels[item]}
                  </Text>
                </View>
              );
            })}
          </View>
          <AnimatedPressable style={styles.cancelButton} onPress={onCancel}>
            <Text style={styles.cancelButtonText}>Cancel transfer</Text>
          </AnimatedPressable>
        </View>
      </View>
    </Modal>
  );
}

export default function ExaPayOfflinePayScreen({
  fontsReady,
  initialMethod,
  initialDraft,
  onBack,
  onReview,
}: ExaPayOfflinePayScreenProps) {
  const insets = useSafeAreaInsets();
  const { user } = useAuth();
  const accountId = user?.id === undefined ? "" : String(user.id);
  const simulator = useMemo(() => createExaPayOfflineSimulator(accountId), [accountId]);
  const initialOfflineDraft = initialDraft?.channel === "bluetooth" ? initialDraft : null;
  const [method, setMethod] = useState<OfflinePaymentMethod>(initialMethod);
  const [devices, setDevices] = useState<SimulatedRecipientDevice[]>([]);
  const [recipientId, setRecipientId] = useState(
    initialOfflineDraft?.offlineTransfer?.recipient.id ?? "sim-device-mathias",
  );
  const [verifiedDevices, setVerifiedDevices] = useState<Record<string, boolean>>(() => (
    initialOfflineDraft?.offlineTransfer
      ? { [initialOfflineDraft.offlineTransfer.recipient.id]: true }
      : {}
  ));
  const [verifyingDeviceId, setVerifyingDeviceId] = useState("");
  const [deviceError, setDeviceError] = useState("");
  const [isDiscovering, setIsDiscovering] = useState(false);
  const [discoveryError, setDiscoveryError] = useState("");
  const [amount, setAmount] = useState(
    initialOfflineDraft ? formatSimulatedAmount(initialOfflineDraft.amountMinor) : "30.00",
  );
  const [amountTouched, setAmountTouched] = useState(false);
  const [attemptedReview, setAttemptedReview] = useState(false);
  const [balanceState, setBalanceState] = useState({ accountId: "", balanceMinor: 0 });
  const [recoveryState, setRecoveryState] = useState({ accountId: "", blocked: true });
  const [isDetailsExpanded, setIsDetailsExpanded] = useState(false);
  const [sheet, setSheet] = useState<SheetName>(null);
  const [scenario, setScenario] = useState<OfflinePaymentScenario>(
    initialOfflineDraft?.offlineTransfer?.scenario ?? "success",
  );
  const [transferReference, setTransferReference] = useState(
    () => initialOfflineDraft?.reference ?? createSimulatedTransferReference(),
  );
  const [deviceAuthenticated, setDeviceAuthenticated] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [activeStage, setActiveStage] = useState<OfflinePaymentStage | null>(null);
  const [paymentResult, setPaymentResult] = useState<{
    outcome: OfflinePaymentOutcome;
    reference: string;
    amountMinor: number;
  } | null>(null);
  const [formError, setFormError] = useState("");
  const cancelRequested = useRef(false);

  const selectedRecipient = useMemo(
    () => devices.find((device) => device.id === recipientId) ?? null,
    [devices, recipientId],
  );
  const recipientVerified = Boolean(selectedRecipient && verifiedDevices[selectedRecipient.id]);
  const availableBalanceMinor = balanceState.accountId === accountId ? balanceState.balanceMinor : 0;
  const recoveryBlocked = recoveryState.accountId !== accountId || recoveryState.blocked;
  const parsedAmount = useMemo(
    () => parseSimulatedAmount(amount, availableBalanceMinor),
    [amount, availableBalanceMinor],
  );
  const remainingBalanceMinor = paymentResult?.outcome === "offline-committed-simulation"
    ? availableBalanceMinor
    : parsedAmount.valid
      ? availableBalanceMinor - parsedAmount.amountMinor
      : null;
  const shouldShowAmountError = amountTouched || attemptedReview;
  const amountError = shouldShowAmountError && !parsedAmount.valid
    ? amountErrorMessage(parsedAmount.reason, availableBalanceMinor)
    : "";
  const selectedScenario = simulationScenarios.find((item) => item.id === scenario) ?? simulationScenarios[0];
  const resultPresentation = paymentResult ? outcomeCopy[paymentResult.outcome] : null;
  const mustStopForRecovery = recoveryBlocked || paymentResult?.outcome === "recovery-required";

  useEffect(() => {
    let active = true;
    void simulator.loadState().then((state) => {
      if (!active) return;
      setBalanceState({ accountId, balanceMinor: state.balanceMinor });
      setRecoveryState({ accountId, blocked: state.recoveryRequired || !state.supported });
    });
    simulator.discoverNearbyDevices()
      .then((found) => {
        if (active) setDevices(found);
      })
      .catch(() => {
        if (active) setDeviceError("Sample recipient devices could not be loaded. Try again.");
      });
    return () => {
      active = false;
    };
  }, [simulator]);

  useEffect(() => {
    const subscription = BackHandler.addEventListener("hardwareBackPress", () => {
      if (isProcessing) {
        cancelRequested.current = true;
        return true;
      }
      if (sheet) {
        setSheet(null);
        return true;
      }
      onBack();
      return true;
    });
    return () => subscription.remove();
  }, [isProcessing, onBack, sheet]);

  async function discoverDevices() {
    setIsDiscovering(true);
    setDiscoveryError("");
    try {
      const found = await simulator.discoverNearbyDevices();
      setDevices(found);
    } catch {
      setDiscoveryError("Nearby sample devices could not be loaded. Try again.");
    } finally {
      setIsDiscovering(false);
    }
  }

  async function verifySelectedRecipient() {
    if (!selectedRecipient || !selectedRecipient.available || verifyingDeviceId) return;
    setVerifyingDeviceId(selectedRecipient.id);
    setDeviceError("");
    try {
      const result = await simulator.verifyRecipient(selectedRecipient);
      if (!result.verified) {
        setDeviceError("This sample device is unavailable and cannot be verified.");
        return;
      }
      setVerifiedDevices((previous) => ({ ...previous, [selectedRecipient.id]: true }));
    } catch {
      setDeviceError("The simulation could not verify this recipient.");
    } finally {
      setVerifyingDeviceId("");
    }
  }

  function chooseRecipient(device: SimulatedRecipientDevice) {
    setRecipientId(device.id);
    setDeviceAuthenticated(false);
    setFormError("");
    setSheet(null);
  }

  function openReview() {
    setAttemptedReview(true);
    setFormError("");
    if (method === "qr") {
      setFormError("QR payments are not connected in this mobile build. No camera was opened.");
      return;
    }
    if (!selectedRecipient) {
      setFormError("Select a sample recipient before continuing.");
      return;
    }
    if (!selectedRecipient.available) {
      setFormError("This recipient is unavailable. Choose an available sample device.");
      return;
    }
    if (!recipientVerified) {
      setFormError("Verify the recipient for this simulation before continuing.");
      return;
    }
    if (!parsedAmount.valid) {
      setFormError(amountErrorMessage(parsedAmount.reason, availableBalanceMinor));
      return;
    }
    if (mustStopForRecovery) {
      setFormError("A previous transfer requires recovery. Do not submit another transfer.");
      return;
    }
    const reference = createSimulatedTransferReference();
    setTransferReference(reference);
    setDeviceAuthenticated(false);
    onReview({
      idempotencyKey: `IDEM-${createPaymentReference("BT")}`,
      reference,
      channel: "bluetooth",
      sourceAccount: { label: "ExaEarn Offline Wallet" },
      recipient: {
        id: selectedRecipient.id,
        displayName: selectedRecipient.name,
        identifier: selectedRecipient.shortId,
        initials: selectedRecipient.name
          .split(/\s+/)
          .map((part) => part[0]?.toUpperCase() ?? "")
          .join("")
          .slice(0, 2),
        verification: "simulation-verified",
      },
      amountMinor: parsedAmount.amountMinor,
      feeMinor: 0,
      asset: { symbol: "simUSDT", precision: 2, network: "Bluetooth · simulation" },
      message: "",
      createdAt: Date.now(),
      expiresAt: null,
      simulation: true,
      offlineTransfer: {
        accountId,
        recipient: selectedRecipient,
        recipientVerifiedForSimulation: recipientVerified,
        method: "bluetooth",
        scenario,
      },
    });
  }

  function startAnotherTransfer() {
    if (mustStopForRecovery) return;
    setPaymentResult(null);
    setAmount(formatSimulatedAmount(Math.min(3_000, availableBalanceMinor)));
    setAmountTouched(false);
    setAttemptedReview(false);
    setFormError("");
    setTransferReference(createSimulatedTransferReference());
  }

  function selectPaymentMethod(nextMethod: OfflinePaymentMethod) {
    setMethod(nextMethod);
    setFormError("");
    if (nextMethod === "nearby") void discoverDevices();
  }

  const connectionStatus = isProcessing && activeStage
    ? stageLabels[activeStage]
    : "Not connected";

  return (
    <View style={styles.fill}>
      <LinearGradient colors={["#090b12", "#0c1018", "#0a0d14"]} style={StyleSheet.absoluteFill} />
      <KeyboardAvoidingView
        style={styles.fill}
        behavior={Platform.OS === "ios" ? "padding" : undefined}
      >
        <ScrollView
          contentContainerStyle={[
            styles.scrollContent,
            { paddingTop: insets.top + 6, paddingBottom: Math.max(insets.bottom, 16) + 24 },
          ]}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode={Platform.OS === "ios" ? "interactive" : "on-drag"}
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.shell}>
            <View style={styles.header}>
              <AnimatedPressable style={styles.backButton} onPress={onBack} accessibilityLabel="Back to ExaPay">
                <Ionicons name="chevron-back" size={20} color="#dce3ed" />
              </AnimatedPressable>
              <View style={styles.brandLockup}>
                <Image
                  source={require("../../../web/src/assets/images/exaearn-logo.png")}
                  style={styles.brandLogo}
                  resizeMode="contain"
                  accessibilityLabel="ExaEarn logo"
                />
                <View>
                  <Text style={styles.brandName}>ExaEarn</Text>
                  <Text style={styles.brandCaption}>EXAPAY</Text>
                </View>
              </View>
              <View style={styles.headerSimulation}>
                <View style={styles.simulationDot} />
                <Text style={styles.headerSimulationText}>Simulation</Text>
              </View>
            </View>

            <View style={styles.titleArea}>
              <View style={styles.titleIcon}>
                <Ionicons name="bluetooth-outline" size={19} color="#b4a1ed" />
              </View>
              <View style={styles.titleCopy}>
                <Text style={[styles.title, !fontsReady ? styles.systemFont : null]}>Offline Pay</Text>
                <Text style={styles.subtitle}>Send value directly to a nearby device, without internet.</Text>
              </View>
            </View>

            {paymentResult && resultPresentation ? (
              <View style={[
                styles.resultCard,
                resultPresentation.tone === "success" ? styles.resultSuccess : null,
                resultPresentation.tone === "warning" ? styles.resultWarning : null,
                resultPresentation.tone === "danger" ? styles.resultDanger : null,
              ]} accessibilityLiveRegion="polite">
                <View style={styles.resultHeader}>
                  <View style={styles.resultIcon}>
                    <Ionicons
                      name={
                        resultPresentation.tone === "success"
                          ? "checkmark-circle-outline"
                          : resultPresentation.tone === "danger"
                            ? "alert-circle-outline"
                            : "information-circle-outline"
                      }
                      size={22}
                      color={
                        resultPresentation.tone === "success"
                          ? "#72d3a0"
                          : resultPresentation.tone === "danger"
                            ? "#f08c8c"
                            : "#e8b866"
                      }
                    />
                  </View>
                  <View style={styles.resultCopy}>
                    <Text style={styles.resultTitle}>{resultPresentation.title}</Text>
                    <Text style={styles.resultDescription}>{resultPresentation.description}</Text>
                  </View>
                </View>
                <View style={styles.resultMeta}>
                  <Text style={styles.resultMetaText}>Reference {paymentResult.reference}</Text>
                  <Text style={styles.resultMetaText}>
                    {formatSimulatedAmount(paymentResult.amountMinor)} simUSDT
                  </Text>
                </View>
                {!mustStopForRecovery ? (
                  <AnimatedPressable style={styles.resultAction} onPress={startAnotherTransfer}>
                    <Text style={styles.resultActionText}>Start another transfer</Text>
                  </AnimatedPressable>
                ) : null}
              </View>
            ) : null}

            <View style={styles.panel}>
              <Text style={styles.sectionTitle}>Payment method</Text>
              <MethodSelector selected={method} onSelect={selectPaymentMethod} />
              {method === "qr" ? (
                <View style={styles.methodNotice}>
                  <Ionicons name="qr-code-outline" size={18} color="#e8b866" />
                  <Text style={styles.methodNoticeText}>
                    QR payment is not connected in this mobile build. No camera or real payment flow will start.
                  </Text>
                </View>
              ) : null}
            </View>

            {method === "nearby" ? (
              <View style={styles.panel}>
                <View style={styles.sectionHeader}>
                  <View>
                    <Text style={styles.sectionTitle}>Nearby Devices</Text>
                    <Text style={styles.sectionHint}>Simulation list · no Bluetooth scan is running</Text>
                  </View>
                  <AnimatedPressable
                    style={styles.refreshButton}
                    onPress={() => void discoverDevices()}
                    disabled={isDiscovering}
                    accessibilityLabel="Refresh simulated devices"
                  >
                    {isDiscovering ? (
                      <ActivityIndicator size="small" color="#c0aff4" />
                    ) : (
                      <Ionicons name="refresh-outline" size={18} color="#c0aff4" />
                    )}
                  </AnimatedPressable>
                </View>
                {discoveryError ? <Text style={styles.inlineError}>{discoveryError}</Text> : null}
                <View style={styles.deviceList}>
                  {devices.map((device) => (
                    <DevicePickerRow
                      key={device.id}
                      device={device}
                      selected={device.id === recipientId}
                      onSelect={() => chooseRecipient(device)}
                      onInfo={() => {
                        setRecipientId(device.id);
                        setSheet("deviceInfo");
                      }}
                    />
                  ))}
                  {!devices.length && isDiscovering ? (
                    <View style={styles.loadingRow}>
                      <ActivityIndicator size="small" color="#c0aff4" />
                      <Text style={styles.loadingText}>Loading sample devices...</Text>
                    </View>
                  ) : null}
                </View>
              </View>
            ) : null}

            <View style={styles.panel}>
              <View style={styles.sectionHeader}>
                <View>
                  <Text style={styles.sectionTitle}>Recipient Device</Text>
                  <Text style={styles.sectionHint}>Sample recipient · simulation only</Text>
                </View>
                <AnimatedPressable style={styles.changeButton} onPress={() => setSheet("recipient")}>
                  <Text style={styles.changeButtonText}>Change</Text>
                  <Ionicons name="chevron-forward" size={14} color={colors.auric300} />
                </AnimatedPressable>
              </View>

              {selectedRecipient ? (
                <View style={styles.recipientCard}>
                  <View style={styles.recipientIcon}>
                    <Ionicons name="phone-portrait-outline" size={21} color="#b7a5ed" />
                  </View>
                  <View style={styles.recipientBody}>
                    <Text style={styles.recipientName}>{selectedRecipient.name}</Text>
                    <Text style={styles.recipientProduct}>{selectedRecipient.product}</Text>
                    <Text style={styles.recipientId}>ID: {selectedRecipient.shortId}</Text>
                    <View style={styles.recipientStatusRow}>
                      {recipientVerified ? (
                        <DeviceStatus label="Verified for simulation" tone="simulation" />
                      ) : (
                        <DeviceStatus label="Not verified" tone="pending" />
                      )}
                      {!selectedRecipient.available ? (
                        <DeviceStatus label="Unavailable" tone="unavailable" />
                      ) : null}
                    </View>
                  </View>
                  <AnimatedPressable
                    style={styles.recipientInfoButton}
                    onPress={() => setSheet("deviceInfo")}
                    accessibilityLabel="Device information"
                  >
                    <Ionicons name="information-circle-outline" size={20} color="#8f9aaa" />
                  </AnimatedPressable>
                </View>
              ) : (
                <View style={styles.loadingRow}>
                  <ActivityIndicator size="small" color="#c0aff4" />
                  <Text style={styles.loadingText}>Preparing sample recipient...</Text>
                </View>
              )}
              {deviceError ? <Text style={styles.inlineError}>{deviceError}</Text> : null}
              {selectedRecipient && !recipientVerified ? (
                <AnimatedPressable
                  style={[
                    styles.verifyButton,
                    (!selectedRecipient.available || Boolean(verifyingDeviceId)) ? styles.buttonDisabled : null,
                  ]}
                  onPress={() => void verifySelectedRecipient()}
                  disabled={!selectedRecipient.available || Boolean(verifyingDeviceId)}
                  accessibilityLabel="Verify this sample recipient for simulation"
                >
                  {verifyingDeviceId === selectedRecipient.id ? (
                    <ActivityIndicator size="small" color="#c9bbf3" />
                  ) : (
                    <Ionicons name="shield-checkmark-outline" size={17} color="#c9bbf3" />
                  )}
                  <Text style={styles.verifyButtonText}>Verify for simulation</Text>
                </AnimatedPressable>
              ) : null}
              <Text style={styles.simulationDisclaimer}>
                This sample identity has not been verified by a live device session.
              </Text>
            </View>

            <View style={styles.panel}>
              <View style={styles.sectionHeader}>
                <Text style={styles.sectionTitle}>Payment Amount</Text>
                <Text style={styles.simulatedAssetBadge}>SIMULATED ASSET</Text>
              </View>
              <View style={[styles.amountInputShell, amountError ? styles.amountInputError : null]}>
                <TextInput
                  value={amount}
                  onChangeText={(value) => {
                    setAmount(value);
                    setAmountTouched(true);
                    setFormError("");
                  }}
                  onBlur={() => setAmountTouched(true)}
                  keyboardType="decimal-pad"
                  inputMode="decimal"
                  maxLength={14}
                  placeholder="0.00"
                  placeholderTextColor="rgba(226,232,240,0.38)"
                  style={styles.amountInput}
                  accessibilityLabel="Payment amount in simulated USDT"
                  accessibilityHint="Enter a positive amount up to two decimal places."
                  editable={!isProcessing && !mustStopForRecovery && !paymentResult}
                />
                <Text style={styles.amountAsset}>simUSDT</Text>
                <AnimatedPressable
                  style={styles.maxButton}
                  onPress={() => {
                    setAmount(formatSimulatedAmount(Math.min(availableBalanceMinor, MAX_SIMULATED_PAYMENT_MINOR)));
                    setAmountTouched(true);
                  }}
                  disabled={!availableBalanceMinor || isProcessing || mustStopForRecovery || Boolean(paymentResult)}
                  accessibilityLabel="Use maximum available simulated balance"
                >
                  <Text style={styles.maxButtonText}>MAX</Text>
                </AnimatedPressable>
              </View>
              {amountError ? <Text style={styles.inlineError}>{amountError}</Text> : null}
              <View style={styles.balanceSummary}>
                <View>
                  <Text style={styles.balanceSummaryLabel}>Offline available balance</Text>
                  <Text style={styles.balanceSummaryValue}>
                    {formatSimulatedAmount(availableBalanceMinor)} simUSDT
                  </Text>
                </View>
                <View style={styles.balanceSummaryDivider} />
                <View style={styles.remainingBalance}>
                  <Text style={styles.balanceSummaryLabel}>Remaining after payment</Text>
                  <Text style={[
                    styles.balanceSummaryValue,
                    remainingBalanceMinor === null ? styles.valueMuted : styles.valueBlue,
                  ]}>
                    {remainingBalanceMinor === null
                      ? "--"
                      : `${formatSimulatedAmount(remainingBalanceMinor)} simUSDT`}
                  </Text>
                </View>
              </View>
              <Text style={styles.limitHint}>Maximum per simulated transfer: 100.00 simUSDT</Text>
            </View>

            <View style={styles.panel}>
              <AnimatedPressable
                style={styles.expandHeader}
                onPress={() => setIsDetailsExpanded((expanded) => !expanded)}
                accessibilityRole="button"
                accessibilityState={{ expanded: isDetailsExpanded }}
              >
                <View>
                  <Text style={styles.sectionTitle}>Transfer Details</Text>
                  <Text style={styles.sectionHint}>Reference and transfer state</Text>
                </View>
                <Ionicons
                  name={isDetailsExpanded ? "chevron-up" : "chevron-down"}
                  size={19}
                  color="#9aa5b4"
                />
              </AnimatedPressable>
              <View style={styles.detailList}>
                <DetailRow label="Transfer Reference" value={transferReference} />
                <DetailRow
                  label="Device Authentication"
                  value={deviceAuthenticated ? "Verified (simulation)" : "Not authenticated"}
                />
                <DetailRow label="Transfer Type" value="Offline device-to-device" />
                <DetailRow
                  label="Remaining Offline Balance"
                  value={remainingBalanceMinor === null
                    ? "--"
                    : `${formatSimulatedAmount(remainingBalanceMinor)} simUSDT`}
                  last={!isDetailsExpanded}
                />
                {isDetailsExpanded ? (
                  <>
                    <DetailRow label="Commit status" value={paymentResult?.outcome ?? "Not started"} />
                    <View style={styles.scenarioControl}>
                      <View style={styles.scenarioCopy}>
                        <Text style={styles.detailLabel}>Simulation scenario</Text>
                        <Text style={styles.scenarioSelected}>{selectedScenario.label}</Text>
                      </View>
                      <AnimatedPressable
                        style={styles.scenarioButton}
                        onPress={() => setSheet("scenario")}
                        accessibilityLabel="Choose simulation scenario"
                      >
                        <Text style={styles.scenarioButtonText}>Change</Text>
                      </AnimatedPressable>
                    </View>
                    <Text style={styles.technicalNote}>
                      Simulation references are temporary and do not represent a signed or settled ledger record.
                    </Text>
                  </>
                ) : null}
              </View>
            </View>

            <View style={styles.connectionCard}>
              <View style={styles.connectionHeader}>
                <View style={styles.connectionIcon}>
                  <Ionicons name="bluetooth-outline" size={20} color="#b5a2ed" />
                </View>
                <View style={styles.connectionCopy}>
                  <Text style={styles.connectionTitle}>Offline Transfer</Text>
                  <Text style={styles.connectionSubtitle}>
                    Bluetooth can communicate without internet. This screen uses a simulator; no Bluetooth transfer is active.
                  </Text>
                </View>
              </View>
              <View style={styles.connectionStateList}>
                <View style={styles.connectionStateRow}>
                  <Text style={styles.connectionStateLabel}>Bluetooth status</Text>
                  <Text style={[styles.connectionStateValue, isProcessing ? styles.connectionConnecting : null]}>
                    {connectionStatus}{isProcessing ? " · Simulation" : " · Not connected"}
                  </Text>
                </View>
                <View style={styles.connectionStateRow}>
                  <Text style={styles.connectionStateLabel}>Recipient identity</Text>
                  <Text style={styles.connectionStateValue}>
                    {recipientVerified ? "Verified for simulation only" : "Not verified"}
                  </Text>
                </View>
              </View>
              <Text style={styles.reconciliationNote}>
                Device communication is not monetary finality. Any future real transfer would require the supported protocol and reconciliation before value becomes available online.
              </Text>
            </View>

            {formError ? (
              <View style={styles.formError} accessibilityLiveRegion="polite">
                <Ionicons name="alert-circle-outline" size={17} color="#f08c8c" />
                <Text style={styles.formErrorText}>{formError}</Text>
              </View>
            ) : null}

            {mustStopForRecovery ? (
              <View style={styles.recoveryLock}>
                <Ionicons name="lock-closed-outline" size={17} color="#f08c8c" />
                <Text style={styles.recoveryLockText}>
                  Further transfers are blocked on this screen until the uncertain state is reconciled.
                </Text>
              </View>
            ) : null}

            <AnimatedPressable
              style={[styles.confirmButton, (isProcessing || mustStopForRecovery || paymentResult) ? styles.buttonDisabled : null]}
              onPress={openReview}
              disabled={isProcessing || mustStopForRecovery || Boolean(paymentResult)}
              accessibilityLabel="Review simulated payment"
            >
              <Ionicons name="arrow-forward" size={18} color="#17140c" />
              <Text style={styles.confirmButtonText}>Review Payment</Text>
            </AnimatedPressable>
            <AnimatedPressable style={styles.cancelPageButton} onPress={onBack} disabled={isProcessing}>
              <Text style={styles.cancelPageButtonText}>Cancel</Text>
            </AnimatedPressable>
            <Text style={styles.pageFootnote}>
              Simulation only. No real USDT, wallet ledger, or Bluetooth device is used.
            </Text>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>

      <BottomSheet
        visible={sheet === "recipient"}
        title="Choose a recipient"
        onClose={() => setSheet(null)}
        insetsBottom={insets.bottom}
      >
        <Text style={styles.sheetDescription}>
          These sample entries do not come from Bluetooth discovery.
        </Text>
        <View style={styles.deviceList}>
          {devices.map((device) => (
            <DevicePickerRow
              key={device.id}
              device={device}
              selected={device.id === recipientId}
              onSelect={() => chooseRecipient(device)}
              onInfo={() => {
                setRecipientId(device.id);
                setSheet("deviceInfo");
              }}
            />
          ))}
        </View>
      </BottomSheet>

      <BottomSheet
        visible={sheet === "deviceInfo"}
        title="Device information"
        onClose={() => setSheet(null)}
        insetsBottom={insets.bottom}
      >
        {selectedRecipient ? (
          <View style={styles.deviceInfoPanel}>
            <Text style={styles.deviceInfoName}>{selectedRecipient.name}</Text>
            <Text style={styles.deviceInfoProduct}>{selectedRecipient.product}</Text>
            <DetailRow label="Sample device ID" value={selectedRecipient.shortId} />
            <DetailRow
              label="Availability"
              value={selectedRecipient.available ? "Available in simulation" : "Unavailable in simulation"}
            />
            <DetailRow
              label="Verification"
              value={verifiedDevices[selectedRecipient.id] ? "Verified for simulation" : "Not verified"}
              last
            />
            <Text style={styles.deviceInfoWarning}>
              This is sample data. Device availability and verification are not backed by a live Bluetooth session.
            </Text>
          </View>
        ) : null}
      </BottomSheet>

      <BottomSheet
        visible={sheet === "scenario"}
        title="Simulation outcome"
        onClose={() => setSheet(null)}
        insetsBottom={insets.bottom}
      >
        <Text style={styles.sheetDescription}>
          Choose a test result to preview transfer handling. These outcomes do not contact a device.
        </Text>
        <View style={styles.scenarioOptions}>
          {simulationScenarios.map((option) => (
            <AnimatedPressable
              key={option.id}
              style={[styles.scenarioOption, scenario === option.id ? styles.scenarioOptionSelected : null]}
              onPress={() => {
                setScenario(option.id);
                setSheet(null);
              }}
              accessibilityState={{ selected: scenario === option.id }}
            >
              <View style={styles.scenarioOptionCopy}>
                <Text style={styles.scenarioOptionTitle}>{option.label}</Text>
                <Text style={styles.scenarioOptionDetail}>{option.detail}</Text>
              </View>
              {scenario === option.id ? (
                <Ionicons name="checkmark-circle" size={19} color={colors.auric300} />
              ) : null}
            </AnimatedPressable>
          ))}
        </View>
      </BottomSheet>

      <ProgressSheet
        visible={isProcessing}
        stage={activeStage}
        onCancel={() => {
          cancelRequested.current = true;
        }}
        insetsBottom={insets.bottom}
      />
    </View>
  );
}

function DevicePickerRow({
  device,
  selected,
  onSelect,
  onInfo,
}: {
  device: SimulatedRecipientDevice;
  selected: boolean;
  onSelect: () => void;
  onInfo: () => void;
}) {
  return (
    <View style={[styles.devicePickerRow, selected ? styles.devicePickerSelected : null]}>
      <AnimatedPressable
        style={styles.devicePickerMain}
        onPress={onSelect}
        disabled={!device.available}
        accessibilityLabel={`Select ${device.name}${device.available ? "" : ", unavailable"}`}
      >
        <View style={styles.devicePickerIcon}>
          <Ionicons name="phone-portrait-outline" size={18} color="#b7a5ed" />
        </View>
        <View style={styles.devicePickerCopy}>
          <Text style={styles.devicePickerName}>{device.name}</Text>
          <Text style={styles.devicePickerMeta}>{device.shortId} · Simulation</Text>
        </View>
        <DeviceStatus
          label={device.available ? "Sample" : "Unavailable"}
          tone={device.available ? "simulation" : "unavailable"}
        />
      </AnimatedPressable>
      <AnimatedPressable style={styles.devicePickerInfo} onPress={onInfo} accessibilityLabel={`Details for ${device.name}`}>
        <Ionicons name="information-circle-outline" size={19} color="#919dad" />
      </AnimatedPressable>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: {
    flex: 1,
    backgroundColor: "#090b12",
  },
  scrollContent: {
    alignItems: "center",
    flexGrow: 1,
  },
  shell: {
    width: "100%",
    maxWidth: 560,
    alignSelf: "center",
    paddingHorizontal: 16,
    paddingBottom: 8,
  },
  header: {
    minHeight: 46,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    marginBottom: 20,
  },
  backButton: {
    width: 42,
    height: 42,
    borderRadius: 13,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.09)",
    backgroundColor: "#111722",
    alignItems: "center",
    justifyContent: "center",
  },
  brandLockup: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    gap: 9,
  },
  brandLogo: {
    width: 32,
    height: 32,
  },
  brandName: {
    color: "#e7ebf1",
    fontFamily: fonts.semibold,
    fontSize: 12,
  },
  brandCaption: {
    marginTop: 1,
    color: colors.auric300,
    fontFamily: fonts.semibold,
    fontSize: 8,
    letterSpacing: 1.3,
  },
  headerSimulation: {
    minHeight: 27,
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    borderWidth: 1,
    borderColor: "rgba(232,184,102,0.22)",
    borderRadius: 99,
    backgroundColor: "rgba(232,184,102,0.07)",
    paddingHorizontal: 9,
  },
  simulationDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: "#e8b866",
  },
  headerSimulationText: {
    color: "#e7c68e",
    fontFamily: fonts.medium,
    fontSize: 9,
  },
  titleArea: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 12,
    marginBottom: 22,
  },
  titleIcon: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: "rgba(170,145,240,0.12)",
    alignItems: "center",
    justifyContent: "center",
    marginTop: 1,
  },
  titleCopy: {
    flex: 1,
  },
  title: {
    color: "#f3f5f8",
    fontFamily: fonts.display,
    fontSize: 25,
    lineHeight: 32,
  },
  systemFont: {
    fontFamily: undefined,
  },
  subtitle: {
    marginTop: 3,
    color: "#a1aab8",
    fontFamily: fonts.body,
    fontSize: 12,
    lineHeight: 17,
  },
  panel: {
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.085)",
    borderRadius: 16,
    backgroundColor: "#111722",
    marginBottom: 12,
    padding: 14,
  },
  sectionHeader: {
    minHeight: 31,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 10,
    marginBottom: 11,
  },
  sectionTitle: {
    color: "#e7ebf1",
    fontFamily: fonts.semibold,
    fontSize: 13,
  },
  sectionHint: {
    marginTop: 3,
    color: "#8995a5",
    fontFamily: fonts.body,
    fontSize: 10,
    lineHeight: 14,
  },
  methodSelector: {
    minHeight: 50,
    flexDirection: "row",
    alignItems: "stretch",
    gap: 4,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.07)",
    borderRadius: 13,
    backgroundColor: "#0d131b",
    padding: 4,
  },
  methodTab: {
    minHeight: 42,
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 6,
    borderRadius: 10,
    paddingHorizontal: 5,
  },
  methodTabSelected: {
    borderWidth: 1,
    borderColor: "rgba(244,207,126,0.18)",
    backgroundColor: "rgba(244,207,126,0.08)",
  },
  methodLabel: {
    color: "#a0aab8",
    fontFamily: fonts.medium,
    fontSize: 10,
  },
  methodLabelSelected: {
    color: "#f0d99f",
    fontFamily: fonts.semibold,
  },
  methodNotice: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 9,
    borderWidth: 1,
    borderColor: "rgba(232,184,102,0.18)",
    borderRadius: 11,
    backgroundColor: "rgba(232,184,102,0.06)",
    marginTop: 10,
    padding: 10,
  },
  methodNoticeText: {
    flex: 1,
    color: "#d8c49e",
    fontFamily: fonts.body,
    fontSize: 10,
    lineHeight: 15,
  },
  refreshButton: {
    width: 40,
    height: 40,
    borderRadius: 11,
    borderWidth: 1,
    borderColor: "rgba(170,145,240,0.2)",
    backgroundColor: "rgba(170,145,240,0.08)",
    alignItems: "center",
    justifyContent: "center",
  },
  deviceList: {
    gap: 8,
  },
  devicePickerRow: {
    minHeight: 62,
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.075)",
    borderRadius: 13,
    backgroundColor: "#0e141d",
    paddingHorizontal: 9,
  },
  devicePickerSelected: {
    borderColor: "rgba(170,145,240,0.36)",
    backgroundColor: "rgba(170,145,240,0.07)",
  },
  devicePickerMain: {
    minHeight: 58,
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    paddingVertical: 8,
  },
  devicePickerIcon: {
    width: 31,
    height: 31,
    borderRadius: 9,
    backgroundColor: "rgba(170,145,240,0.11)",
    alignItems: "center",
    justifyContent: "center",
  },
  devicePickerCopy: {
    flex: 1,
    minWidth: 0,
  },
  devicePickerName: {
    color: "#e4e9f0",
    fontFamily: fonts.semibold,
    fontSize: 11,
  },
  devicePickerMeta: {
    marginTop: 2,
    color: "#8995a5",
    fontFamily: fonts.body,
    fontSize: 9,
  },
  devicePickerInfo: {
    width: 40,
    height: 44,
    alignItems: "center",
    justifyContent: "center",
  },
  deviceStatus: {
    minHeight: 22,
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    borderRadius: 99,
    backgroundColor: "rgba(232,184,102,0.11)",
    paddingHorizontal: 7,
  },
  deviceStatusSimulation: {
    backgroundColor: "rgba(170,145,240,0.11)",
  },
  deviceStatusDot: {
    width: 5,
    height: 5,
    borderRadius: 3,
  },
  deviceStatusText: {
    color: "#d4bd92",
    fontFamily: fonts.medium,
    fontSize: 9,
  },
  changeButton: {
    minHeight: 40,
    flexDirection: "row",
    alignItems: "center",
    gap: 2,
    paddingHorizontal: 4,
  },
  changeButtonText: {
    color: colors.auric300,
    fontFamily: fonts.semibold,
    fontSize: 11,
  },
  recipientCard: {
    minHeight: 91,
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 10,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.07)",
    borderRadius: 13,
    backgroundColor: "#0e141d",
    padding: 11,
  },
  recipientIcon: {
    width: 37,
    height: 37,
    borderRadius: 11,
    backgroundColor: "rgba(170,145,240,0.11)",
    alignItems: "center",
    justifyContent: "center",
  },
  recipientBody: {
    flex: 1,
    minWidth: 0,
  },
  recipientName: {
    color: "#eef1f6",
    fontFamily: fonts.semibold,
    fontSize: 12,
  },
  recipientProduct: {
    marginTop: 2,
    color: "#9da8b7",
    fontFamily: fonts.body,
    fontSize: 10,
  },
  recipientId: {
    marginTop: 3,
    color: "#7f8a99",
    fontFamily: fonts.medium,
    fontSize: 9,
  },
  recipientStatusRow: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 6,
    marginTop: 7,
  },
  recipientInfoButton: {
    width: 36,
    height: 40,
    alignItems: "center",
    justifyContent: "center",
  },
  verifyButton: {
    minHeight: 43,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 7,
    borderWidth: 1,
    borderColor: "rgba(170,145,240,0.25)",
    borderRadius: 11,
    backgroundColor: "rgba(170,145,240,0.09)",
    marginTop: 9,
  },
  verifyButtonText: {
    color: "#cfc2f3",
    fontFamily: fonts.semibold,
    fontSize: 11,
  },
  simulationDisclaimer: {
    marginTop: 8,
    color: "#8390a0",
    fontFamily: fonts.body,
    fontSize: 9,
    lineHeight: 14,
  },
  simulatedAssetBadge: {
    borderWidth: 1,
    borderColor: "rgba(232,184,102,0.18)",
    borderRadius: 99,
    backgroundColor: "rgba(232,184,102,0.06)",
    color: "#d9bd87",
    paddingHorizontal: 8,
    paddingVertical: 5,
    fontFamily: fonts.semibold,
    fontSize: 8,
    letterSpacing: 0.6,
  },
  amountInputShell: {
    minHeight: 60,
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.1)",
    borderRadius: 13,
    backgroundColor: "#0e141d",
    paddingHorizontal: 11,
  },
  amountInputError: {
    borderColor: "rgba(240,140,140,0.54)",
  },
  amountInput: {
    minWidth: 0,
    minHeight: 50,
    flex: 1,
    color: "#f4f6fa",
    fontFamily: fonts.display,
    fontSize: 25,
    paddingVertical: 4,
  },
  amountAsset: {
    color: "#9ca7b5",
    fontFamily: fonts.semibold,
    fontSize: 10,
    marginRight: 8,
  },
  maxButton: {
    minWidth: 42,
    minHeight: 34,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
    borderColor: "rgba(244,207,126,0.22)",
    borderRadius: 9,
    backgroundColor: "rgba(244,207,126,0.07)",
    paddingHorizontal: 7,
  },
  maxButtonText: {
    color: colors.auric300,
    fontFamily: fonts.semibold,
    fontSize: 9,
  },
  balanceSummary: {
    minHeight: 57,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    marginTop: 11,
  },
  balanceSummaryDivider: {
    width: 1,
    height: 35,
    backgroundColor: "rgba(255,255,255,0.10)",
  },
  remainingBalance: {
    flex: 1,
  },
  balanceSummaryLabel: {
    color: "#8f9aaa",
    fontFamily: fonts.body,
    fontSize: 9,
  },
  balanceSummaryValue: {
    marginTop: 4,
    color: "#e7ebf1",
    fontFamily: fonts.semibold,
    fontSize: 11,
  },
  valueBlue: {
    color: "#72cce8",
  },
  valueMuted: {
    color: "#8290a1",
  },
  limitHint: {
    marginTop: 2,
    color: "#7f8a99",
    fontFamily: fonts.body,
    fontSize: 9,
  },
  expandHeader: {
    minHeight: 46,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  detailList: {
    marginTop: 7,
  },
  detailRow: {
    minHeight: 38,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "rgba(255,255,255,0.09)",
    paddingVertical: 8,
  },
  detailRowLast: {
    borderBottomWidth: 0,
  },
  detailLabel: {
    flex: 1,
    color: "#929dad",
    fontFamily: fonts.body,
    fontSize: 10,
  },
  detailValue: {
    flexShrink: 1,
    color: "#d9e0e9",
    fontFamily: fonts.medium,
    fontSize: 10,
    textAlign: "right",
  },
  scenarioControl: {
    minHeight: 52,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "rgba(255,255,255,0.09)",
  },
  scenarioCopy: {
    flex: 1,
    gap: 3,
  },
  scenarioSelected: {
    color: "#d9e0e9",
    fontFamily: fonts.medium,
    fontSize: 10,
  },
  scenarioButton: {
    minWidth: 62,
    minHeight: 37,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
    borderColor: "rgba(244,207,126,0.2)",
    borderRadius: 9,
    paddingHorizontal: 8,
  },
  scenarioButtonText: {
    color: colors.auric300,
    fontFamily: fonts.semibold,
    fontSize: 10,
  },
  technicalNote: {
    color: "#8995a5",
    fontFamily: fonts.body,
    fontSize: 9,
    lineHeight: 14,
    paddingTop: 9,
  },
  connectionCard: {
    borderWidth: 1,
    borderColor: "rgba(170,145,240,0.17)",
    borderRadius: 15,
    backgroundColor: "rgba(34,30,48,0.52)",
    marginBottom: 12,
    padding: 13,
  },
  connectionHeader: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 10,
  },
  connectionIcon: {
    width: 36,
    height: 36,
    borderRadius: 11,
    backgroundColor: "rgba(170,145,240,0.11)",
    alignItems: "center",
    justifyContent: "center",
  },
  connectionCopy: {
    flex: 1,
  },
  connectionTitle: {
    color: "#e8e2f5",
    fontFamily: fonts.semibold,
    fontSize: 12,
  },
  connectionSubtitle: {
    marginTop: 4,
    color: "#a7a4b4",
    fontFamily: fonts.body,
    fontSize: 10,
    lineHeight: 15,
  },
  connectionStateList: {
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: "rgba(255,255,255,0.09)",
    marginTop: 12,
    paddingTop: 8,
  },
  connectionStateRow: {
    minHeight: 29,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 10,
  },
  connectionStateLabel: {
    color: "#929dad",
    fontFamily: fonts.body,
    fontSize: 9,
  },
  connectionStateValue: {
    flexShrink: 1,
    color: "#bcaeec",
    fontFamily: fonts.medium,
    fontSize: 9,
    textAlign: "right",
  },
  connectionConnecting: {
    color: "#e8b866",
  },
  reconciliationNote: {
    marginTop: 6,
    color: "#8c95a3",
    fontFamily: fonts.body,
    fontSize: 9,
    lineHeight: 14,
  },
  inlineError: {
    color: "#f09b9b",
    fontFamily: fonts.medium,
    fontSize: 10,
    lineHeight: 15,
    marginTop: 8,
  },
  formError: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 8,
    borderWidth: 1,
    borderColor: "rgba(240,140,140,0.23)",
    borderRadius: 11,
    backgroundColor: "rgba(240,140,140,0.07)",
    marginBottom: 10,
    padding: 11,
  },
  formErrorText: {
    flex: 1,
    color: "#f0aaaa",
    fontFamily: fonts.medium,
    fontSize: 10,
    lineHeight: 15,
  },
  recoveryLock: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 8,
    borderWidth: 1,
    borderColor: "rgba(240,140,140,0.24)",
    borderRadius: 11,
    backgroundColor: "rgba(240,140,140,0.07)",
    marginBottom: 10,
    padding: 11,
  },
  recoveryLockText: {
    flex: 1,
    color: "#efa5a5",
    fontFamily: fonts.medium,
    fontSize: 10,
    lineHeight: 15,
  },
  confirmButton: {
    minHeight: 50,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
    borderRadius: 13,
    backgroundColor: colors.auric300,
    marginTop: 1,
  },
  confirmButtonText: {
    color: "#17140c",
    fontFamily: fonts.semibold,
    fontSize: 13,
  },
  cancelPageButton: {
    minHeight: 46,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 12,
    marginTop: 5,
  },
  cancelPageButtonText: {
    color: "#aab3c0",
    fontFamily: fonts.medium,
    fontSize: 12,
  },
  pageFootnote: {
    color: "#778391",
    fontFamily: fonts.body,
    fontSize: 9,
    lineHeight: 14,
    textAlign: "center",
    paddingBottom: 8,
  },
  resultCard: {
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.11)",
    borderRadius: 15,
    backgroundColor: "#111722",
    marginBottom: 13,
    padding: 13,
  },
  resultSuccess: {
    borderColor: "rgba(114,211,160,0.23)",
  },
  resultWarning: {
    borderColor: "rgba(232,184,102,0.24)",
  },
  resultDanger: {
    borderColor: "rgba(240,140,140,0.28)",
  },
  resultHeader: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 9,
  },
  resultIcon: {
    width: 30,
    alignItems: "center",
  },
  resultCopy: {
    flex: 1,
  },
  resultTitle: {
    color: "#edf0f5",
    fontFamily: fonts.semibold,
    fontSize: 12,
  },
  resultDescription: {
    marginTop: 4,
    color: "#abb4c1",
    fontFamily: fonts.body,
    fontSize: 10,
    lineHeight: 15,
  },
  resultMeta: {
    flexDirection: "row",
    flexWrap: "wrap",
    justifyContent: "space-between",
    gap: 5,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: "rgba(255,255,255,0.09)",
    marginTop: 10,
    paddingTop: 9,
  },
  resultMetaText: {
    color: "#909baa",
    fontFamily: fonts.medium,
    fontSize: 9,
  },
  resultAction: {
    minHeight: 38,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.1)",
    borderRadius: 9,
    marginTop: 10,
  },
  resultActionText: {
    color: "#e5d49f",
    fontFamily: fonts.semibold,
    fontSize: 10,
  },
  sheetBackdrop: {
    flex: 1,
    justifyContent: "flex-end",
    backgroundColor: "rgba(0,0,0,0.68)",
  },
  sheet: {
    maxHeight: "88%",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.12)",
    borderBottomWidth: 0,
    borderTopLeftRadius: 23,
    borderTopRightRadius: 23,
    backgroundColor: "#111720",
    paddingHorizontal: 18,
    paddingTop: 10,
  },
  sheetHandle: {
    width: 37,
    height: 4,
    alignSelf: "center",
    borderRadius: 3,
    backgroundColor: "rgba(255,255,255,0.22)",
    marginBottom: 13,
  },
  sheetHeader: {
    minHeight: 43,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 10,
    marginBottom: 7,
  },
  sheetTitle: {
    color: "#eef1f6",
    fontFamily: fonts.display,
    fontSize: 17,
  },
  sheetClose: {
    width: 38,
    height: 38,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 11,
    backgroundColor: "rgba(255,255,255,0.06)",
  },
  sheetDescription: {
    color: "#9da8b7",
    fontFamily: fonts.body,
    fontSize: 11,
    lineHeight: 16,
    marginBottom: 12,
  },
  deviceInfoPanel: {
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.08)",
    borderRadius: 13,
    backgroundColor: "#0d131b",
    marginBottom: 10,
    padding: 12,
  },
  deviceInfoName: {
    color: "#eef1f6",
    fontFamily: fonts.semibold,
    fontSize: 13,
  },
  deviceInfoProduct: {
    color: "#9ca7b5",
    fontFamily: fonts.body,
    fontSize: 10,
    marginTop: 3,
    marginBottom: 7,
  },
  deviceInfoWarning: {
    color: "#d9bf8d",
    fontFamily: fonts.body,
    fontSize: 10,
    lineHeight: 15,
    marginTop: 9,
  },
  reviewSimulationBanner: {
    minHeight: 35,
    flexDirection: "row",
    alignItems: "center",
    gap: 7,
    borderRadius: 10,
    backgroundColor: "rgba(232,184,102,0.07)",
    marginBottom: 8,
    paddingHorizontal: 10,
  },
  reviewSimulationText: {
    color: "#dfc797",
    fontFamily: fonts.medium,
    fontSize: 10,
  },
  reviewDetails: {
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.08)",
    borderRadius: 13,
    backgroundColor: "#0d131b",
    paddingHorizontal: 11,
  },
  reviewAuthorization: {
    color: "#929dad",
    fontFamily: fonts.body,
    fontSize: 10,
    lineHeight: 15,
    marginTop: 12,
  },
  reviewActions: {
    flexDirection: "row",
    gap: 9,
    marginTop: 15,
  },
  reviewCancelButton: {
    minHeight: 48,
    flex: 0.7,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.12)",
    borderRadius: 12,
  },
  reviewCancelText: {
    color: "#c2cad5",
    fontFamily: fonts.semibold,
    fontSize: 11,
  },
  reviewConfirmButton: {
    minHeight: 48,
    flex: 1.3,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 12,
    backgroundColor: colors.auric300,
    paddingHorizontal: 10,
  },
  reviewConfirmText: {
    color: "#17140c",
    fontFamily: fonts.semibold,
    fontSize: 11,
  },
  scenarioOptions: {
    gap: 8,
  },
  scenarioOption: {
    minHeight: 59,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.08)",
    borderRadius: 12,
    backgroundColor: "#0d131b",
    paddingHorizontal: 12,
  },
  scenarioOptionSelected: {
    borderColor: "rgba(244,207,126,0.28)",
    backgroundColor: "rgba(244,207,126,0.06)",
  },
  scenarioOptionCopy: {
    gap: 3,
  },
  scenarioOptionTitle: {
    color: "#e5eaf1",
    fontFamily: fonts.semibold,
    fontSize: 11,
  },
  scenarioOptionDetail: {
    color: "#909baa",
    fontFamily: fonts.body,
    fontSize: 9,
  },
  progressBackdrop: {
    flex: 1,
    justifyContent: "flex-end",
    backgroundColor: "rgba(0,0,0,0.72)",
    paddingHorizontal: 12,
    paddingBottom: 12,
  },
  progressCard: {
    width: "100%",
    maxWidth: 500,
    alignSelf: "center",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.12)",
    borderRadius: 20,
    backgroundColor: "#111720",
    padding: 17,
  },
  progressHeading: {
    flexDirection: "row",
    alignItems: "center",
    gap: 11,
  },
  progressSpinner: {
    width: 38,
    height: 38,
    borderRadius: 12,
    backgroundColor: "rgba(244,207,126,0.08)",
    alignItems: "center",
    justifyContent: "center",
  },
  progressHeadingCopy: {
    flex: 1,
  },
  progressTitle: {
    color: "#eef1f6",
    fontFamily: fonts.semibold,
    fontSize: 13,
  },
  progressSubtitle: {
    color: "#929dad",
    fontFamily: fonts.body,
    fontSize: 10,
    marginTop: 3,
  },
  progressSteps: {
    gap: 0,
    marginTop: 15,
  },
  progressStep: {
    minHeight: 36,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  progressStepIcon: {
    width: 20,
    height: 20,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.14)",
    alignItems: "center",
    justifyContent: "center",
  },
  progressStepDone: {
    borderColor: colors.auric300,
    backgroundColor: colors.auric300,
  },
  progressStepCurrent: {
    borderColor: "rgba(244,207,126,0.5)",
    backgroundColor: "rgba(244,207,126,0.08)",
  },
  progressStepEmpty: {
    width: 5,
    height: 5,
    borderRadius: 3,
    backgroundColor: "#687383",
  },
  progressStepText: {
    color: "#8894a3",
    fontFamily: fonts.medium,
    fontSize: 11,
  },
  progressStepTextCurrent: {
    color: "#e8d69e",
    fontFamily: fonts.semibold,
  },
  cancelButton: {
    minHeight: 45,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.11)",
    borderRadius: 11,
    marginTop: 13,
  },
  cancelButtonText: {
    color: "#c4ccd7",
    fontFamily: fonts.semibold,
    fontSize: 11,
  },
  loadingRow: {
    minHeight: 52,
    flexDirection: "row",
    alignItems: "center",
    gap: 9,
  },
  loadingText: {
    color: "#9da8b7",
    fontFamily: fonts.body,
    fontSize: 10,
  },
  buttonDisabled: {
    opacity: 0.55,
  },
});
