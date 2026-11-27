import { p256 } from "@noble/curves/nist.js";
import { createSignedOfflineQrRequest, verifySignedOfflineQrRequest, type OfflineQrCrypto, type OfflineQrIdentity, type SignedOfflineDeviceCertificate } from "./exaPayOfflineQrProtocol";
import { signDevicePayloadDer } from "./exaPayDeviceCrypto";

export { signDevicePayloadDer } from "./exaPayDeviceCrypto";

export type OfflineConsumptionClaim = {
  instrument_id: string;
  claim_id: string;
  consumed_at: number;
};

export type DeviceProtocolRequest = <T = Record<string, unknown>>(
  path: string,
  options?: RequestInit & { headers?: Record<string, string> },
) => Promise<T>;

export type DeviceKeyValueStore = {
  isAvailableAsync?: () => Promise<boolean>;
  getItemAsync: (key: string) => Promise<string | null>;
  setItemAsync: (key: string, value: string, options?: unknown) => Promise<void>;
};

export type AtomicDeviceJournalStore = DeviceKeyValueStore & {
  updateItemAtomically: <T>(
    key: string,
    update: (current: string | null) => { value: string; result: T },
  ) => Promise<T>;
};

export type DeviceProtocolCrypto = {
  randomBytes: (length: number) => Promise<Uint8Array>;
  randomUuid: () => string;
  digestSha256Hex: (value: string) => Promise<string>;
  secureStoreOptions?: unknown;
};

type DeviceIdentity = {
  version: 1;
  deviceUuid: string;
  keyVersion: 1;
  privateKeyHex: string;
};

type PendingJournal = {
  counter: number;
  previousDigest: string;
  nonce: string;
  signedAt: number;
  claims: OfflineConsumptionClaim[];
  canonicalPayload: string;
  signature: string | null;
  eventDigest: string | null;
  quarantineCode: string | null;
};

type DeviceJournalState = {
  version: 1;
  deviceUuid: string;
  acceptedCounter: number;
  acceptedDigest: string;
  pending: PendingJournal | null;
};

export type EnrolledDevice = {
  deviceUuid: string;
  keyVersion: 1;
  trustClass: "SOFTWARE_ONLY";
  hardwareSecurityLevel: string;
  realFundsEnabled: false;
  certificate: SignedOfflineDeviceCertificate | null;
};

export type SignedJournalReceipt = {
  deviceUuid: string;
  counter: number;
  eventDigest: string;
  status: "pending_review";
  redemptionStatus: "not_redeemed";
  realFunds: false;
};

type Dependencies = {
  request: DeviceProtocolRequest;
  apiBaseUrl: string;
  accountId: string;
  userId: string;
  identityStore: DeviceKeyValueStore;
  journalStore: AtomicDeviceJournalStore;
  crypto: DeviceProtocolCrypto;
};

const SPKI_P256_PREFIX = hexToBytes("3059301306072a8648ce3d020106082a8648ce3d030107034200");
const BASE64_ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const DIGEST_PATTERN = /^[a-f0-9]{64}$/;

function hexToBytes(value: string): Uint8Array {
  if (value.length % 2 || !/^[a-f0-9]*$/i.test(value)) throw new Error("Invalid hexadecimal encoding.");
  const bytes = new Uint8Array(value.length / 2);
  for (let index = 0; index < bytes.length; index += 1) bytes[index] = Number.parseInt(value.slice(index * 2, index * 2 + 2), 16);
  return bytes;
}

function bytesToHex(bytes: Uint8Array) {
  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join("");
}

function bytesToBase64(bytes: Uint8Array) {
  let result = "";
  for (let index = 0; index < bytes.length; index += 3) {
    const a = bytes[index];
    const b = bytes[index + 1];
    const c = bytes[index + 2];
    const triple = (a << 16) | ((b ?? 0) << 8) | (c ?? 0);
    result += BASE64_ALPHABET[(triple >> 18) & 63];
    result += BASE64_ALPHABET[(triple >> 12) & 63];
    result += b === undefined ? "=" : BASE64_ALPHABET[(triple >> 6) & 63];
    result += c === undefined ? "=" : BASE64_ALPHABET[triple & 63];
  }
  return result;
}

function bytesToBase64Url(bytes: Uint8Array) {
  return bytesToBase64(bytes).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
}

function pemFromPublicKey(publicKey: Uint8Array) {
  const der = new Uint8Array(SPKI_P256_PREFIX.length + publicKey.length);
  der.set(SPKI_P256_PREFIX, 0);
  der.set(publicKey, SPKI_P256_PREFIX.length);
  const base64 = bytesToBase64(der);
  const lines = base64.match(/.{1,64}/g) ?? [];
  return `-----BEGIN PUBLIC KEY-----\n${lines.join("\n")}\n-----END PUBLIC KEY-----`;
}

export function canonicalOfflineJournalPayload(input: {
  deviceUuid: string;
  counter: number;
  previousDigest: string;
  nonce: string;
  signedAt: number;
  claims: OfflineConsumptionClaim[];
}) {
  return JSON.stringify({
    protocol_version: 1,
    device_id: input.deviceUuid.toLowerCase(),
    counter: input.counter,
    previous_digest: input.previousDigest,
    nonce: input.nonce,
    signed_at: input.signedAt,
    consumption_claims: input.claims.map((claim) => ({
      instrument_id: claim.instrument_id.toLowerCase(),
      claim_id: claim.claim_id.toLowerCase(),
      consumed_at: claim.consumed_at,
    })),
  });
}

function readRecord(value: unknown): Record<string, unknown> | null {
  return value !== null && typeof value === "object" && !Array.isArray(value) ? value as Record<string, unknown> : null;
}

function unwrap(value: unknown) {
  const outer = readRecord(value);
  return outer && "data" in outer ? outer.data : value;
}

function validateClaims(claims: OfflineConsumptionClaim[], now: number) {
  if (!Array.isArray(claims) || claims.length > 100) throw new Error("Offline journal claim count is invalid.");
  const seenClaims = new Set<string>();
  const seenInstruments = new Set<string>();
  for (const claim of claims) {
    if (!claim || !UUID_PATTERN.test(claim.instrument_id) || !UUID_PATTERN.test(claim.claim_id)
      || !Number.isSafeInteger(claim.consumed_at) || claim.consumed_at < 1 || claim.consumed_at > now
      || now - claim.consumed_at > 2_592_000) {
      throw new Error("Offline journal contains invalid SIMUSDT consumption evidence.");
    }
    const id = claim.claim_id.toLowerCase();
    const instrumentId = claim.instrument_id.toLowerCase();
    if (seenClaims.has(id) || seenInstruments.has(instrumentId)) throw new Error("Duplicate SIMUSDT consumption evidence is not accepted.");
    seenClaims.add(id);
    seenInstruments.add(instrumentId);
  }
}

function normalizeClaims(claims: OfflineConsumptionClaim[]): OfflineConsumptionClaim[] {
  return claims.map((claim) => ({
    instrument_id: claim.instrument_id.toLowerCase(),
    claim_id: claim.claim_id.toLowerCase(),
    consumed_at: claim.consumed_at,
  }));
}

function readIdentity(value: string | null): DeviceIdentity | null {
  if (value === null) return null;
  let parsed: unknown;
  try { parsed = JSON.parse(value); } catch { throw new Error("Stored device identity is corrupt; preserve it for recovery."); }
  const identity = readRecord(parsed);
  if (!identity || identity.version !== 1 || identity.keyVersion !== 1
    || typeof identity.deviceUuid !== "string" || !UUID_PATTERN.test(identity.deviceUuid)
    || typeof identity.privateKeyHex !== "string" || !/^[a-f0-9]{64}$/i.test(identity.privateKeyHex)) {
    throw new Error("Stored device identity is invalid; it will not be replaced automatically.");
  }
  if (!p256.utils.isValidSecretKey(hexToBytes(identity.privateKeyHex))) throw new Error("Stored device key is invalid; it will not be replaced automatically.");
  return identity as unknown as DeviceIdentity;
}

function readJournal(value: string | null, deviceUuid: string): DeviceJournalState {
  if (value === null) return { version: 1, deviceUuid, acceptedCounter: 0, acceptedDigest: "", pending: null };
  let parsed: unknown;
  try { parsed = JSON.parse(value); } catch { throw new Error("Signed device journal is corrupt and quarantined."); }
  const state = readRecord(parsed);
  if (!state || state.version !== 1 || state.deviceUuid !== deviceUuid || !Number.isSafeInteger(state.acceptedCounter)
    || Number(state.acceptedCounter) < 0 || typeof state.acceptedDigest !== "string"
    || (state.acceptedDigest !== "" && !DIGEST_PATTERN.test(state.acceptedDigest))) {
    throw new Error("Signed device journal state is invalid and quarantined.");
  }
  if (state.pending !== null && state.pending !== undefined) {
    const pending = readRecord(state.pending);
    if (!pending || !Number.isSafeInteger(pending.counter) || Number(pending.counter) !== Number(state.acceptedCounter) + 1
      || typeof pending.canonicalPayload !== "string" || typeof pending.nonce !== "string"
      || !/^[A-Za-z0-9_-]{32,128}$/.test(pending.nonce)
      || !Number.isSafeInteger(pending.signedAt) || !Array.isArray(pending.claims)
      || typeof pending.previousDigest !== "string"
      || (pending.previousDigest !== "" && !DIGEST_PATTERN.test(pending.previousDigest))
      || (pending.signature !== null && (typeof pending.signature !== "string" || !/^[A-Za-z0-9+/=]+$/.test(pending.signature)))
      || (pending.eventDigest !== null && (typeof pending.eventDigest !== "string" || !DIGEST_PATTERN.test(pending.eventDigest)))
      || (pending.quarantineCode !== null && typeof pending.quarantineCode !== "string")) {
      throw new Error("Pending signed journal state is invalid and quarantined.");
    }
    const claims = pending.claims as OfflineConsumptionClaim[];
    if (JSON.stringify(claims) !== JSON.stringify(normalizeClaims(claims))
      || pending.canonicalPayload !== canonicalOfflineJournalPayload({
        deviceUuid,
        counter: Number(pending.counter),
        previousDigest: pending.previousDigest as string,
        nonce: pending.nonce as string,
        signedAt: Number(pending.signedAt),
        claims,
      })) {
      throw new Error("Pending signed journal was modified and is quarantined.");
    }
  }
  return state as unknown as DeviceJournalState;
}

function allowedSimulationApi(baseUrl: string) {
  try {
    const url = new URL(baseUrl);
    if (url.protocol !== "https:" && !(url.protocol === "http:" && ["localhost", "127.0.0.1", "::1"].includes(url.hostname))) return false;
    const host = url.hostname.toLowerCase();
    if (["api.exaearn.com", "exaearn.com", "www.exaearn.com"].includes(host)) return false;
    return ["localhost", "127.0.0.1", "::1"].includes(host)
      || /(^|[-.])(staging|sandbox|test|testing|dev|development)([-.]|$)/.test(host);
  } catch {
    return false;
  }
}

export function createExaPaySignedDeviceProtocol(deps: Dependencies) {
  if (!/^[A-Za-z0-9_-]{1,100}$/.test(deps.accountId) || !/^[A-Za-z0-9_-]{1,100}$/.test(deps.userId)) {
    throw new Error("A valid signed-in simulation account is required.");
  }
  if (!allowedSimulationApi(deps.apiBaseUrl)) throw new Error("Signed offline operations are restricted to explicit non-production API hosts.");
  const identityStorageKey = `exaearn.exapay.device-key.v1.${deps.accountId}`;
  const journalStorageKey = `exaearn.exapay.signed-journal.v1.${deps.accountId}`;
  const issuerPinStorageKey = `exaearn.exapay.issuer-key.v1.${deps.accountId}`;

  async function loadOrCreateIdentity(): Promise<DeviceIdentity> {
    if (deps.identityStore.isAvailableAsync && !await deps.identityStore.isAvailableAsync()) {
      throw new Error("OS secure storage is unavailable; device enrollment is disabled.");
    }
    const raw = await deps.identityStore.getItemAsync(identityStorageKey);
    const existing = readIdentity(raw);
    if (existing) return existing;

    let privateKey = new Uint8Array(32);
    for (let attempt = 0; attempt < 8 && !p256.utils.isValidSecretKey(privateKey); attempt += 1) {
      privateKey = new Uint8Array(await deps.crypto.randomBytes(32));
    }
    if (!p256.utils.isValidSecretKey(privateKey)) throw new Error("Secure random device-key generation failed.");
    const identity: DeviceIdentity = {
      version: 1,
      deviceUuid: deps.crypto.randomUuid().toLowerCase(),
      keyVersion: 1,
      privateKeyHex: bytesToHex(privateKey),
    };
    if (!UUID_PATTERN.test(identity.deviceUuid)) throw new Error("Secure device identifier generation failed.");
    const serialized = JSON.stringify(identity);
    try {
      await deps.identityStore.setItemAsync(identityStorageKey, serialized, deps.crypto.secureStoreOptions);
    } catch {
      if (await deps.identityStore.getItemAsync(identityStorageKey) !== serialized) {
        throw new Error("Device key storage is uncertain; enrollment was not sent.");
      }
    }
    return identity;
  }

  async function assertSimulationCapability() {
    const response = readRecord(unwrap(await deps.request("/api/exapay/offline/capabilities", { method: "GET" })));
    if (!response || response.offline_simulation_enabled !== true || response.asset !== "SIMUSDT"
      || response.real_funds_accessible !== false) {
      throw new Error("The server has not explicitly enabled SIMUSDT simulation-only device operations.");
    }
    if (typeof response.issuer_public_key === "string" && response.issuer_public_key.length > 0) {
      await pinIssuerKey(response.issuer_public_key);
    }
  }

  async function enrollDevice(): Promise<EnrolledDevice> {
    await assertSimulationCapability();
    const identity = await loadOrCreateIdentity();
    const publicKey = p256.getPublicKey(hexToBytes(identity.privateKeyHex), false);
    const nonce = bytesToBase64Url(await deps.crypto.randomBytes(32));
    const signedAt = Math.floor(Date.now() / 1000);
    const enrollmentMessage = ["exaearn-offline-enroll:v1", deps.userId, identity.deviceUuid, nonce, String(signedAt)].join("|");
    const signature = signDevicePayloadDer(enrollmentMessage, identity.privateKeyHex);
    const result = readRecord(unwrap(await deps.request("/api/exapay/offline/devices/enroll", {
      method: "POST",
      body: JSON.stringify({
        device_uuid: identity.deviceUuid,
        public_key: pemFromPublicKey(publicKey),
        nonce,
        signed_at: signedAt,
        signature,
      }),
    })));
    if (!result || result.device_uuid !== identity.deviceUuid || result.status !== "active"
      || result.real_funds_enabled !== false || typeof result.hardware_security_level !== "string") {
      throw new Error("Laravel did not confirm the expected SIMUSDT software-only device enrollment.");
    }
    return {
      deviceUuid: identity.deviceUuid,
      keyVersion: 1,
      trustClass: "SOFTWARE_ONLY",
      hardwareSecurityLevel: result.hardware_security_level,
      realFundsEnabled: false,
      certificate: readRecord(result.device_certificate) as unknown as SignedOfflineDeviceCertificate | null,
    };
  }

  async function createQrPaymentRequest(input: { amountMinor: number; message?: string }): Promise<string> {
    const enrolled = await enrollDevice();
    const identity = await loadOrCreateIdentity();
    if (identity.deviceUuid !== enrolled.deviceUuid) throw new Error("Device identity changed during QR request creation.");
    const certificate = enrolled.certificate;
    if (!certificate || typeof certificate.canonical_payload !== "string"
      || typeof certificate.signature !== "string" || typeof certificate.issuer_public_key !== "string") {
      throw new Error("The backend did not issue a signed enrollment certificate; offline QR requests are disabled.");
    }
    const capability = readRecord(unwrap(await deps.request("/api/exapay/offline/capabilities", { method: "GET" })));
    const issuerPublicKey = typeof capability?.issuer_public_key === "string" ? capability.issuer_public_key : "";
    if (!issuerPublicKey || issuerPublicKey !== certificate.issuer_public_key) {
      throw new Error("The QR certificate issuer does not match the authenticated simulation capability.");
    }
    const issuerPublicKeyPin = await pinIssuerKey(issuerPublicKey);
    const qrCrypto: OfflineQrCrypto = {
      randomUuid: deps.crypto.randomUuid,
      randomBytes: deps.crypto.randomBytes,
      digestSha256Hex: deps.crypto.digestSha256Hex,
    };
    return createSignedOfflineQrRequest({
      deviceUuid: identity.deviceUuid,
      keyVersion: identity.keyVersion,
      privateKeyHex: identity.privateKeyHex,
      certificate,
      issuerPublicKeyPin,
      amountMinor: input.amountMinor,
      message: input.message,
      crypto: qrCrypto,
    });
  }

  async function pinIssuerKey(candidate?: string): Promise<string> {
    const stored = await deps.identityStore.getItemAsync(issuerPinStorageKey);
    if (stored !== null) {
      if (candidate && stored !== candidate) throw new Error("The offline issuer key changed. Re-enrollment and reviewed key rotation are required before QR use.");
      return stored;
    }
    if (!candidate || candidate.length > 4096 || !candidate.includes("-----BEGIN PUBLIC KEY-----")) {
      throw new Error("No trusted issuer key is available for offline QR verification.");
    }
    await deps.identityStore.setItemAsync(issuerPinStorageKey, candidate, deps.crypto.secureStoreOptions);
    const written = await deps.identityStore.getItemAsync(issuerPinStorageKey);
    if (written !== candidate) throw new Error("The issuer public-key pin could not be stored securely.");
    return written;
  }

  async function verifyQrPaymentRequest(encoded: string): Promise<OfflineQrIdentity> {
    const issuerPublicKeyPin = await pinIssuerKey();
    return verifySignedOfflineQrRequest(encoded, issuerPublicKeyPin, deps.crypto);
  }

  async function getSigningContext() {
    const enrolled = await enrollDevice();
    const identity = await loadOrCreateIdentity();
    if (!enrolled.certificate) throw new Error("The backend device certificate is required for offline SIMUSDT transfers.");
    const issuerPublicKey = await pinIssuerKey(enrolled.certificate.issuer_public_key);
    const publicKeyPem = pemFromPublicKey(p256.getPublicKey(hexToBytes(identity.privateKeyHex), false));
    return {
      deviceUuid: identity.deviceUuid,
      keyVersion: identity.keyVersion,
      publicKeyPem,
      certificate: enrolled.certificate,
      trustedIssuerPublicKey: issuerPublicKey,
      signPayload: (payload: string) => signDevicePayloadDer(payload, identity.privateKeyHex),
    };
  }

  async function submitJournal(claims: OfflineConsumptionClaim[]): Promise<SignedJournalReceipt> {
    validateClaims(claims, Math.floor(Date.now() / 1000));
    const normalizedClaims = normalizeClaims(claims);
    const enrolled = await enrollDevice();
    const identity = await loadOrCreateIdentity();
    if (identity.deviceUuid !== enrolled.deviceUuid) throw new Error("Device identity changed during signed journal submission.");

    const generatedNonce = bytesToBase64Url(await deps.crypto.randomBytes(32));
    const generatedAt = Math.floor(Date.now() / 1000);
    const pending = await deps.journalStore.updateItemAtomically<PendingJournal>(journalStorageKey, (raw) => {
      const state = readJournal(raw, identity.deviceUuid);
      if (state.pending) {
        if (state.pending.quarantineCode) throw new Error("A prior signed journal is quarantined and requires recovery.");
        if (JSON.stringify(state.pending.claims) !== JSON.stringify(normalizedClaims)) {
          throw new Error("A different journal cannot replace an unacknowledged signed journal.");
        }
        return { value: JSON.stringify(state), result: state.pending };
      }
      const counter = state.acceptedCounter + 1;
      const canonicalPayload = canonicalOfflineJournalPayload({
        deviceUuid: identity.deviceUuid,
        counter,
        previousDigest: state.acceptedDigest,
        nonce: generatedNonce,
        signedAt: generatedAt,
        claims: normalizedClaims,
      });
      const nextPending: PendingJournal = {
        counter,
        previousDigest: state.acceptedDigest,
        nonce: generatedNonce,
        signedAt: generatedAt,
        claims: normalizedClaims,
        canonicalPayload,
        signature: null,
        eventDigest: null,
        quarantineCode: null,
      };
      const next = { ...state, pending: nextPending };
      return { value: JSON.stringify(next), result: nextPending };
    });

    let signed = pending;
    if (!signed.signature || !signed.eventDigest) {
      const signature = signDevicePayloadDer(signed.canonicalPayload, identity.privateKeyHex);
      const eventDigest = await deps.crypto.digestSha256Hex(signed.canonicalPayload);
      if (!DIGEST_PATTERN.test(eventDigest)) throw new Error("Device journal digest generation failed.");
      signed = await deps.journalStore.updateItemAtomically<PendingJournal>(journalStorageKey, (raw) => {
        const state = readJournal(raw, identity.deviceUuid);
        if (!state.pending || state.pending.counter !== pending.counter || state.pending.canonicalPayload !== pending.canonicalPayload) {
          throw new Error("Pending journal changed before its signature could be stored.");
        }
        if (state.pending.signature && state.pending.eventDigest) return { value: JSON.stringify(state), result: state.pending };
        const nextPending = { ...state.pending, signature, eventDigest, quarantineCode: null };
        return { value: JSON.stringify({ ...state, pending: nextPending }), result: nextPending };
      });
    }

    if (!signed.signature || !signed.eventDigest) throw new Error("Signed journal is incomplete and was not submitted.");
    if (signed.signature !== signDevicePayloadDer(signed.canonicalPayload, identity.privateKeyHex)
      || signed.eventDigest !== await deps.crypto.digestSha256Hex(signed.canonicalPayload)) {
      await quarantineJournal(identity.deviceUuid, signed.counter, "local_signed_journal_integrity_failure");
      throw new Error("Stored signed journal integrity check failed; its evidence was quarantined.");
    }
    const payload = {
      device_uuid: identity.deviceUuid,
      counter: signed.counter,
      previous_digest: signed.previousDigest || null,
      protocol_version: 1,
      nonce: signed.nonce,
      signed_at: signed.signedAt,
      consumption_claims: signed.claims,
      signature: signed.signature,
    };

    let responseRecord: Record<string, unknown> | null;
    try {
      responseRecord = readRecord(unwrap(await deps.request("/api/exapay/offline/journals/sync", {
        method: "POST",
        body: JSON.stringify(payload),
      })));
    } catch (error) {
      const status = readRecord(error)?.status;
      if (status === 409 || status === 422 || status === 403) {
        await quarantineJournal(identity.deviceUuid, signed.counter, String(readRecord(error)?.backendCode ?? "server_rejected_signed_journal"));
      }
      // A network failure leaves the exact signed request pending for idempotent retry.
      throw error;
    }

    if (!responseRecord || Number(responseRecord.counter) !== signed.counter || responseRecord.event_digest !== signed.eventDigest
      || responseRecord.real_funds !== false || responseRecord.redemption_status !== "not_redeemed") {
      await quarantineJournal(identity.deviceUuid, signed.counter, "journal_response_mismatch");
      throw new Error("Laravel journal acknowledgment did not match the signed SIMUSDT request.");
    }

    if (responseRecord.status === "conflict") {
      const reason = typeof responseRecord.conflict_code === "string" ? responseRecord.conflict_code : "server_conflict";
      await quarantineJournal(identity.deviceUuid, signed.counter, reason);
      throw new Error("Laravel quarantined the signed journal; its evidence is preserved and will not be retried automatically.");
    }
    if (responseRecord.status !== "pending_review") {
      await quarantineJournal(identity.deviceUuid, signed.counter, "unknown_journal_status");
      throw new Error("Laravel returned an unknown journal status; the evidence remains quarantined.");
    }

    const finalized = await deps.journalStore.updateItemAtomically<boolean>(journalStorageKey, (raw) => {
      const state = readJournal(raw, identity.deviceUuid);
      if (!state.pending && state.acceptedCounter === signed.counter && state.acceptedDigest === signed.eventDigest) {
        return { value: JSON.stringify(state), result: true };
      }
      if (!state.pending || state.pending.counter !== signed.counter || state.pending.eventDigest !== signed.eventDigest) {
        throw new Error("Journal acknowledgment does not match the locally pending sequence.");
      }
      const next: DeviceJournalState = {
        ...state,
        acceptedCounter: signed.counter,
        acceptedDigest: signed.eventDigest!,
        pending: null,
      };
      return { value: JSON.stringify(next), result: true };
    });
    if (!finalized) throw new Error("Journal acknowledgment was not durably recorded.");

    return {
      deviceUuid: identity.deviceUuid,
      counter: signed.counter,
      eventDigest: signed.eventDigest,
      status: "pending_review",
      redemptionStatus: "not_redeemed",
      realFunds: false,
    };
  }

  async function quarantineJournal(deviceUuid: string, counter: number, reason: string) {
    await deps.journalStore.updateItemAtomically<void>(journalStorageKey, (raw) => {
      const state = readJournal(raw, deviceUuid);
      if (!state.pending || state.pending.counter !== counter) {
        return { value: JSON.stringify(state), result: undefined };
      }
      const next = { ...state, pending: { ...state.pending, quarantineCode: reason.slice(0, 80) } };
      return { value: JSON.stringify(next), result: undefined };
    });
  }

  async function submitHardwareAttestation(format: "android_keystore" | "strongbox" | "ios_app_attest" | "ios_secure_enclave" | "secure_element", attestation: Record<string, unknown>) {
    if (!attestation || Object.keys(attestation).length === 0) {
      throw new Error("Empty device data is not hardware attestation.");
    }
    const identity = await loadOrCreateIdentity();
    const challengeResponse = readRecord(unwrap(await deps.request(
      `/api/exapay/offline/devices/${encodeURIComponent(identity.deviceUuid)}/attestation-challenge`,
      { method: "GET" },
    )));
    const challenge = typeof challengeResponse?.challenge === "string" ? challengeResponse.challenge : "";
    if (!/^[A-Za-z0-9_-]{43}$/.test(challenge)
      || challengeResponse?.device_uuid !== identity.deviceUuid
      || challengeResponse?.real_funds_enabled !== false) {
      throw new Error("A fresh Laravel attestation challenge is unavailable; hardware trust was not requested.");
    }
    const result = readRecord(unwrap(await deps.request(
      `/api/exapay/offline/devices/${encodeURIComponent(identity.deviceUuid)}/attestation`,
      { method: "POST", body: JSON.stringify({ format, challenge, attestation }) },
    )));
    const allowed = ["UNSUPPORTED", "NOT_VERIFIED", "VERIFIED", "REJECTED"];
    if (!result || !allowed.includes(String(result.attestation_status))
      || result.real_funds_accessible !== false) {
      throw new Error("Laravel did not return a valid fail-closed device trust assessment.");
    }
    return {
      status: result.attestation_status as "UNSUPPORTED" | "NOT_VERIFIED" | "VERIFIED" | "REJECTED",
      trustClass: result.trust_class === "VERIFIED_HARDWARE" && result.attestation_status === "VERIFIED"
        ? "VERIFIED_HARDWARE" as const : "SOFTWARE_ONLY" as const,
      reasonCode: typeof result.reason_code === "string" ? result.reason_code : "assessment_unavailable",
    };
  }

  return { enrollDevice, createQrPaymentRequest, verifyQrPaymentRequest, getSigningContext, submitJournal, submitHardwareAttestation };
}

