import { p256 } from "@noble/curves/nist.js";

import {
  canonicalOfflineJournalPayload,
  createExaPaySignedDeviceProtocol,
  signDevicePayloadDer,
  type AtomicDeviceJournalStore,
  type DeviceKeyValueStore,
} from "./exaPaySignedJournalProtocol";

const DEVICE_UUID = "0f8fad5b-d9cb-469f-a165-70867728950e";
const INSTRUMENT_UUID = "7d444840-9dc0-11d1-b245-5ffdce74fad2";
const CLAIM_UUID = "7d444840-9dc0-41d1-b245-5ffdce74fad2";
const PRIVATE_KEY = "0000000000000000000000000000000000000000000000000000000000000001";
const PUBLIC_KEY = p256.getPublicKey(Uint8Array.from(PRIVATE_KEY.match(/.{2}/g) ?? [], (byte) => Number.parseInt(byte, 16)), false);

function assert(condition: unknown, message: string): asserts condition {
  if (!condition) throw new Error(message);
}

async function digest(value: string) {
  const bytes = await globalThis.crypto.subtle.digest("SHA-256", new TextEncoder().encode(value));
  return Array.from(new Uint8Array(bytes), (byte) => byte.toString(16).padStart(2, "0")).join("");
}

function base64ToBytes(value: string) {
  const binary = atob(value);
  return Uint8Array.from(binary, (character) => character.charCodeAt(0));
}

function serverCanonicalPayload(body: Record<string, unknown>) {
  const claims = body.consumption_claims as Array<{ instrument_id: string; claim_id: string; consumed_at: number }>;
  return JSON.stringify({
    protocol_version: body.protocol_version,
    device_id: body.device_uuid,
    counter: body.counter,
    previous_digest: body.previous_digest ?? "",
    nonce: body.nonce,
    signed_at: body.signed_at,
    consumption_claims: claims.map((claim) => ({
      instrument_id: claim.instrument_id,
      claim_id: claim.claim_id,
      consumed_at: claim.consumed_at,
    })),
  });
}

function makeStores() {
  const identity = new Map<string, string>();
  const journal = new Map<string, string>();
  let lock: Promise<void> = Promise.resolve();
  const identityStore: DeviceKeyValueStore = {
    isAvailableAsync: async () => true,
    getItemAsync: async (key) => identity.get(key) ?? null,
    setItemAsync: async (key, value) => { identity.set(key, value); },
  };
  const journalStore: AtomicDeviceJournalStore = {
    isAvailableAsync: async () => true,
    getItemAsync: async (key) => journal.get(key) ?? null,
    setItemAsync: async (key, value) => { journal.set(key, value); },
    async updateItemAtomically<T>(key: string, update: (current: string | null) => { value: string; result: T }) {
      let release!: () => void;
      const nextLock = new Promise<void>((resolve) => { release = resolve; });
      const previous = lock;
      lock = previous.then(() => nextLock);
      await previous;
      try {
        const changed = update(journal.get(key) ?? null);
        journal.set(key, changed.value);
        return changed.result;
      } finally {
        release();
      }
    },
  };
  return { identityStore, journalStore, journal };
}

function makeProtocol(input: {
  apiBaseUrl?: string;
  serverEnabled?: boolean;
  syncBehavior?: (body: Record<string, unknown>) => Promise<unknown>;
} = {}) {
  const stores = makeStores();
  const requests: Array<{ path: string; body: Record<string, unknown> | null }> = [];
  let randomSequence = 0;
  const request = async <T,>(path: string, options: RequestInit = {}): Promise<T> => {
    const body = typeof options.body === "string" ? JSON.parse(options.body) as Record<string, unknown> : null;
    requests.push({ path, body });
    let result: unknown;
    if (path.endsWith("/capabilities")) {
      result = { data: {
        offline_simulation_enabled: input.serverEnabled ?? true,
        asset: "SIMUSDT",
        real_funds_accessible: false,
      } };
    } else if (path.endsWith("/attestation-challenge")) {
      result = { data: { device_uuid: DEVICE_UUID, challenge: "A".repeat(43), expires_in_seconds: 300, real_funds_enabled: false } };
    } else if (path.endsWith("/attestation")) {
      result = { data: { device_uuid: DEVICE_UUID, attestation_status: "UNSUPPORTED", trust_class: "SOFTWARE_ONLY", real_funds_accessible: false, reason_code: "hardware_attestation_adapter_unavailable" } };
    } else if (path.endsWith("/devices/enroll")) {
      result = { data: {
        device_uuid: body?.device_uuid,
        status: "active",
        hardware_security_level: "software_key; secure-element attestation unavailable",
        real_funds_enabled: false,
      } };
    } else if (path.endsWith("/journals/sync")) {
      result = await (input.syncBehavior?.(body ?? {}) ?? Promise.resolve({ data: {} }));
    } else {
      throw new Error(`Unexpected API path ${path}`);
    }
    return result as T;
  };

  const protocol = createExaPaySignedDeviceProtocol({
    request,
    apiBaseUrl: input.apiBaseUrl ?? "https://api.staging.exaearn.com",
    accountId: "user-42",
    userId: "42",
    identityStore: stores.identityStore,
    journalStore: stores.journalStore,
    crypto: {
      randomBytes: async (length) => {
        randomSequence += 1;
        if (randomSequence === 1) return Uint8Array.from(PRIVATE_KEY.match(/.{2}/g) ?? [], (byte) => Number.parseInt(byte, 16));
        const result = new Uint8Array(length);
        result.fill(randomSequence);
        return result;
      },
      randomUuid: () => DEVICE_UUID,
      digestSha256Hex: async (value) => digest(value),
    },
  });
  return { protocol, requests, stores };
}

export async function runSignedJournalProtocolTests() {
  const canonical = canonicalOfflineJournalPayload({
    deviceUuid: DEVICE_UUID,
    counter: 1,
    previousDigest: "",
    nonce: "A".repeat(43),
    signedAt: 1_800_000_000,
    claims: [{ instrument_id: INSTRUMENT_UUID, claim_id: CLAIM_UUID, consumed_at: 1_800_000_000 }],
  });
  assert(canonical === `{"protocol_version":1,"device_id":"${DEVICE_UUID}","counter":1,"previous_digest":"","nonce":"${"A".repeat(43)}","signed_at":1800000000,"consumption_claims":[{"instrument_id":"${INSTRUMENT_UUID}","claim_id":"${CLAIM_UUID}","consumed_at":1800000000}]}`, "Canonical payload field order/shape must match Laravel's OfflineJournalContract.");

  const signature = base64ToBytes(signDevicePayloadDer(canonical, PRIVATE_KEY));
  assert(p256.verify(signature, new TextEncoder().encode(canonical), PUBLIC_KEY, { prehash: true, format: "der" }), "P-256 DER signature must verify with the enrolled public key.");
  assert(!p256.verify(signature, new TextEncoder().encode(`${canonical} `), PUBLIC_KEY, { prehash: true, format: "der" }), "A modified journal payload must fail signature verification.");

  const retryBodies: Array<Record<string, unknown>> = [];
  let shouldDropResponse = true;
  const retry = makeProtocol({
    syncBehavior: async (body) => {
      retryBodies.push({ ...body });
      if (shouldDropResponse) {
        shouldDropResponse = false;
        throw new Error("simulated response loss after server acceptance");
      }
      const currentCanonical = serverCanonicalPayload(body);
      assert(p256.verify(base64ToBytes(String(body.signature)), new TextEncoder().encode(currentCanonical), PUBLIC_KEY, { prehash: true, format: "der" }), "Submitted signature must verify against the independently reconstructed Laravel JSON payload.");
      return { data: {
        counter: Number(body.counter),
        event_digest: await digest(currentCanonical),
        status: "pending_review",
        redemption_status: "not_redeemed",
        real_funds: false,
      } };
    },
  });
  const claims = [{ instrument_id: INSTRUMENT_UUID.toUpperCase(), claim_id: CLAIM_UUID.toUpperCase(), consumed_at: Math.floor(Date.now() / 1000) }];
  let lostResponse = false;
  try { await retry.protocol.submitJournal(claims); } catch { lostResponse = true; }
  assert(lostResponse && retryBodies.length === 1, "Network uncertainty must retain the staged signed request.");
  const receipt = await retry.protocol.submitJournal(claims);
  assert(Number(retryBodies.length) === 2, "A retry must resubmit the same pending journal.");
  assert(JSON.stringify(retryBodies[0]) === JSON.stringify(retryBodies[1]), "Counter, nonce, signature and claims must remain byte-for-byte stable after response loss.");
  assert(receipt.status === "pending_review" && receipt.realFunds === false && receipt.redemptionStatus === "not_redeemed", "Journal sync must never claim settlement or real funds.");

  const conflict = makeProtocol({
    syncBehavior: async (body) => {
      const currentCanonical = serverCanonicalPayload(body);
      return { data: { counter: body.counter, event_digest: await digest(currentCanonical), status: "conflict", conflict_code: "lineage_mismatch", redemption_status: "not_redeemed", real_funds: false } };
    },
  });
  let quarantined = false;
  try { await conflict.protocol.submitJournal([]); } catch { quarantined = true; }
  assert(quarantined, "Conflicting server evidence must be surfaced as quarantined.");
  let retryBlocked = false;
  try { await conflict.protocol.submitJournal([]); } catch { retryBlocked = true; }
  assert(retryBlocked, "A quarantined journal must not be automatically retried or replaced.");

  const disabled = makeProtocol({ serverEnabled: false });
  let capabilityBlocked = false;
  try { await disabled.protocol.enrollDevice(); } catch { capabilityBlocked = true; }
  assert(capabilityBlocked && disabled.requests.every((item) => !item.path.endsWith("/devices/enroll")), "Enrollment must fail closed when server SIMUSDT capability is disabled.");

  const attestationProtocol = makeProtocol();
  const attestation = await attestationProtocol.protocol.submitHardwareAttestation("android_keystore", { certificates: ["test-certificate"] });
  assert(attestation.status === "UNSUPPORTED" && attestation.trustClass === "SOFTWARE_ONLY", "A signed request cannot promote a device without a configured server root verifier.");
  assert(attestationProtocol.requests.some((item) => item.path.endsWith("/attestation") && item.body?.challenge === "A".repeat(43)), "Hardware evidence must be bound to the fresh server challenge.");

  let productionBlocked = false;
  try { makeProtocol({ apiBaseUrl: "https://api.exaearn.com" }); } catch { productionBlocked = true; }
  assert(productionBlocked, "Signed simulation journals must reject the production API host.");

  let duplicateBlocked = false;
  try {
    await retry.protocol.submitJournal([
      { instrument_id: INSTRUMENT_UUID, claim_id: CLAIM_UUID, consumed_at: Math.floor(Date.now() / 1000) },
      { instrument_id: INSTRUMENT_UUID, claim_id: "d0f8e7a0-0000-4000-8000-000000000001", consumed_at: Math.floor(Date.now() / 1000) },
    ]);
  } catch { duplicateBlocked = true; }
  assert(duplicateBlocked, "Duplicate instrument consumption must be rejected locally.");

  return "Signed SIMUSDT journal protocol: canonical Laravel payload, P-256 signature, stable idempotent retry, quarantine, and fail-closed gates passed.";
}
