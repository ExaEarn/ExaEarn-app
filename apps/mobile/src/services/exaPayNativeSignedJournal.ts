import * as Crypto from "expo-crypto";
import * as SecureStore from "expo-secure-store";
import { Platform } from "react-native";

import { exaPaySqlCipherStore } from "./exaPaySqlCipherStore";
import {
  createExaPaySignedDeviceProtocol,
  type DeviceProtocolRequest,
} from "./exaPaySignedJournalProtocol";
import { createExaPayOfflineReconciliationApi } from "./exaPayOfflineReconciliationApi";
import { createExaPayOfflineTransferJournal, type SignedIssuedInstrument, type OfflineTransferSourceInstrument } from "./exaPayOfflineTransferJournal";
import type { OfflineQrIdentity } from "./exaPayOfflineQrProtocol";
import { isPinnedIssuerP256Key } from "./exaPayOfflineQrProtocol";
import type { OfflineTransferAcknowledgment, SignedOfflineTransfer } from "./exaPayOfflineTransferProtocol";
import type { OfflineTransferEvidence } from "./exaPayOfflineReconciliationApi";

const DEVICE_KEY_OPTIONS: SecureStore.SecureStoreOptions = {
  keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
};

/**
 * Creates the SIMUSDT-only device protocol. The private P-256 key is generated in
 * JavaScript and stored using OS secure storage; this is SOFTWARE_ONLY, not a
 * hardware-backed signing key or protected monetary state.
 */
export function createExaPayNativeSignedJournal(input: {
  request: DeviceProtocolRequest;
  apiBaseUrl: string;
  userId: string;
}) {
  if (Platform.OS === "web") throw new Error("Offline device enrollment is supported only by native app builds.");
  if (!/^[0-9A-Za-z_-]{1,100}$/.test(input.userId)) throw new Error("Sign in before enrolling an offline simulation device.");

  return createExaPaySignedDeviceProtocol({
    request: input.request,
    apiBaseUrl: input.apiBaseUrl,
    accountId: input.userId,
    userId: input.userId,
    identityStore: {
      isAvailableAsync: SecureStore.isAvailableAsync,
      getItemAsync: (key) => SecureStore.getItemAsync(key, DEVICE_KEY_OPTIONS),
      setItemAsync: (key, value) => SecureStore.setItemAsync(key, value, DEVICE_KEY_OPTIONS),
    },
    journalStore: exaPaySqlCipherStore,
    crypto: {
      randomBytes: (length) => Crypto.getRandomBytesAsync(length),
      randomUuid: () => Crypto.randomUUID(),
      digestSha256Hex: (value) => Crypto.digestStringAsync(
        Crypto.CryptoDigestAlgorithm.SHA256,
        value,
        { encoding: Crypto.CryptoEncoding.HEX },
      ),
      secureStoreOptions: DEVICE_KEY_OPTIONS,
    },
  });
}

/**
 * Native SIMUSDT transfer facade: shares the enrolled P-256 identity and the
 * existing SQLCipher store, then submits recipient evidence through the same
 * authenticated Laravel claim client used by the reconciliation screen.
 */
export function createExaPayNativeOfflineTransfers(input: {
  request: DeviceProtocolRequest;
  apiBaseUrl: string;
  userId: string;
}) {
  if (Platform.OS === "web") throw new Error("Native encrypted offline transfer storage is unavailable on web.");
  const device = createExaPayNativeSignedJournal(input);
  const reconciliation = createExaPayOfflineReconciliationApi(input.request);
  const readRecord = (value: unknown): Record<string, unknown> | null => value !== null && typeof value === "object" && !Array.isArray(value) ? value as Record<string, unknown> : null;
  const unwrap = (value: unknown) => { const envelope = readRecord(value); return envelope && "data" in envelope ? envelope.data : value; };
  async function service() {
    const context = await device.getSigningContext();
    return createExaPayOfflineTransferJournal({
      accountId: input.userId,
      deviceUuid: context.deviceUuid,
      store: exaPaySqlCipherStore,
      request: input.request,
      signDevicePayload: context.signPayload,
      submitClaim: (claim) => reconciliation.submitRecipientTransferClaim(claim),
      crypto: {
        randomBytes: (length) => Crypto.getRandomBytesAsync(length),
        randomUuid: () => Crypto.randomUUID(),
        digestSha256Hex: (value) => Crypto.digestStringAsync(Crypto.CryptoDigestAlgorithm.SHA256, value,
          { encoding: Crypto.CryptoEncoding.HEX }),
      },
    });
  }
  return {
    getDeviceContext: () => device.getSigningContext(),
    createPaymentRequest: (amountMinor: number, message = "") => device.createQrPaymentRequest({ amountMinor, message }),
    verifyPaymentRequest: (encoded: string) => device.verifyQrPaymentRequest(encoded),
    acknowledgeIssuedInstrument: async (issued: SignedIssuedInstrument) => {
      const context = await device.getSigningContext();
      const current = await service();
      return current.saveInstrumentAfterAcknowledgment({ issued, certificate: context.certificate,
        trustedIssuerPublicKey: context.trustedIssuerPublicKey, privateKeyHex: undefined });
    },
    issueAndAcknowledge: async (amountMinor: number, idempotencyKey: string) => {
      if (!Number.isSafeInteger(amountMinor) || amountMinor < 1 || amountMinor > 1_000_000_000
        || !/^[A-Za-z0-9._:-]{16,160}$/.test(idempotencyKey)) throw new Error("A valid SIMUSDT amount and stable issuance key are required.");
      const context = await device.getSigningContext();
      const capabilities = readRecord(unwrap(await input.request("/api/exapay/offline/capabilities", { method: "GET" })));
      if (!capabilities || capabilities.offline_simulation_enabled !== true || capabilities.asset !== "SIMUSDT"
        || capabilities.real_funds_accessible !== false || capabilities.issuer_public_key !== context.trustedIssuerPublicKey
        || !isPinnedIssuerP256Key(context.trustedIssuerPublicKey)) {
        throw new Error("Issuer capabilities are unavailable or unsupported for verified P-256 SIMUSDT simulation; issuance was not requested.");
      }
      const issued = readRecord(unwrap(await input.request("/api/exapay/offline/instruments", {
        method: "POST", headers: { "Idempotency-Key": idempotencyKey },
        body: JSON.stringify({ device_uuid: context.deviceUuid, asset: "SIMUSDT", amount_minor: amountMinor }),
      })));
      if (!issued || issued.asset !== "SIMUSDT" || issued.real_funds !== false || issued.amount_minor !== amountMinor) {
        throw new Error("Laravel did not issue the requested SIMUSDT-only instrument.");
      }
      await (await service()).saveInstrumentAfterAcknowledgment({ issued: issued as unknown as SignedIssuedInstrument,
        certificate: context.certificate, trustedIssuerPublicKey: context.trustedIssuerPublicKey });
      return { instrumentId: String(issued.instrument_id), reference: String(issued.reference), amountMinor, asset: "SIMUSDT" as const,
        status: "acknowledged" as const, realFunds: false as const };
    },
    prepareTransfer: async (args: {
      outputUuid: string; recipient: OfflineQrIdentity;
      transferUuid: string; recipientOutputUuid: string; changeOutputUuid: string | null; nonce: string; idempotencyKey: string;
    }) => {
      const context = await device.getSigningContext();
      return (await service()).prepareTransfer({ ...args, senderCertificate: context.certificate,
        trustedIssuerPublicKey: context.trustedIssuerPublicKey });
    },
    acceptTransfer: async (args: { envelope: { transfer: SignedOfflineTransfer; lineage_evidence: OfflineTransferEvidence[];
      source_instrument: OfflineTransferSourceInstrument };
      recipientRequest: OfflineQrIdentity }) => {
      const context = await device.getSigningContext();
      return (await service()).acceptTransfer({ ...args, recipientCertificate: context.certificate,
        trustedIssuerPublicKey: context.trustedIssuerPublicKey });
    },
    commitTransfer: async (args: { transferUuid: string; acknowledgment: OfflineTransferAcknowledgment; recipientRequest: OfflineQrIdentity }) => {
      const context = await device.getSigningContext();
      return (await service()).commitTransfer({ ...args, trustedIssuerPublicKey: context.trustedIssuerPublicKey });
    },
    finalizeReceivedTransfer: async (args: { commit: Parameters<Awaited<ReturnType<typeof service>>["finalizeReceivedTransfer"]>[0]["commit"];
      recipientRequest: OfflineQrIdentity }) => {
      const context = await device.getSigningContext();
      return (await service()).finalizeReceivedTransfer({ ...args, trustedIssuerPublicKey: context.trustedIssuerPublicKey });
    },
    submitOutputEvidence: (outputUuid: string, idempotencyKey: string) => service().then((client) => client.submitOutputEvidence(outputUuid, idempotencyKey)),
    getPendingTransfer: (transferUuid: string) => service().then((client) => client.getPendingTransfer(transferUuid)),
    getCommittedTransfer: (transferUuid: string) => service().then((client) => client.getCommittedTransfer(transferUuid)),
    markRecoveryRequired: (transferUuid: string) => service().then((client) => client.markRecoveryRequired(transferUuid)),
    snapshot: () => service().then((client) => client.snapshot()),
  };
}
