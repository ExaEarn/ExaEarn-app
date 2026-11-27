import { Ionicons } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import {
  ActivityIndicator,
  BackHandler,
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
  useWindowDimensions,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useAuth } from "../context/AuthContext";

import { AnimatedPressable } from "../components/AnimatedPressable";
import {
  formatSimulatedSyncAmount,
  type RedemptionHistoryItem,
  type ReconciliationTransaction,
  type SyncProgressStage,
  type SyncRedeemSnapshot,
} from "../services/exaPaySyncRedeemSimulator";
import { createExaPayOfflineReconciliationApi } from "../services/exaPayOfflineReconciliationApi";
import { createExaPayNativeOfflineTransfers, createExaPayNativeSignedJournal } from "../services/exaPayNativeSignedJournal";
import { mapExaPayRedemptionResult } from "../services/transactionResultMapper";
import TransactionResultScreen from "./TransactionResultScreen";
import { colors, fonts } from "../theme/colors";

type Props = {
  fontsReady: boolean;
  onBack: () => void;
  onViewAllHistory: () => void;
  onOpenRecoverySupport: () => void;
};

type Tone = "amber" | "green" | "red" | "purple" | "muted";

const syncStageLabels: Record<SyncProgressStage, string> = {
  "loading-local-queue": "Loading local offline queue",
  "validating-reference": "Validating transaction reference",
  "checking-simulation": "Checking simulated reconciliation",
};

function StatusBadge({ label, tone }: { label: string; tone: Tone }) {
  return (
    <View style={[styles.statusBadge, styles[`statusBadge_${tone}`]]} accessible accessibilityLabel={label}>
      <View style={[styles.statusDot, styles[`statusDot_${tone}`]]} />
      <Text style={[styles.statusLabel, styles[`statusLabel_${tone}`]]}>{label}</Text>
    </View>
  );
}

function SectionHeader({
  title,
  action,
  onAction,
  actionIcon,
  actionBusy,
}: {
  title: string;
  action?: string;
  onAction?: () => void;
  actionIcon?: keyof typeof Ionicons.glyphMap;
  actionBusy?: boolean;
}) {
  return (
    <View style={styles.sectionHeader}>
      <Text style={styles.sectionTitle} accessibilityRole="header">{title}</Text>
      {action && onAction ? (
        <AnimatedPressable
          style={styles.sectionAction}
          onPress={onAction}
          disabled={actionBusy}
          accessibilityLabel={action}
        >
          {actionBusy ? (
            <ActivityIndicator size="small" color={colors.auric300} />
          ) : actionIcon ? (
            <Ionicons name={actionIcon} size={15} color={colors.auric300} />
          ) : null}
          <Text style={styles.sectionActionText}>{action}</Text>
        </AnimatedPressable>
      ) : null}
    </View>
  );
}

function OfflineValueOverview({ snapshot }: { snapshot: SyncRedeemSnapshot }) {
  const amounts = [
    { label: "Open SIMUSDT Claims", amount: snapshot.localOfflineValueMinor, tone: "purple" as const },
    { label: "Pending Verification", amount: snapshot.pendingVerificationMinor, tone: "amber" as const },
    { label: "Redeemable", amount: snapshot.redeemableMinor, tone: "green" as const },
  ];

  return (
    <View style={styles.overviewCard}>
      <View style={styles.balanceGrid}>
        {amounts.map((item) => (
          <View key={item.label} style={styles.balanceTile}>
            <View style={styles.balanceTitleRow}>
              <View style={[styles.balanceMarker, styles[`balanceMarker_${item.tone}`]]} />
              <Text style={styles.balanceLabel}>{item.label}</Text>
            </View>
            <Text style={[styles.balanceAmount, !item.amount ? styles.balanceAmountMuted : null]}>
              {formatSimulatedSyncAmount(item.amount)}
            </Text>
            <Text style={styles.balanceAsset}>simUSDT</Text>
          </View>
        ))}
      </View>
      <View style={styles.balanceExplanation}>
        <Ionicons name="information-circle-outline" size={16} color="#c6b8ee" />
        <Text style={styles.balanceExplanationText}>
          Open claims are not wallet funds. Only a signed, independently verified SIMUSDT output can be redeemed.
        </Text>
      </View>
    </View>
  );
}

function QueueTransactionCard({
  transaction,
  onPress,
}: {
  transaction: ReconciliationTransaction;
  onPress: () => void;
}) {
  const isVerified = transaction.reconciliationStatus === "verified";
  const isFailed = transaction.reconciliationStatus === "failed"
    || transaction.reconciliationStatus === "recovery-required";
  const reconciliationLabel = isVerified
    ? "Verified · Ready to redeem"
    : transaction.reconciliationStatus === "recovery-required"
      ? "Recovery required"
      : transaction.reconciliationStatus === "failed"
        ? "Verification failed"
        : "Pending Verification";
  const reconciliationTone: Tone = isVerified ? "green" : isFailed ? "red" : "amber";

  return (
    <AnimatedPressable style={styles.queueCard} onPress={onPress} accessibilityLabel={`Transaction ${transaction.reference}, ${reconciliationLabel}. View details`}>
      <View style={styles.queueCardTop}>
        <View style={styles.queueRefGroup}>
          <View style={styles.transactionIcon}>
            <Ionicons name="swap-horizontal-outline" size={17} color="#b5a2ed" />
          </View>
          <View style={styles.queueRefCopy}>
            <Text style={styles.queueReference}>{transaction.reference}</Text>
            <Text style={styles.queueSender}>{transaction.sender}</Text>
          </View>
        </View>
        <Ionicons name="chevron-forward" size={18} color="#8995a5" />
      </View>
      <View style={styles.queueAmountRow}>
        <Text style={styles.queueAmount}>
          {formatSimulatedSyncAmount(transaction.amountMinor)} <Text style={styles.queueAsset}>simUSDT</Text>
        </Text>
        <StatusBadge label={reconciliationLabel} tone={reconciliationTone} />
      </View>
      <View style={styles.queueStatusRow}>
        <Text style={styles.queueStatusLabel}>Local status</Text>
        <StatusBadge label="Offline Committed" tone="purple" />
      </View>
    </AnimatedPressable>
  );
}

function RedemptionPanel({
  snapshot,
  canRedeem,
  redemptionBusy,
  previousOutcome,
  onRedeem,
}: {
  snapshot: SyncRedeemSnapshot;
  canRedeem: boolean;
  redemptionBusy: boolean;
  previousOutcome: "success" | "failed" | "recovery-required" | null;
  onRedeem: () => void;
}) {
  const reason = snapshot.transaction?.reconciliationStatus === "recovery-required"
    ? "Recovery is required before this value can be redeemed."
    : snapshot.transaction?.reconciliationStatus === "failed"
      ? "Verification failed. Retry synchronization before redeeming."
      : snapshot.pendingVerificationMinor > 0
        ? "This amount is pending verification and is not available online."
        : "No verified value is currently available to redeem.";

  return (
    <View style={styles.redemptionCard}>
      <View style={styles.redemptionHeading}>
        <View style={styles.redemptionIcon}>
          <Ionicons name="arrow-up-circle-outline" size={20} color="#d8c38f" />
        </View>
        <View style={styles.redemptionTitleCopy}>
          <Text style={styles.redemptionTitle}>Redeem to Online Balance</Text>
          <Text style={styles.redemptionSubtitle}>Only successfully reconciled simulated value is eligible.</Text>
        </View>
      </View>
      <View style={styles.redeemAmountBox}>
        <View>
          <Text style={styles.redeemAmountLabel}>Verified redeemable amount</Text>
          <Text style={styles.redeemAmount}>
            {formatSimulatedSyncAmount(snapshot.redeemableMinor)} <Text style={styles.redeemAsset}>simUSDT</Text>
          </Text>
        </View>
        <StatusBadge label={canRedeem ? "Verified" : "Not eligible"} tone={canRedeem ? "green" : "muted"} />
      </View>
      {!canRedeem ? <Text style={styles.redeemReason}>{reason}</Text> : null}
      <AnimatedPressable
        style={[styles.redeemButton, !canRedeem ? styles.redeemButtonDisabled : null]}
        onPress={onRedeem}
        disabled={!canRedeem || redemptionBusy}
        accessibilityLabel={previousOutcome === "failed" ? "Retry simulated redemption" : "Redeem verified simulated value"}
      >
        {redemptionBusy ? (
          <ActivityIndicator size="small" color="#17140c" />
        ) : (
          <Ionicons name={previousOutcome === "failed" ? "refresh" : "arrow-up-outline"} size={17} color={canRedeem ? "#17140c" : "#7f8792"} />
        )}
        <Text style={[styles.redeemButtonText, !canRedeem ? styles.redeemButtonTextDisabled : null]}>
          {redemptionBusy
            ? "Processing simulation…"
            : previousOutcome === "failed" && canRedeem
              ? "Retry Redemption"
              : "Redeem Now"}
        </Text>
      </AnimatedPressable>
      <View style={styles.verificationNote}>
        <Ionicons name="shield-checkmark-outline" size={14} color="#a991f0" />
        <Text style={styles.verificationNoteText}>
          Redemption is simulated locally. No API, wallet ledger, or real online balance is changed.
        </Text>
      </View>
    </View>
  );
}

function RedemptionHistory({
  history,
  onViewAll,
  onRefresh,
  refreshing,
}: {
  history: RedemptionHistoryItem[];
  onViewAll: () => void;
  onRefresh: () => void;
  refreshing: boolean;
}) {
  const statusTone = (status: RedemptionHistoryItem["status"]): Tone => (
    status === "Redeemed" ? "green" : status === "Failed" ? "red" : "amber"
  );
  const statusIcon = (status: RedemptionHistoryItem["status"]) => (
    status === "Redeemed" ? "checkmark-circle-outline" : status === "Failed" ? "close-circle-outline" : "time-outline"
  );

  return (
    <View style={styles.historyCard}>
      {history.map((item, index) => (
        <View key={`${item.reference}-${index}`} style={[styles.historyRow, index < history.length - 1 ? styles.historyDivider : null]}>
          <View style={[styles.historyIcon, styles[`historyIcon_${statusTone(item.status)}`]]}>
            <Ionicons name={statusIcon(item.status)} size={16} color={statusTone(item.status) === "green" ? "#72d3a0" : statusTone(item.status) === "red" ? "#f08c8c" : "#e8b866"} />
          </View>
          <View style={styles.historyCopy}>
            <Text style={styles.historyReference}>{item.reference}</Text>
            <Text style={styles.historyTime}>{item.timestamp}</Text>
          </View>
          <View style={styles.historyStatus}>
            <StatusBadge label={item.status} tone={statusTone(item.status)} />
            <Text style={styles.historyAmount}>{formatSimulatedSyncAmount(item.amountMinor)} simUSDT</Text>
          </View>
        </View>
      ))}
      {!history.length ? (
        <View style={styles.historyEmpty}>
          <Ionicons name="receipt-outline" size={20} color="#8490a0" />
          <Text style={styles.historyEmptyText}>No redemption activity yet.</Text>
        </View>
      ) : null}
      <View style={styles.historyActions}>
        <AnimatedPressable style={styles.historyActionButton} onPress={onRefresh} disabled={refreshing}>
          {refreshing ? <ActivityIndicator size="small" color={colors.auric300} /> : <Ionicons name="refresh-outline" size={15} color={colors.auric300} />}
          <Text style={styles.historyActionText}>{refreshing ? "Refreshing…" : "Refresh"}</Text>
        </AnimatedPressable>
        <AnimatedPressable style={styles.historyActionButton} onPress={onViewAll}>
          <Text style={styles.historyActionText}>View All</Text>
          <Ionicons name="chevron-forward" size={15} color={colors.auric300} />
        </AnimatedPressable>
      </View>
    </View>
  );
}

function BottomSheet({
  visible,
  title,
  onClose,
  bottomInset,
  children,
}: {
  visible: boolean;
  title: string;
  onClose: () => void;
  bottomInset: number;
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
        <View style={[styles.sheet, { paddingBottom: Math.max(bottomInset, 16) + 16 }]} accessibilityViewIsModal>
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

function SyncProgressIndicator({
  visible,
  stage,
}: {
  visible: boolean;
  stage: SyncProgressStage | null;
}) {
  const stages = Object.keys(syncStageLabels) as SyncProgressStage[];
  const activeIndex = stage ? stages.indexOf(stage) : 0;
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={() => undefined}>
      <View style={styles.progressBackdrop}>
        <View style={styles.progressCard}>
          <View style={styles.progressHeader}>
            <ActivityIndicator size="small" color={colors.auric300} />
            <View style={styles.progressHeaderCopy}>
              <Text style={styles.progressTitle}>Checking offline claims</Text>
              <Text style={styles.progressSubtitle}>Authenticated ExaPay API · SIMUSDT only</Text>
            </View>
          </View>
          <View style={styles.progressSteps}>
            {stages.map((item, index) => (
              <View key={item} style={styles.progressStep}>
                <View style={[
                  styles.progressStepDot,
                  index < activeIndex ? styles.progressStepDone : null,
                  index === activeIndex ? styles.progressStepActive : null,
                ]}>
                  {index < activeIndex ? <Ionicons name="checkmark" size={12} color="#11140f" /> : null}
                </View>
                <Text style={[styles.progressStepLabel, index === activeIndex ? styles.progressStepLabelActive : null]}>
                  {syncStageLabels[item]}
                </Text>
              </View>
            ))}
          </View>
        </View>
      </View>
    </Modal>
  );
}

export default function ExaPaySyncRedeemScreen({
  fontsReady,
  onBack,
  onViewAllHistory,
  onOpenRecoverySupport,
}: Props) {
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const { request, user, apiBaseUrl } = useAuth();
  const shellWidth = Math.min(width - 32, 620);
  const simulator = useMemo(() => createExaPayOfflineReconciliationApi(request), [request]);
  const [snapshot, setSnapshot] = useState<SyncRedeemSnapshot>(() => simulator.getSnapshot());
  const [selectedTransaction, setSelectedTransaction] = useState<ReconciliationTransaction | null>(null);
  const [showRedemptionConfirmation, setShowRedemptionConfirmation] = useState(false);
  const [syncStatus, setSyncStatus] = useState<"idle" | "syncing" | "verified" | "pending" | "failed" | "network-error" | "recovery-required">("idle");
  const [syncStage, setSyncStage] = useState<SyncProgressStage | null>(null);
  const [syncError, setSyncError] = useState("");
  const [redemptionOutcome, setRedemptionOutcome] = useState<"success" | "failed" | "recovery-required" | null>(null);
  const [transactionResult, setTransactionResult] = useState<ReturnType<typeof mapExaPayRedemptionResult> | null>(null);
  const [isRedeeming, setIsRedeeming] = useState(false);
  const [isRefreshingHistory, setIsRefreshingHistory] = useState(false);
  const [signedJournalBusy, setSignedJournalBusy] = useState(false);
  const [signedJournalMessage, setSignedJournalMessage] = useState("");
  const [evidenceOutputUuid, setEvidenceOutputUuid] = useState("");
  const syncLock = useRef(false);
  const redemptionLock = useRef(false);

  const queueTransaction = snapshot.transaction?.reconciliationStatus === "redeemed"
    ? null
    : snapshot.transaction;
  const transactionCount = queueTransaction ? 1 : 0;
  const canRedeem = Boolean(
    snapshot.transaction?.reconciliationStatus === "verified"
    && snapshot.redeemableMinor > 0
    && snapshot.transaction.simulationOnly === true
    && snapshot.transaction.realFunds === false
    && snapshot.transaction.asset === "SIMUSDT"
    && snapshot.transaction.onlineCredit === false,
  );
  const connectivityStatus = simulator.getConnectivityStatus();

  useEffect(() => {
    let active = true;
    void simulator.refreshSnapshot().then((next) => {
      if (active) setSnapshot(next);
    }).catch(() => {
      if (active) {
        setSyncStatus("network-error");
        setSyncError("Authenticated reconciliation status is unavailable. No local record was treated as verified.");
      }
    });
    return () => { active = false; };
  }, [simulator]);

  useEffect(() => {
    const subscription = BackHandler.addEventListener("hardwareBackPress", () => {
      if (showRedemptionConfirmation || selectedTransaction) {
        setShowRedemptionConfirmation(false);
        setSelectedTransaction(null);
        return true;
      }
      if (syncStatus === "syncing" || isRedeeming) return true;
      onBack();
      return true;
    });
    return () => subscription.remove();
  }, [isRedeeming, onBack, selectedTransaction, showRedemptionConfirmation, syncStatus]);

  async function synchronizeOfflinePayments() {
    if (syncLock.current) return;
    syncLock.current = true;
    setSyncStatus("syncing");
    setSyncStage("loading-local-queue");
    setSyncError("");
    setRedemptionOutcome(null);
    try {
      const result = await simulator.syncOfflinePayments({
        onProgress: (stage) => setSyncStage(stage),
      });
      setSnapshot(result.snapshot);
      setSyncStatus(result.outcome === "verified"
        ? "verified"
        : result.outcome === "pending"
          ? "pending"
        : result.outcome === "verification-failed"
          ? "failed"
          : result.outcome);
      if (result.outcome === "verification-failed") {
        setSyncError("The backend rejected or quarantined a claim. Review its server status before taking further action.");
      } else if (result.outcome === "pending") {
        setSyncError("The backend has not verified the selected claim. It cannot be redeemed yet.");
      } else if (result.outcome === "recovery-required") {
        setSyncError("The backend marked this claim for recovery. Redemption is blocked until its state is resolved.");
      }
    } catch {
      setSyncStatus("network-error");
      setSyncError("Status check failed. The app did not treat local data as verified.");
    } finally {
      syncLock.current = false;
      setSyncStage(null);
    }
  }

  async function confirmRedemption() {
    if (
      redemptionLock.current
      || !canRedeem
      || !snapshot.transaction
    ) return;
    redemptionLock.current = true;
    setIsRedeeming(true);
    setSyncError("");
    setShowRedemptionConfirmation(false);
    const transaction = snapshot.transaction;
    try {
      const result = await simulator.redeemVerifiedValue(transaction.claimUuid ?? transaction.reference);
      setSnapshot(result.snapshot);
      setTransactionResult(mapExaPayRedemptionResult(transaction, result.outcome));
      if (result.outcome === "redeemed-simulation") {
        setRedemptionOutcome("success");
        setSyncStatus("verified");
      } else if (result.outcome === "failed") {
        setRedemptionOutcome("failed");
      } else if (result.outcome === "recovery-required") {
        setRedemptionOutcome("recovery-required");
        setSyncStatus("recovery-required");
        setSyncError("The redemption outcome needs recovery. Do not retry until its state is resolved.");
      } else {
        setRedemptionOutcome("failed");
        setSyncError("The backend rejected redemption because the claim is not verified SIMUSDT value.");
      }
    } catch {
      setRedemptionOutcome("recovery-required");
      setSyncStatus("recovery-required");
      setTransactionResult(mapExaPayRedemptionResult(transaction, "recovery-required"));
      setSyncError("The redemption outcome is uncertain. Refresh server status; do not create another redemption request.");
    } finally {
      redemptionLock.current = false;
      setIsRedeeming(false);
    }
  }

  async function refreshHistory() {
    if (isRefreshingHistory) return;
    setIsRefreshingHistory(true);
    try {
      const history = await simulator.refreshHistory();
      setSnapshot((previous) => ({ ...previous, history }));
    } catch {
      setSyncError("Redemption history could not be refreshed. Try again.");
    } finally {
      setIsRefreshingHistory(false);
    }
  }

  async function enrollSimulationDevice() {
    if (signedJournalBusy) return;
    const userId = user?.id === undefined || user?.id === null ? "" : String(user.id);
    if (!userId) {
      setSignedJournalMessage("Sign in before enrolling this device.");
      return;
    }
    setSignedJournalBusy(true);
    setSignedJournalMessage("");
    try {
      const protocol = createExaPayNativeSignedJournal({ request, apiBaseUrl, userId });
      const device = await protocol.enrollDevice();
      setSignedJournalMessage(`Device ${device.deviceUuid} is enrolled for SIMUSDT simulation. No payment claim or journal event was submitted.`);
    } catch {
      setSignedJournalMessage("Device enrollment was not confirmed. Check server status before retrying; no local payment was treated as verified.");
    } finally {
      setSignedJournalBusy(false);
    }
  }

  async function submitCommittedTransferEvidence() {
    if (signedJournalBusy) return;
    const outputUuid = evidenceOutputUuid.trim().toLowerCase();
    const userId = user?.id === undefined || user?.id === null ? "" : String(user.id);
    if (!userId || !/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(outputUuid)) {
      setSignedJournalMessage("Enter the UUID of a locally committed SIMUSDT recipient output and sign in. Empty or simulated journals cannot be submitted.");
      return;
    }
    setSignedJournalBusy(true);
    setSignedJournalMessage("");
    try {
      const transfers = createExaPayNativeOfflineTransfers({ request, apiBaseUrl, userId });
      const claim = await transfers.submitOutputEvidence(outputUuid, `EXAPAY-RECIPIENT-CLAIM:${outputUuid}`);
      const status = claim && typeof claim === "object" && "reconciliationStatus" in claim
        ? String((claim as { reconciliationStatus: unknown }).reconciliationStatus) : "pending-verification";
      setSignedJournalMessage(`Signed committed SIMUSDT transfer evidence reached Laravel. Claim status: ${status}. No online credit or real funds are enabled.`);
    } catch {
      setSignedJournalMessage("Evidence was not confirmed by Laravel. The local committed output is preserved; no value was restored or marked redeemed.");
    } finally {
      setSignedJournalBusy(false);
    }
  }

  if (transactionResult) {
    return (
      <TransactionResultScreen
        fontsReady={fontsReady}
        transaction={transactionResult}
        returnLabel="Back to Sync & Redeem"
        onReturn={() => setTransactionResult(null)}
      />
    );
  }

  const syncBadge: { label: string; tone: Tone } = syncStatus === "verified"
    ? { label: "Server checked", tone: "green" }
    : syncStatus === "pending"
      ? { label: "Verification pending", tone: "amber" }
    : syncStatus === "failed" || syncStatus === "network-error"
      ? { label: "Sync needs attention", tone: "red" }
      : syncStatus === "recovery-required"
        ? { label: "Recovery required", tone: "red" }
        : { label: "Ready to sync", tone: "amber" };

  return (
    <View style={styles.fill}>
      <LinearGradient colors={["#090b12", "#0c1018", "#0a0d14"]} style={StyleSheet.absoluteFill} />
      <ScrollView
        contentContainerStyle={[
          styles.scrollContent,
          { paddingTop: insets.top + 9, paddingBottom: Math.max(insets.bottom, 14) + 26 },
        ]}
        showsVerticalScrollIndicator={false}
      >
        <View style={[styles.shell, { width: shellWidth }]}>
          <View style={styles.topBar}>
            <AnimatedPressable style={styles.backButton} onPress={onBack} accessibilityLabel="Back to ExaPay">
              <Ionicons name="arrow-back" size={20} color="#dce3ed" />
            </AnimatedPressable>
            <View style={styles.brandLockup}>
              <Image
                source={require("../../../web/src/assets/images/exaearn-logo.png")}
                style={styles.logo}
                accessibilityLabel="ExaEarn"
              />
              <View>
                <Text style={styles.brandName}>ExaEarn</Text>
                <Text style={styles.brandCaption}>EXAPAY</Text>
              </View>
            </View>
            <View style={styles.simulationChip}>
              <View style={styles.simulationDot} />
              <Text style={styles.simulationText}>Simulation</Text>
            </View>
          </View>

          <View style={styles.titleBlock}>
            <View style={styles.titleIcon}>
              <Ionicons name="sync-outline" size={21} color="#b5a2ed" />
            </View>
            <View style={styles.titleCopy}>
              <Text style={[styles.title, !fontsReady ? styles.systemFont : null]} accessibilityRole="header">Sync &amp; Redeem</Text>
              <Text style={styles.subtitle}>Reconcile offline payments and redeem eligible value to your online balance.</Text>
            </View>
          </View>

          <View style={styles.connectivityCard}>
            <View style={styles.connectivityIcon}>
              <Ionicons name="cloud-offline-outline" size={16} color="#e5c181" />
            </View>
            <View style={styles.connectivityCopy}>
              <Text style={styles.connectivityTitle}>SIMUSDT simulation ledger</Text>
              <Text style={styles.connectivitySubtitle}>Authenticated server status; no real-funds operations.</Text>
            </View>
            <StatusBadge label="SIM" tone="muted" />
          </View>

          <View style={styles.deviceProtocolCard}>
            <View style={styles.deviceProtocolHeader}>
              <View style={styles.deviceProtocolIcon}><Ionicons name="key-outline" size={17} color="#b5a2ed" /></View>
              <View style={styles.deviceProtocolCopy}>
                <Text style={styles.deviceProtocolTitle}>Software device enrollment</Text>
                <Text style={styles.deviceProtocolDescription}>Register this software-only P-256 device key for SIMUSDT simulation. This does not authenticate or settle a payment.</Text>
              </View>
            </View>
            <AnimatedPressable
              style={[styles.deviceProtocolButton, signedJournalBusy ? styles.syncButtonDisabled : null]}
              onPress={() => void enrollSimulationDevice()}
              disabled={signedJournalBusy}
              accessibilityLabel="Enroll software-only SIMUSDT simulation device"
            >
              {signedJournalBusy ? <ActivityIndicator size="small" color="#17140c" /> : <Ionicons name="shield-checkmark-outline" size={15} color="#17140c" />}
              <Text style={styles.syncButtonText}>{signedJournalBusy ? "Enrolling device…" : "Enroll simulation device"}</Text>
            </AnimatedPressable>
            <TextInput
              value={evidenceOutputUuid}
              onChangeText={setEvidenceOutputUuid}
              placeholder="Committed recipient output UUID"
              placeholderTextColor="#697586"
              autoCapitalize="none"
              autoCorrect={false}
              style={styles.deviceProtocolInput}
              accessibilityLabel="SIMUSDT committed recipient output UUID"
            />
            <AnimatedPressable
              style={[styles.deviceProtocolButton, signedJournalBusy ? styles.syncButtonDisabled : null]}
              onPress={() => void submitCommittedTransferEvidence()}
              disabled={signedJournalBusy}
              accessibilityLabel="Submit committed signed SIMUSDT evidence to Laravel"
            >
              {signedJournalBusy ? <ActivityIndicator size="small" color="#17140c" /> : <Ionicons name="cloud-upload-outline" size={15} color="#17140c" />}
              <Text style={styles.syncButtonText}>{signedJournalBusy ? "Submitting evidence…" : "Submit committed evidence"}</Text>
            </AnimatedPressable>
            {signedJournalMessage ? <Text style={styles.deviceProtocolResult} accessibilityRole="alert">{signedJournalMessage}</Text> : null}
            <Text style={styles.deviceProtocolWarning}>Software keys and local counters can be copied or rolled back on a compromised device. Hardware attestation and protected monetary state are not available here.</Text>
          </View>

          <SectionHeader title="Offline value overview" />
          <OfflineValueOverview snapshot={snapshot} />

          <View style={styles.queueHeading}>
            <View style={styles.queueHeadingCopy}>
              <Text style={styles.sectionTitle} accessibilityRole="header">Reconciliation Queue ({transactionCount})</Text>
              <Text style={styles.sectionHint}>Refreshes authenticated server claims. Unsigned local simulations are never submitted as evidence.</Text>
            </View>
            <AnimatedPressable
              style={[styles.syncButton, syncStatus === "recovery-required" ? styles.syncButtonDisabled : null]}
              onPress={() => void synchronizeOfflinePayments()}
              disabled={syncStatus === "syncing"}
              accessibilityLabel={syncStatus === "failed" || syncStatus === "network-error" ? "Retry status refresh" : "Refresh offline claims"}
            >
              {syncStatus === "syncing" ? (
                <ActivityIndicator size="small" color="#17140c" />
              ) : (
                <Ionicons name={syncStatus === "failed" || syncStatus === "network-error" ? "refresh" : "sync-outline"} size={16} color="#17140c" />
              )}
              <Text style={styles.syncButtonText}>
                {syncStatus === "syncing"
                  ? "Syncing…"
                  : syncStatus === "failed" || syncStatus === "network-error"
                    ? "Retry Refresh"
                    : "Refresh Claims"}
              </Text>
            </AnimatedPressable>
          </View>
          <View style={styles.syncStatusRow}>
            <StatusBadge label={syncBadge.label} tone={syncBadge.tone} />
            <Text style={styles.syncStatusText}>Connectivity: {connectivityStatus}</Text>
          </View>

          {syncError ? (
            <View style={[styles.feedbackCard, syncStatus === "recovery-required" ? styles.feedbackDanger : styles.feedbackWarning]} accessibilityRole="alert">
              <Ionicons name={syncStatus === "recovery-required" ? "warning-outline" : "alert-circle-outline"} size={17} color={syncStatus === "recovery-required" ? "#f08c8c" : "#e8b866"} />
              <Text style={styles.feedbackText}>{syncError}</Text>
            </View>
          ) : null}

          {queueTransaction ? (
            <QueueTransactionCard transaction={queueTransaction} onPress={() => setSelectedTransaction(queueTransaction)} />
          ) : (
            <View style={styles.queueEmpty}>
              <View style={styles.queueEmptyIcon}><Ionicons name="checkmark-done-outline" size={21} color="#72d3a0" /></View>
              <View style={styles.queueEmptyCopy}>
                <Text style={styles.queueEmptyTitle}>Queue is clear</Text>
                <Text style={styles.queueEmptyText}>No offline transactions are waiting for reconciliation.</Text>
              </View>
            </View>
          )}

          <RedemptionPanel
            snapshot={snapshot}
            canRedeem={canRedeem}
            redemptionBusy={isRedeeming}
            previousOutcome={redemptionOutcome}
            onRedeem={() => setShowRedemptionConfirmation(true)}
          />

          {redemptionOutcome ? (
            <View style={[
              styles.outcomeCard,
              redemptionOutcome === "success" ? styles.outcomeSuccess : redemptionOutcome === "failed" ? styles.outcomeFailure : styles.outcomeRecovery,
            ]} accessibilityRole="alert">
              <Ionicons
                name={redemptionOutcome === "success" ? "checkmark-circle-outline" : redemptionOutcome === "failed" ? "close-circle-outline" : "warning-outline"}
                size={19}
                color={redemptionOutcome === "success" ? "#72d3a0" : redemptionOutcome === "failed" ? "#f08c8c" : "#e8b866"}
              />
              <View style={styles.outcomeCopy}>
                <Text style={styles.outcomeTitle}>
                  {redemptionOutcome === "success" ? "Simulated redemption complete" : redemptionOutcome === "failed" ? "Redemption failed" : "Recovery required"}
                </Text>
                <Text style={styles.outcomeDescription}>
                  {redemptionOutcome === "success"
                    ? "The simulator confirmed this local state update. No real online balance or ledger changed."
                    : redemptionOutcome === "failed"
                      ? "The verified amount remains eligible in simulation. Retry when ready."
                      : "Do not retry until the transaction state is reconciled."}
                </Text>
                {redemptionOutcome === "success" ? (
                  <Text style={styles.simulatedCredit}>The Wallet screen is the source for the current SIMUSDT balance.</Text>
                ) : null}
              </View>
            </View>
          ) : null}

          <SectionHeader title="Redemption history" />
          <RedemptionHistory
            history={snapshot.history}
            onViewAll={onViewAllHistory}
            onRefresh={() => void refreshHistory()}
            refreshing={isRefreshingHistory}
          />

          <View style={styles.securityCard}>
            <View style={styles.securityIcon}>
              <Ionicons name="shield-checkmark-outline" size={19} color="#b5a2ed" />
            </View>
            <View style={styles.securityCopy}>
              <Text style={styles.securityTitle}>Security &amp; reconciliation</Text>
              <Text style={styles.securityDescription}>
                Offline payments must pass reconciliation checks before redemption into an online balance.
              </Text>
            </View>
          </View>
          <Text style={styles.footerNote}>
            All balances and transaction records on this screen are illustrative simUSDT values. Real-money offline redemption is disabled.
          </Text>
        </View>
      </ScrollView>

      <BottomSheet
        visible={Boolean(selectedTransaction)}
        title="Transaction details"
        onClose={() => setSelectedTransaction(null)}
        bottomInset={insets.bottom}
      >
        {selectedTransaction ? (
          <>
            <View style={styles.sheetTransactionHeader}>
              <View style={styles.transactionIconLarge}><Ionicons name="swap-horizontal-outline" size={20} color="#b5a2ed" /></View>
              <View style={styles.sheetTransactionCopy}>
                <Text style={styles.sheetTransactionSender}>{selectedTransaction.sender}</Text>
                <Text style={styles.sheetTransactionAmount}>{formatSimulatedSyncAmount(selectedTransaction.amountMinor)} simUSDT</Text>
              </View>
              <StatusBadge label={selectedTransaction.reconciliationStatus === "verified" ? "Verified" : selectedTransaction.reconciliationStatus === "failed" ? "Failed" : selectedTransaction.reconciliationStatus === "recovery-required" ? "Recovery required" : selectedTransaction.reconciliationStatus === "redeemed" ? "Redeemed" : "Pending"} tone={selectedTransaction.reconciliationStatus === "verified" ? "green" : selectedTransaction.reconciliationStatus === "failed" || selectedTransaction.reconciliationStatus === "recovery-required" ? "red" : selectedTransaction.reconciliationStatus === "redeemed" ? "green" : "amber"} />
            </View>
            <View style={styles.sheetDetails}>
              <View style={styles.sheetDetailRow}><Text style={styles.sheetDetailLabel}>Transaction reference</Text><Text style={styles.sheetDetailValue}>{selectedTransaction.reference}</Text></View>
              <View style={styles.sheetDetailRow}><Text style={styles.sheetDetailLabel}>Local status</Text><Text style={styles.sheetDetailValue}>Offline Committed</Text></View>
              <View style={styles.sheetDetailRow}><Text style={styles.sheetDetailLabel}>Reconciliation status</Text><Text style={styles.sheetDetailValue}>{selectedTransaction.reconciliationStatus === "pending-verification" ? "Pending Verification" : selectedTransaction.reconciliationStatus === "verified" ? "Verified · Ready to redeem" : selectedTransaction.reconciliationStatus === "failed" ? "Verification failed" : selectedTransaction.reconciliationStatus === "recovery-required" ? "Recovery required" : "Redeemed in simulation"}</Text></View>
              <View style={[styles.sheetDetailRow, styles.sheetDetailLast]}><Text style={styles.sheetDetailLabel}>Asset</Text><Text style={styles.sheetDetailValue}>simUSDT · 2 decimal places</Text></View>
            </View>
            <Text style={styles.sheetDisclosure}>
              Pending value is not spendable online. This simulator does not verify a backend ledger or guarantee settlement.
            </Text>
            {selectedTransaction.reconciliationStatus === "failed" || selectedTransaction.reconciliationStatus === "recovery-required" ? (
              <AnimatedPressable
                style={styles.sheetPrimary}
                onPress={() => {
                  setSelectedTransaction(null);
                  if (selectedTransaction.reconciliationStatus === "recovery-required") {
                    onOpenRecoverySupport();
                  } else {
                    void synchronizeOfflinePayments();
                  }
                }}
              >
                <Ionicons
                  name={selectedTransaction.reconciliationStatus === "recovery-required" ? "help-circle-outline" : "refresh"}
                  size={16}
                  color="#17140c"
                />
                <Text style={styles.sheetPrimaryText}>
                  {selectedTransaction.reconciliationStatus === "recovery-required" ? "Contact Support" : "Retry synchronization"}
                </Text>
              </AnimatedPressable>
            ) : null}
          </>
        ) : null}
      </BottomSheet>

      <BottomSheet
        visible={showRedemptionConfirmation}
        title="Confirm simulated redemption"
        onClose={() => setShowRedemptionConfirmation(false)}
        bottomInset={insets.bottom}
      >
        <View style={styles.confirmationSummary}>
          <StatusBadge label="Verified value · Simulation" tone="green" />
          <Text style={styles.confirmationAmount}>
            {formatSimulatedSyncAmount(snapshot.redeemableMinor)} <Text style={styles.confirmationAsset}>simUSDT</Text>
          </Text>
          <Text style={styles.confirmationCopy}>This amount has been verified by the local simulator only.</Text>
        </View>
        <View style={styles.confirmationDetails}>
          <View style={styles.sheetDetailRow}><Text style={styles.sheetDetailLabel}>Transaction</Text><Text style={styles.sheetDetailValue}>{snapshot.transaction?.reference ?? "—"}</Text></View>
          <View style={[styles.sheetDetailRow, styles.sheetDetailLast]}><Text style={styles.sheetDetailLabel}>Real wallet effect</Text><Text style={styles.sheetDetailValue}>None</Text></View>
        </View>
        <Text style={styles.sheetDisclosure}>
          Confirming updates only the simulator. No API, ledger, or production online balance is called or credited.
        </Text>
        <View style={styles.confirmationActions}>
          <AnimatedPressable style={styles.confirmCancel} onPress={() => setShowRedemptionConfirmation(false)}>
            <Text style={styles.confirmCancelText}>Cancel</Text>
          </AnimatedPressable>
          <AnimatedPressable
            style={[styles.sheetPrimary, !canRedeem ? styles.sheetPrimaryDisabled : null]}
            onPress={() => void confirmRedemption()}
            disabled={!canRedeem || isRedeeming}
          >
            <Ionicons name="checkmark" size={17} color={canRedeem ? "#17140c" : "#7f8792"} />
            <Text style={[styles.sheetPrimaryText, !canRedeem ? styles.disabledText : null]}>Confirm Redeem</Text>
          </AnimatedPressable>
        </View>
      </BottomSheet>

      <SyncProgressIndicator visible={syncStatus === "syncing"} stage={syncStage} />
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: "#090b12" },
  scrollContent: { alignItems: "center" },
  shell: { alignSelf: "center" },
  topBar: { height: 48, flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginBottom: 19 },
  backButton: { width: 44, height: 44, borderRadius: 13, borderWidth: 1, borderColor: "rgba(224,232,241,0.1)", backgroundColor: "#111720", alignItems: "center", justifyContent: "center" },
  brandLockup: { flexDirection: "row", alignItems: "center", gap: 9 },
  logo: { width: 34, height: 34, borderRadius: 10, resizeMode: "contain", borderWidth: 1, borderColor: "rgba(239,215,129,0.2)", backgroundColor: "rgba(239,215,129,0.05)" },
  brandName: { color: "#eef2f7", fontFamily: fonts.semibold, fontSize: 12 },
  brandCaption: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 9, letterSpacing: 1.5, marginTop: 1 },
  simulationChip: { minHeight: 27, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 5, borderRadius: 99, borderWidth: 1, borderColor: "rgba(232,184,102,0.22)", backgroundColor: "rgba(232,184,102,0.07)", paddingHorizontal: 8 },
  simulationDot: { width: 6, height: 6, borderRadius: 3, backgroundColor: "#e8b866" },
  simulationText: { color: "#e7c68e", fontFamily: fonts.semibold, fontSize: 9 },
  titleBlock: { flexDirection: "row", alignItems: "center", gap: 12, marginBottom: 15 },
  titleIcon: { width: 46, height: 46, borderRadius: 14, borderWidth: 1, borderColor: "rgba(170,145,240,0.18)", backgroundColor: "rgba(170,145,240,0.1)", alignItems: "center", justifyContent: "center" },
  titleCopy: { flex: 1 },
  title: { color: "#eef2f7", fontFamily: fonts.display, fontSize: 21, letterSpacing: -0.5 },
  systemFont: { fontFamily: undefined },
  subtitle: { color: "#9da8b7", fontFamily: fonts.body, fontSize: 10, lineHeight: 15, marginTop: 4 },
  connectivityCard: { minHeight: 54, flexDirection: "row", alignItems: "center", gap: 9, borderRadius: 12, borderWidth: 1, borderColor: "rgba(232,184,102,0.15)", backgroundColor: "rgba(232,184,102,0.045)", paddingHorizontal: 10, marginBottom: 19 },
  connectivityIcon: { width: 30, height: 30, borderRadius: 9, backgroundColor: "rgba(232,184,102,0.08)", alignItems: "center", justifyContent: "center" },
  connectivityCopy: { flex: 1 },
  connectivityTitle: { color: "#e4d6b7", fontFamily: fonts.semibold, fontSize: 11 },
  connectivitySubtitle: { color: "#a69b84", fontFamily: fonts.body, fontSize: 9, marginTop: 3 },
  deviceProtocolCard: { borderRadius: 14, borderWidth: 1, borderColor: "rgba(170,145,240,0.16)", backgroundColor: "rgba(170,145,240,0.045)", padding: 11, marginBottom: 17 },
  deviceProtocolHeader: { flexDirection: "row", alignItems: "flex-start", gap: 9 },
  deviceProtocolIcon: { width: 31, height: 31, borderRadius: 9, alignItems: "center", justifyContent: "center", backgroundColor: "rgba(170,145,240,0.1)" },
  deviceProtocolCopy: { flex: 1 },
  deviceProtocolTitle: { color: "#e7e1f5", fontFamily: fonts.semibold, fontSize: 10 },
  deviceProtocolDescription: { color: "#9da8b7", fontFamily: fonts.body, fontSize: 9, lineHeight: 14, marginTop: 3 },
  deviceProtocolButton: { minHeight: 43, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 7, borderRadius: 10, backgroundColor: colors.auric300, paddingHorizontal: 9, marginTop: 9 },
  deviceProtocolInput: { minHeight: 42, borderWidth: 1, borderColor: "rgba(224,232,241,0.12)", borderRadius: 9, backgroundColor: "#0b1017", paddingHorizontal: 11, color: "#e4e9f0", fontFamily: fonts.body, fontSize: 10, marginTop: 9 },
  deviceProtocolResult: { color: "#bfbbcc", fontFamily: fonts.body, fontSize: 9, lineHeight: 14, marginTop: 8 },
  deviceProtocolWarning: { color: "#837f91", fontFamily: fonts.body, fontSize: 8, lineHeight: 13, marginTop: 7 },
  sectionHeader: { minHeight: 31, flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginBottom: 9, marginTop: 3 },
  sectionTitle: { color: "#e9edf3", fontFamily: fonts.semibold, fontSize: 12 },
  sectionHint: { color: "#8793a2", fontFamily: fonts.body, fontSize: 10, lineHeight: 14, marginTop: 4 },
  sectionAction: { minWidth: 44, minHeight: 44, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 5, paddingLeft: 8 },
  sectionActionText: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 10 },
  overviewCard: { borderRadius: 15, borderWidth: 1, borderColor: "rgba(224,232,241,0.09)", backgroundColor: "#111720", padding: 11, marginBottom: 17 },
  balanceGrid: { flexDirection: "row", gap: 7 },
  balanceTile: { minWidth: 0, minHeight: 91, flex: 1, justifyContent: "center", borderRadius: 11, borderWidth: 1, borderColor: "rgba(255,255,255,0.06)", backgroundColor: "#0d131b", paddingHorizontal: 8, paddingVertical: 9 },
  balanceTitleRow: { minHeight: 28, flexDirection: "row", alignItems: "flex-start", gap: 6 },
  balanceMarker: { width: 6, height: 6, borderRadius: 3, marginTop: 4 },
  balanceMarker_purple: { backgroundColor: "#aa91f0" },
  balanceMarker_amber: { backgroundColor: "#e8b866" },
  balanceMarker_green: { backgroundColor: "#72d3a0" },
  balanceLabel: { flex: 1, color: "#a6b0bd", fontFamily: fonts.medium, fontSize: 9, lineHeight: 12 },
  balanceAmount: { color: "#eef2f7", fontFamily: fonts.display, fontSize: 16, letterSpacing: -0.25, marginTop: 5 },
  balanceAmountMuted: { color: "#8692a1" },
  balanceAsset: { color: "#8995a5", fontFamily: fonts.body, fontSize: 9, marginTop: 2 },
  balanceExplanation: { flexDirection: "row", alignItems: "flex-start", gap: 7, marginTop: 10, paddingHorizontal: 2 },
  balanceExplanationText: { flex: 1, color: "#9da8b7", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  queueHeading: { minHeight: 45, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 8, marginTop: 2 },
  queueHeadingCopy: { flex: 1 },
  syncButton: { minHeight: 44, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 6, borderRadius: 11, backgroundColor: colors.auric300, paddingHorizontal: 11 },
  syncButtonDisabled: { backgroundColor: "#29303a" },
  syncButtonText: { color: "#17140c", fontFamily: fonts.semibold, fontSize: 10 },
  disabledText: { color: "#7f8792" },
  syncStatusRow: { minHeight: 27, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 8, marginBottom: 8 },
  syncStatusText: { color: "#7f8a99", fontFamily: fonts.body, fontSize: 9 },
  statusBadge: { minHeight: 22, flexDirection: "row", alignItems: "center", gap: 5, alignSelf: "flex-start", borderRadius: 99, paddingHorizontal: 7, paddingVertical: 3 },
  statusBadge_amber: { backgroundColor: "rgba(232,184,102,0.11)" },
  statusBadge_green: { backgroundColor: "rgba(114,211,160,0.11)" },
  statusBadge_red: { backgroundColor: "rgba(240,140,140,0.1)" },
  statusBadge_purple: { backgroundColor: "rgba(170,145,240,0.11)" },
  statusBadge_muted: { backgroundColor: "rgba(255,255,255,0.06)" },
  statusDot: { width: 5, height: 5, borderRadius: 3 },
  statusDot_amber: { backgroundColor: "#e8b866" },
  statusDot_green: { backgroundColor: "#72d3a0" },
  statusDot_red: { backgroundColor: "#f08c8c" },
  statusDot_purple: { backgroundColor: "#aa91f0" },
  statusDot_muted: { backgroundColor: "#8f9aaa" },
  statusLabel: { fontFamily: fonts.medium, fontSize: 9 },
  statusLabel_amber: { color: "#e7c78c" },
  statusLabel_green: { color: "#8ed9b1" },
  statusLabel_red: { color: "#efa3a3" },
  statusLabel_purple: { color: "#c4b5ef" },
  statusLabel_muted: { color: "#aeb8c7" },
  queueCard: { borderRadius: 14, borderWidth: 1, borderColor: "rgba(232,184,102,0.17)", backgroundColor: "#111720", padding: 12, marginBottom: 15 },
  queueCardTop: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 8 },
  queueRefGroup: { flexDirection: "row", alignItems: "center", gap: 9, flex: 1 },
  transactionIcon: { width: 34, height: 34, borderRadius: 10, backgroundColor: "rgba(170,145,240,0.1)", alignItems: "center", justifyContent: "center" },
  queueRefCopy: { flex: 1 },
  queueReference: { color: "#e8edf4", fontFamily: fonts.semibold, fontSize: 10 },
  queueSender: { color: "#929dad", fontFamily: fonts.body, fontSize: 10, marginTop: 3 },
  queueAmountRow: { minHeight: 39, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 8, borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.065)" },
  queueAmount: { color: "#eef2f7", fontFamily: fonts.semibold, fontSize: 14 },
  queueAsset: { color: "#929dad", fontFamily: fonts.medium, fontSize: 10 },
  queueStatusRow: { minHeight: 33, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 8 },
  queueStatusLabel: { color: "#929dad", fontFamily: fonts.body, fontSize: 10 },
  queueEmpty: { minHeight: 74, flexDirection: "row", alignItems: "center", gap: 10, borderRadius: 13, borderWidth: 1, borderColor: "rgba(114,211,160,0.14)", backgroundColor: "rgba(114,211,160,0.04)", padding: 12, marginBottom: 15 },
  queueEmptyIcon: { width: 36, height: 36, borderRadius: 11, backgroundColor: "rgba(114,211,160,0.09)", alignItems: "center", justifyContent: "center" },
  queueEmptyCopy: { flex: 1 },
  queueEmptyTitle: { color: "#dcebe1", fontFamily: fonts.semibold, fontSize: 11 },
  queueEmptyText: { color: "#9aa79f", fontFamily: fonts.body, fontSize: 9, lineHeight: 14, marginTop: 3 },
  feedbackCard: { flexDirection: "row", alignItems: "flex-start", gap: 8, borderRadius: 11, padding: 10, marginBottom: 9 },
  feedbackWarning: { borderWidth: 1, borderColor: "rgba(232,184,102,0.18)", backgroundColor: "rgba(232,184,102,0.06)" },
  feedbackDanger: { borderWidth: 1, borderColor: "rgba(240,140,140,0.2)", backgroundColor: "rgba(240,140,140,0.06)" },
  feedbackText: { flex: 1, color: "#c9b991", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  redemptionCard: { borderRadius: 15, borderWidth: 1, borderColor: "rgba(114,211,160,0.15)", backgroundColor: "#111720", padding: 13, marginBottom: 17 },
  redemptionHeading: { flexDirection: "row", alignItems: "center", gap: 9 },
  redemptionIcon: { width: 35, height: 35, borderRadius: 11, backgroundColor: "rgba(232,184,102,0.08)", alignItems: "center", justifyContent: "center" },
  redemptionTitleCopy: { flex: 1 },
  redemptionTitle: { color: "#e8edf4", fontFamily: fonts.semibold, fontSize: 11 },
  redemptionSubtitle: { color: "#929dad", fontFamily: fonts.body, fontSize: 9, lineHeight: 13, marginTop: 3 },
  redeemAmountBox: { minHeight: 67, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 10, borderRadius: 11, backgroundColor: "#0d131b", paddingHorizontal: 11, marginTop: 12 },
  redeemAmountLabel: { color: "#929dad", fontFamily: fonts.body, fontSize: 9 },
  redeemAmount: { color: "#eaf0f5", fontFamily: fonts.display, fontSize: 17, marginTop: 4 },
  redeemAsset: { color: "#929dad", fontFamily: fonts.medium, fontSize: 10 },
  redeemReason: { color: "#8591a0", fontFamily: fonts.body, fontSize: 10, lineHeight: 15, marginTop: 8 },
  redeemButton: { minHeight: 47, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 7, borderRadius: 11, backgroundColor: colors.auric300, marginTop: 11 },
  redeemButtonDisabled: { backgroundColor: "#232a33" },
  redeemButtonText: { color: "#17140c", fontFamily: fonts.semibold, fontSize: 10 },
  redeemButtonTextDisabled: { color: "#7f8792" },
  verificationNote: { flexDirection: "row", alignItems: "flex-start", gap: 7, marginTop: 10 },
  verificationNoteText: { flex: 1, color: "#8f9aaa", fontFamily: fonts.body, fontSize: 9, lineHeight: 14 },
  outcomeCard: { flexDirection: "row", alignItems: "flex-start", gap: 9, borderRadius: 12, padding: 11, marginTop: -7, marginBottom: 14 },
  outcomeSuccess: { borderWidth: 1, borderColor: "rgba(114,211,160,0.2)", backgroundColor: "rgba(114,211,160,0.055)" },
  outcomeFailure: { borderWidth: 1, borderColor: "rgba(240,140,140,0.2)", backgroundColor: "rgba(240,140,140,0.055)" },
  outcomeRecovery: { borderWidth: 1, borderColor: "rgba(232,184,102,0.2)", backgroundColor: "rgba(232,184,102,0.055)" },
  outcomeCopy: { flex: 1 },
  outcomeTitle: { color: "#e5ece8", fontFamily: fonts.semibold, fontSize: 11 },
  outcomeDescription: { color: "#9ba9a1", fontFamily: fonts.body, fontSize: 9, lineHeight: 14, marginTop: 3 },
  simulatedCredit: { color: "#9ae0b7", fontFamily: fonts.medium, fontSize: 9, marginTop: 5 },
  historyCard: { borderRadius: 14, borderWidth: 1, borderColor: "rgba(224,232,241,0.08)", backgroundColor: "#111720", paddingHorizontal: 11, marginBottom: 15 },
  historyRow: { minHeight: 64, flexDirection: "row", alignItems: "center", gap: 8, paddingVertical: 8 },
  historyDivider: { borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.065)" },
  historyIcon: { width: 31, height: 31, borderRadius: 10, alignItems: "center", justifyContent: "center" },
  historyIcon_green: { backgroundColor: "rgba(114,211,160,0.09)" },
  historyIcon_red: { backgroundColor: "rgba(240,140,140,0.08)" },
  historyIcon_amber: { backgroundColor: "rgba(232,184,102,0.08)" },
  historyIcon_muted: { backgroundColor: "rgba(255,255,255,0.05)" },
  historyIcon_purple: { backgroundColor: "rgba(170,145,240,0.08)" },
  historyCopy: { flex: 1, minWidth: 0 },
  historyReference: { color: "#e4e9f0", fontFamily: fonts.semibold, fontSize: 10 },
  historyTime: { color: "#8793a2", fontFamily: fonts.body, fontSize: 9, marginTop: 3 },
  historyStatus: { alignItems: "flex-end", gap: 4 },
  historyAmount: { color: "#9da8b7", fontFamily: fonts.medium, fontSize: 9 },
  historyEmpty: { minHeight: 70, alignItems: "center", justifyContent: "center", gap: 7 },
  historyEmptyText: { color: "#8f9aaa", fontFamily: fonts.body, fontSize: 10 },
  historyActions: { minHeight: 44, flexDirection: "row", alignItems: "center", justifyContent: "space-between", borderTopWidth: 1, borderTopColor: "rgba(255,255,255,0.06)" },
  historyActionButton: { minHeight: 44, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 6, paddingHorizontal: 5 },
  historyActionText: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 10 },
  securityCard: { flexDirection: "row", alignItems: "flex-start", gap: 10, borderRadius: 13, borderWidth: 1, borderColor: "rgba(170,145,240,0.14)", backgroundColor: "rgba(170,145,240,0.045)", padding: 12, marginBottom: 11 },
  securityIcon: { width: 35, height: 35, borderRadius: 11, backgroundColor: "rgba(170,145,240,0.09)", alignItems: "center", justifyContent: "center" },
  securityCopy: { flex: 1 },
  securityTitle: { color: "#e4e0ef", fontFamily: fonts.semibold, fontSize: 11 },
  securityDescription: { color: "#a5a1b1", fontFamily: fonts.body, fontSize: 10, lineHeight: 15, marginTop: 4 },
  footerNote: { color: "#788493", fontFamily: fonts.body, fontSize: 9, lineHeight: 14, textAlign: "center", paddingHorizontal: 8, marginBottom: 4 },
  sheetBackdrop: { flex: 1, justifyContent: "flex-end", backgroundColor: "rgba(0,0,0,0.68)" },
  sheet: { maxHeight: "88%", borderTopLeftRadius: 22, borderTopRightRadius: 22, borderWidth: 1, borderBottomWidth: 0, borderColor: "rgba(224,232,241,0.11)", backgroundColor: "#111720", paddingHorizontal: 17, paddingTop: 9 },
  sheetHandle: { width: 38, height: 4, borderRadius: 3, alignSelf: "center", backgroundColor: "rgba(255,255,255,0.22)", marginBottom: 12 },
  sheetHeader: { minHeight: 44, flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginBottom: 8 },
  sheetTitle: { color: "#edf1f6", fontFamily: fonts.display, fontSize: 16 },
  sheetClose: { width: 44, height: 44, alignItems: "center", justifyContent: "center" },
  sheetTransactionHeader: { flexDirection: "row", alignItems: "center", gap: 9, marginVertical: 5 },
  transactionIconLarge: { width: 40, height: 40, borderRadius: 12, backgroundColor: "rgba(170,145,240,0.1)", alignItems: "center", justifyContent: "center" },
  sheetTransactionCopy: { flex: 1 },
  sheetTransactionSender: { color: "#e8edf4", fontFamily: fonts.semibold, fontSize: 11 },
  sheetTransactionAmount: { color: "#a4afbd", fontFamily: fonts.body, fontSize: 10, marginTop: 3 },
  sheetDetails: { borderRadius: 12, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "#0d131b", paddingHorizontal: 11, marginTop: 10 },
  sheetDetailRow: { minHeight: 43, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 10, borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.06)" },
  sheetDetailLast: { borderBottomWidth: 0 },
  sheetDetailLabel: { color: "#929dad", fontFamily: fonts.body, fontSize: 10 },
  sheetDetailValue: { color: "#dce3ed", fontFamily: fonts.medium, fontSize: 10, textAlign: "right", flexShrink: 1 },
  sheetDisclosure: { color: "#929dad", fontFamily: fonts.body, fontSize: 10, lineHeight: 15, marginTop: 10, marginBottom: 7 },
  sheetPrimary: { minHeight: 46, flex: 1, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 7, borderRadius: 11, backgroundColor: colors.auric300, paddingHorizontal: 10, marginTop: 9 },
  sheetPrimaryText: { color: "#17140c", fontFamily: fonts.semibold, fontSize: 10 },
  sheetPrimaryDisabled: { backgroundColor: "#232a33" },
  confirmationSummary: { alignItems: "center", paddingVertical: 6 },
  confirmationAmount: { color: "#eff2f7", fontFamily: fonts.display, fontSize: 25, marginTop: 11 },
  confirmationAsset: { color: "#929dad", fontFamily: fonts.medium, fontSize: 11 },
  confirmationCopy: { color: "#9da8b7", fontFamily: fonts.body, fontSize: 10, textAlign: "center", marginTop: 4 },
  confirmationDetails: { borderRadius: 12, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "#0d131b", paddingHorizontal: 11, marginTop: 8 },
  confirmationActions: { flexDirection: "row", gap: 8, marginTop: 5 },
  confirmCancel: { minHeight: 46, flex: 0.7, alignItems: "center", justifyContent: "center", borderRadius: 11, borderWidth: 1, borderColor: "rgba(224,232,241,0.15)", marginTop: 9 },
  confirmCancelText: { color: "#cbd3dd", fontFamily: fonts.medium, fontSize: 10 },
  progressBackdrop: { flex: 1, justifyContent: "center", padding: 20, backgroundColor: "rgba(0,0,0,0.7)" },
  progressCard: { width: "100%", maxWidth: 430, alignSelf: "center", borderRadius: 17, borderWidth: 1, borderColor: "rgba(224,232,241,0.12)", backgroundColor: "#111720", padding: 16 },
  progressHeader: { flexDirection: "row", alignItems: "center", gap: 10 },
  progressHeaderCopy: { flex: 1 },
  progressTitle: { color: "#edf1f6", fontFamily: fonts.semibold, fontSize: 12 },
  progressSubtitle: { color: "#929dad", fontFamily: fonts.body, fontSize: 10, marginTop: 3 },
  progressSteps: { gap: 10, marginTop: 16 },
  progressStep: { minHeight: 25, flexDirection: "row", alignItems: "center", gap: 9 },
  progressStepDot: { width: 17, height: 17, borderRadius: 9, borderWidth: 1, borderColor: "rgba(255,255,255,0.18)", alignItems: "center", justifyContent: "center" },
  progressStepDone: { backgroundColor: colors.auric300, borderColor: colors.auric300 },
  progressStepActive: { borderColor: colors.auric300 },
  progressStepLabel: { color: "#8490a0", fontFamily: fonts.body, fontSize: 10 },
  progressStepLabelActive: { color: "#e6d39a", fontFamily: fonts.semibold },
});
