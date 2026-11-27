import { createExaPayOfflineReconciliationApi, type AuthenticatedApiRequest } from "./exaPayOfflineReconciliationApi";

function assert(condition: unknown, message: string): asserts condition {
  if (!condition) throw new Error(message);
}

const claimId = "b69ba427-b716-4ecb-9ad8-6e467e3c7da2";
const outputId = "ac7f852b-43ab-4e84-94d9-93f2de99c128";

function transferEvidence() {
  return {
    transfer_uuid: "1e9a224c-5207-4e69-a071-492dbb7e1f0e",
    sender_device_uuid: "d33edb15-cc7c-4989-b34e-5a177b729927",
    recipient_device_uuid: "0135b18c-87c6-4fa4-b1f0-b6e101855c49",
    sender_key_version: 1,
    recipient_key_version: 1,
    input_output_uuid: "b6f511cb-d3b7-4b61-9f44-a2278e7d1529",
    recipient_output_uuid: outputId,
    change_output_uuid: "f76f8d31-80bb-411c-9c4a-0723cdac39af",
    input_amount_minor: 1500,
    transfer_amount_minor: 1250,
    change_amount_minor: 250,
    asset: "SIMUSDT" as const,
    transfer_counter: 1,
    protocol_version: 1 as const,
    hop_count: 1,
    nonce: "0123456789abcdefghijklmnopqrstuv",
    signed_at: 1795737600,
    parent_digest: "a".repeat(64),
    idempotency_key: "EXAPAY-TRANSFER-ONE-0001",
    sender_signature: "c2VuZGVyLXNpZ25hdHVyZQ==",
    recipient_signature: "cmVjaXBpZW50LXNpZ25hdHVyZQ==",
  };
}

function claim(status: string, options: { realFunds?: boolean; asset?: string } = {}) {
  return {
    claim_uuid: claimId,
    status,
    expected_output_uuid: "ac7f852b-43ab-4e84-94d9-93f2de99c128",
    expected_amount_minor: 1_250,
    result: status === "VERIFIED" || status === "REDEEMED"
      ? { asset: options.asset ?? "SIMUSDT", online_credit: false }
      : null,
    simulation_only: true,
    real_funds: options.realFunds ?? false,
    reason_code: null,
  };
}

export async function runOfflineReconciliationApiTests() {
  const calls: Array<{ path: string; options?: RequestInit & { headers?: Record<string, string> } }> = [];
  let currentClaim = claim("VERIFIED");
  let redemption: Record<string, unknown> | null = null;
  const request: AuthenticatedApiRequest = async <T,>(path: string, options?: RequestInit & { headers?: Record<string, string> }) => {
    calls.push({ path, options });
    if (path.includes("/redemptions?")) return ({ data: { data: redemption ? [redemption] : [] } } as unknown) as T;
    if (path.endsWith("/reconciliation/claims?per_page=100")) return ({ data: { data: [currentClaim] } } as unknown) as T;
    if (path.endsWith(`/reconciliation/claims/${claimId}`)) return ({ data: currentClaim } as unknown) as T;
    if (path.endsWith(`/reconciliation/claims/${claimId}/redeem`)) {
      assert(options?.method === "POST", "redemption uses authenticated POST");
      assert(options?.headers?.["Idempotency-Key"] === `EXAPAY-REDEEM:${claimId}`, "redemption key is stable across retries");
      currentClaim = claim("REDEEMED");
      redemption = { redemption_uuid: "ac2e0ced-0008-4b32-a064-72007bf45487", claim_uuid: claimId, amount_minor: 1_250, status: "REDEEMED", real_funds: false, redeemed_at: "2026-11-26T00:00:00Z" };
      return ({ data: { redemption_uuid: redemption.redemption_uuid, claim_uuid: claimId, amount_minor: 1_250, asset: "SIMUSDT", status: "REDEEMED", real_funds: false } } as unknown) as T;
    }
    throw new Error("unexpected test endpoint");
  };
  const api = createExaPayOfflineReconciliationApi(request);
  const before = await api.refreshSnapshot();
  assert(before.transaction?.claimUuid === claimId && before.redeemableMinor === 1_250, "verified backend SIMUSDT claim becomes redeemable after detail verification");

  const redeemed = await api.redeemVerifiedValue(claimId);
  assert(redeemed.outcome === "redeemed-simulation" && redeemed.snapshot.history[0]?.status === "Redeemed", "redemption reflects the authoritative server response and refreshed history");
  assert(calls.some((call) => call.path.endsWith(`/reconciliation/claims/${claimId}/redeem`)), "client invokes the existing Laravel redemption endpoint");

  const transfer = transferEvidence();
  const claimCalls: Array<{ path: string; options?: RequestInit & { headers?: Record<string, string> } }> = [];
  const claimRequest: AuthenticatedApiRequest = async <T,>(path: string, options?: RequestInit & { headers?: Record<string, string> }) => {
    if (path === "/api/exapay/offline/reconciliation/claims") {
      claimCalls.push({ path, options });
      return ({ data: {
        claim_uuid: claimId,
        status: "EVIDENCE_INCOMPLETE",
        expected_output_uuid: outputId,
        expected_amount_minor: 1_250,
        simulation_only: true,
        real_funds: false,
      } } as unknown) as T;
    }
    throw new Error("unexpected claim endpoint");
  };
  const claimApi = createExaPayOfflineReconciliationApi(claimRequest);
  const submittedClaim = await claimApi.submitRecipientTransferClaim({
    expected_output_uuid: outputId,
    expected_amount_minor: 1_250,
    idempotency_key: "EXAPAY-CLAIM-RECIPIENT-0001",
    transfers: [transfer],
  });
  const claimCall = claimCalls[0];
  assert(submittedClaim.claimUuid === claimId && submittedClaim.reconciliationStatus === "pending-verification", "recipient transfer evidence remains pending until Laravel verifies the signatures and lineage");
  assert(claimCall?.path === "/api/exapay/offline/reconciliation/claims", "mobile submits chain evidence through the authenticated Laravel claim route");
  assert(claimCall?.options?.headers?.["Idempotency-Key"] === "EXAPAY-CLAIM-RECIPIENT-0001", "recipient evidence submission has a stable idempotency key");
  const claimBody = JSON.parse(String(claimCall?.options?.body)) as { transfers: Array<Record<string, unknown>> };
  assert(claimBody.transfers[0]?.asset === "SIMUSDT" && claimBody.transfers[0]?.recipient_signature === transfer.recipient_signature, "the signed SIMUSDT evidence is submitted byte-for-byte to the backend verifier");

  let unsafeRequestSent = false;
  const unsafeTransferApi = createExaPayOfflineReconciliationApi(async <T,>() => {
    unsafeRequestSent = true;
    throw new Error("invalid evidence must not reach the backend");
  });
  let rejectedUnsafeTransfer = false;
  try {
    await unsafeTransferApi.submitRecipientTransferClaim({
      expected_output_uuid: outputId,
      expected_amount_minor: 1_250,
      idempotency_key: "EXAPAY-CLAIM-RECIPIENT-0002",
      transfers: [{ ...transfer, asset: "USDT" as unknown as "SIMUSDT" }],
    });
  } catch { rejectedUnsafeTransfer = true; }
  assert(rejectedUnsafeTransfer && !unsafeRequestSent, "non-SIMUSDT evidence is rejected before network submission");

  let redemptionPosted = false;
  const unsafeRequest: AuthenticatedApiRequest = async <T,>(path: string, options?: RequestInit & { headers?: Record<string, string> }) => {
    if (path.endsWith("/reconciliation/claims?per_page=100")) return ({ data: { data: [claim("VERIFIED")] } } as unknown) as T;
    if (path.endsWith(`/reconciliation/claims/${claimId}`)) return ({ data: claim("VERIFIED", { realFunds: true }) } as unknown) as T;
    if (path.includes("/redemptions?")) return ({ data: { data: [] } } as unknown) as T;
    if (path.endsWith("/redeem")) redemptionPosted = options?.method === "POST";
    throw new Error("unsafe claim must not redeem");
  };
  const unsafeResult = await createExaPayOfflineReconciliationApi(unsafeRequest).redeemVerifiedValue(claimId);
  assert(unsafeResult.outcome === "ineligible" && !redemptionPosted, "real-funds or unverified claims are never posted for redemption");

  return "Authenticated ExaPay reconciliation API checks passed (account claims, verified SIMUSDT gating, stable idempotency, authoritative redemption, real-funds rejection).";
}
