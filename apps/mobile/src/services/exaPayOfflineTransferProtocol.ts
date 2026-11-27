import { p256 } from "@noble/curves/nist.js";
import { signDevicePayloadDer } from "./exaPayDeviceCrypto";
import type { OfflineQrIdentity, SignedOfflineDeviceCertificate } from "./exaPayOfflineQrProtocol";
import { verifyIssuerDeviceCertificate, verifySignedOfflineQrRequest } from "./exaPayOfflineQrProtocol";

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const NONCE = /^[A-Za-z0-9_-]{32,128}$/;
const DIGEST = /^[a-f0-9]{64}$/;

export type OfflineTransferCommitmentV1 = {
  protocol_version: 1;
  transfer_uuid: string;
  payment_mode: "OFFLINE_STORED_VALUE";
  asset: "SIMUSDT";
  sender_device_uuid: string;
  recipient_device_uuid: string;
  sender_key_version: number;
  recipient_key_version: number;
  input_output_uuid: string;
  recipient_output_uuid: string;
  change_output_uuid: string | null;
  input_amount_minor: number;
  transfer_amount_minor: number;
  change_amount_minor: number;
  transfer_counter: number;
  hop_count: number;
  nonce: string;
  signed_at: number;
  parent_digest: string;
  idempotency_key: string;
};

export type SignedOfflineTransfer = {
  canonical_payload: string;
  sender_signature: string;
  sender_certificate: SignedOfflineDeviceCertificate;
};

export type OfflineTransferAcknowledgment = {
  transfer_digest: string;
  sender_signature_digest: string;
  recipient_device_uuid: string;
  recipient_key_version: number;
  nonce: string;
  signed_at: number;
  recipient_signature: string;
  recipient_certificate: SignedOfflineDeviceCertificate;
};

export type TransferProtocolCrypto = {
  digestSha256Hex: (value: string) => Promise<string>;
};

function base64Bytes(value: string) {
  if (!/^[A-Za-z0-9+/]+={0,2}$/.test(value) || value.length % 4 !== 0) throw new Error("Invalid signature encoding.");
  const binary = atob(value);
  return Uint8Array.from(binary, (character) => character.charCodeAt(0));
}

function certificatePayload(certificate: SignedOfflineDeviceCertificate, issuerPin: string, now: number) {
  if (!verifyIssuerDeviceCertificate(certificate, issuerPin)) throw new Error("The device certificate is not signed by the trusted P-256 issuer.");
  const payload = JSON.parse(certificate.canonical_payload) as Record<string, unknown>;
  if (payload.asset !== "SIMUSDT" || payload.simulation_only !== true || !UUID.test(String(payload.device_uuid))
    || !Number.isSafeInteger(payload.key_version) || Number(payload.expires_at) <= now) {
    throw new Error("The device certificate is expired or outside SIMUSDT simulation policy.");
  }
  return payload;
}

function validateCommitment(value: OfflineTransferCommitmentV1) {
  if (value.protocol_version !== 1 || value.payment_mode !== "OFFLINE_STORED_VALUE" || value.asset !== "SIMUSDT"
    || ![value.transfer_uuid, value.sender_device_uuid, value.recipient_device_uuid, value.input_output_uuid, value.recipient_output_uuid].every((id) => UUID.test(id))
    || (value.change_output_uuid !== null && !UUID.test(value.change_output_uuid))
    || ![value.sender_key_version, value.recipient_key_version, value.input_amount_minor, value.transfer_amount_minor,
      value.change_amount_minor, value.transfer_counter, value.hop_count, value.signed_at].every(Number.isSafeInteger)
    || value.sender_key_version < 1 || value.recipient_key_version < 1 || value.input_amount_minor < 1
    || value.transfer_amount_minor < 1 || value.change_amount_minor < 0 || value.transfer_counter < 1 || value.hop_count < 1
    || value.signed_at < 1 || value.transfer_amount_minor + value.change_amount_minor !== value.input_amount_minor
    || (value.change_amount_minor === 0) !== (value.change_output_uuid === null)
    || !NONCE.test(value.nonce) || !DIGEST.test(value.parent_digest)
    || !/^[A-Za-z0-9._:-]{16,160}$/.test(value.idempotency_key)) {
    throw new Error("The offline transfer commitment is malformed or violates SIMUSDT monetary invariants.");
  }
}

export function canonicalOfflineTransferCommitment(value: OfflineTransferCommitmentV1) {
  validateCommitment(value);
  return JSON.stringify({
    protocol_version: value.protocol_version,
    transfer_uuid: value.transfer_uuid.toLowerCase(),
    sender_device_uuid: value.sender_device_uuid.toLowerCase(),
    recipient_device_uuid: value.recipient_device_uuid.toLowerCase(),
    sender_key_version: value.sender_key_version,
    recipient_key_version: value.recipient_key_version,
    input_output_uuid: value.input_output_uuid.toLowerCase(),
    recipient_output_uuid: value.recipient_output_uuid.toLowerCase(),
    change_output_uuid: value.change_output_uuid?.toLowerCase() ?? null,
    input_amount_minor: value.input_amount_minor,
    transfer_amount_minor: value.transfer_amount_minor,
    change_amount_minor: value.change_amount_minor,
    transfer_counter: value.transfer_counter,
    hop_count: value.hop_count,
    nonce: value.nonce,
    signed_at: value.signed_at,
    parent_digest: value.parent_digest,
    idempotency_key: value.idempotency_key,
    asset: value.asset,
  });
}

export async function createSignedOfflineTransfer(input: {
  commitment: OfflineTransferCommitmentV1;
  senderPrivateKeyHex?: string;
  signPayload?: (payload: string) => string | Promise<string>;
  senderCertificate: SignedOfflineDeviceCertificate;
  trustedIssuerPublicKey: string;
  nowSeconds?: number;
}): Promise<SignedOfflineTransfer> {
  const now = input.nowSeconds ?? Math.floor(Date.now() / 1000);
  const certified = certificatePayload(input.senderCertificate, input.trustedIssuerPublicKey, now);
  if (certified.device_uuid !== input.commitment.sender_device_uuid.toLowerCase()
    || certified.key_version !== input.commitment.sender_key_version) {
    throw new Error("The transfer sender does not match its issuer-certified device identity.");
  }
  const canonical = canonicalOfflineTransferCommitment(input.commitment);
  if (!input.signPayload && !input.senderPrivateKeyHex) throw new Error("A protected sender signing operation is required.");
  const senderSignature = input.signPayload ? await input.signPayload(canonical) : signDevicePayloadDer(canonical, input.senderPrivateKeyHex!);
  if (!p256.verify(base64Bytes(senderSignature), new TextEncoder().encode(canonical), publicKeyBytes(String(certified.public_key)), { prehash: true, format: "der" })) {
    throw new Error("The protected signing key does not match the issuer-certified sender device.");
  }
  return {
    canonical_payload: canonical,
    sender_signature: senderSignature,
    sender_certificate: input.senderCertificate,
  };
}

export async function acceptSignedOfflineTransfer(input: {
  transfer: SignedOfflineTransfer;
  recipientRequest: OfflineQrIdentity;
  recipientPrivateKeyHex?: string;
  signPayload?: (payload: string) => string | Promise<string>;
  recipientCertificate: SignedOfflineDeviceCertificate;
  trustedIssuerPublicKey: string;
  crypto: TransferProtocolCrypto;
  nowSeconds?: number;
}): Promise<OfflineTransferAcknowledgment> {
  const now = input.nowSeconds ?? Math.floor(Date.now() / 1000);
  await assertSignedRecipientRequest(input.recipientRequest, input.trustedIssuerPublicKey, input.crypto, now);
  const sender = certificatePayload(input.transfer.sender_certificate, input.trustedIssuerPublicKey, now);
  const recipient = certificatePayload(input.recipientCertificate, input.trustedIssuerPublicKey, now);
  const commitment = { ...JSON.parse(input.transfer.canonical_payload), payment_mode: "OFFLINE_STORED_VALUE" } as OfflineTransferCommitmentV1;
  validateCommitment(commitment);
  if (canonicalOfflineTransferCommitment(commitment) !== input.transfer.canonical_payload
    || !p256.verify(base64Bytes(input.transfer.sender_signature), new TextEncoder().encode(input.transfer.canonical_payload),
      publicKeyBytes(String(sender.public_key)), { prehash: true, format: "der" })) {
    throw new Error("The sender commitment is not canonical or its P-256 signature is invalid.");
  }
  if (sender.device_uuid !== commitment.sender_device_uuid || sender.key_version !== commitment.sender_key_version
    || recipient.device_uuid !== commitment.recipient_device_uuid || recipient.key_version !== commitment.recipient_key_version
    || input.recipientRequest.deviceUuid !== commitment.recipient_device_uuid
    || input.recipientRequest.keyVersion !== commitment.recipient_key_version
    || input.recipientRequest.amountMinor !== commitment.transfer_amount_minor
    || input.recipientRequest.expiresAt < commitment.signed_at
    || now > input.recipientRequest.expiresAt) {
    throw new Error("The signed transfer does not match the recipient's authenticated SIMUSDT request.");
  }
  const digest = await input.crypto.digestSha256Hex(input.transfer.canonical_payload);
  const senderSignatureDigest = await input.crypto.digestSha256Hex(input.transfer.sender_signature);
  if (!DIGEST.test(digest) || !DIGEST.test(senderSignatureDigest)) throw new Error("Transfer digest generation failed.");
  const ackSignedAt = now;
  if (ackSignedAt < commitment.signed_at || ackSignedAt > input.recipientRequest.expiresAt) {
    throw new Error("The recipient acknowledgment is outside the signed request's validity window.");
  }
  const nonce = commitment.nonce;
  const challenge = [
    "exaearn-offline-transfer-accept:v1", digest, commitment.recipient_device_uuid,
    nonce, String(commitment.signed_at), senderSignatureDigest,
  ].join("|");
  if (!input.signPayload && !input.recipientPrivateKeyHex) throw new Error("A protected recipient signing operation is required.");
  const recipientSignature = input.signPayload ? await input.signPayload(challenge) : signDevicePayloadDer(challenge, input.recipientPrivateKeyHex!);
  if (!p256.verify(base64Bytes(recipientSignature), new TextEncoder().encode(challenge), publicKeyBytes(String(recipient.public_key)), { prehash: true, format: "der" })) {
    throw new Error("The protected signing key does not match the issuer-certified recipient device.");
  }
  return {
    transfer_digest: digest,
    sender_signature_digest: senderSignatureDigest,
    recipient_device_uuid: commitment.recipient_device_uuid,
    recipient_key_version: commitment.recipient_key_version,
    nonce,
    signed_at: ackSignedAt,
    recipient_signature: recipientSignature,
    recipient_certificate: input.recipientCertificate,
  };
}

export async function verifyOfflineTransferAcknowledgment(input: {
  transfer: SignedOfflineTransfer;
  acknowledgment: OfflineTransferAcknowledgment;
  recipientRequest: OfflineQrIdentity;
  trustedIssuerPublicKey: string;
  crypto: TransferProtocolCrypto;
  nowSeconds?: number;
}) {
  await assertSignedRecipientRequest(input.recipientRequest, input.trustedIssuerPublicKey, input.crypto, input.acknowledgment.signed_at);
  const commitment = { ...JSON.parse(input.transfer.canonical_payload), payment_mode: "OFFLINE_STORED_VALUE" } as OfflineTransferCommitmentV1;
  if (!Number.isSafeInteger(input.acknowledgment.signed_at) || input.acknowledgment.signed_at < commitment.signed_at) return false;
  const recipient = certificatePayload(input.acknowledgment.recipient_certificate, input.trustedIssuerPublicKey, input.acknowledgment.signed_at);
  const digest = await input.crypto.digestSha256Hex(input.transfer.canonical_payload);
  const senderSignatureDigest = await input.crypto.digestSha256Hex(input.transfer.sender_signature);
  if (input.acknowledgment.transfer_digest !== digest || input.acknowledgment.sender_signature_digest !== senderSignatureDigest
    || input.acknowledgment.recipient_device_uuid !== commitment.recipient_device_uuid
    || input.acknowledgment.recipient_device_uuid !== input.recipientRequest.deviceUuid
    || input.acknowledgment.recipient_key_version !== input.recipientRequest.keyVersion
    || recipient.device_uuid !== input.recipientRequest.deviceUuid || recipient.key_version !== input.recipientRequest.keyVersion
    || commitment.transfer_amount_minor !== input.recipientRequest.amountMinor
    || input.acknowledgment.signed_at < commitment.signed_at
    || input.acknowledgment.signed_at > input.recipientRequest.expiresAt) return false;
  const challenge = ["exaearn-offline-transfer-accept:v1", digest, commitment.recipient_device_uuid,
    commitment.nonce, String(commitment.signed_at), senderSignatureDigest].join("|");
  try {
    return p256.verify(base64Bytes(input.acknowledgment.recipient_signature), new TextEncoder().encode(challenge),
      publicKeyBytes(String(recipient.public_key)), { prehash: true, format: "der" });
  } catch {
    return false;
  }
}

async function assertSignedRecipientRequest(identity: OfflineQrIdentity, issuerPin: string, crypto: TransferProtocolCrypto, now: number) {
  if (typeof identity.signedRequest !== "string") throw new Error("A verified signed QR request is required for offline transfer.");
  const verified = await verifySignedOfflineQrRequest(identity.signedRequest, issuerPin, crypto, now);
  if (verified.deviceUuid !== identity.deviceUuid || verified.keyVersion !== identity.keyVersion
    || verified.amountMinor !== identity.amountMinor || verified.requestUuid !== identity.requestUuid
    || verified.expiresAt !== identity.expiresAt || verified.publicKeyPem !== identity.publicKeyPem) {
    throw new Error("The recipient identity differs from its signed QR request.");
  }
}

function publicKeyBytes(pem: string) {
  const match = pem.match(/^-----BEGIN PUBLIC KEY-----\s*([A-Za-z0-9+/=\r\n]+)\s*-----END PUBLIC KEY-----$/);
  if (!match) throw new Error("Unsupported device key encoding.");
  const binary = atob(match[1].replace(/\s/g, ""));
  const der = Uint8Array.from(binary, (character) => character.charCodeAt(0));
  const prefix = Uint8Array.from("3059301306072a8648ce3d020106082a8648ce3d030107034200".match(/.{2}/g) ?? [], (item) => Number.parseInt(item, 16));
  if (der.length !== prefix.length + 65 || !prefix.every((byte, index) => der[index] === byte)) throw new Error("Unsupported device key algorithm.");
  return der.slice(-65);
}
