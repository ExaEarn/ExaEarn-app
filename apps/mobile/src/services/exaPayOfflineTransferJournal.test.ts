import { p256 } from "@noble/curves/nist.js";
import { createExaPayOfflineReconciliationApi, type AuthenticatedApiRequest } from "./exaPayOfflineReconciliationApi";
import { createSignedOfflineQrRequest, verifySignedOfflineQrRequest, type OfflineQrIdentity, type SignedOfflineDeviceCertificate } from "./exaPayOfflineQrProtocol";
import { signDevicePayloadDer } from "./exaPayDeviceCrypto";
import { createExaPayOfflineTransferJournal, type SignedIssuedInstrument } from "./exaPayOfflineTransferJournal";
import type { AtomicDeviceJournalStore } from "./exaPaySignedJournalProtocol";

function assert(value: unknown, message: string): asserts value { if (!value) throw new Error(message); }
function base64(bytes: Uint8Array) { let result = ""; for (const byte of bytes) result += String.fromCharCode(byte); return btoa(result); }
function publicPem(privateHex: string) {
  const raw = p256.getPublicKey(Uint8Array.from(privateHex.match(/.{2}/g) ?? [], (value) => Number.parseInt(value, 16)), false);
  const prefix = Uint8Array.from("3059301306072a8648ce3d020106082a8648ce3d030107034200".match(/.{2}/g) ?? [], (item) => Number.parseInt(item, 16));
  const der = new Uint8Array(prefix.length + raw.length); der.set(prefix); der.set(raw, prefix.length);
  return `-----BEGIN PUBLIC KEY-----\n${(base64(der).match(/.{1,64}/g) ?? []).join("\n")}\n-----END PUBLIC KEY-----`;
}
async function digest(value: string) {
  const bytes = await globalThis.crypto.subtle.digest("SHA-256", new TextEncoder().encode(value));
  return Array.from(new Uint8Array(bytes), (byte) => byte.toString(16).padStart(2, "0")).join("");
}
function certificate(deviceUuid: string, privateHex: string, issuerPrivate: string, now: number): SignedOfflineDeviceCertificate {
  const canonical_payload = JSON.stringify({ certificate_version: 1, device_uuid: deviceUuid, public_key: publicPem(privateHex),
    key_version: 1, protocol_version: 1, trust_class: "SOFTWARE_ONLY", trust_epoch: 1, asset: "SIMUSDT",
    simulation_only: true, issued_at: now - 1, expires_at: now + 86_400 });
  return { canonical_payload, signature: signDevicePayloadDer(canonical_payload, issuerPrivate),
    issuer_public_key: publicPem(issuerPrivate), signature_algorithm: "SHA256" };
}
function memoryStore(): AtomicDeviceJournalStore {
  const values = new Map<string, string>();
  return {
    async getItemAsync(key) { return values.get(key) ?? null; },
    async setItemAsync(key, value) { values.set(key, value); },
    async updateItemAtomically<T>(key: string, update: (current: string | null) => { value: string; result: T }) {
      const changed = update(values.get(key) ?? null); values.set(key, changed.value); return changed.result as T;
    },
  };
}

export async function runOfflineTransferJournalTests() {
  const now = Math.floor(Date.now() / 1000);
  const issuerPrivate = "0000000000000000000000000000000000000000000000000000000000000001";
  const senderPrivate = "0000000000000000000000000000000000000000000000000000000000000002";
  const middlePrivate = "0000000000000000000000000000000000000000000000000000000000000003";
  const finalPrivate = "0000000000000000000000000000000000000000000000000000000000000004";
  const issuer = publicPem(issuerPrivate);
  const senderId = "0f8fad5b-d9cb-469f-a165-70867728950e";
  const middleId = "7d444840-9dc0-41d1-b245-5ffdce74fad2";
  const finalId = "a8098c1a-f86e-4da0-bd1a-bf1a9ab0e9d0";
  const senderCertificate = certificate(senderId, senderPrivate, issuerPrivate, now);
  const middleCertificate = certificate(middleId, middlePrivate, issuerPrivate, now);
  const finalCertificate = certificate(finalId, finalPrivate, issuerPrivate, now);
  let uuidCounter = 0;
  const crypto = {
    randomBytes: async (length: number) => Uint8Array.from({ length }, (_, index) => (index + uuidCounter++) % 251),
    randomUuid: () => `00000000-0000-4000-8000-${String(++uuidCounter).padStart(12, "0")}`,
    digestSha256Hex: digest,
  };
  const calls: Array<{ path: string; body: Record<string, unknown> }> = [];
  const request = async <T,>(path: string, options?: RequestInit): Promise<T> => {
    const body = options?.body ? JSON.parse(String(options.body)) as Record<string, unknown> : {};
    calls.push({ path, body });
    const reference = decodeURIComponent(path.split("/").at(-2) ?? "");
    return ({ data: { reference, delivery_status: "acknowledged", real_funds: false } } as unknown) as T;
  };
  const claimCalls: Array<Record<string, unknown>> = [];
  const claimRequest: AuthenticatedApiRequest = async <T,>(path: string, options?: RequestInit & { headers?: Record<string, string> }) => {
    const body = JSON.parse(String(options?.body)) as Record<string, unknown>; claimCalls.push({ path, ...body });
    return ({ data: { claim_uuid: "3a8b48d1-f746-41b9-b307-efb0bf7b22b1", status: "EVIDENCE_INCOMPLETE",
      expected_output_uuid: body.expected_output_uuid, expected_amount_minor: body.expected_amount_minor,
      simulation_only: true, real_funds: false } } as unknown) as T;
  };
  const reconciliation = createExaPayOfflineReconciliationApi(claimRequest);
  const makeClient = (accountId: string, deviceUuid: string, privateHex: string) => createExaPayOfflineTransferJournal({
    accountId, deviceUuid, store: memoryStore(), request, signDevicePayload: (payload) => signDevicePayloadDer(payload, privateHex),
    crypto, submitClaim: (claim) => reconciliation.submitRecipientTransferClaim(claim),
  });
  const sender = makeClient("acct-a", senderId, senderPrivate);
  const middle = makeClient("acct-b", middleId, middlePrivate);
  const final = makeClient("acct-c", finalId, finalPrivate);

  const instrumentId = "b6f511cb-d3b7-4b61-9f44-a2278e7d1529";
  const issuedPayload = { protocol_version: 1, payment_mode: "OFFLINE_STORED_VALUE", environment: "SIMULATION",
    instrument_id: instrumentId, reference: "EXAO-TEST-ISSUANCE", owner_user_id: 1001, device_uuid: senderId,
    device_key_version: 1, device_trust_epoch: 1, asset: "SIMUSDT", amount_minor: 4_000, minor_units_per_token: 100,
    device_counter: 1, nonce: "a".repeat(48), issuer_key_id: (await digest(issuer)).slice(0, 32), issued_at: now, expires_at: now + 3_600 };
  const issued: SignedIssuedInstrument = { instrument_id: instrumentId, reference: "EXAO-TEST-ISSUANCE", asset: "SIMUSDT",
    amount_minor: 4_000, payload: issuedPayload, issuer_signature: signDevicePayloadDer(JSON.stringify(issuedPayload), issuerPrivate),
    issuer_public_key: issuer, status: "pending_acknowledgment", delivery_status: "pending", real_funds: false };
  await sender.saveInstrumentAfterAcknowledgment({ issued, certificate: senderCertificate, trustedIssuerPublicKey: issuer, privateKeyHex: senderPrivate });
  const rootOutput = (await sender.snapshot()).outputs.find((item) => item.outputUuid === instrumentId);
  const expectedRootLineage = await digest(`exaearn-offline-root:v1|${instrumentId}|${await digest(JSON.stringify(issuedPayload))}`);
  assert(rootOutput?.lineageDigest === expectedRootLineage && rootOutput.amountMinor === 4_000,
    "mobile root-output lineage hash and amount match Laravel's issuer-root formula");

  async function requestQr(deviceId: string, privateHex: string, cert: SignedOfflineDeviceCertificate, amount: number, createdAt = now) {
    const signed = await createSignedOfflineQrRequest({ deviceUuid: deviceId, keyVersion: 1, privateKeyHex: privateHex,
      certificate: cert, issuerPublicKeyPin: issuer, amountMinor: amount, nowSeconds: createdAt, crypto });
    return verifySignedOfflineQrRequest(signed, issuer, { digestSha256Hex: digest }, createdAt);
  }
  const qr1: OfflineQrIdentity = await requestQr(middleId, middlePrivate, middleCertificate, 3_000);
  const prepared1 = await sender.prepareTransfer({ outputUuid: instrumentId, recipient: qr1, senderCertificate, privateKeyHex: senderPrivate,
    trustedIssuerPublicKey: issuer, transferUuid: "a34c8a0a-455f-4d6e-b915-5a27cbe3d092", recipientOutputUuid: "ac7f852b-43ab-4e84-94d9-93f2de99c128",
    changeOutputUuid: "f76f8d31-80bb-411c-9c4a-0723cdac39af", nonce: "0123456789abcdefghijklmnopqrstuv", idempotencyKey: "EXAPAY-TRANSFER-CHAIN-A-0001", nowSeconds: now });
  const pendingAfterRestart = await sender.getPendingTransfer(JSON.parse(prepared1.transfer.canonical_payload).transfer_uuid);
  const idempotentPrepare = await sender.prepareTransfer({ outputUuid: instrumentId, recipient: qr1, senderCertificate, privateKeyHex: senderPrivate,
    trustedIssuerPublicKey: issuer, transferUuid: "11111111-1111-4111-8111-111111111111", recipientOutputUuid: "11111111-2222-4222-8222-111111111111",
    changeOutputUuid: "11111111-3333-4333-8333-111111111111", nonce: "abcdefghijklmnopqrstuv0123456789", idempotencyKey: "EXAPAY-TRANSFER-CHAIN-A-0001", nowSeconds: now + 1 });
  assert(pendingAfterRestart?.transfer.canonical_payload === prepared1.transfer.canonical_payload
    && pendingAfterRestart?.recipient_request?.signedRequest === qr1.signedRequest
    && idempotentPrepare.transfer.canonical_payload === prepared1.transfer.canonical_payload,
    "interrupted prepare recovers the original signed recipient proof and retries retain the original signed intent");
  const ack1 = await middle.acceptTransfer({ envelope: prepared1, recipientRequest: qr1, recipientPrivateKeyHex: middlePrivate,
    recipientCertificate: middleCertificate, trustedIssuerPublicKey: issuer, nowSeconds: now + 1 });
  const recipientPendingAfterRestart = await middle.getPendingTransfer(JSON.parse(prepared1.transfer.canonical_payload).transfer_uuid);
  assert(recipientPendingAfterRestart?.recipient_request?.signedRequest === qr1.signedRequest,
    "recipient acknowledgment recovery retains the original signed request and does not create a replacement output");
  const commit1 = JSON.parse(await sender.commitTransfer({ transferUuid: prepared1.transfer.canonical_payload
    ? JSON.parse(prepared1.transfer.canonical_payload).transfer_uuid : "", acknowledgment: ack1.acknowledgment,
    recipientRequest: qr1, trustedIssuerPublicKey: issuer, privateKeyHex: senderPrivate, nowSeconds: now + 600 }));
  const commitReplay = await sender.commitTransfer({ transferUuid: JSON.parse(prepared1.transfer.canonical_payload).transfer_uuid,
    acknowledgment: ack1.acknowledgment, recipientRequest: qr1, trustedIssuerPublicKey: issuer,
    privateKeyHex: senderPrivate, nowSeconds: now + 1_000 });
  assert(commitReplay === JSON.stringify(commit1), "a delayed identical commit retry returns the same durable commitment and receipt");
  await middle.finalizeReceivedTransfer({ commit: commit1, recipientRequest: qr1, trustedIssuerPublicKey: issuer, nowSeconds: now + 601 });
  const middleOutput = (await middle.snapshot()).outputs.find((item) => item.outputUuid === "ac7f852b-43ab-4e84-94d9-93f2de99c128");
  assert(middleOutput?.lineageDigest === await digest(`${await digest(prepared1.transfer.canonical_payload)}|recipient|ac7f852b-43ab-4e84-94d9-93f2de99c128`)
    && middleOutput.amountMinor === 3_000, "recipient output digest matches the Laravel canonical transfer lineage formula");

  const qr2: OfflineQrIdentity = await requestQr(finalId, finalPrivate, finalCertificate, 1_000, now + 604);
  const prepared2 = await middle.prepareTransfer({ outputUuid: "ac7f852b-43ab-4e84-94d9-93f2de99c128", recipient: qr2,
    senderCertificate: middleCertificate, privateKeyHex: middlePrivate, trustedIssuerPublicKey: issuer,
    transferUuid: "6e3f8b7a-b29c-42a2-8b6d-09bb4b9b0101", recipientOutputUuid: "2c0baf36-6cd7-45b3-991e-e351bb982c0f",
    changeOutputUuid: "3f1a32d6-36c9-4ed2-a73a-98a07e137f83", nonce: "abcdefghijklmnopqrstuv0123456789", idempotencyKey: "EXAPAY-TRANSFER-CHAIN-B-0002", nowSeconds: now + 604 });
  assert(prepared2.lineage_evidence.length === 1, "second-hop package carries real committed first-hop evidence");
  const ack2 = await final.acceptTransfer({ envelope: prepared2, recipientRequest: qr2, recipientPrivateKeyHex: finalPrivate,
    recipientCertificate: finalCertificate, trustedIssuerPublicKey: issuer, nowSeconds: now + 605 });
  const commit2 = JSON.parse(await middle.commitTransfer({ transferUuid: JSON.parse(prepared2.transfer.canonical_payload).transfer_uuid,
    acknowledgment: ack2.acknowledgment, recipientRequest: qr2, trustedIssuerPublicKey: issuer, privateKeyHex: middlePrivate, nowSeconds: now + 606 }));
  await final.finalizeReceivedTransfer({ commit: commit2, recipientRequest: qr2, trustedIssuerPublicKey: issuer, nowSeconds: now + 607 });
  const finalOutput = (await final.snapshot()).outputs.find((item) => item.outputUuid === "2c0baf36-6cd7-45b3-991e-e351bb982c0f");
  assert(finalOutput?.lineageDigest === await digest(`${await digest(prepared2.transfer.canonical_payload)}|recipient|2c0baf36-6cd7-45b3-991e-e351bb982c0f`)
    && finalOutput.amountMinor === 1_000 && finalOutput.lineageEvidence.length === 2,
    "final recipient has the exact 10 SIMUSDT two-hop output and backend-compatible lineage digest");
  const result = await final.submitOutputEvidence("2c0baf36-6cd7-45b3-991e-e351bb982c0f", "EXAPAY-CLAIM-CHAIN-C-0003");
  assert(result && claimCalls.length === 1 && claimCalls[0].path === "/api/exapay/offline/reconciliation/claims", "committed output evidence reaches the existing authenticated claim client");
  const submitted = claimCalls[0].transfers as Array<Record<string, unknown>>;
  assert(submitted.length === 2 && submitted[0]?.asset === "SIMUSDT" && submitted[1]?.asset === "SIMUSDT"
    && submitted[0]?.recipient_signature && submitted[1]?.recipient_signature, "actual non-empty signed two-hop evidence is submitted for C's 10 SIMUSDT output");
  assert(claimCalls[0].expected_amount_minor === 1_000 && claimCalls[0].expected_output_uuid === "2c0baf36-6cd7-45b3-991e-e351bb982c0f",
    "reconciliation binds the exact recipient output and integer amount");

  const middleSnapshot = await middle.snapshot();
  const senderSnapshot = await sender.snapshot();
  const spendableMinor = [senderSnapshot, middleSnapshot, await final.snapshot()].flatMap((item) => item.outputs)
    .filter((item) => item.status === "available").reduce((sum, item) => sum + item.amountMinor, 0);
  assert(spendableMinor === 4_000 && middleSnapshot.outputs.find((item) => item.outputUuid === "3f1a32d6-36c9-4ed2-a73a-98a07e137f83")?.amountMinor === 2_000,
    "40 simUSDT conserves across A=10, B=20 and C=10 after offline change and second hop");

  const replayAck = await middle.acceptTransfer({ envelope: prepared1, recipientRequest: qr1, recipientPrivateKeyHex: middlePrivate,
    recipientCertificate: middleCertificate, trustedIssuerPublicKey: issuer, nowSeconds: now + 1_000 });
  assert(replayAck.acknowledgment.recipient_signature === ack1.acknowledgment.recipient_signature,
    "replayed transfer retransmits its original durable acknowledgment without creating another output");
  assert(senderSnapshot.outputs.find((item) => item.outputUuid === instrumentId)?.status === "spent"
    && senderSnapshot.outputs.find((item) => item.outputUuid === "f76f8d31-80bb-411c-9c4a-0723cdac39af")?.amountMinor === 1_000,
    "sender journal atomically commits the 30 SIMUSDT spend and 10 SIMUSDT change");
  return "Encrypted-journal transfer flow passed: issuer-signed issuance acknowledgment, A→B→C signed commitments, durable recipient acks, atomic change/spend, idempotent replay recovery, and populated authenticated Laravel claim request.";
}
