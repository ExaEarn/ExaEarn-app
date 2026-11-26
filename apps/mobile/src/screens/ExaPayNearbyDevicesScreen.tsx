import { Ionicons } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import {
  ActivityIndicator,
  BackHandler,
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import {
  createNearbyDeviceSimulator,
  type IncomingTransferRequest,
  type NearbyConnectionState,
  type NearbyDevice,
  type NearbyHistoryItem,
  type NearbyTransferState,
} from "../services/exaPayNearbySimulator";
import { colors, fonts } from "../theme/colors";

type Props = {
  fontsReady: boolean;
  onBack: () => void;
  onOpenQrFallback: () => void;
  onViewAllHistory: () => void;
};

type SheetState = "device" | "incoming" | "reject" | null;

const stateLabels: Record<NearbyTransferState, string> = {
  PREPARED: "Awaiting Confirmation",
  COMMITTED: "Offline Committed",
  PENDING_RECONCILIATION: "Pending Reconciliation",
  FAILED: "Failed",
  RECOVERY_REQUIRED: "Recovery Required",
  REJECTED: "Declined",
};

function formatAmount(amountMinor: number) {
  return `${Math.floor(amountMinor / 100)}.${String(amountMinor % 100).padStart(2, "0")}`;
}

function formatTime(timestamp: string) {
  return new Date(timestamp).toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
}

function StatusBadge({
  label,
  tone,
}: {
  label: string;
  tone: "green" | "purple" | "amber" | "red" | "muted";
}) {
  return (
    <View style={[styles.badge, styles[`badge_${tone}`]]} accessible accessibilityLabel={label}>
      <View style={[styles.badgeDot, styles[`badgeDot_${tone}`]]} />
      <Text style={[styles.badgeText, styles[`badgeText_${tone}`]]}>{label}</Text>
    </View>
  );
}

function SectionHeader({
  title,
  action,
  onAction,
}: {
  title: string;
  action?: string;
  onAction?: () => void;
}) {
  return (
    <View style={styles.sectionHeader}>
      <Text style={styles.sectionTitle} accessibilityRole="header">{title}</Text>
      {action && onAction ? (
        <AnimatedPressable
          style={styles.sectionActionButton}
          onPress={onAction}
          accessibilityLabel={`${action} ${title}`}
        >
          <Text style={styles.sectionAction}>{action}</Text>
        </AnimatedPressable>
      ) : null}
    </View>
  );
}

function DeviceCard({
  device,
  connectionState,
  connected,
  onPress,
}: {
  device: NearbyDevice;
  connectionState: NearbyConnectionState;
  connected: boolean;
  onPress: () => void;
}) {
  const isTrusted = device.trust === "trusted-simulation";
  const isConnecting = connected && connectionState === "connecting";
  return (
    <AnimatedPressable
      style={styles.deviceCard}
      onPress={onPress}
      accessibilityLabel={`${device.name}, ${isTrusted ? "trusted in simulation" : "unverified"}, signal ${device.signalQuality}`}
    >
      <View style={styles.deviceGlyph}>
        <Ionicons name="phone-portrait-outline" size={19} color="#b4a1ed" />
      </View>
      <View style={styles.deviceCopy}>
        <Text style={styles.deviceName} numberOfLines={1}>{device.name}</Text>
        <View style={styles.deviceMetaRow}>
          <StatusBadge label={isTrusted ? "Trusted · sample" : "Unverified"} tone={isTrusted ? "purple" : "amber"} />
          <View style={styles.signalMeta}>
            <Ionicons name="wifi-outline" size={12} color="#929eae" />
            <Text style={styles.signalText}>{device.signalQuality} signal</Text>
          </View>
        </View>
        {isConnecting ? (
          <Text style={styles.connectionHint}>Connecting in simulation…</Text>
        ) : connected && connectionState === "connected" ? (
          <Text style={styles.connectedHint}>Connected · simulation only</Text>
        ) : null}
      </View>
      <Ionicons name="chevron-forward" size={18} color="#8995a5" />
    </AnimatedPressable>
  );
}

function HistoryRow({ item, last }: { item: NearbyHistoryItem; last: boolean }) {
  const tone = item.state === "COMMITTED"
    ? "green"
    : item.state === "PENDING_RECONCILIATION" || item.state === "PREPARED"
      ? "amber"
      : item.state === "FAILED" || item.state === "RECOVERY_REQUIRED"
        ? "red"
        : "muted";
  return (
    <View style={[styles.historyRow, !last ? styles.historyDivider : null]}>
      <View style={styles.historyGlyph}>
        <Ionicons
          name={item.state === "REJECTED" || item.state === "FAILED" ? "arrow-down-outline" : "arrow-up-outline"}
          size={16}
          color={item.state === "COMMITTED" ? "#72d3a0" : "#c0cad6"}
        />
      </View>
      <View style={styles.historyCopy}>
        <Text style={styles.historyTitle} numberOfLines={1}>{item.title}</Text>
        <Text style={styles.historyTime}>{formatTime(item.timestamp)} · {item.reference}</Text>
        <StatusBadge label={stateLabels[item.state]} tone={tone} />
      </View>
      <Text style={styles.historyAmount}>{formatAmount(item.amountMinor)}{"\n"}<Text style={styles.historyCurrency}>simUSDT</Text></Text>
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
      <View style={styles.modalRoot}>
        <Pressable
          style={StyleSheet.absoluteFill}
          onPress={onClose}
          accessibilityRole="button"
          accessibilityLabel="Close sheet"
        />
        <View style={[styles.sheet, { paddingBottom: Math.max(bottomInset, 16) + 18 }]} accessibilityViewIsModal>
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

export default function ExaPayNearbyDevicesScreen({
  fontsReady,
  onBack,
  onOpenQrFallback,
  onViewAllHistory,
}: Props) {
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const shellWidth = Math.min(width - 32, 500);
  const simulator = useMemo(() => createNearbyDeviceSimulator(), []);
  const [devices, setDevices] = useState<NearbyDevice[]>([]);
  const [incoming, setIncoming] = useState<IncomingTransferRequest | null>(() => simulator.getIncomingRequest());
  const [history, setHistory] = useState<NearbyHistoryItem[]>(() => simulator.getHistory());
  const [sheet, setSheet] = useState<SheetState>(null);
  const [selectedDeviceId, setSelectedDeviceId] = useState("sim-nearby-kendrick");
  const [connectionState, setConnectionState] = useState<NearbyConnectionState>("disconnected");
  const [connectedDeviceId, setConnectedDeviceId] = useState("");
  const [isDiscovering, setIsDiscovering] = useState(false);
  const [isConnecting, setIsConnecting] = useState(false);
  const [isAuthenticating, setIsAuthenticating] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [authenticatedReference, setAuthenticatedReference] = useState("");
  const [discoveryError, setDiscoveryError] = useState("");
  const [permissionNote, setPermissionNote] = useState("");
  const [actionError, setActionError] = useState("");
  const [lastDecision, setLastDecision] = useState<NearbyTransferState | null>(null);
  const submissionLock = useRef(false);

  const selectedDevice = devices.find((device) => device.id === selectedDeviceId) ?? null;
  const senderConnected = Boolean(incoming && connectedDeviceId === incoming.senderDeviceId && connectionState === "connected");
  const senderAuthenticated = Boolean(incoming && authenticatedReference === incoming.reference);

  const discoverDevices = useCallback(async () => {
    setIsDiscovering(true);
    setDiscoveryError("");
    try {
      const [found, permission] = await Promise.all([
        simulator.discoverDevices(),
        simulator.requestBluetoothPermission(),
      ]);
      setDevices(found);
      setPermissionNote(permission.message);
    } catch {
      setDiscoveryError("Nearby sample devices could not be loaded. Try again.");
    } finally {
      setIsDiscovering(false);
    }
  }, [simulator]);

  useEffect(() => {
    void discoverDevices();
  }, [discoverDevices]);

  useEffect(() => {
    const subscription = BackHandler.addEventListener("hardwareBackPress", () => {
      if (sheet) {
        setSheet(null);
        return true;
      }
      onBack();
      return true;
    });
    return () => subscription.remove();
  }, [onBack, sheet]);

  async function connectTo(deviceId: string, retry = false) {
    if (isConnecting) return;
    setSelectedDeviceId(deviceId);
    setIsConnecting(true);
    setConnectionState("connecting");
    setConnectedDeviceId(deviceId);
    setActionError("");
    setPermissionNote(
      "Bluetooth permission was not requested: this build has no native BLE transport configured. Connecting is simulation-only.",
    );
    try {
      const state = retry
        ? await simulator.retryConnection(deviceId)
        : await simulator.connectToDevice(deviceId);
      setConnectionState(state);
      setConnectedDeviceId(state === "connected" ? deviceId : "");
      if (state !== "connected") {
        setActionError("The simulated connection could not be established. Check availability and retry.");
      }
    } catch {
      setConnectionState("failed");
      setConnectedDeviceId("");
      setActionError("The simulated connection failed. Retry to try again.");
    } finally {
      setIsConnecting(false);
    }
  }

  function disconnect() {
    setConnectionState(simulator.disconnect());
    setConnectedDeviceId("");
    setAuthenticatedReference("");
    setActionError("");
  }

  function openIncomingConfirmation() {
    setLastDecision(null);
    setActionError("");
    setSheet("incoming");
  }

  function openRejectConfirmation() {
    setActionError("");
    setSheet("reject");
  }

  async function authenticateSender() {
    if (!incoming || isAuthenticating) return;
    if (!senderConnected) {
      await connectTo(incoming.senderDeviceId);
      return;
    }
    setIsAuthenticating(true);
    setActionError("");
    try {
      const result = await simulator.authenticateIncomingRequest(incoming.reference);
      if (!result.authenticated) {
        setActionError("The sender could not be authenticated in the simulator.");
        return;
      }
      setAuthenticatedReference(incoming.reference);
    } catch {
      setActionError("Simulated device authentication failed. No payment was accepted.");
    } finally {
      setIsAuthenticating(false);
    }
  }

  async function acceptPayment() {
    if (!incoming || submissionLock.current || !senderAuthenticated) return;
    submissionLock.current = true;
    setIsSubmitting(true);
    setActionError("");
    try {
      const result = await simulator.acceptIncomingRequest(incoming.reference);
      setLastDecision(result.state);
      setIncoming(simulator.getIncomingRequest());
      setHistory(simulator.getHistory());
      if (result.state === "FAILED" || result.state === "RECOVERY_REQUIRED") {
        setActionError("The incoming request was not completed. No simulated value was added to any balance.");
      }
      setSheet(null);
    } catch {
      setLastDecision("RECOVERY_REQUIRED");
      setActionError("The transfer result is uncertain. Stop and reconcile before retrying. No balance was credited.");
      setSheet(null);
    } finally {
      submissionLock.current = false;
      setIsSubmitting(false);
    }
  }

  async function rejectPayment() {
    if (!incoming || submissionLock.current) return;
    submissionLock.current = true;
    setIsSubmitting(true);
    setActionError("");
    try {
      const result = await simulator.rejectIncomingRequest(incoming.reference);
      setLastDecision(result.state);
      setIncoming(simulator.getIncomingRequest());
      setHistory(simulator.getHistory());
      setSheet(null);
    } catch {
      setActionError("The simulated request could not be rejected. Try again.");
    } finally {
      submissionLock.current = false;
      setIsSubmitting(false);
    }
  }

  return (
    <View style={styles.fill}>
      <LinearGradient colors={["#090b12", "#0c1018", "#0a0d14"]} style={StyleSheet.absoluteFill} />
      <ScrollView
        contentContainerStyle={[
          styles.scrollContent,
          { paddingTop: insets.top + 8, paddingBottom: Math.max(insets.bottom, 14) + 24 },
        ]}
        showsVerticalScrollIndicator={false}
      >
        <View style={[styles.shell, { width: shellWidth }]}>
          <View style={styles.topBar}>
            <AnimatedPressable style={styles.backButton} onPress={onBack} accessibilityLabel="Back to ExaPay">
              <Ionicons name="arrow-back" size={20} color="#e1e6ed" />
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
            <View style={styles.headerSimulation}>
              <View style={styles.simulationDot} />
              <Text style={styles.headerSimulationText}>SIM</Text>
            </View>
          </View>

          <View style={styles.titleBlock}>
            <View style={styles.titleIcon}>
              <Ionicons name="bluetooth-outline" size={21} color="#b7a3ef" />
            </View>
            <View style={styles.titleCopy}>
              <Text style={[styles.title, !fontsReady ? styles.systemFont : null]} accessibilityRole="header">Nearby Devices</Text>
              <Text style={styles.subtitle}>Discover and connect to nearby ExaPay devices for offline payments.</Text>
            </View>
          </View>

          <View style={styles.simulationNotice}>
            <Ionicons name="information-circle-outline" size={17} color="#e5c181" />
            <Text style={styles.simulationNoticeText}>
              Simulation Mode · Sample devices only. Native Bluetooth scanning and permission prompts are not configured in this build.
            </Text>
          </View>

          <View style={styles.sectionHeader}>
            <View>
              <Text style={styles.sectionTitle}>Nearby devices</Text>
              <Text style={styles.sectionHint}>Signal quality shown · distance unavailable</Text>
            </View>
            <AnimatedPressable
              style={styles.refreshButton}
              onPress={() => void discoverDevices()}
              disabled={isDiscovering}
              accessibilityLabel="Refresh nearby sample devices"
            >
              {isDiscovering
                ? <ActivityIndicator size="small" color={colors.auric300} />
                : <Ionicons name="refresh" size={17} color="#cbd3dd" />}
            </AnimatedPressable>
          </View>

          {discoveryError ? (
            <View style={styles.inlineError} accessibilityRole="alert">
              <Ionicons name="alert-circle-outline" size={17} color="#ef9999" />
              <Text style={styles.inlineErrorText}>{discoveryError}</Text>
              <AnimatedPressable onPress={() => void discoverDevices()} accessibilityLabel="Retry device discovery">
                <Text style={styles.retryText}>Retry</Text>
              </AnimatedPressable>
            </View>
          ) : null}
          {isDiscovering && devices.length === 0 ? (
            <View style={styles.loadingCard}><ActivityIndicator color={colors.auric300} /><Text style={styles.emptyCopy}>Loading sample devices…</Text></View>
          ) : devices.length ? (
            <View style={styles.deviceList}>
              {devices.map((device) => (
                <DeviceCard
                  key={device.id}
                  device={device}
                  connectionState={connectionState}
                  connected={connectedDeviceId === device.id}
                  onPress={() => {
                    setSelectedDeviceId(device.id);
                    setSheet("device");
                  }}
                />
              ))}
            </View>
          ) : !discoveryError ? (
            <View style={styles.emptyCard}>
              <View style={styles.emptyIcon}><Ionicons name="radio-outline" size={22} color="#a991f0" /></View>
              <Text style={styles.emptyTitle}>No nearby devices</Text>
              <Text style={styles.emptyCopy}>Try refreshing the sample list. This screen does not run a live Bluetooth scan.</Text>
              <AnimatedPressable style={styles.retryButton} onPress={() => void discoverDevices()}>
                <Ionicons name="refresh" size={16} color="#e8d69e" />
                <Text style={styles.retryButtonText}>Refresh devices</Text>
              </AnimatedPressable>
            </View>
          ) : null}
          {permissionNote ? <Text style={styles.permissionNote}>{permissionNote}</Text> : null}

          <SectionHeader title="Incoming transfer" />
          {incoming ? (
            <View style={styles.incomingCard}>
              <View style={styles.incomingTop}>
                <StatusBadge label="INCOMING TRANSFER" tone="purple" />
                <StatusBadge label="Awaiting Confirmation" tone="amber" />
              </View>
              <Text style={[styles.incomingAmount, !fontsReady ? styles.systemFont : null]}>
                {formatAmount(incoming.amountMinor)} <Text style={styles.incomingCurrency}>simUSDT</Text>
              </Text>
              <Text style={styles.fromLabel}>From</Text>
              <View style={styles.senderRow}>
                <View style={styles.senderIcon}><Ionicons name="phone-portrait-outline" size={18} color="#b8a5ee" /></View>
                <View style={styles.senderCopy}>
                  <Text style={styles.senderName}>{incoming.senderName}</Text>
                  <Text style={styles.senderMethod}>Bluetooth · Sample request</Text>
                </View>
                <StatusBadge label={senderAuthenticated ? "Authenticated · simulated" : "Not authenticated"} tone={senderAuthenticated ? "purple" : "amber"} />
              </View>
              <View style={styles.transferDetails}>
                <View style={styles.transferDetailRow}>
                  <Text style={styles.transferDetailLabel}>Transfer reference</Text>
                  <Text style={styles.transferDetailValue}>{incoming.reference}</Text>
                </View>
                <View style={styles.transferDetailRow}>
                  <Text style={styles.transferDetailLabel}>Device authentication</Text>
                  <Text style={styles.transferDetailValue}>{senderAuthenticated ? "Simulated only" : "Not yet performed"}</Text>
                </View>
                <View style={[styles.transferDetailRow, styles.transferDetailLast]}>
                  <Text style={styles.transferDetailLabel}>Transfer method</Text>
                  <Text style={styles.transferDetailValue}>{incoming.method} · simulated</Text>
                </View>
              </View>
              <View style={styles.incomingActions}>
                <AnimatedPressable style={styles.acceptButton} onPress={openIncomingConfirmation}>
                  <Ionicons name="checkmark-circle-outline" size={18} color="#06140e" />
                  <Text style={styles.acceptButtonText}>Accept Payment</Text>
                </AnimatedPressable>
                <AnimatedPressable style={styles.rejectButton} onPress={openRejectConfirmation}>
                  <Text style={styles.rejectButtonText}>Reject</Text>
                </AnimatedPressable>
              </View>
              <AnimatedPressable style={styles.qrFallback} onPress={onOpenQrFallback}>
                <Ionicons name="qr-code-outline" size={16} color="#bda9ef" />
                <Text style={styles.qrFallbackText}>QR Fallback</Text>
                <Ionicons name="arrow-forward" size={14} color="#a4adba" />
              </AnimatedPressable>
            </View>
          ) : (
            <View style={styles.emptyIncoming}>
              <View style={styles.emptyIncomingIcon}><Ionicons name="checkmark-done-outline" size={20} color="#72d3a0" /></View>
              <View style={styles.emptyIncomingCopy}>
                <Text style={styles.emptyIncomingTitle}>{lastDecision === "PENDING_RECONCILIATION" ? "Request accepted for reconciliation" : lastDecision === "REJECTED" ? "Request declined" : "No incoming request"}</Text>
                <Text style={styles.emptyIncomingDescription}>
                  {lastDecision === "PENDING_RECONCILIATION"
                    ? "The simulated request is pending reconciliation. No wallet balance was credited."
                    : lastDecision === "REJECTED"
                      ? "The simulated request was declined. No value was received."
                      : "When a supported request is available, its verified details will appear here."}
                </Text>
              </View>
            </View>
          )}

          {actionError ? (
            <View style={styles.inlineError} accessibilityRole="alert">
              <Ionicons name="alert-circle-outline" size={17} color="#ef9999" />
              <Text style={styles.inlineErrorText}>{actionError}</Text>
            </View>
          ) : null}

          <SectionHeader title="Offline history" action="View All" onAction={onViewAllHistory} />
          <View style={styles.historyCard}>
            {history.slice(0, 3).map((item, index) => (
              <HistoryRow key={item.id} item={item} last={index === Math.min(history.length, 3) - 1} />
            ))}
            {!history.length ? (
              <View style={styles.historyEmpty}>
                <Text style={styles.emptyCopy}>No offline activity yet. Accepted sample requests will appear here.</Text>
              </View>
            ) : null}
          </View>
          <Text style={styles.safetyFootnote}>
            All values and activity are simulated. Acceptance never credits spendable funds. Any real offline payment requires a supported, audited protocol and reconciliation.
          </Text>
        </View>
      </ScrollView>

      <BottomSheet
        visible={sheet === "device"}
        title="Device details"
        onClose={() => setSheet(null)}
        bottomInset={insets.bottom}
      >
        {selectedDevice ? (
          <>
            <View style={styles.sheetDeviceHeader}>
              <View style={styles.deviceGlyphLarge}><Ionicons name="phone-portrait-outline" size={23} color="#b4a1ed" /></View>
              <View style={styles.sheetDeviceCopy}>
                <Text style={styles.sheetDeviceName}>{selectedDevice.name}</Text>
                <Text style={styles.sheetDeviceProduct}>ExaPay device · sample entry</Text>
              </View>
            </View>
            <View style={styles.sheetDetails}>
              <View style={styles.sheetDetailRow}><Text style={styles.transferDetailLabel}>Identity status</Text><Text style={styles.transferDetailValue}>{selectedDevice.trust === "trusted-simulation" ? "Trusted in simulation only" : "Unverified device"}</Text></View>
              <View style={styles.sheetDetailRow}><Text style={styles.transferDetailLabel}>Signal quality</Text><Text style={styles.transferDetailValue}>{selectedDevice.signalQuality}</Text></View>
              <View style={[styles.sheetDetailRow, styles.transferDetailLast]}><Text style={styles.transferDetailLabel}>Bluetooth status</Text><Text style={styles.transferDetailValue}>{connectedDeviceId === selectedDevice.id && connectionState === "connected" ? "Connected · simulated" : connectionState === "failed" && selectedDeviceId === selectedDevice.id ? "Connection failed" : "Not connected"}</Text></View>
            </View>
            <Text style={styles.sheetDisclosure}>No live device identity, cryptographic proof, hardware security, or radio link is verified by this simulator.</Text>
            {connectedDeviceId === selectedDevice.id && connectionState === "connected" ? (
              <AnimatedPressable style={styles.outlineAction} onPress={disconnect}>
                <Ionicons name="bluetooth-outline" size={17} color="#cbb9f4" />
                <Text style={styles.outlineActionText}>Disconnect (simulation)</Text>
              </AnimatedPressable>
            ) : (
              <AnimatedPressable
                style={styles.primarySheetAction}
                onPress={() => void connectTo(selectedDevice.id, connectionState === "failed")}
                disabled={isConnecting}
              >
                {isConnecting ? <ActivityIndicator size="small" color="#17140c" /> : <Ionicons name={connectionState === "failed" ? "refresh" : "bluetooth-outline"} size={17} color="#17140c" />}
                <Text style={styles.primarySheetActionText}>{connectionState === "failed" ? "Retry connection (simulation)" : "Connect (simulation)"}</Text>
              </AnimatedPressable>
            )}
          </>
        ) : null}
      </BottomSheet>

      <BottomSheet
        visible={sheet === "incoming"}
        title="Review incoming payment"
        onClose={() => setSheet(null)}
        bottomInset={insets.bottom}
      >
        {incoming ? (
          <>
            <View style={styles.reviewAmountBlock}>
              <StatusBadge label="SIMULATION · NO REAL FUNDS" tone="amber" />
              <Text style={[styles.reviewAmount, !fontsReady ? styles.systemFont : null]}>{formatAmount(incoming.amountMinor)} <Text style={styles.incomingCurrency}>simUSDT</Text></Text>
              <Text style={styles.reviewSender}>{incoming.senderName} · {incoming.method}</Text>
            </View>
            <View style={styles.sheetDetails}>
              <View style={styles.sheetDetailRow}><Text style={styles.transferDetailLabel}>Reference</Text><Text style={styles.transferDetailValue}>{incoming.reference}</Text></View>
              <View style={styles.sheetDetailRow}><Text style={styles.transferDetailLabel}>Device identity</Text><Text style={styles.transferDetailValue}>{senderAuthenticated ? "Authenticated in simulation" : "Not authenticated"}</Text></View>
              <View style={[styles.sheetDetailRow, styles.transferDetailLast]}><Text style={styles.transferDetailLabel}>Result if accepted</Text><Text style={styles.transferDetailValue}>Pending reconciliation only</Text></View>
            </View>
            <Text style={styles.sheetDisclosure}>
              Device authentication is a separate simulated step. Accepting records a non-spendable sample event; it does not credit your wallet or complete a real payment.
            </Text>
            {actionError ? <Text style={styles.sheetError}>{actionError}</Text> : null}
            {!senderAuthenticated ? (
              <AnimatedPressable
                style={styles.primarySheetAction}
                onPress={() => void authenticateSender()}
                disabled={isAuthenticating || isConnecting}
              >
                {isAuthenticating || isConnecting
                  ? <ActivityIndicator size="small" color="#17140c" />
                  : <Ionicons name={senderConnected ? "shield-checkmark-outline" : "bluetooth-outline"} size={18} color="#17140c" />}
                <Text style={styles.primarySheetActionText}>
                  {senderConnected ? "Authenticate sender (simulation)" : "Connect to sender (simulation)"}
                </Text>
              </AnimatedPressable>
            ) : (
              <AnimatedPressable
                style={styles.acceptButton}
                onPress={() => void acceptPayment()}
                disabled={isSubmitting || !senderConnected}
              >
                {isSubmitting ? <ActivityIndicator size="small" color="#06140e" /> : <Ionicons name="checkmark-circle-outline" size={18} color="#06140e" />}
                <Text style={styles.acceptButtonText}>{isSubmitting ? "Recording simulated request…" : "Accept · pending reconciliation"}</Text>
              </AnimatedPressable>
            )}
            <AnimatedPressable style={styles.outlineAction} onPress={() => void rejectPayment()} disabled={isSubmitting}>
              {isSubmitting ? <ActivityIndicator size="small" color="#cbd3dd" /> : <Ionicons name="close-circle-outline" size={18} color="#cbd3dd" />}
              <Text style={styles.outlineActionText}>Reject request</Text>
            </AnimatedPressable>
          </>
        ) : (
          <Text style={styles.emptyCopy}>This request is no longer available.</Text>
        )}
      </BottomSheet>

      <BottomSheet
        visible={sheet === "reject"}
        title="Reject incoming payment?"
        onClose={() => setSheet(null)}
        bottomInset={insets.bottom}
      >
        {incoming ? (
          <>
            <View style={styles.reviewAmountBlock}>
              <StatusBadge label="SIMULATED REQUEST" tone="amber" />
              <Text style={[styles.reviewAmount, !fontsReady ? styles.systemFont : null]}>{formatAmount(incoming.amountMinor)} <Text style={styles.incomingCurrency}>simUSDT</Text></Text>
              <Text style={styles.reviewSender}>{incoming.senderName} · {incoming.method}</Text>
            </View>
            <Text style={styles.sheetDisclosure}>
              The request will be marked declined in local simulation history. No funds or wallet balances will change.
            </Text>
            {actionError ? <Text style={styles.sheetError}>{actionError}</Text> : null}
            <AnimatedPressable style={styles.rejectConfirmButton} onPress={() => void rejectPayment()} disabled={isSubmitting}>
              {isSubmitting ? <ActivityIndicator size="small" color="#f0aaaa" /> : <Ionicons name="close-circle-outline" size={18} color="#f0aaaa" />}
              <Text style={styles.rejectConfirmText}>{isSubmitting ? "Declining…" : "Confirm rejection"}</Text>
            </AnimatedPressable>
            <AnimatedPressable style={styles.outlineAction} onPress={() => setSheet(null)} disabled={isSubmitting}>
              <Text style={styles.outlineActionText}>Keep request</Text>
            </AnimatedPressable>
          </>
        ) : (
          <Text style={styles.emptyCopy}>This request is no longer available.</Text>
        )}
      </BottomSheet>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: "#090b12" },
  scrollContent: { alignItems: "center" },
  shell: { alignSelf: "center" },
  topBar: { height: 50, flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginBottom: 22 },
  backButton: { width: 44, height: 44, borderRadius: 13, borderWidth: 1, borderColor: "rgba(224,232,241,0.1)", backgroundColor: "#111720", alignItems: "center", justifyContent: "center" },
  brandLockup: { flexDirection: "row", alignItems: "center", gap: 9 },
  logo: { width: 34, height: 34, borderRadius: 10, resizeMode: "contain", borderWidth: 1, borderColor: "rgba(239,215,129,0.2)", backgroundColor: "rgba(239,215,129,0.05)" },
  brandName: { color: "#eef2f7", fontFamily: fonts.semibold, fontSize: 12 },
  brandCaption: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 8, letterSpacing: 1.5, marginTop: 1 },
  headerSimulation: { minWidth: 42, height: 27, borderRadius: 99, borderWidth: 1, borderColor: "rgba(232,184,102,0.22)", backgroundColor: "rgba(232,184,102,0.07)", alignItems: "center", justifyContent: "center", flexDirection: "row", gap: 5, paddingHorizontal: 8 },
  simulationDot: { width: 6, height: 6, borderRadius: 3, backgroundColor: "#e8b866" },
  headerSimulationText: { color: "#e7c68e", fontFamily: fonts.semibold, fontSize: 8, letterSpacing: 0.6 },
  titleBlock: { flexDirection: "row", alignItems: "center", gap: 12, marginBottom: 17 },
  titleIcon: { width: 46, height: 46, borderRadius: 14, borderWidth: 1, borderColor: "rgba(170,145,240,0.18)", backgroundColor: "rgba(170,145,240,0.1)", alignItems: "center", justifyContent: "center" },
  titleCopy: { flex: 1 },
  title: { color: "#eef2f7", fontFamily: fonts.display, fontSize: 23, letterSpacing: -0.5 },
  systemFont: { fontFamily: undefined },
  subtitle: { marginTop: 4, color: "#9da8b7", fontFamily: fonts.body, fontSize: 11, lineHeight: 16 },
  simulationNoticeText: { flex: 1, color: "#cdbb98", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  sectionHeader: { minHeight: 31, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 10, marginBottom: 9, marginTop: 3 },
  sectionTitle: { color: "#e9edf3", fontFamily: fonts.semibold, fontSize: 13 },
  sectionHint: { color: "#8591a0", fontFamily: fonts.body, fontSize: 9, marginTop: 3 },
  sectionActionButton: { minWidth: 44, minHeight: 44, alignItems: "flex-end", justifyContent: "center", paddingLeft: 10 },
  sectionAction: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 10 },
  refreshButton: { width: 44, height: 44, borderRadius: 12, borderWidth: 1, borderColor: "rgba(224,232,241,0.1)", backgroundColor: "#111720", alignItems: "center", justifyContent: "center" },
  deviceList: { gap: 8, marginBottom: 9 },
  deviceCard: { minHeight: 76, flexDirection: "row", alignItems: "center", gap: 10, borderRadius: 14, borderWidth: 1, borderColor: "rgba(224,232,241,0.09)", backgroundColor: "#111720", paddingHorizontal: 11, paddingVertical: 10 },
  deviceGlyph: { width: 38, height: 38, borderRadius: 12, backgroundColor: "rgba(170,145,240,0.11)", alignItems: "center", justifyContent: "center" },
  deviceCopy: { flex: 1, gap: 6 },
  deviceName: { color: "#edf0f5", fontFamily: fonts.semibold, fontSize: 12 },
  deviceMetaRow: { flexDirection: "row", alignItems: "center", flexWrap: "wrap", gap: 8 },
  signalMeta: { flexDirection: "row", alignItems: "center", gap: 4 },
  signalText: { color: "#9da8b7", fontFamily: fonts.body, fontSize: 9 },
  connectionHint: { color: "#ddc18d", fontFamily: fonts.medium, fontSize: 9 },
  connectedHint: { color: "#8fcfae", fontFamily: fonts.medium, fontSize: 9 },
  badge: { minHeight: 21, alignSelf: "flex-start", flexDirection: "row", alignItems: "center", gap: 5, borderRadius: 99, paddingHorizontal: 7, paddingVertical: 3 },
  badge_green: { backgroundColor: "rgba(114,211,160,0.11)" },
  badge_purple: { backgroundColor: "rgba(170,145,240,0.12)" },
  badge_amber: { backgroundColor: "rgba(232,184,102,0.12)" },
  badge_red: { backgroundColor: "rgba(240,140,140,0.11)" },
  badge_muted: { backgroundColor: "rgba(255,255,255,0.07)" },
  badgeDot: { width: 5, height: 5, borderRadius: 3 },
  badgeDot_green: { backgroundColor: "#72d3a0" },
  badgeDot_purple: { backgroundColor: "#aa91f0" },
  badgeDot_amber: { backgroundColor: "#e8b866" },
  badgeDot_red: { backgroundColor: "#f08c8c" },
  badgeDot_muted: { backgroundColor: "#9ca7b5" },
  badgeText: { fontFamily: fonts.medium, fontSize: 8 },
  badgeText_green: { color: "#8ed9b1" },
  badgeText_purple: { color: "#c4b5ef" },
  badgeText_amber: { color: "#e7c78c" },
  badgeText_red: { color: "#f0a3a3" },
  badgeText_muted: { color: "#b3bdc9" },
  simulationNotice: { flexDirection: "row", alignItems: "flex-start", gap: 9, borderRadius: 12, borderWidth: 1, borderColor: "rgba(232,184,102,0.16)", backgroundColor: "rgba(232,184,102,0.055)", padding: 11, marginBottom: 21 },
  permissionNote: { color: "#808c9c", fontFamily: fonts.body, fontSize: 9, lineHeight: 14, marginBottom: 12, marginTop: 1 },
  inlineError: { flexDirection: "row", alignItems: "center", gap: 8, borderRadius: 11, borderWidth: 1, borderColor: "rgba(240,140,140,0.22)", backgroundColor: "rgba(240,140,140,0.06)", padding: 10, marginBottom: 10 },
  inlineErrorText: { color: "#e8a4a4", fontFamily: fonts.body, fontSize: 10, lineHeight: 14, flex: 1 },
  retryText: { minWidth: 44, minHeight: 44, textAlign: "center", textAlignVertical: "center", color: "#e5d49f", fontFamily: fonts.semibold, fontSize: 10, padding: 6 },
  loadingCard: { minHeight: 84, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 10, borderRadius: 14, backgroundColor: "#111720", marginBottom: 13 },
  emptyCopy: { color: "#8f9aaa", fontFamily: fonts.body, fontSize: 10, lineHeight: 15, textAlign: "center" },
  emptyCard: { alignItems: "center", borderRadius: 15, borderWidth: 1, borderColor: "rgba(224,232,241,0.09)", backgroundColor: "#111720", paddingHorizontal: 20, paddingVertical: 19, marginBottom: 12 },
  emptyIcon: { width: 43, height: 43, borderRadius: 13, backgroundColor: "rgba(170,145,240,0.1)", alignItems: "center", justifyContent: "center", marginBottom: 9 },
  emptyTitle: { color: "#e9edf3", fontFamily: fonts.semibold, fontSize: 12, marginBottom: 5 },
  retryButton: { minHeight: 44, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 7, borderRadius: 11, borderWidth: 1, borderColor: "rgba(244,207,126,0.2)", paddingHorizontal: 13, marginTop: 12 },
  retryButtonText: { color: "#e8d69e", fontFamily: fonts.semibold, fontSize: 10 },
  incomingCard: { borderRadius: 16, borderWidth: 1, borderColor: "rgba(170,145,240,0.19)", backgroundColor: "#111720", padding: 14, marginBottom: 11 },
  incomingTop: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginBottom: 12 },
  incomingAmount: { color: "#f0f3f8", fontFamily: fonts.display, fontSize: 27, letterSpacing: -0.4 },
  incomingCurrency: { color: "#9da8b7", fontFamily: fonts.medium, fontSize: 11 },
  fromLabel: { color: "#8390a0", fontFamily: fonts.medium, fontSize: 9, marginTop: 11, marginBottom: 6 },
  senderRow: { minHeight: 53, flexDirection: "row", alignItems: "center", gap: 8, borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.075)", paddingBottom: 10 },
  senderIcon: { width: 35, height: 35, borderRadius: 11, backgroundColor: "rgba(170,145,240,0.11)", alignItems: "center", justifyContent: "center" },
  senderCopy: { flex: 1 },
  senderName: { color: "#e8edf4", fontFamily: fonts.semibold, fontSize: 11 },
  senderMethod: { color: "#8995a5", fontFamily: fonts.body, fontSize: 9, marginTop: 3 },
  transferDetails: { paddingTop: 4, marginBottom: 8 },
  transferDetailRow: { minHeight: 32, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 8, borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.055)" },
  transferDetailLast: { borderBottomWidth: 0 },
  transferDetailLabel: { color: "#929dad", fontFamily: fonts.body, fontSize: 9 },
  transferDetailValue: { color: "#dce3ed", fontFamily: fonts.medium, fontSize: 9, textAlign: "right", flexShrink: 1 },
  incomingActions: { gap: 8, marginTop: 4 },
  acceptButton: { minHeight: 48, borderRadius: 12, backgroundColor: "#72d3a0", flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 8, paddingHorizontal: 10 },
  acceptButtonText: { color: "#06140e", fontFamily: fonts.semibold, fontSize: 11, textAlign: "center" },
  rejectButton: { minHeight: 45, borderRadius: 12, borderWidth: 1, borderColor: "rgba(224,232,241,0.16)", alignItems: "center", justifyContent: "center" },
  rejectButtonText: { color: "#c5ceda", fontFamily: fonts.medium, fontSize: 10 },
  qrFallback: { minHeight: 44, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 8, marginTop: 3 },
  qrFallbackText: { color: "#c9b8f1", fontFamily: fonts.semibold, fontSize: 10 },
  emptyIncoming: { flexDirection: "row", alignItems: "center", gap: 10, borderRadius: 14, borderWidth: 1, borderColor: "rgba(114,211,160,0.14)", backgroundColor: "rgba(114,211,160,0.045)", padding: 13, marginBottom: 11 },
  emptyIncomingIcon: { width: 36, height: 36, borderRadius: 11, backgroundColor: "rgba(114,211,160,0.1)", alignItems: "center", justifyContent: "center" },
  emptyIncomingCopy: { flex: 1 },
  emptyIncomingTitle: { color: "#dfeee5", fontFamily: fonts.semibold, fontSize: 11 },
  emptyIncomingDescription: { color: "#98a99e", fontFamily: fonts.body, fontSize: 9, lineHeight: 14, marginTop: 3 },
  historyCard: { borderRadius: 15, borderWidth: 1, borderColor: "rgba(224,232,241,0.08)", backgroundColor: "#111720", paddingHorizontal: 11, marginBottom: 12 },
  historyRow: { minHeight: 76, flexDirection: "row", alignItems: "center", gap: 9, paddingVertical: 10 },
  historyDivider: { borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.07)" },
  historyGlyph: { width: 31, height: 31, borderRadius: 10, backgroundColor: "rgba(255,255,255,0.05)", alignItems: "center", justifyContent: "center" },
  historyCopy: { flex: 1, gap: 4 },
  historyTitle: { color: "#e6ebf2", fontFamily: fonts.semibold, fontSize: 10 },
  historyTime: { color: "#7f8b9b", fontFamily: fonts.body, fontSize: 8 },
  historyAmount: { color: "#dce4ee", fontFamily: fonts.semibold, fontSize: 10, textAlign: "right" },
  historyCurrency: { color: "#8e9aaa", fontFamily: fonts.body, fontSize: 8 },
  historyEmpty: { paddingVertical: 18 },
  safetyFootnote: { color: "#7e8998", fontFamily: fonts.body, fontSize: 9, lineHeight: 14, textAlign: "center", paddingHorizontal: 8, marginBottom: 7 },
  modalRoot: { flex: 1, justifyContent: "flex-end", backgroundColor: "rgba(0,0,0,0.68)" },
  sheet: { maxHeight: "88%", borderTopLeftRadius: 22, borderTopRightRadius: 22, borderWidth: 1, borderBottomWidth: 0, borderColor: "rgba(224,232,241,0.11)", backgroundColor: "#111720", paddingHorizontal: 17, paddingTop: 9 },
  sheetHandle: { width: 38, height: 4, borderRadius: 3, alignSelf: "center", backgroundColor: "rgba(255,255,255,0.22)", marginBottom: 12 },
  sheetHeader: { minHeight: 42, flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginBottom: 8 },
  sheetTitle: { color: "#edf1f6", fontFamily: fonts.display, fontSize: 16 },
  sheetClose: { width: 44, height: 44, alignItems: "center", justifyContent: "center" },
  sheetDeviceHeader: { flexDirection: "row", alignItems: "center", gap: 10, marginVertical: 7 },
  deviceGlyphLarge: { width: 44, height: 44, borderRadius: 13, backgroundColor: "rgba(170,145,240,0.11)", alignItems: "center", justifyContent: "center" },
  sheetDeviceCopy: { flex: 1 },
  sheetDeviceName: { color: "#edf1f6", fontFamily: fonts.semibold, fontSize: 13 },
  sheetDeviceProduct: { color: "#929dad", fontFamily: fonts.body, fontSize: 10, marginTop: 3 },
  sheetDetails: { borderRadius: 12, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "#0d131b", paddingHorizontal: 11, marginTop: 9 },
  sheetDetailRow: { minHeight: 42, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 9, borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.06)" },
  sheetDisclosure: { color: "#929dad", fontFamily: fonts.body, fontSize: 9, lineHeight: 14, marginTop: 10, marginBottom: 7 },
  sheetError: { color: "#efa3a3", fontFamily: fonts.body, fontSize: 10, lineHeight: 14, marginVertical: 6 },
  primarySheetAction: { minHeight: 48, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 8, borderRadius: 12, backgroundColor: colors.auric300, paddingHorizontal: 10, marginTop: 8 },
  primarySheetActionText: { color: "#17140c", fontFamily: fonts.semibold, fontSize: 10, textAlign: "center" },
  outlineAction: { minHeight: 46, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 8, borderRadius: 12, borderWidth: 1, borderColor: "rgba(224,232,241,0.15)", backgroundColor: "transparent", marginTop: 8 },
  outlineActionText: { color: "#cbd3dd", fontFamily: fonts.medium, fontSize: 10 },
  rejectConfirmButton: { minHeight: 48, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 8, borderRadius: 12, borderWidth: 1, borderColor: "rgba(240,140,140,0.28)", backgroundColor: "rgba(240,140,140,0.07)", marginTop: 8 },
  rejectConfirmText: { color: "#f0aaaa", fontFamily: fonts.semibold, fontSize: 10 },
  reviewAmountBlock: { alignItems: "center", paddingVertical: 8 },
  reviewAmount: { color: "#eff2f7", fontFamily: fonts.display, fontSize: 27, marginTop: 12 },
  reviewSender: { color: "#a1acba", fontFamily: fonts.body, fontSize: 10, marginTop: 5 },
});
