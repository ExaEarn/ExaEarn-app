import { p256 } from "@noble/curves/nist.js";
import { signDevicePayloadDer } from "./exaPayDeviceCrypto";
import { canonicalOfflineTransferCommitment, createSignedOfflineTransfer, acceptSignedOfflineTransfer, verifyOfflineTransferAcknowledgment, type OfflineTransferCommitmentV1 } from "./exaPayOfflineTransferProtocol";
import { createSignedOfflineQrRequest, verifySignedOfflineQrRequest, type SignedOfflineDeviceCertificate } from "./exaPayOfflineQrProtocol";

function assert(condition: unknown, message: string): asserts condition {
  if (!condition) throw new Error(message);
}

function base64(bytes: Uint8Array) {
  let binary = "";
  for (const byte of bytes) binary += String.fromCharCode(byte);
  return btoa(binary);
}

function publicPem(privateHex: string) {
  const raw = p256.getPublicKey(Uint8Array.from(privateHex.match(/.{2}/g) ?? [], (item) => Number.parseInt(item, 16)), false);
  const prefix = Uint8Array.from("3059301306072a8648ce3d020106082a8648ce3d030107034200".match(/.{2}/g) ?? [], (item) => Number.parseInt(item, 16));
  const der = new Uint8Array(prefix.length + raw.length);
  der.set(prefix);
  der.set(raw, prefix.length);
  return `-----BEGIN PUBLIC KEY-----\n${(base64(der).match(/.{1,64}/g) ?? []).join("\n")}\n-----END PUBLIC KEY-----`;
}

function certificate(deviceUuid: string, publicKey: string, privateIssuer: string, now: number): SignedOfflineDeviceCertificate {
  const canonical = JSON.stringify({
    certificate_version: 1, device_uuid: deviceUuid, public_key: publicKey, key_version: 1,
    protocol_version: 1, trust_class: "SOFTWARE_ONLY", trust_epoch: 1, asset: "SIMUSDT",
    simulation_only: true, issued_at: now - 30, expires_at: now + 86_400,
  });
  return { canonical_payload: canonical, signature: signDevicePayloadDer(canonical, privateIssuer), issuer_public_key: publicPem("0000000000000000000000000000000000000000000000000000000000000001"), signature_algorithm: "SHA256" };
}

async function digest(value: string) {
  const bytes = await globalThis.crypto.subtle.digest("SHA-256", new TextEncoder().encode(value));
  return Array.from(new Uint8Array(bytes), (byte) => byte.toString(16).padStart(2, "0")).join("");
}

export async function runOfflineTransferProtocolTests() {
  const issuerPrivate = "0000000000000000000000000000000000000000000000000000000000000001";
  const senderPrivate = "0000000000000000000000000000000000000000000000000000000000000002";
  const recipientPrivate = "0000000000000000000000000000000000000000000000000000000000000003";
  const senderUuid = "0f8fad5b-d9cb-469f-a165-70867728950e";
  const recipientUuid = "7d444840-9dc0-41d1-b245-5ffdce74fad2";
  const now = 1_800_000_000;
  const issuerPin = publicPem(issuerPrivate);
  const senderCertificate = certificate(senderUuid, publicPem(senderPrivate), issuerPrivate, now);
  const recipientCertificate = certificate(recipientUuid, publicPem(recipientPrivate), issuerPrivate, now);
  const commitment: OfflineTransferCommitmentV1 = {
    protocol_version: 1,
    transfer_uuid: "a34c8a0a-455f-4d6e-b915-5a27cbe3d092",
    payment_mode: "OFFLINE_STORED_VALUE",
    asset: "SIMUSDT",
    sender_device_uuid: senderUuid,
    recipient_device_uuid: recipientUuid,
    sender_key_version: 1,
    recipient_key_version: 1,
    input_output_uuid: "b6f511cb-d3b7-4b61-9f44-a2278e7d1529",
    recipient_output_uuid: "ac7f852b-43ab-4e84-94d9-93f2de99c128",
    change_output_uuid: "f76f8d31-80bb-411c-9c4a-0723cdac39af",
    input_amount_minor: 1_500,
    transfer_amount_minor: 1_250,
    change_amount_minor: 250,
    transfer_counter: 1,
    hop_count: 1,
    nonce: "0123456789abcdefghijklmnopqrstuv",
    signed_at: now,
    parent_digest: "a".repeat(64),
    idempotency_key: "EXAPAY-TRANSFER-QR-REQUEST-0001",
  };
  const canonical = canonicalOfflineTransferCommitment(commitment);
  assert(canonical === JSON.stringify({
    protocol_version: 1, transfer_uuid: commitment.transfer_uuid, sender_device_uuid: senderUuid,
    recipient_device_uuid: recipientUuid, sender_key_version: 1, recipient_key_version: 1,
    input_output_uuid: commitment.input_output_uuid, recipient_output_uuid: commitment.recipient_output_uuid,
    change_output_uuid: commitment.change_output_uuid, input_amount_minor: 1_500,
    transfer_amount_minor: 1_250, change_amount_minor: 250, transfer_counter: 1,
    hop_count: 1, nonce: commitment.nonce, signed_at: now, parent_digest: commitment.parent_digest,
    idempotency_key: commitment.idempotency_key, asset: "SIMUSDT",
  }), "Commitment JSON field order matches Laravel's canonical contract byte-for-byte.");

  const transfer = await createSignedOfflineTransfer({
    commitment, senderPrivateKeyHex: senderPrivate, senderCertificate,
    trustedIssuerPublicKey: issuerPin, nowSeconds: now,
  });
  const qrCrypto = { randomUuid: () => "d1ea8f31-a473-4d0e-80b0-bc9c21d6d628",
    randomBytes: async (length: number) => new Uint8Array(length).fill(17), digestSha256Hex: digest };
  const signedQr = await createSignedOfflineQrRequest({ deviceUuid: recipientUuid, keyVersion: 1, privateKeyHex: recipientPrivate,
    certificate: recipientCertificate, issuerPublicKeyPin: issuerPin, amountMinor: 1_250, nowSeconds: now - 1, crypto: qrCrypto });
  const recipientRequest = await verifySignedOfflineQrRequest(signedQr, issuerPin, { digestSha256Hex: digest }, now);
  const acknowledgment = await acceptSignedOfflineTransfer({
    transfer, recipientRequest, recipientPrivateKeyHex: recipientPrivate,
    recipientCertificate, trustedIssuerPublicKey: issuerPin,
    crypto: { digestSha256Hex: digest }, nowSeconds: now + 1,
  });
  assert(await verifyOfflineTransferAcknowledgment({
    transfer, acknowledgment, recipientRequest, trustedIssuerPublicKey: issuerPin,
    crypto: { digestSha256Hex: digest }, nowSeconds: now + 2,
  }), "recipient acknowledgement verifies against the exact sender commitment and signed QR request");

  let amountSubstitutionRejected = false;
  try {
    await acceptSignedOfflineTransfer({
      transfer,
      recipientRequest: { ...recipientRequest, amountMinor: 1_000 },
      recipientPrivateKeyHex: recipientPrivate,
      recipientCertificate,
      trustedIssuerPublicKey: issuerPin,
      crypto: { digestSha256Hex: digest },
      nowSeconds: now + 1,
    });
  } catch { amountSubstitutionRejected = true; }
  assert(amountSubstitutionRejected, "recipient signs only the exact requested amount, device and signed commitment");

  const changedAck = { ...acknowledgment, recipient_signature: transfer.sender_signature };
  assert(!(await verifyOfflineTransferAcknowledgment({
    transfer, acknowledgment: changedAck, recipientRequest, trustedIssuerPublicKey: issuerPin,
    crypto: { digestSha256Hex: digest }, nowSeconds: now + 2,
  })), "forged or substituted recipient acknowledgments are rejected");

  let conservationRejected = false;
  try { canonicalOfflineTransferCommitment({ ...commitment, change_amount_minor: 249 }); }
  catch { conservationRejected = true; }
  assert(conservationRejected, "transfer commitment enforces integer conservation across recipient and change outputs");

  return "Offline transfer commitment checks passed (Laravel canonical JSON, sender P-256 signature, issuer-certified recipient acknowledgment, amount/device binding, conservation, tamper rejection).";
}
