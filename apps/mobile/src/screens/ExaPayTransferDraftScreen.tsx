import { Ionicons } from "@expo/vector-icons";
import { CameraView, useCameraPermissions } from "expo-camera";
import * as Clipboard from "expo-clipboard";
import * as Crypto from "expo-crypto";
import { useMemo, useRef, useState } from "react";
import QRCode from "react-native-qrcode-svg";
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
import { useAuth } from "../context/AuthContext";

import { AnimatedPressable } from "../components/AnimatedPressable";
import {
  createPaymentReference,
  parsePaymentAmount,
  type ExaPayPaymentDraft,
} from "../services/exaPayPaymentReviewModel";
import { createExaPayNativeSignedJournal } from "../services/exaPayNativeSignedJournal";
import type { OfflineQrIdentity } from "../services/exaPayOfflineQrProtocol";
import { createExaPayNativeOfflineTransfers } from "../services/exaPayNativeSignedJournal";
import { createExaPayQrFrameAssembler, createExaPayQrFrames, type ExaPayQrFrameAssembler } from "../services/exaPayQrFrames";
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
  const { user, request, apiBaseUrl } = useAuth();
  const [cameraPermission, requestCameraPermission] = useCameraPermissions();
  const [scannerVisible, setScannerVisible] = useState(false);
  const qrScanHandled = useRef(false);
  const [recipient, setRecipient] = useState(
    mode === "online" && initialDraft?.channel === "online"
      ? initialDraft.recipient.username ?? ""
      : "",
  );
  const [amount, setAmount] = useState(initialDraft ? formatMinorForInput(initialDraft.amountMinor) : "");
  const [message, setMessage] = useState(initialDraft?.message ?? "");
  const [qrPayload, setQrPayload] = useState("");
  const [formError, setFormError] = useState("");
  const [validatedQr, setValidatedQr] = useState<OfflineQrIdentity | null>(null);
  const [requestAmount, setRequestAmount] = useState("");
  const [recipientRequest, setRecipientRequest] = useState<OfflineQrIdentity | null>(null);
  const [shareFrames, setShareFrames] = useState<Array<{ type: string; payload_digest: string; index: number; total: number; data: string; frame_digest: string }>>([]);
  const [sharePayload, setSharePayload] = useState("");
  const [shareFrameIndex, setShareFrameIndex] = useState(0);
  const [incomingTransfer, setIncomingTransfer] = useState("");
  const [incomingAcknowledgment, setIncomingAcknowledgment] = useState("");
  const [incomingCommit, setIncomingCommit] = useState("");
  const [preparedTransferUuid, setPreparedTransferUuid] = useState("");
  const [availableOutputs, setAvailableOutputs] = useState<Array<{ outputUuid: string; amountMinor: number; status: string; hasTransferEvidence: boolean }>>([]);
  const [pendingTransfers, setPendingTransfers] = useState<Array<Record<string, unknown>>>([]);
  const [selectedOutputUuid, setSelectedOutputUuid] = useState("");
  const [finalizedOutputUuid, setFinalizedOutputUuid] = useState("");
  const [handshakeStatus, setHandshakeStatus] = useState("");
  const qrAssembler = useRef<ExaPayQrFrameAssembler | null>(null);
  const isQr = mode === "qr";
  const parsedAmount = useMemo(() => parsePaymentAmount(amount), [amount]);

  function signedTransfers() {
    const userId = user?.id === undefined || user?.id === null ? "" : String(user.id);
    if (!userId || Platform.OS === "web") throw new Error("Sign in on the native SIMUSDT simulation app before starting an offline transfer.");
    return createExaPayNativeOfflineTransfers({ request, apiBaseUrl, userId });
  }

  async function makeShareFrames(payload: string) {
    const frames = await createExaPayQrFrames({ payload, digestSha256Hex: (value) => Crypto.digestStringAsync(
      Crypto.CryptoDigestAlgorithm.SHA256, value, { encoding: Crypto.CryptoEncoding.HEX },
    ) });
    setShareFrames(frames);
    setSharePayload(payload);
    setShareFrameIndex(0);
  }

  async function copySharePayload() {
    if (!sharePayload) return;
    await Clipboard.setStringAsync(sharePayload);
    setHandshakeStatus("The signed SIMUSDT protocol payload was copied. QR frames and recipient-side cryptographic verification remain authoritative.");
  }

  async function refreshOfflineOutputs() {
    const snapshot = await signedTransfers().snapshot();
    const outputs = snapshot.outputs.filter((output) => output.status === "available");
    setAvailableOutputs(outputs.map(({ outputUuid, amountMinor, status, lineageEvidence }) => ({ outputUuid, amountMinor, status, hasTransferEvidence: lineageEvidence.length > 0 })));
    setSelectedOutputUuid((current) => outputs.some((output) => output.outputUuid === current) ? current : outputs[0]?.outputUuid ?? "");
    setPendingTransfers(snapshot.pendingTransfers.filter((pending) => pending.state !== "committed") as unknown as Array<Record<string, unknown>>);
    return snapshot;
  }

  async function resumePendingTransfer(pending: Record<string, unknown>) {
    setFormError("");
    try {
      const transfer = record(pending.transfer);
      const commitment = record(JSON.parse(String(transfer.canonical_payload)));
      const transferUuid = String(commitment.transfer_uuid ?? "");
      const client = signedTransfers();
      const context = await client.getDeviceContext();
      const recipientProof = pending.recipientRequest as OfflineQrIdentity | undefined;
      if (!recipientProof?.signedRequest) throw new Error("This older journal entry does not include its signed recipient request. Preserve the reserved output and follow the manual recovery flow; do not restore it as available.");
      if (String(commitment.sender_device_uuid).toLowerCase() === context.deviceUuid.toLowerCase()) {
        setValidatedQr(recipientProof);
        setPreparedTransferUuid(transferUuid);
        const committed = await client.getCommittedTransfer(transferUuid);
        if (committed) {
          setIncomingCommit(committed);
          await makeShareFrames(committed);
          setHandshakeStatus("Recovered the sender's durable commit receipt. Reshare it with the recipient; no value was restored or retransferred.");
        } else {
          const prepared = await client.getPendingTransfer(transferUuid);
          if (!prepared) throw new Error("The prepared transfer journal entry is unavailable; keep the input reserved for recovery.");
          const payload = JSON.stringify(prepared);
          setIncomingTransfer(payload);
          await makeShareFrames(payload);
          setHandshakeStatus("Recovered the original sender preparation and recipient request from the encrypted journal. Share the same commitment or verify its acknowledgment.");
        }
      } else {
        setRecipientRequest(recipientProof);
        const prepared = await client.getPendingTransfer(transferUuid);
        if (!prepared) throw new Error("The received transfer is no longer in an acknowledgment-pending state.");
        setIncomingTransfer(JSON.stringify(prepared));
        if (pending.acknowledgment) {
          const acknowledgment = JSON.stringify({ type: "EXAEARN_SIMUSDT_TRANSFER_ACK_V1", transfer,
            acknowledgment: pending.acknowledgment, lineage_evidence: prepared.lineage_evidence });
          setIncomingAcknowledgment(acknowledgment);
          await makeShareFrames(acknowledgment);
          setHandshakeStatus("Recovered the exact durable recipient acknowledgment. Resend it; do not create a new acknowledgment or output.");
        } else {
          setHandshakeStatus("Recovered the prepared incoming transfer. Validate and acknowledge the same payload to resume safely.");
        }
      }
      setPreparedTransferUuid(transferUuid);
    } catch (error) {
      setFormError(error instanceof Error ? error.message : "The offline transfer could not be recovered safely.");
    }
  }

  function randomNonce() {
    return Crypto.getRandomBytesAsync(32).then((bytes) => {
      let binary = "";
      bytes.forEach((byte) => { binary += String.fromCharCode(byte); });
      return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
    });
  }

  function record(value: unknown): Record<string, unknown> {
    if (value === null || typeof value !== "object" || Array.isArray(value)) throw new Error("The SIMUSDT handshake message is malformed.");
    return value as Record<string, unknown>;
  }

  async function prepareOfflineTransfer() {
    setFormError("");
    setHandshakeStatus("");
    if (!validatedQr) { setFormError("Scan and verify the recipient's issuer-signed SIMUSDT request first."); return; }
    if (!selectedOutputUuid) { await refreshOfflineOutputs(); setFormError("No available SIMUSDT journal output was found on this device. Issuance must complete and be acknowledged before sending."); return; }
    try {
      const client = signedTransfers();
      const transferUuid = Crypto.randomUUID();
      const selected = availableOutputs.find((output) => output.outputUuid === selectedOutputUuid);
      if (!selected) throw new Error("Refresh the encrypted SIMUSDT journal and select an available output.");
      const commitment = await client.prepareTransfer({
        outputUuid: selectedOutputUuid,
        recipient: validatedQr,
        transferUuid,
        recipientOutputUuid: Crypto.randomUUID(),
        changeOutputUuid: selected.amountMinor > validatedQr.amountMinor ? Crypto.randomUUID() : null,
        nonce: await randomNonce(),
        idempotencyKey: transferUuid,
      });
      const canonical = JSON.parse(commitment.transfer.canonical_payload) as Record<string, unknown>;
      setPreparedTransferUuid(String(canonical.transfer_uuid));
      setIncomingAcknowledgment("");
      const payload = JSON.stringify(commitment);
      setIncomingTransfer(payload);
      await makeShareFrames(payload);
      await refreshOfflineOutputs();
      setHandshakeStatus(`Transfer ${String(canonical.transfer_uuid).slice(0, 8)} prepared and durably reserved. The sender output is unavailable until a verified recipient acknowledgment is committed.`);
    } catch (error) {
      setFormError(error instanceof Error ? error.message : "The offline transfer could not be prepared.");
    }
  }

  async function createRecipientRequest() {
    await createOfflineRequest();
  }

  async function acknowledgeIncomingTransfer() {
    setFormError("");
    if (!recipientRequest) { setFormError("Create this device's signed SIMUSDT request before acknowledging a transfer."); return; }
    try {
      const envelope = record(JSON.parse(incomingTransfer));
      if (envelope.type !== "EXAEARN_SIMUSDT_TRANSFER_PREPARE_V1") throw new Error("Expected a signed sender preparation envelope.");
      const acknowledgment = await signedTransfers().acceptTransfer({
        envelope: envelope as never,
        recipientRequest,
      });
      const payload = JSON.stringify(acknowledgment);
      setIncomingAcknowledgment(payload);
      await makeShareFrames(payload);
      setHandshakeStatus("Recipient acknowledgment is signed and durable. The recipient output is not spendable until the sender commit receipt is verified.");
    } catch (error) {
      setFormError(error instanceof Error ? error.message : "The sender commitment could not be acknowledged.");
    }
  }

  async function commitOutgoingTransfer() {
    setFormError("");
    if (!validatedQr || !preparedTransferUuid) { setFormError("Prepare a transfer to the verified recipient before committing it."); return; }
    try {
      const envelope = record(JSON.parse(incomingAcknowledgment));
      if (envelope.type !== "EXAEARN_SIMUSDT_TRANSFER_ACK_V1") throw new Error("Expected a signed recipient acknowledgment envelope.");
      const result = await signedTransfers().commitTransfer({
        transferUuid: preparedTransferUuid,
        acknowledgment: record(envelope.acknowledgment) as never,
        recipientRequest: validatedQr,
      });
      setIncomingCommit(result);
      await makeShareFrames(result);
      await refreshOfflineOutputs();
      setHandshakeStatus("Sender commitment is durably recorded and signed. Share the commit receipt with the recipient; do not retry or restore this reserved value as a refund.");
    } catch (error) {
      setFormError(error instanceof Error ? error.message : "The sender could not verify the recipient acknowledgment.");
    }
  }

  async function finalizeIncomingTransfer() {
    setFormError("");
    if (!recipientRequest) { setFormError("Create this device's signed request before finalizing the incoming transfer."); return; }
    try {
      const commit = record(JSON.parse(incomingCommit));
      if (commit.type !== "EXAEARN_SIMUSDT_TRANSFER_COMMIT_V1") throw new Error("Expected a signed sender commit receipt.");
      const result = await signedTransfers().finalizeReceivedTransfer({
        commit: commit as never,
        recipientRequest,
      });
      const transfer = record(commit.transfer);
      const commitment = record(JSON.parse(String(transfer.canonical_payload)));
      const outputUuid = String(commitment.recipient_output_uuid ?? "").toLowerCase();
      if (!/^[0-9a-f-]{36}$/.test(outputUuid)) throw new Error("The committed output identifier is invalid.");
      setFinalizedOutputUuid(outputUuid);
      await refreshOfflineOutputs();
      setHandshakeStatus(`The recipient commit receipt is verified and ${formatAmount(Number(commitment.transfer_amount_minor))} SIMUSDT is now recorded in this device journal. This remains simulated offline value pending server reconciliation.`);
      void result;
    } catch (error) {
      setFormError(error instanceof Error ? error.message : "The sender commit could not be finalized.");
    }
  }

  async function submitFinalizedEvidence(outputUuid = finalizedOutputUuid) {
    if (!outputUuid) return;
    setFormError("");
    try {
      const result = await signedTransfers().submitOutputEvidence(outputUuid, `EXAPAY-${outputUuid}`);
      setHandshakeStatus(`Laravel received the committed SIMUSDT transfer claim. Authoritative status: ${JSON.stringify(result)}. Reconciliation does not imply redemption or real funds.`);
    } catch (error) {
      setFormError(error instanceof Error ? error.message : "The SIMUSDT journal evidence was not accepted by Laravel.");
    }
  }

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

  async function validateQrRequest(payload = qrPayload) {
    setFormError("");
    const userId = user?.id === undefined || user?.id === null ? "" : String(user.id);
    if (!userId) {
      setValidatedQr(null);
      setFormError("Sign in and enroll this SIMUSDT simulation device online before verifying signed offline requests.");
      return;
    }
    try {
      const protocol = createExaPayNativeSignedJournal({ request, apiBaseUrl, userId });
      setValidatedQr(await protocol.verifyQrPaymentRequest(payload));
    } catch {
      setValidatedQr(null);
      setFormError("The QR signature, enrolled device certificate, issuer key, asset or expiry could not be verified. No payment was accepted.");
    }
  }

  async function createOfflineRequest() {
    setFormError("");
    const parsed = parsePaymentAmount(requestAmount);
    if (!parsed.valid) {
      setFormError("Enter a SIMUSDT amount with no more than two decimal places.");
      return;
    }
    const userId = user?.id === undefined || user?.id === null ? "" : String(user.id);
    if (!userId || Platform.OS === "web") {
      setFormError("Sign in on a native development build to enroll the recipient device and create a signed SIMUSDT request.");
      return;
    }
    try {
      const protocol = signedTransfers();
      const signed = await protocol.createPaymentRequest(parsed.amountMinor, message.trim());
      setRecipientRequest(await protocol.verifyPaymentRequest(signed));
      setQrPayload(signed);
      setValidatedQr(null);
      await makeShareFrames(signed);
      void refreshOfflineOutputs();
    } catch {
      setFormError("A signed request could not be created. Verify device enrollment, issuer key support and the active SIMUSDT policy.");
    }
  }

  async function openQrScanner() {
    setFormError("");
    const permission = cameraPermission?.granted ? cameraPermission : await requestCameraPermission();
    if (!permission?.granted) {
      setFormError("Camera access is required to scan a QR request. You can paste a SIMUSDT simulation request instead.");
      return;
    }
    qrScanHandled.current = false;
    setScannerVisible(true);
  }

  async function acceptScannedQr(payload: string) {
    if (qrScanHandled.current) return;
    qrScanHandled.current = true;
    try {
      let completePayload = payload;
      let parsed: Record<string, unknown>;
      try { parsed = record(JSON.parse(payload)); } catch { throw new Error("This is not a valid ExaEarn offline payment QR."); }
      if (parsed.type === "EXAEARN_SIMUSDT_QR_FRAME_V1") {
        qrAssembler.current ??= createExaPayQrFrameAssembler({ digestSha256Hex: (value) => Crypto.digestStringAsync(
          Crypto.CryptoDigestAlgorithm.SHA256, value, { encoding: Crypto.CryptoEncoding.HEX },
        ) });
        const progress = await qrAssembler.current.add(payload);
        if (!progress.payload) {
          setFormError(`QR transfer frame ${progress.received} of ${progress.expected} received. Keep scanning the remaining frames.`);
          qrScanHandled.current = false;
          return;
        }
        completePayload = progress.payload;
        qrAssembler.current.reset();
      }
      setScannerVisible(false);
      const value = record(JSON.parse(completePayload));
      if (value.type === "EXAEARN_SIMUSDT_OFFLINE_REQUEST") {
        setQrPayload(completePayload);
        setValidatedQr(null);
        await validateQrRequest(completePayload);
      } else if (value.type === "EXAEARN_SIMUSDT_TRANSFER_PREPARE_V1") {
        setIncomingTransfer(completePayload);
        setFormError("");
        setHandshakeStatus("Sender preparation received. Acknowledgment will be signed only after the encrypted journal validates the request and output lineage.");
      } else if (value.type === "EXAEARN_SIMUSDT_TRANSFER_ACK_V1") {
        setIncomingAcknowledgment(completePayload);
        setFormError("");
        setHandshakeStatus("Recipient acknowledgment received. The sender journal will verify it before committing the reserved output.");
      } else if (value.type === "EXAEARN_SIMUSDT_TRANSFER_COMMIT_V1") {
        setIncomingCommit(completePayload);
        setFormError("");
        setHandshakeStatus("Sender commit receipt received. The recipient journal will verify its signature and acknowledgment before finalizing.");
      } else {
        throw new Error("Unsupported offline payment QR message type.");
      }
    } catch (error) {
      setFormError(error instanceof Error ? error.message : "The scanned offline payment QR could not be processed.");
      setScannerVisible(false);
    }
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
                  <Text style={styles.noticeText}>SIMUSDT simulation only. Signed QR messages are verified and journaled at each step. A sender commit is not server settlement; uncertain value is never automatically refunded.</Text>
                </View>
                <View style={styles.card}>
                  <Text style={styles.sectionTitle}>Receive offline payment</Text>
                  <AnimatedPressable style={styles.secondaryButton} onPress={() => { void refreshOfflineOutputs().catch((error) => setFormError(error.message)); }}><Ionicons name="refresh-outline" size={16} color="#c5b5ef" /><Text style={styles.secondaryButtonText}>Load encrypted journal & recovery</Text></AnimatedPressable>
                  <Field label="Requested amount · simUSDT" value={requestAmount} onChangeText={(value) => { setRequestAmount(value); setRecipientRequest(null); setShareFrames([]); setFormError(""); }} placeholder="0.00" accessibilityLabel="Requested SIMUSDT amount" keyboardType="decimal-pad" />
                  <Field label="Request note · optional" value={message} onChangeText={setMessage} placeholder="Add a note" accessibilityLabel="Offline request note" maxLength={100} />
                  <AnimatedPressable style={styles.secondaryButton} onPress={() => void createRecipientRequest()}><Ionicons name="qr-code-outline" size={16} color="#c5b5ef" /><Text style={styles.secondaryButtonText}>Create signed payment request</Text></AnimatedPressable>
                  {recipientRequest ? <Text style={styles.unverifiedNote}>Signed request · {formatAmount(recipientRequest.amountMinor)} simUSDT · expires {new Date(recipientRequest.expiresAt * 1000).toLocaleTimeString()}</Text> : null}
                  <Text style={[styles.sectionTitle, styles.sectionGap]}>Acknowledge sender preparation</Text>
                  <TextInput value={incomingTransfer} onChangeText={setIncomingTransfer} multiline autoCapitalize="none" autoCorrect={false} placeholder="Scan or paste sender preparation" placeholderTextColor="#697586" style={styles.payloadInput} accessibilityLabel="Incoming sender preparation envelope" />
                  <View style={styles.qrActions}>
                    <AnimatedPressable style={styles.secondaryButton} onPress={() => void openQrScanner()}><Ionicons name="scan-outline" size={16} color="#c5b5ef" /><Text style={styles.secondaryButtonText}>Scan message</Text></AnimatedPressable>
                    <AnimatedPressable style={styles.validateButton} onPress={() => void acknowledgeIncomingTransfer()}><Ionicons name="shield-checkmark-outline" size={16} color="#17140c" /><Text style={styles.validateButtonText}>Verify & acknowledge</Text></AnimatedPressable>
                  </View>
                  <Text style={[styles.sectionTitle, styles.sectionGap]}>Finalize sender commitment</Text>
                  <TextInput value={incomingCommit} onChangeText={setIncomingCommit} multiline autoCapitalize="none" autoCorrect={false} placeholder="Scan or paste sender commit receipt" placeholderTextColor="#697586" style={styles.payloadInput} accessibilityLabel="Incoming sender commit receipt" />
                  <View style={styles.qrActions}>
                    <AnimatedPressable style={styles.secondaryButton} onPress={() => void openQrScanner()}><Ionicons name="scan-outline" size={16} color="#c5b5ef" /><Text style={styles.secondaryButtonText}>Scan commit</Text></AnimatedPressable>
                    <AnimatedPressable style={styles.validateButton} onPress={() => void finalizeIncomingTransfer()}><Ionicons name="checkmark-done-outline" size={16} color="#17140c" /><Text style={styles.validateButtonText}>Finalize locally</Text></AnimatedPressable>
                  </View>
                  {finalizedOutputUuid ? <AnimatedPressable style={styles.secondaryButton} onPress={() => void submitFinalizedEvidence()}><Ionicons name="cloud-upload-outline" size={16} color="#c5b5ef" /><Text style={styles.secondaryButtonText}>Submit evidence to Laravel</Text></AnimatedPressable> : null}
                  {availableOutputs.filter((output) => output.hasTransferEvidence && output.outputUuid !== finalizedOutputUuid).map((output) => <AnimatedPressable key={output.outputUuid} style={styles.secondaryButton} onPress={() => void submitFinalizedEvidence(output.outputUuid)}><Ionicons name="cloud-upload-outline" size={16} color="#c5b5ef" /><Text style={styles.secondaryButtonText}>Reconcile committed {formatAmount(output.amountMinor)} simUSDT · {output.outputUuid.slice(0, 8)}</Text></AnimatedPressable>)}
                </View>
                <View style={styles.card}>
                  <Text style={styles.sectionTitle}>Pay a signed request</Text>
                  <TextInput value={qrPayload} onChangeText={(value) => { setQrPayload(value); setValidatedQr(null); setFormError(""); }} multiline autoCapitalize="none" autoCorrect={false} placeholder="Scan or paste recipient's signed SIMUSDT request" placeholderTextColor="#697586" style={styles.payloadInput} accessibilityLabel="Signed recipient payment request" />
                  <View style={styles.qrActions}>
                    <AnimatedPressable style={styles.secondaryButton} onPress={() => void openQrScanner()}><Ionicons name="scan-outline" size={16} color="#c5b5ef" /><Text style={styles.secondaryButtonText}>Scan request or reply</Text></AnimatedPressable>
                    <AnimatedPressable style={styles.validateButton} onPress={() => void validateQrRequest()}><Ionicons name="checkmark-circle-outline" size={16} color="#17140c" /><Text style={styles.validateButtonText}>Verify request</Text></AnimatedPressable>
                  </View>
                  {validatedQr ? <View style={styles.validatedCard} accessibilityRole="summary"><Ionicons name="checkmark-circle-outline" size={19} color="#72d3a0" /><View style={styles.validatedCopy}><Text style={styles.validatedTitle}>Issuer-signed recipient verified</Text><Text style={styles.validatedDescription}>Device {validatedQr.deviceUuid.slice(0, 8)} · {formatAmount(validatedQr.amountMinor)} simUSDT</Text><Text style={styles.unverifiedNote}>No transfer has committed until recipient acknowledgment is verified.</Text></View></View> : null}
                  {validatedQr ? <>
                    <Text style={[styles.sectionTitle, styles.sectionGap]}>Choose available encrypted output</Text>
                    <AnimatedPressable style={styles.secondaryButton} onPress={() => { void refreshOfflineOutputs().catch((error) => setFormError(error.message)); }}><Ionicons name="refresh-outline" size={16} color="#c5b5ef" /><Text style={styles.secondaryButtonText}>Refresh journal outputs</Text></AnimatedPressable>
                    {availableOutputs.map((output) => <AnimatedPressable key={output.outputUuid} style={[styles.outputChoice, selectedOutputUuid === output.outputUuid ? styles.outputChoiceSelected : null]} onPress={() => setSelectedOutputUuid(output.outputUuid)} accessibilityRole="radio" accessibilityState={{ selected: selectedOutputUuid === output.outputUuid }}><Ionicons name={selectedOutputUuid === output.outputUuid ? "radio-button-on" : "radio-button-off"} size={17} color="#c5b5ef" /><Text style={styles.outputChoiceText}>{formatAmount(output.amountMinor)} simUSDT · {output.outputUuid.slice(0, 8)}</Text></AnimatedPressable>)}
                    <AnimatedPressable style={styles.validateButton} onPress={() => void prepareOfflineTransfer()}><Ionicons name="create-outline" size={16} color="#17140c" /><Text style={styles.validateButtonText}>Prepare & reserve transfer</Text></AnimatedPressable>
                  </> : null}
                  <Text style={[styles.sectionTitle, styles.sectionGap]}>Commit after recipient acknowledgment</Text>
                  <TextInput value={incomingAcknowledgment} onChangeText={setIncomingAcknowledgment} multiline autoCapitalize="none" autoCorrect={false} placeholder="Scan or paste recipient's signed acknowledgment" placeholderTextColor="#697586" style={styles.payloadInput} accessibilityLabel="Signed recipient acknowledgment" />
                  <View style={styles.qrActions}>
                    <AnimatedPressable style={styles.secondaryButton} onPress={() => void openQrScanner()}><Ionicons name="scan-outline" size={16} color="#c5b5ef" /><Text style={styles.secondaryButtonText}>Scan acknowledgment</Text></AnimatedPressable>
                    <AnimatedPressable style={styles.validateButton} onPress={() => void commitOutgoingTransfer()}><Ionicons name="lock-closed-outline" size={16} color="#17140c" /><Text style={styles.validateButtonText}>Verify & commit</Text></AnimatedPressable>
                  </View>
                </View>
                {shareFrames.length ? <View style={styles.qrShareCard}><Text style={styles.sectionTitle}>Share signed offline message</Text><Text style={styles.unverifiedNote}>Frame {shareFrameIndex + 1} of {shareFrames.length}. Scan all frames; message signatures are checked after reassembly.</Text><View style={styles.qrCanvas}><QRCode value={JSON.stringify(shareFrames[shareFrameIndex])} size={224} ecl="L" quietZone={6} /></View><View style={styles.qrActions}><AnimatedPressable style={styles.secondaryButton} onPress={() => setShareFrameIndex((index) => Math.max(0, index - 1))}><Ionicons name="chevron-back" size={16} color="#c5b5ef" /><Text style={styles.secondaryButtonText}>Previous</Text></AnimatedPressable><AnimatedPressable style={styles.secondaryButton} onPress={() => setShareFrameIndex((index) => Math.min(shareFrames.length - 1, index + 1))}><Text style={styles.secondaryButtonText}>Next frame</Text><Ionicons name="chevron-forward" size={16} color="#c5b5ef" /></AnimatedPressable></View><AnimatedPressable style={styles.secondaryButton} onPress={() => void copySharePayload()}><Ionicons name="copy-outline" size={15} color="#c5b5ef" /><Text style={styles.secondaryButtonText}>Copy full signed payload</Text></AnimatedPressable></View> : null}
                {pendingTransfers.length ? <View style={styles.card}><Text style={styles.sectionTitle}>Interrupted transfer recovery</Text><Text style={styles.unverifiedNote}>Reserved outputs stay unavailable until the original signed exchange resolves. No automatic refund is available.</Text>{pendingTransfers.map((pending) => { const transfer = record(pending.transfer); let id = "unknown"; try { id = String(record(JSON.parse(String(transfer.canonical_payload))).transfer_uuid); } catch { /* keep damaged evidence visible */ } return <AnimatedPressable key={id} style={styles.secondaryButton} onPress={() => void resumePendingTransfer(pending)}><Ionicons name="time-outline" size={16} color="#c5b5ef" /><Text style={styles.secondaryButtonText}>Resume {id.slice(0, 8)} · {String(pending.state ?? "pending")}</Text></AnimatedPressable>; })}<AnimatedPressable style={styles.secondaryButton} onPress={() => { void refreshOfflineOutputs().catch((error) => setFormError(error.message)); }}><Ionicons name="refresh-outline" size={16} color="#c5b5ef" /><Text style={styles.secondaryButtonText}>Refresh recovery status</Text></AnimatedPressable></View> : null}
                {scannerVisible ? <View style={styles.scannerCard}><CameraView style={styles.scannerPreview} facing="back" barcodeScannerSettings={{ barcodeTypes: ["qr"] }} onBarcodeScanned={({ data }) => { void acceptScannedQr(data); }} accessibilityLabel="Scan signed ExaEarn SIMUSDT offline payment messages" /><View style={styles.scannerFooter}><Text style={styles.scannerHint}>For multi-frame messages, scan every frame. The complete protocol signature is verified before acting.</Text><AnimatedPressable style={styles.cancelScanButton} onPress={() => { setScannerVisible(false); qrAssembler.current?.reset(); qrAssembler.current = null; qrScanHandled.current = false; }}><Text style={styles.cancelScanText}>Stop scan</Text></AnimatedPressable></View></View> : null}
                {handshakeStatus ? <View style={styles.statusNotice} accessibilityRole="summary"><Ionicons name="information-circle-outline" size={17} color="#72d3a0" /><Text style={styles.statusText}>{handshakeStatus}</Text></View> : null}
              </>            ) : (
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
          {!isQr ? <AnimatedPressable
            style={styles.primaryButton}
            onPress={continueOnlineTransfer}
            accessibilityLabel="Review online transfer"
          >
            <Text style={styles.primaryButtonText}>Review Payment</Text>
            <Ionicons name="arrow-forward" size={17} color="#17140c" />
          </AnimatedPressable> : null}
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
  scannerCard: { overflow: "hidden", borderRadius: 16, borderWidth: 1, borderColor: "rgba(170,145,240,0.2)", backgroundColor: "#111720", marginBottom: 13 },
  scannerPreview: { width: "100%", height: 260 },
  scannerFooter: { gap: 8, padding: 12 },
  scannerHint: { color: "#c1cad5", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  cancelScanButton: { minHeight: 38, alignItems: "center", justifyContent: "center", borderRadius: 10, borderWidth: 1, borderColor: "rgba(224,232,241,0.1)" },
  cancelScanText: { color: "#dce3ed", fontFamily: fonts.semibold, fontSize: 10 },
  card: { borderRadius: 16, borderWidth: 1, borderColor: "rgba(224,232,241,0.09)", backgroundColor: "#111720", padding: 14 },
  sectionTitle: { color: "#eff2f7", fontFamily: fonts.semibold, fontSize: 12 },
  sectionGap: { marginTop: 18, marginBottom: 8 },
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
  outputChoice: { minHeight: 43, flexDirection: "row", alignItems: "center", gap: 8, borderRadius: 10, borderWidth: 1, borderColor: "rgba(224,232,241,0.09)", backgroundColor: "#0d131b", paddingHorizontal: 10, marginTop: 7 },
  outputChoiceSelected: { borderColor: "rgba(170,145,240,0.52)", backgroundColor: "rgba(170,145,240,0.09)" },
  outputChoiceText: { color: "#dce3ed", fontFamily: fonts.medium, fontSize: 10 },
  qrShareCard: { alignItems: "center", gap: 9, borderRadius: 16, borderWidth: 1, borderColor: "rgba(170,145,240,0.2)", backgroundColor: "#111720", padding: 14 },
  qrCanvas: { alignItems: "center", justifyContent: "center", borderRadius: 12, backgroundColor: "white", padding: 10, marginVertical: 3 },
  statusNotice: { flexDirection: "row", alignItems: "flex-start", gap: 8, borderRadius: 11, borderWidth: 1, borderColor: "rgba(114,211,160,0.17)", backgroundColor: "rgba(114,211,160,0.05)", padding: 11 },
  statusText: { flex: 1, color: "#b8d9c5", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
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
