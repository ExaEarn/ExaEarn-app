import { p256 } from "@noble/curves/nist.js";
import { verifyPinnedIssuerP256Proof } from "./exaPayOfflineQrProtocol";
import { signDevicePayloadDer } from "./exaPayDeviceCrypto";
import {
  acceptSignedOfflineTransfer,
  canonicalOfflineTransferCommitment,
  createSignedOfflineTransfer,
  verifyOfflineTransferAcknowledgment,
  type OfflineTransferAcknowledgment,
  type OfflineTransferCommitmentV1,
  type SignedOfflineTransfer,
} from "./exaPayOfflineTransferProtocol";
import { verifySignedOfflineQrRequest, type OfflineQrIdentity, type SignedOfflineDeviceCertificate } from "./exaPayOfflineQrProtocol";
import type { AtomicDeviceJournalStore, DeviceProtocolCrypto } from "./exaPaySignedJournalProtocol";
import type { OfflineTransferEvidence } from "./exaPayOfflineReconciliationApi";

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const KEY = /^[A-Za-z0-9._:-]{16,160}$/;
const ROOT_PREFIX = "exaearn-offline-root:v1|";

export type SignedIssuedInstrument = {
  instrument_id: string;
  reference: string;
  asset: "SIMUSDT";
  amount_minor: number;
  payload: Record<string, unknown>;
  issuer_signature: string;
  issuer_public_key: string;
  status: string;
  delivery_status: string;
  real_funds: false;
};

type Output = {
  outputUuid: string;
  amountMinor: number;
  lineageDigest: string;
  sourceInstrument: { payload: Record<string, unknown>; signature: string; issuerPublicKey: string };
  lineageEvidence: OfflineTransferEvidence[];
  status: "available" | "reserved" | "spent" | "pending_ack" | "recovery_required";
};
export type OfflineTransferSourceInstrument = Output["sourceInstrument"];
type PendingTransfer = {
  transfer: SignedOfflineTransfer;
  /** Original signed recipient request is persisted to support safe interrupted-transfer recovery. */
  recipientRequest?: OfflineQrIdentity;
  acknowledgment?: OfflineTransferAcknowledgment;
  receipt?: string;
  inputOutputUuid: string;
  sourceInstrument: Output["sourceInstrument"];
  lineageEvidence: OfflineTransferEvidence[];
  state: "prepared" | "acknowledged" | "committed" | "recovery_required";
};
type TransferJournalState = {
  version: 1;
  deviceUuid: string;
  nextCounter: number;
  outputs: Record<string, Output>;
  pending: Record<string, PendingTransfer>;
  acceptedTransfers: string[];
  seenNonces: string[];
};

export type OfflineTransferJournalDependencies = {
  accountId: string;
  deviceUuid: string;
  store: AtomicDeviceJournalStore;
  crypto: DeviceProtocolCrypto;
  request: <T = Record<string, unknown>>(path: string, options?: RequestInit & { headers?: Record<string, string> }) => Promise<T>;
  signDevicePayload?: (payload: string) => string | Promise<string>;
  submitClaim: (input: { expected_output_uuid: string; expected_amount_minor: number; idempotency_key: string; transfers: OfflineTransferEvidence[] }) => Promise<unknown>;
};

function record(value: unknown): Record<string, unknown> | null {
  return value !== null && typeof value === "object" && !Array.isArray(value) ? value as Record<string, unknown> : null;
}
function unwrap(value: unknown): unknown { const item = record(value); return item && "data" in item ? item.data : value; }
function readState(raw: string | null, deviceUuid: string): TransferJournalState {
  if (raw === null) return { version: 1, deviceUuid, nextCounter: 1, outputs: {}, pending: {}, acceptedTransfers: [], seenNonces: [] };
  const state = JSON.parse(raw) as TransferJournalState;
  if (state.version !== 1 || state.deviceUuid !== deviceUuid || !Number.isSafeInteger(state.nextCounter)
    || !state.outputs || !state.pending || !Array.isArray(state.acceptedTransfers) || !Array.isArray(state.seenNonces)) {
    throw new Error("The encrypted transfer journal is invalid; preserve it for recovery.");
  }
  return state;
}
function storageKey(accountId: string, deviceUuid: string) {
  if (!/^[A-Za-z0-9._:-]{1,100}$/.test(accountId) || !UUID.test(deviceUuid)) throw new Error("Invalid SIMUSDT journal scope.");
  return `exaearn.exapay.transfers.v1.${accountId}.${deviceUuid.toLowerCase()}`;
}
function canonicalInstrument(payload: Record<string, unknown>) { return JSON.stringify(payload); }
function rootLineageDigest(instrumentId: string, canonical: string, crypto: DeviceProtocolCrypto) {
  return crypto.digestSha256Hex(canonical)
    .then((payloadDigest) => crypto.digestSha256Hex(`${ROOT_PREFIX}${instrumentId.toLowerCase()}|${payloadDigest}`));
}
function transferEvidence(transfer: SignedOfflineTransfer, acknowledgment: OfflineTransferAcknowledgment): OfflineTransferEvidence {
  const item = { ...JSON.parse(transfer.canonical_payload), payment_mode: "OFFLINE_STORED_VALUE" } as OfflineTransferCommitmentV1;
  return {
    transfer_uuid: item.transfer_uuid, sender_device_uuid: item.sender_device_uuid, recipient_device_uuid: item.recipient_device_uuid,
    sender_key_version: item.sender_key_version, recipient_key_version: item.recipient_key_version,
    input_output_uuid: item.input_output_uuid, recipient_output_uuid: item.recipient_output_uuid, change_output_uuid: item.change_output_uuid,
    input_amount_minor: item.input_amount_minor, transfer_amount_minor: item.transfer_amount_minor, change_amount_minor: item.change_amount_minor,
    asset: item.asset, transfer_counter: item.transfer_counter, protocol_version: item.protocol_version, hop_count: item.hop_count,
    nonce: item.nonce, signed_at: item.signed_at, parent_digest: item.parent_digest, idempotency_key: item.idempotency_key,
    sender_signature: transfer.sender_signature, recipient_signature: acknowledgment.recipient_signature,
  };
}
function canonicalReceipt(digest: string, acknowledgment: OfflineTransferAcknowledgment, senderDeviceUuid: string) {
  return ["exaearn-offline-transfer-commit:v1", digest, acknowledgment.transfer_digest,
    acknowledgment.recipient_signature, senderDeviceUuid.toLowerCase()].join("|");
}
async function evidenceOutputDigest(evidence: OfflineTransferEvidence, outputUuid: string, crypto: DeviceProtocolCrypto) {
  const commitment: OfflineTransferCommitmentV1 = {
    protocol_version: 1, transfer_uuid: evidence.transfer_uuid, payment_mode: "OFFLINE_STORED_VALUE", asset: "SIMUSDT",
    sender_device_uuid: evidence.sender_device_uuid, recipient_device_uuid: evidence.recipient_device_uuid,
    sender_key_version: evidence.sender_key_version, recipient_key_version: evidence.recipient_key_version,
    input_output_uuid: evidence.input_output_uuid, recipient_output_uuid: evidence.recipient_output_uuid,
    change_output_uuid: evidence.change_output_uuid, input_amount_minor: evidence.input_amount_minor,
    transfer_amount_minor: evidence.transfer_amount_minor, change_amount_minor: evidence.change_amount_minor,
    transfer_counter: evidence.transfer_counter, hop_count: evidence.hop_count, nonce: evidence.nonce,
    signed_at: evidence.signed_at, parent_digest: evidence.parent_digest, idempotency_key: evidence.idempotency_key,
  };
  const kind = evidence.recipient_output_uuid === outputUuid ? "recipient"
    : evidence.change_output_uuid === outputUuid ? "change" : null;
  if (!kind) throw new Error("The signed transfer evidence does not contain its claimed child output.");
  const digest = await crypto.digestSha256Hex(canonicalOfflineTransferCommitment(commitment));
  return crypto.digestSha256Hex(`${digest}|${kind}|${outputUuid}`);
}

/** Durable SIMUSDT-only transfer protocol. A device's local journal is not an authoritative ledger. */
export function createExaPayOfflineTransferJournal(deps: OfflineTransferJournalDependencies) {
  const key = storageKey(deps.accountId, deps.deviceUuid);

  async function saveInstrumentAfterAcknowledgment(input: {
    issued: SignedIssuedInstrument;
    certificate: SignedOfflineDeviceCertificate;
    trustedIssuerPublicKey: string;
    privateKeyHex?: string;
  }) {
    const payload = input.issued.payload;
    const canonical = canonicalInstrument(payload);
    const device = JSON.parse(input.certificate.canonical_payload) as Record<string, unknown>;
    const certificateCanonical = JSON.stringify(device);
    if (input.issued.real_funds !== false || input.issued.asset !== "SIMUSDT" || payload.asset !== "SIMUSDT"
      || payload.environment !== "SIMULATION" || payload.instrument_id !== input.issued.instrument_id
      || payload.device_uuid !== deps.deviceUuid.toLowerCase() || input.issued.issuer_public_key !== input.trustedIssuerPublicKey
      || !verifyPinnedIssuerP256Proof(canonical, input.issued.issuer_signature, input.trustedIssuerPublicKey)
      || input.certificate.issuer_public_key !== input.trustedIssuerPublicKey
      || !verifyPinnedIssuerP256Proof(certificateCanonical, input.certificate.signature, input.trustedIssuerPublicKey)
      || device.device_uuid !== deps.deviceUuid.toLowerCase() || typeof device.public_key !== "string"
      || Number(device.key_version) !== Number(payload.device_key_version) || device.asset !== "SIMUSDT"
      || device.simulation_only !== true || Number(device.expires_at) <= Math.floor(Date.now() / 1000)) {
      throw new Error("The issuer instrument or enrolled device proof is invalid; no value was added to the local journal.");
    }
    if (input.issued.amount_minor !== payload.amount_minor || !Number.isSafeInteger(payload.amount_minor)
      || Number(payload.amount_minor) < 1 || !UUID.test(String(payload.instrument_id))
      || !/^[A-Za-z0-9]+$/.test(String(payload.nonce)) || Number(payload.expires_at) <= Math.floor(Date.now() / 1000)) {
      throw new Error("The issuer instrument amount, identity, or validity is invalid.");
    }
    // The server does not expose value locally until its signed delivery acknowledgment succeeds.
    const nonce = bytesToBase64Url(await deps.crypto.randomBytes(32));
    const signedAt = Math.floor(Date.now() / 1000);
    const acknowledgmentPayload = `exaearn-offline-ack:v1|${input.issued.reference}|${deps.deviceUuid.toLowerCase()}|${nonce}|${signedAt}`;
    const acknowledgmentSignature = await signPayload(acknowledgmentPayload, input.privateKeyHex, deps.signDevicePayload);
    if (!verifyP256Device(acknowledgmentSignature, acknowledgmentPayload, String(device.public_key), p256)) {
      throw new Error("The signing key does not match the issuer-certified device; no delivery acknowledgment was sent.");
    }
    const proof = {
      device_uuid: deps.deviceUuid,
      nonce,
      signed_at: signedAt,
      signature: acknowledgmentSignature,
    };
    const ack = record(unwrap(await deps.request(`/api/exapay/offline/instruments/${encodeURIComponent(input.issued.reference)}/acknowledge`, {
      method: "POST", body: JSON.stringify(proof),
    })));
    if (ack?.reference !== input.issued.reference || ack.delivery_status !== "acknowledged" || ack.real_funds === true) {
      throw new Error("Laravel did not acknowledge the SIMUSDT instrument; it remains unavailable locally.");
    }
    const lineageDigest = await rootLineageDigest(String(payload.instrument_id), canonical, deps.crypto);
    return deps.store.updateItemAtomically<boolean>(key, (raw) => {
      const state = readState(raw, deps.deviceUuid);
      const id = String(payload.instrument_id).toLowerCase();
      const prior = state.outputs[id];
      const output: Output = { outputUuid: id, amountMinor: Number(payload.amount_minor), lineageDigest,
        sourceInstrument: { payload, signature: input.issued.issuer_signature, issuerPublicKey: input.trustedIssuerPublicKey },
        lineageEvidence: [], status: "available" };
      if (prior && JSON.stringify(prior) !== JSON.stringify(output)) throw new Error("A conflicting instrument already exists in the local journal.");
      return { value: JSON.stringify({ ...state, outputs: { ...state.outputs, [id]: output } }), result: true };
    });
  }

  async function prepareTransfer(input: {
    outputUuid: string; recipient: OfflineQrIdentity;
    senderCertificate: SignedOfflineDeviceCertificate; privateKeyHex?: string; trustedIssuerPublicKey: string;
    idempotencyKey: string; transferUuid: string; recipientOutputUuid: string; changeOutputUuid: string | null;
    nonce: string; nowSeconds?: number;
  }) {
    if (!KEY.test(input.idempotencyKey)) throw new Error("A stable transfer idempotency key is required.");
    const now = input.nowSeconds ?? Math.floor(Date.now() / 1000);
    if (typeof input.recipient.signedRequest !== "string") throw new Error("A cryptographically verified signed QR request is required.");
    const qrIdentity = await verifySignedOfflineQrRequest(input.recipient.signedRequest, input.trustedIssuerPublicKey, deps.crypto, now);
    if (qrIdentity.deviceUuid !== input.recipient.deviceUuid || qrIdentity.keyVersion !== input.recipient.keyVersion
      || qrIdentity.amountMinor !== input.recipient.amountMinor || qrIdentity.requestUuid !== input.recipient.requestUuid) {
      throw new Error("Recipient fields do not match the signed request proof.");
    }
    const stored = await deps.store.updateItemAtomically<{ commitment: OfflineTransferCommitmentV1; lineage: OfflineTransferEvidence[] }>(key, (raw) => {
      const state = readState(raw, deps.deviceUuid);
      const replay = Object.values(state.pending).find((item) => (JSON.parse(item.transfer.canonical_payload) as OfflineTransferCommitmentV1).idempotency_key === input.idempotencyKey);
      if (replay) return { value: JSON.stringify(state), result: {
        commitment: { ...JSON.parse(replay.transfer.canonical_payload), payment_mode: "OFFLINE_STORED_VALUE" } as OfflineTransferCommitmentV1,
        lineage: replay.lineageEvidence,
      } };
      const outputId = input.outputUuid.toLowerCase();
      const output = state.outputs[outputId];
      if (!output || output.status !== "available") throw new Error("The SIMUSDT output is unavailable, already spent, or awaiting recovery.");
      const transferAmount = input.recipient.amountMinor;
      if (!Number.isSafeInteger(transferAmount) || transferAmount < 1 || transferAmount > output.amountMinor) throw new Error("The offline transfer exceeds the verified output value.");
      const changeAmount = output.amountMinor - transferAmount;
      if ((changeAmount > 0) !== (input.changeOutputUuid !== null)) throw new Error("A unique change output is required when value remains.");
      const transferId = input.transferUuid.toLowerCase();
      const recipientOutputId = input.recipientOutputUuid.toLowerCase();
      const changeOutputId = input.changeOutputUuid?.toLowerCase() ?? null;
      const pendingCommitments = Object.values(state.pending).map((item) => JSON.parse(item.transfer.canonical_payload) as Record<string, unknown>);
      const claimedIds = new Set<string>(Object.keys(state.outputs));
      for (const pendingCommitment of pendingCommitments) {
        for (const id of [pendingCommitment.transfer_uuid, pendingCommitment.recipient_output_uuid, pendingCommitment.change_output_uuid]) {
          if (typeof id === "string") claimedIds.add(id.toLowerCase());
        }
      }
      if (claimedIds.has(transferId) || claimedIds.has(recipientOutputId)
        || (changeOutputId !== null && (changeOutputId === recipientOutputId || claimedIds.has(changeOutputId)))) {
        throw new Error("A transfer or output identifier has already been reserved in this device journal.");
      }
      const parent = output.lineageEvidence.at(-1);
      const parentDevice = parent ? parent.recipient_device_uuid : String(output.sourceInstrument.payload.device_uuid);
      const parentDigest = output.lineageDigest;
      if (parentDevice !== deps.deviceUuid.toLowerCase() || !UUID.test(input.transferUuid) || !UUID.test(input.recipientOutputUuid)
        || (input.changeOutputUuid !== null && !UUID.test(input.changeOutputUuid))) throw new Error("The output ownership or transfer identifiers are invalid.");
      const commitment: OfflineTransferCommitmentV1 = {
        protocol_version: 1, transfer_uuid: input.transferUuid.toLowerCase(), payment_mode: "OFFLINE_STORED_VALUE", asset: "SIMUSDT",
        sender_device_uuid: deps.deviceUuid.toLowerCase(), recipient_device_uuid: input.recipient.deviceUuid,
        sender_key_version: Number(JSON.parse(input.senderCertificate.canonical_payload).key_version), recipient_key_version: input.recipient.keyVersion,
        input_output_uuid: outputId, recipient_output_uuid: input.recipientOutputUuid.toLowerCase(), change_output_uuid: input.changeOutputUuid?.toLowerCase() ?? null,
        input_amount_minor: output.amountMinor, transfer_amount_minor: transferAmount, change_amount_minor: changeAmount,
        transfer_counter: state.nextCounter, hop_count: (parent?.hop_count ?? 0) + 1, nonce: input.nonce, signed_at: now,
        parent_digest: parentDigest, idempotency_key: input.idempotencyKey,
      };
      const pending: PendingTransfer = { transfer: { canonical_payload: canonicalOfflineTransferCommitment(commitment), sender_signature: "", sender_certificate: input.senderCertificate },
        recipientRequest: input.recipient,
        inputOutputUuid: outputId, sourceInstrument: output.sourceInstrument, lineageEvidence: output.lineageEvidence, state: "prepared" };
      const next: TransferJournalState = { ...state, nextCounter: state.nextCounter + 1,
        outputs: { ...state.outputs, [outputId]: { ...output, status: "reserved" } },
        pending: { ...state.pending, [commitment.transfer_uuid]: pending } };
      return { value: JSON.stringify(next), result: { commitment, lineage: output.lineageEvidence } };
    });
    const commitment = stored.commitment;
    const signed = await createSignedOfflineTransfer({ commitment, signPayload: deps.signDevicePayload,
      senderPrivateKeyHex: input.privateKeyHex,
      senderCertificate: input.senderCertificate, trustedIssuerPublicKey: input.trustedIssuerPublicKey, nowSeconds: now });
    const persisted = await deps.store.updateItemAtomically<SignedOfflineTransfer>(key, (raw) => {
      const state = readState(raw, deps.deviceUuid); const pending = state.pending[commitment.transfer_uuid];
      if (!pending || pending.transfer.canonical_payload !== signed.canonical_payload) throw new Error("Transfer reservation changed during signing.");
      const nextPending = { ...pending, transfer: signed };
      return { value: JSON.stringify({ ...state, pending: { ...state.pending, [commitment.transfer_uuid]: nextPending } }), result: nextPending.transfer };
    });
    const latest = readState(await deps.store.getItemAsync(key), deps.deviceUuid).pending[commitment.transfer_uuid];
    return { type: "EXAEARN_SIMUSDT_TRANSFER_PREPARE_V1" as const, transfer: persisted, lineage_evidence: stored.lineage,
      source_instrument: latest.sourceInstrument };
  }

  async function acceptTransfer(input: {
    envelope: { transfer: SignedOfflineTransfer; lineage_evidence: OfflineTransferEvidence[]; source_instrument: Output["sourceInstrument"] };
    recipientRequest: OfflineQrIdentity; recipientPrivateKeyHex?: string; recipientCertificate: SignedOfflineDeviceCertificate;
    trustedIssuerPublicKey: string; nowSeconds?: number;
  }) {
    const replayCommitment = JSON.parse(input.envelope.transfer.canonical_payload) as Record<string, unknown>;
    const priorState = readState(await deps.store.getItemAsync(key), deps.deviceUuid);
    const priorTransfer = typeof replayCommitment.transfer_uuid === "string" ? priorState.pending[replayCommitment.transfer_uuid.toLowerCase()] : undefined;
    if (priorTransfer?.acknowledgment
      && priorTransfer.transfer.canonical_payload === input.envelope.transfer.canonical_payload
      && priorTransfer.transfer.sender_signature === input.envelope.transfer.sender_signature) {
      return { type: "EXAEARN_SIMUSDT_TRANSFER_ACK_V1" as const, transfer: priorTransfer.transfer,
        acknowledgment: priorTransfer.acknowledgment, lineage_evidence: priorTransfer.lineageEvidence };
    }
    const acknowledgment = await acceptSignedOfflineTransfer({ transfer: input.envelope.transfer, recipientRequest: input.recipientRequest,
      recipientPrivateKeyHex: input.recipientPrivateKeyHex, signPayload: deps.signDevicePayload, recipientCertificate: input.recipientCertificate,
      trustedIssuerPublicKey: input.trustedIssuerPublicKey, crypto: deps.crypto, nowSeconds: input.nowSeconds });
    const commitment = { ...JSON.parse(input.envelope.transfer.canonical_payload), payment_mode: "OFFLINE_STORED_VALUE" } as OfflineTransferCommitmentV1;
    if (commitment.recipient_device_uuid !== deps.deviceUuid.toLowerCase() || input.envelope.lineage_evidence.length > 100) throw new Error("Transfer recipient or lineage scope is invalid.");
    const sourcePayload = input.envelope.source_instrument.payload;
    const sourceCanonical = JSON.stringify(sourcePayload);
    if (input.envelope.source_instrument.issuerPublicKey !== input.trustedIssuerPublicKey
      || !verifyPinnedIssuerP256Proof(sourceCanonical, input.envelope.source_instrument.signature, input.trustedIssuerPublicKey)
      || sourcePayload.asset !== "SIMUSDT" || sourcePayload.environment !== "SIMULATION") {
      throw new Error("The transfer source instrument is not issuer-authenticated SIMUSDT evidence.");
    }
    const prior = input.envelope.lineage_evidence.at(-1);
    const expectedInput = prior
      ? prior.recipient_output_uuid === commitment.input_output_uuid || prior.change_output_uuid === commitment.input_output_uuid
      : sourcePayload.instrument_id === commitment.input_output_uuid;
    if (!expectedInput) throw new Error("The transfer evidence lineage does not lead to its issuer-backed input.");
    const expectedParentDigest = prior
      ? await evidenceOutputDigest(prior, commitment.input_output_uuid, deps.crypto)
      : await rootLineageDigest(String(sourcePayload.instrument_id), sourceCanonical, deps.crypto);
    if (expectedParentDigest !== commitment.parent_digest || input.envelope.lineage_evidence.length !== commitment.hop_count - 1) {
      throw new Error("The transfer parent digest or hop counter does not match its evidence lineage.");
    }
    const durableAck = await deps.store.updateItemAtomically<OfflineTransferAcknowledgment>(key, (raw) => {
      const state = readState(raw, deps.deviceUuid);
      const replay = state.pending[commitment.transfer_uuid];
      if (replay) {
        if (replay.transfer.canonical_payload !== input.envelope.transfer.canonical_payload
          || replay.transfer.sender_signature !== input.envelope.transfer.sender_signature || !replay.acknowledgment) {
          throw new Error("A conflicting transfer replay was rejected.");
        }
        return { value: JSON.stringify(state), result: replay.acknowledgment };
      }
      if (state.acceptedTransfers.includes(commitment.transfer_uuid) || state.seenNonces.includes(commitment.nonce)) throw new Error("This offline transfer or nonce was already accepted.");
      const recipientOutput = commitment.recipient_output_uuid.toLowerCase();
      if (state.outputs[recipientOutput] || Object.values(state.pending).some((item) => {
        const priorCommitment = JSON.parse(item.transfer.canonical_payload) as Record<string, unknown>;
        return String(priorCommitment.recipient_output_uuid).toLowerCase() === recipientOutput;
      })) throw new Error("The recipient output identifier is already reserved and cannot be reused.");
      return { value: JSON.stringify({ ...state, acceptedTransfers: [...state.acceptedTransfers, commitment.transfer_uuid],
        seenNonces: [...state.seenNonces, commitment.nonce].slice(-1000),
        pending: { ...state.pending, [commitment.transfer_uuid]: { transfer: input.envelope.transfer, acknowledgment,
          recipientRequest: input.recipientRequest,
          inputOutputUuid: commitment.input_output_uuid, sourceInstrument: input.envelope.source_instrument,
          lineageEvidence: input.envelope.lineage_evidence, state: "acknowledged" } } }), result: acknowledgment };
    });
    return { type: "EXAEARN_SIMUSDT_TRANSFER_ACK_V1" as const, transfer: input.envelope.transfer, acknowledgment: durableAck,
      lineage_evidence: input.envelope.lineage_evidence };
  }

  async function commitTransfer(input: {
    transferUuid: string; acknowledgment: OfflineTransferAcknowledgment; recipientRequest: OfflineQrIdentity;
    trustedIssuerPublicKey: string; privateKeyHex?: string; nowSeconds?: number;
  }) {
    const state = readState(await deps.store.getItemAsync(key), deps.deviceUuid);
    const pending = state.pending[input.transferUuid.toLowerCase()];
    if (!pending) throw new Error("The prepared transfer is missing; no local balance was changed.");
    if (pending.state === "committed" && pending.receipt && pending.acknowledgment) {
      const committedEvidence = transferEvidence(pending.transfer, pending.acknowledgment);
      return JSON.stringify({ type: "EXAEARN_SIMUSDT_TRANSFER_COMMIT_V1", transfer: pending.transfer,
        acknowledgment: pending.acknowledgment, receipt: pending.receipt, source_instrument: pending.sourceInstrument,
        lineage_evidence: [...pending.lineageEvidence, committedEvidence] });
    }
    const valid = await verifyOfflineTransferAcknowledgment({ transfer: pending.transfer, acknowledgment: input.acknowledgment,
      recipientRequest: input.recipientRequest, trustedIssuerPublicKey: input.trustedIssuerPublicKey, crypto: deps.crypto, nowSeconds: input.nowSeconds });
    if (!valid) throw new Error("Recipient acknowledgment is invalid; the reserved transfer requires recovery.");
    const commitment = { ...JSON.parse(pending.transfer.canonical_payload), payment_mode: "OFFLINE_STORED_VALUE" } as OfflineTransferCommitmentV1;
    const digest = await deps.crypto.digestSha256Hex(pending.transfer.canonical_payload);
    const receipt = await signPayload(canonicalReceipt(digest, input.acknowledgment, deps.deviceUuid), input.privateKeyHex, deps.signDevicePayload);
    const senderCertificate = JSON.parse(pending.transfer.sender_certificate.canonical_payload) as Record<string, unknown>;
    if (senderCertificate.device_uuid !== deps.deviceUuid.toLowerCase()
      || Number(senderCertificate.key_version) !== commitment.sender_key_version
      || !verifyPinnedIssuerP256Proof(pending.transfer.sender_certificate.canonical_payload,
        pending.transfer.sender_certificate.signature, input.trustedIssuerPublicKey)
      || !verifyP256Device(receipt, canonicalReceipt(digest, input.acknowledgment, deps.deviceUuid), String(senderCertificate.public_key), p256)) {
      throw new Error("The sender commit signature does not match the issuer-certified device; the output remains reserved for recovery.");
    }
    const evidence = transferEvidence(pending.transfer, input.acknowledgment);
    const changeLineageDigest = commitment.change_output_uuid
      ? await deps.crypto.digestSha256Hex(`${digest}|change|${commitment.change_output_uuid}`) : null;
    const committedEnvelope = await deps.store.updateItemAtomically<string>(key, (raw) => {
      const latest = readState(raw, deps.deviceUuid); const live = latest.pending[input.transferUuid.toLowerCase()];
      const source = latest.outputs[commitment.input_output_uuid];
      if (!live || !source) throw new Error("Reserved output state changed; transfer remains unresolved.");
      if (live.state === "committed" && live.receipt && live.acknowledgment) {
        const existingEvidence = transferEvidence(live.transfer, live.acknowledgment);
        return { value: JSON.stringify(latest), result: JSON.stringify({ type: "EXAEARN_SIMUSDT_TRANSFER_COMMIT_V1",
          transfer: live.transfer, acknowledgment: live.acknowledgment, receipt: live.receipt,
          source_instrument: live.sourceInstrument, lineage_evidence: [...live.lineageEvidence, existingEvidence] }) };
      }
      if (source.status !== "reserved") throw new Error("Reserved output state changed; transfer remains unresolved.");
      const outputs = { ...latest.outputs, [source.outputUuid]: { ...source, status: "spent" as const } };
      if (commitment.change_output_uuid && commitment.change_amount_minor > 0) {
        outputs[commitment.change_output_uuid] = { ...source, outputUuid: commitment.change_output_uuid,
          amountMinor: commitment.change_amount_minor,
          lineageDigest: changeLineageDigest!,
          lineageEvidence: [...source.lineageEvidence, evidence], status: "available" };
      }
      const nextPending: PendingTransfer = { ...live, acknowledgment: input.acknowledgment, receipt, state: "committed" };
      const envelope = JSON.stringify({ type: "EXAEARN_SIMUSDT_TRANSFER_COMMIT_V1", transfer: pending.transfer,
        acknowledgment: input.acknowledgment, receipt, source_instrument: pending.sourceInstrument,
        lineage_evidence: [...pending.lineageEvidence, evidence] });
      return { value: JSON.stringify({ ...latest, outputs, pending: { ...latest.pending, [input.transferUuid.toLowerCase()]: nextPending } }), result: envelope };
    });
    return committedEnvelope;
  }

  async function finalizeReceivedTransfer(input: {
    commit: { transfer: SignedOfflineTransfer; acknowledgment: OfflineTransferAcknowledgment; receipt: string;
      source_instrument: Output["sourceInstrument"]; lineage_evidence: OfflineTransferEvidence[] };
    trustedIssuerPublicKey: string; recipientRequest: OfflineQrIdentity;
    nowSeconds?: number;
  }) {
    const commitment = { ...JSON.parse(input.commit.transfer.canonical_payload), payment_mode: "OFFLINE_STORED_VALUE" } as OfflineTransferCommitmentV1;
    if (commitment.recipient_device_uuid !== deps.deviceUuid.toLowerCase()) throw new Error("Transfer commit belongs to another recipient.");
    const digest = await deps.crypto.digestSha256Hex(input.commit.transfer.canonical_payload);
    const senderCertificate = input.commit.transfer.sender_certificate;
    const sender = JSON.parse(senderCertificate.canonical_payload) as Record<string, unknown>;
    const receiptPayload = canonicalReceipt(digest, input.commit.acknowledgment, commitment.sender_device_uuid);
    if (sender.device_uuid !== commitment.sender_device_uuid || !verifyPinnedIssuerP256Proof(senderCertificate.canonical_payload,
      senderCertificate.signature, input.trustedIssuerPublicKey)
      || !verifyP256Device(input.commit.receipt, receiptPayload, String(sender.public_key), p256)) {
      throw new Error("The sender's durable commit receipt could not be verified.");
    }
    const validAck = await verifyOfflineTransferAcknowledgment({ transfer: input.commit.transfer, acknowledgment: input.commit.acknowledgment,
      recipientRequest: input.recipientRequest, trustedIssuerPublicKey: input.trustedIssuerPublicKey, crypto: deps.crypto, nowSeconds: input.nowSeconds });
    if (!validAck) throw new Error("Recipient acknowledgment did not match the sender commit.");
    if (input.commit.lineage_evidence.length !== commitment.hop_count
      || input.commit.lineage_evidence.at(-1)?.transfer_uuid !== commitment.transfer_uuid) {
      throw new Error("The sender commit does not carry the complete ordered transfer lineage.");
    }
    const rootCanonical = JSON.stringify(input.commit.source_instrument.payload);
    if (input.commit.source_instrument.issuerPublicKey !== input.trustedIssuerPublicKey
      || !verifyPinnedIssuerP256Proof(rootCanonical, input.commit.source_instrument.signature, input.trustedIssuerPublicKey)
      || input.commit.source_instrument.payload.asset !== "SIMUSDT") throw new Error("The source issuer proof is invalid.");
    const outputLineageDigest = await deps.crypto.digestSha256Hex(`${digest}|recipient|${commitment.recipient_output_uuid}`);
    await deps.store.updateItemAtomically<void>(key, (raw) => {
      const state = readState(raw, deps.deviceUuid); const transferId = commitment.transfer_uuid.toLowerCase();
      const existing = state.outputs[commitment.recipient_output_uuid];
      const output: Output = { outputUuid: commitment.recipient_output_uuid, amountMinor: commitment.transfer_amount_minor,
        lineageDigest: outputLineageDigest, sourceInstrument: input.commit.source_instrument,
        lineageEvidence: input.commit.lineage_evidence, status: "available" };
      if (existing && JSON.stringify(existing) !== JSON.stringify(output)) throw new Error("A conflicting recipient output already exists; the value remains quarantined.");
      const pending = state.pending[transferId];
      if (!pending || pending.acknowledgment?.transfer_digest !== input.commit.acknowledgment.transfer_digest) throw new Error("No matching durable recipient acknowledgment exists.");
      return { value: JSON.stringify({ ...state, outputs: { ...state.outputs, [output.outputUuid]: output },
        pending: { ...state.pending, [transferId]: { ...pending, receipt: input.commit.receipt, state: "committed" } } }), result: undefined };
    });
  }

  async function submitOutputEvidence(outputUuid: string, idempotencyKey: string) {
    if (!KEY.test(idempotencyKey)) throw new Error("A stable reconciliation idempotency key is required.");
    const state = readState(await deps.store.getItemAsync(key), deps.deviceUuid);
    const output = state.outputs[outputUuid.toLowerCase()];
    if (!output || output.status !== "available" || output.lineageEvidence.length < 1) throw new Error("Only a committed received SIMUSDT output with signed transfer evidence can be reconciled.");
    return deps.submitClaim({ expected_output_uuid: output.outputUuid, expected_amount_minor: output.amountMinor,
      idempotency_key: idempotencyKey, transfers: output.lineageEvidence });
  }

  async function getPendingTransfer(transferUuid: string) {
    const state = readState(await deps.store.getItemAsync(key), deps.deviceUuid);
    const pending = state.pending[transferUuid.toLowerCase()];
    if (!pending || pending.state === "committed") return null;
    return { type: "EXAEARN_SIMUSDT_TRANSFER_PREPARE_V1" as const, transfer: pending.transfer,
      lineage_evidence: pending.lineageEvidence, source_instrument: pending.sourceInstrument,
      recipient_request: pending.recipientRequest, durable_state: pending.state };
  }

  async function getCommittedTransfer(transferUuid: string) {
    const state = readState(await deps.store.getItemAsync(key), deps.deviceUuid);
    const pending = state.pending[transferUuid.toLowerCase()];
    if (!pending || pending.state !== "committed" || !pending.receipt || !pending.acknowledgment) return null;
    return JSON.stringify({ type: "EXAEARN_SIMUSDT_TRANSFER_COMMIT_V1", transfer: pending.transfer,
      acknowledgment: pending.acknowledgment, receipt: pending.receipt, source_instrument: pending.sourceInstrument,
      lineage_evidence: [...pending.lineageEvidence, transferEvidence(pending.transfer, pending.acknowledgment)] });
  }

  async function markRecoveryRequired(transferUuid: string) {
    return deps.store.updateItemAtomically<void>(key, (raw) => {
      const state = readState(raw, deps.deviceUuid); const pending = state.pending[transferUuid.toLowerCase()];
      if (!pending) return { value: JSON.stringify(state), result: undefined };
      const output = state.outputs[pending.inputOutputUuid];
      return { value: JSON.stringify({ ...state, outputs: output ? { ...state.outputs, [output.outputUuid]: { ...output, status: "recovery_required" } } : state.outputs,
        pending: { ...state.pending, [transferUuid.toLowerCase()]: { ...pending, state: "recovery_required" } } }), result: undefined };
    });
  }

  async function snapshot() {
    const state = readState(await deps.store.getItemAsync(key), deps.deviceUuid);
    return { asset: "SIMUSDT" as const, outputs: Object.values(state.outputs), pendingTransfers: Object.values(state.pending), realFunds: false as const };
  }

  return { saveInstrumentAfterAcknowledgment, prepareTransfer, acceptTransfer, commitTransfer, finalizeReceivedTransfer,
    submitOutputEvidence, getPendingTransfer, getCommittedTransfer, markRecoveryRequired, snapshot };
}

function bytesFromBase64(signature: string) {
  if (!/^[A-Za-z0-9+/]+={0,2}$/.test(signature) || signature.length % 4 !== 0) throw new Error("Invalid signature encoding.");
  const binary = atob(signature); return Uint8Array.from(binary, (character) => character.charCodeAt(0));
}
async function signPayload(payload: string, fallbackPrivateKeyHex?: string, signer?: (value: string) => string | Promise<string>) {
  if (signer) return signer(payload);
  if (fallbackPrivateKeyHex) return signDevicePayloadDer(payload, fallbackPrivateKeyHex);
  throw new Error("The protected device signing operation is unavailable.");
}
function publicBytes(pem: string) {
  const match = pem.match(/^-----BEGIN PUBLIC KEY-----\s*([A-Za-z0-9+/=\r\n]+)\s*-----END PUBLIC KEY-----$/);
  if (!match) throw new Error("Unsupported device public key encoding.");
  const der = Uint8Array.from(atob(match[1].replace(/\s/g, "")), (character) => character.charCodeAt(0));
  const prefix = Uint8Array.from("3059301306072a8648ce3d020106082a8648ce3d030107034200".match(/.{2}/g) ?? [], (byte) => Number.parseInt(byte, 16));
  if (der.length !== prefix.length + 65 || !prefix.every((item, index) => der[index] === item)) throw new Error("Unsupported device signing algorithm.");
  return der.slice(-65);
}
function verifyP256Device(signature: string, payload: string, publicKey: string, curve: typeof import("@noble/curves/nist.js").p256) {
  try { return curve.verify(bytesFromBase64(signature), new TextEncoder().encode(payload), publicBytes(publicKey), { prehash: true, format: "der" }); }
  catch { return false; }
}
function bytesToBase64Url(bytes: Uint8Array) {
  let raw = ""; for (const byte of bytes) raw += String.fromCharCode(byte);
  return btoa(raw).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
}
