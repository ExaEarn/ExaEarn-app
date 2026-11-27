import { p256 } from "@noble/curves/nist.js";
import { createSignedOfflineQrRequest, verifySignedOfflineQrRequest, type SignedOfflineDeviceCertificate } from "./exaPayOfflineQrProtocol";
import { signDevicePayloadDer } from "./exaPaySignedJournalProtocol";

function assert(condition: unknown, message: string): asserts condition {
  if (!condition) throw new Error(message);
}

function base64(bytes: Uint8Array) {
  let binary = "";
  for (const byte of bytes) binary += String.fromCharCode(byte);
  return btoa(binary);
}

function p256Pem(privateKeyHex: string) {
  const publicKey = p256.getPublicKey(Uint8Array.from(privateKeyHex.match(/.{2}/g) ?? [], (item) => Number.parseInt(item, 16)), false);
  const prefix = Uint8Array.from("3059301306072a8648ce3d020106082a8648ce3d030107034200".match(/.{2}/g) ?? [], (item) => Number.parseInt(item, 16));
  const der = new Uint8Array(prefix.length + publicKey.length);
  der.set(prefix);
  der.set(publicKey, prefix.length);
  const encoded = base64(der).match(/.{1,64}/g)?.join("\n") ?? "";
  return `-----BEGIN PUBLIC KEY-----\n${encoded}\n-----END PUBLIC KEY-----`;
}

async function digest(value: string) {
  const bytes = await globalThis.crypto.subtle.digest("SHA-256", new TextEncoder().encode(value));
  return Array.from(new Uint8Array(bytes), (byte) => byte.toString(16).padStart(2, "0")).join("");
}

export async function runOfflineQrProtocolTests() {
  const issuerPrivate = "0000000000000000000000000000000000000000000000000000000000000001";
  const devicePrivate = "0000000000000000000000000000000000000000000000000000000000000002";
  const issuerPublic = p256Pem(issuerPrivate);
  const devicePublic = p256Pem(devicePrivate);
  const now = 1_800_000_000;
  const deviceUuid = "0f8fad5b-d9cb-469f-a165-70867728950e";
  const certificatePayload = JSON.stringify({
    certificate_version: 1,
    device_uuid: deviceUuid,
    public_key: devicePublic,
    key_version: 1,
    protocol_version: 1,
    trust_class: "SOFTWARE_ONLY",
    trust_epoch: 1,
    asset: "SIMUSDT",
    simulation_only: true,
    issued_at: now - 10,
    expires_at: now + 86_400,
  });
  const certificate: SignedOfflineDeviceCertificate = {
    canonical_payload: certificatePayload,
    signature: signDevicePayloadDer(certificatePayload, issuerPrivate),
    issuer_public_key: issuerPublic,
    signature_algorithm: "SHA256",
  };
  const crypto = {
    randomUuid: () => "7d444840-9dc0-41d1-b245-5ffdce74fad2",
    randomBytes: async (length: number) => new Uint8Array(length).fill(7),
    digestSha256Hex: digest,
  };
  const encoded = await createSignedOfflineQrRequest({
    deviceUuid,
    keyVersion: 1,
    privateKeyHex: devicePrivate,
    certificate,
    issuerPublicKeyPin: issuerPublic,
    amountMinor: 1_250,
    message: "Offline SIMUSDT",
    nowSeconds: now,
    crypto,
  });
  const verified = await verifySignedOfflineQrRequest(encoded, issuerPublic, crypto, now + 1);
  assert(verified.deviceUuid === deviceUuid && verified.amountMinor === 1_250, "QR verification binds amount to the issuer-certified enrolled device key");
  assert(verified.expiresAt === now + 300, "offline payment requests have a bounded five-minute lifetime");

  let alteredRejected = false;
  try {
    const envelope = JSON.parse(encoded) as Record<string, unknown>;
    const payload = JSON.parse(String(envelope.canonical_payload)) as Record<string, unknown>;
    payload.amount_minor = 1;
    envelope.canonical_payload = JSON.stringify(payload);
    await verifySignedOfflineQrRequest(JSON.stringify(envelope), issuerPublic, crypto, now + 1);
  } catch { alteredRejected = true; }
  assert(alteredRejected, "Changing the requested amount invalidates the device signature");

  let wrongIssuerRejected = false;
  try { await verifySignedOfflineQrRequest(encoded, p256Pem("0000000000000000000000000000000000000000000000000000000000000003"), crypto, now + 1); }
  catch { wrongIssuerRejected = true; }
  assert(wrongIssuerRejected, "A different issuer key cannot certify a QR request");

  let expiredRejected = false;
  try { await verifySignedOfflineQrRequest(encoded, issuerPublic, crypto, now + 301); }
  catch { expiredRejected = true; }
  assert(expiredRejected, "Expired QR requests cannot be accepted");

  let realAssetRejected = false;
  const wrongAssetPayload = certificatePayload.replace('"asset":"SIMUSDT"', '"asset":"USDT"');
  try { await createSignedOfflineQrRequest({
    deviceUuid, keyVersion: 1, privateKeyHex: devicePrivate,
    certificate: { ...certificate, canonical_payload: wrongAssetPayload, signature: signDevicePayloadDer(wrongAssetPayload, issuerPrivate) },
    issuerPublicKeyPin: issuerPublic, amountMinor: 1, crypto,
  }); } catch { realAssetRejected = true; }
  assert(realAssetRejected, "A QR certificate for a non-SIMUSDT asset is rejected");

  return "Signed offline QR protocol checks passed (issuer-certified P-256 device, canonical amount signature, bounded expiry, tamper rejection, issuer pin).";
}
