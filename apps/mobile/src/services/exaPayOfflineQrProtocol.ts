import { p256 } from "@noble/curves/nist.js";

import { signDevicePayloadDer } from "./exaPayDeviceCrypto";

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const NONCE = /^[A-Za-z0-9_-]{32,128}$/;
const BASE64 = /^[A-Za-z0-9+/]+={0,2}$/;
const SPKI_P256_PREFIX = hex("3059301306072a8648ce3d020106082a8648ce3d030107034200");

export type SignedOfflineDeviceCertificate = {
  canonical_payload: string;
  signature: string;
  issuer_public_key: string;
  signature_algorithm: "SHA256";
};

export type SignedOfflineQrRequest = {
  type: "EXAEARN_SIMUSDT_OFFLINE_REQUEST";
  protocol_version: 1;
  canonical_payload: string;
  signature: string;
  device_certificate: SignedOfflineDeviceCertificate;
};

export type OfflineQrIdentity = {
  deviceUuid: string;
  keyVersion: number;
  publicKeyPem: string;
  requestUuid: string;
  amountMinor: number;
  message: string;
  createdAt: number;
  expiresAt: number;
  /** Original signed QR envelope; downstream code must revalidate it, not trust UI fields. */
  signedRequest: string;
};

export type OfflineQrCrypto = {
  randomUuid: () => string;
  randomBytes: (length: number) => Promise<Uint8Array>;
  digestSha256Hex: (value: string) => Promise<string>;
};

type RequestPayload = {
  protocol_version: 1;
  payment_mode: "OFFLINE_STORED_VALUE";
  asset: "SIMUSDT";
  request_uuid: string;
  recipient_device_uuid: string;
  recipient_key_version: number;
  device_certificate_digest: string;
  amount_minor: number;
  nonce: string;
  created_at: number;
  expires_at: number;
  message: string;
};

function hex(value: string): Uint8Array {
  return Uint8Array.from(value.match(/.{2}/g) ?? [], (byte) => Number.parseInt(byte, 16));
}

function base64Bytes(value: string): Uint8Array {
  if (!BASE64.test(value) || value.length % 4 !== 0) throw new Error("Invalid signed QR encoding.");
  const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
  const clean = value.replace(/=+$/, "");
  const output: number[] = [];
  let buffer = 0;
  let bits = 0;
  for (const character of clean) {
    const item = alphabet.indexOf(character);
    if (item < 0) throw new Error("Invalid signed QR encoding.");
    buffer = (buffer << 6) | item;
    bits += 6;
    if (bits >= 8) {
      bits -= 8;
      output.push((buffer >> bits) & 255);
    }
  }
  return Uint8Array.from(output);
}

function pemP256PublicKey(pem: string): Uint8Array {
  const match = pem.match(/^-----BEGIN PUBLIC KEY-----\s*([A-Za-z0-9+/=\r\n]+)\s*-----END PUBLIC KEY-----$/);
  if (!match) throw new Error("The QR certificate does not contain a supported P-256 public key.");
  const der = base64Bytes(match[1].replace(/\s/g, ""));
  if (der.length !== SPKI_P256_PREFIX.length + 65
    || !SPKI_P256_PREFIX.every((byte, index) => der[index] === byte)
    || der[der.length - 65] !== 4) {
    throw new Error("The QR certificate key is not an uncompressed P-256 key.");
  }
  return der.slice(-65);
}

export function isPinnedIssuerP256Key(pem: string) {
  try { pemP256PublicKey(pem); return true; } catch { return false; }
}

function parseCanonical<T extends Record<string, unknown>>(canonical: string): T {
  let value: unknown;
  try { value = JSON.parse(canonical); } catch { throw new Error("The signed QR payload is malformed."); }
  if (!value || typeof value !== "object" || Array.isArray(value)
    || JSON.stringify(value) !== canonical) throw new Error("The signed QR payload is not canonical.");
  return value as T;
}

function verifyP256(canonical: string, signature: string, publicKeyPem: string): boolean {
  try {
    return p256.verify(base64Bytes(signature), new TextEncoder().encode(canonical), pemP256PublicKey(publicKeyPem), {
      prehash: true,
      format: "der",
    });
  } catch {
    return false;
  }
}

/** Verify a PHP OpenSSL SHA-256 issuer proof when the pinned issuer uses P-256.
 * RSA issuer keys are deliberately unsupported by this offline client verifier. */
export function verifyPinnedIssuerP256Proof(canonical: string, signature: string, publicKeyPem: string) {
  return verifyP256(canonical, signature, publicKeyPem);
}

export function verifyIssuerDeviceCertificate(certificate: SignedOfflineDeviceCertificate, trustedIssuerPublicKey: string) {
  if (!certificate || certificate.signature_algorithm !== "SHA256"
    || certificate.issuer_public_key !== trustedIssuerPublicKey
    || typeof certificate.canonical_payload !== "string" || typeof certificate.signature !== "string") return false;
  try {
    parseCanonical<Record<string, unknown>>(certificate.canonical_payload);
    return verifyP256(certificate.canonical_payload, certificate.signature, trustedIssuerPublicKey);
  } catch {
    return false;
  }
}

export async function createSignedOfflineQrRequest(input: {
  deviceUuid: string;
  keyVersion: number;
  privateKeyHex: string;
  certificate: SignedOfflineDeviceCertificate;
  issuerPublicKeyPin: string;
  amountMinor: number;
  message?: string;
  nowSeconds?: number;
  crypto: OfflineQrCrypto;
}): Promise<string> {
  const now = input.nowSeconds ?? Math.floor(Date.now() / 1000);
  const certificate = parseCanonical<Record<string, unknown>>(input.certificate.canonical_payload);
  if (!verifyP256(input.certificate.canonical_payload, input.certificate.signature, input.issuerPublicKeyPin)
    || input.certificate.issuer_public_key !== input.issuerPublicKeyPin
    || certificate.device_uuid !== input.deviceUuid.toLowerCase()
    || certificate.key_version !== input.keyVersion || certificate.asset !== "SIMUSDT"
    || certificate.simulation_only !== true || Number(certificate.expires_at) <= now) {
    throw new Error("The enrolled SIMUSDT device certificate is invalid, expired, or not trusted.");
  }
  if (!UUID.test(input.deviceUuid) || !Number.isSafeInteger(input.keyVersion) || input.keyVersion < 1
    || !Number.isSafeInteger(input.amountMinor) || input.amountMinor < 1 || input.amountMinor > 1_000_000_000
    || (input.message ?? "").length > 100) throw new Error("The offline QR request fields are invalid.");
  const nonceBytes = await input.crypto.randomBytes(32);
  if (nonceBytes.length !== 32) throw new Error("Secure nonce generation failed.");
  const nonce = toBase64Url(nonceBytes);
  if (!NONCE.test(nonce)) throw new Error("Secure nonce generation failed.");
  const request: RequestPayload = {
    protocol_version: 1,
    payment_mode: "OFFLINE_STORED_VALUE",
    asset: "SIMUSDT",
    request_uuid: input.crypto.randomUuid().toLowerCase(),
    recipient_device_uuid: input.deviceUuid.toLowerCase(),
    recipient_key_version: input.keyVersion,
    device_certificate_digest: await input.crypto.digestSha256Hex(input.certificate.canonical_payload),
    amount_minor: input.amountMinor,
    nonce,
    created_at: now,
    expires_at: now + 300,
    message: input.message ?? "",
  };
  if (!UUID.test(request.request_uuid) || !/^[a-f0-9]{64}$/.test(request.device_certificate_digest)) {
    throw new Error("The signed QR request could not be created safely.");
  }
  const canonical = JSON.stringify(request);
  return JSON.stringify({
    type: "EXAEARN_SIMUSDT_OFFLINE_REQUEST",
    protocol_version: 1,
    canonical_payload: canonical,
    signature: signDevicePayloadDer(canonical, input.privateKeyHex),
    device_certificate: input.certificate,
  } satisfies SignedOfflineQrRequest);
}

export async function verifySignedOfflineQrRequest(
  encoded: string,
  trustedIssuerPublicKey: string,
  crypto: Pick<OfflineQrCrypto, "digestSha256Hex">,
  nowSeconds = Math.floor(Date.now() / 1000),
): Promise<OfflineQrIdentity> {
  if (encoded.length > 12_000) throw new Error("The offline QR payload is too large.");
  let envelope: unknown;
  try { envelope = JSON.parse(encoded); } catch { throw new Error("The offline QR payload is malformed."); }
  const outer = envelope && typeof envelope === "object" && !Array.isArray(envelope)
    ? envelope as Record<string, unknown> : null;
  if (!outer || outer.type !== "EXAEARN_SIMUSDT_OFFLINE_REQUEST" || outer.protocol_version !== 1
    || typeof outer.canonical_payload !== "string" || typeof outer.signature !== "string"
    || !outer.device_certificate || typeof outer.device_certificate !== "object") {
    throw new Error("This QR is not a supported signed SIMUSDT request.");
  }
  const certificate = outer.device_certificate as SignedOfflineDeviceCertificate;
  if (certificate.signature_algorithm !== "SHA256" || certificate.issuer_public_key !== trustedIssuerPublicKey
    || !verifyP256(certificate.canonical_payload, certificate.signature, trustedIssuerPublicKey)) {
    throw new Error("The QR device enrollment certificate could not be verified.");
  }
  const certified = parseCanonical<Record<string, unknown>>(certificate.canonical_payload);
  if (certified.asset !== "SIMUSDT" || certified.simulation_only !== true
    || !UUID.test(String(certified.device_uuid)) || !Number.isSafeInteger(certified.key_version)
    || Number(certified.expires_at) <= nowSeconds) throw new Error("The QR device certificate is expired or outside SIMUSDT simulation policy.");

  const canonical = outer.canonical_payload;
  const request = parseCanonical<RequestPayload>(canonical);
  const certificateDigest = await crypto.digestSha256Hex(certificate.canonical_payload);
  if (request.protocol_version !== 1 || request.payment_mode !== "OFFLINE_STORED_VALUE"
    || request.asset !== "SIMUSDT" || request.recipient_device_uuid !== certified.device_uuid
    || request.recipient_key_version !== certified.key_version || request.device_certificate_digest !== certificateDigest
    || !UUID.test(request.request_uuid) || !NONCE.test(request.nonce)
    || !Number.isSafeInteger(request.amount_minor) || request.amount_minor < 1 || request.amount_minor > 1_000_000_000
    || !Number.isSafeInteger(request.created_at) || !Number.isSafeInteger(request.expires_at)
    || request.created_at > nowSeconds + 60 || request.expires_at <= nowSeconds
    || request.expires_at - request.created_at > 300 || request.message.length > 100
    || !verifyP256(canonical, outer.signature, String(certified.public_key))) {
    throw new Error("The signed QR request is invalid, expired, or does not match its enrolled device.");
  }
  return {
    deviceUuid: request.recipient_device_uuid,
    keyVersion: request.recipient_key_version,
    publicKeyPem: String(certified.public_key),
    requestUuid: request.request_uuid,
    amountMinor: request.amount_minor,
    message: request.message,
    createdAt: request.created_at,
    expiresAt: request.expires_at,
    signedRequest: encoded,
  };
}

function toBase64Url(bytes: Uint8Array) {
  const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_";
  let output = "";
  for (let index = 0; index < bytes.length; index += 3) {
    const first = bytes[index];
    const second = bytes[index + 1];
    const third = bytes[index + 2];
    const triple = (first << 16) | ((second ?? 0) << 8) | (third ?? 0);
    output += alphabet[(triple >> 18) & 63] + alphabet[(triple >> 12) & 63];
    if (second !== undefined) output += alphabet[(triple >> 6) & 63];
    if (third !== undefined) output += alphabet[triple & 63];
  }
  return output;
}
