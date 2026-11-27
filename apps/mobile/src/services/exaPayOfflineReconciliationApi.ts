import type {
  ReconciliationTransaction,
  RedemptionHistoryItem,
  RedemptionOutcome,
  SyncOutcome,
  SyncProgressStage,
  SyncRedeemSnapshot,
} from "./exaPaySyncRedeemSimulator";

export type AuthenticatedApiRequest = <T = Record<string, unknown>>(
  path: string,
  options?: RequestInit & { headers?: Record<string, string> },
) => Promise<T>;

type JsonRecord = Record<string, unknown>;
type ApiClaim = {
  claim_uuid: string;
  status: string;
  expected_output_uuid?: string;
  expected_amount_minor: number;
  reason_code?: string | null;
  result?: JsonRecord | null;
  simulation_only?: boolean;
  real_funds?: boolean;
  created_at?: string;
  updated_at?: string;
};

export type OfflineTransferEvidence = {
  transfer_uuid: string;
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
  asset: "SIMUSDT";
  transfer_counter: number;
  protocol_version: 1;
  hop_count: number;
  nonce: string;
  signed_at: number;
  parent_digest: string;
  idempotency_key: string;
  sender_signature: string;
  recipient_signature: string;
};

export type OfflineTransferClaimRequest = {
  expected_output_uuid: string;
  expected_amount_minor: number;
  idempotency_key: string;
  transfers: OfflineTransferEvidence[];
};

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const DIGEST_RE = /^[a-f0-9]{64}$/;
const NONCE_RE = /^[A-Za-z0-9_-]{32,128}$/;
const SIGNATURE_RE = /^[A-Za-z0-9+/]+={0,2}$/;

function validateTransferClaim(input: OfflineTransferClaimRequest) {
  if (!UUID_RE.test(input.expected_output_uuid) || !Number.isSafeInteger(input.expected_amount_minor)
    || input.expected_amount_minor < 1 || !/^[A-Za-z0-9._:-]{16,160}$/.test(input.idempotency_key)
    || !Array.isArray(input.transfers) || input.transfers.length < 1 || input.transfers.length > 100) {
    throw new Error("The SIMUSDT transfer claim is incomplete or invalid.");
  }
  for (const transfer of input.transfers) {
    if (transfer.asset !== "SIMUSDT" || transfer.protocol_version !== 1
      || ![transfer.transfer_uuid, transfer.sender_device_uuid, transfer.recipient_device_uuid,
        transfer.input_output_uuid, transfer.recipient_output_uuid].every((value) => UUID_RE.test(value))
      || (transfer.change_output_uuid !== null && !UUID_RE.test(transfer.change_output_uuid))
      || ![transfer.sender_key_version, transfer.recipient_key_version, transfer.transfer_counter,
        transfer.hop_count, transfer.signed_at, transfer.input_amount_minor,
        transfer.transfer_amount_minor, transfer.change_amount_minor].every(Number.isSafeInteger)
      || transfer.sender_key_version < 1 || transfer.recipient_key_version < 1 || transfer.transfer_counter < 1
      || transfer.hop_count < 1 || transfer.signed_at < 1 || transfer.input_amount_minor < 1
      || transfer.transfer_amount_minor < 1 || transfer.change_amount_minor < 0
      || transfer.transfer_amount_minor + transfer.change_amount_minor !== transfer.input_amount_minor
      || (transfer.change_amount_minor === 0) !== (transfer.change_output_uuid === null)
      || !NONCE_RE.test(transfer.nonce) || !DIGEST_RE.test(transfer.parent_digest)
      || !/^[A-Za-z0-9._:-]{16,160}$/.test(transfer.idempotency_key)
      || !SIGNATURE_RE.test(transfer.sender_signature) || !SIGNATURE_RE.test(transfer.recipient_signature)) {
      throw new Error("The transfer chain contains malformed or non-SIMUSDT evidence.");
    }
  }
  const finalTransfer = input.transfers[input.transfers.length - 1];
  if (finalTransfer.recipient_output_uuid.toLowerCase() !== input.expected_output_uuid.toLowerCase()
    || finalTransfer.transfer_amount_minor !== input.expected_amount_minor) {
    throw new Error("The final signed transfer does not match the claimed recipient output and amount.");
  }
}

function record(value: unknown): JsonRecord | null {
  return value !== null && typeof value === "object" && !Array.isArray(value) ? value as JsonRecord : null;
}

function unwrap(value: unknown): unknown {
  const outer = record(value);
  return outer && "data" in outer ? outer.data : value;
}

function list(value: unknown): JsonRecord[] {
  let data = unwrap(value);
  const page = record(data);
  if (page && Array.isArray(page.data)) data = page.data;
  if (!Array.isArray(data)) throw new Error("The offline reconciliation API returned an invalid list response.");
  return data.map((item) => {
    const parsed = record(item);
    if (!parsed) throw new Error("The offline reconciliation API returned a malformed record.");
    return parsed;
  });
}

function claimFrom(value: unknown): ApiClaim {
  const claim = record(unwrap(value));
  if (!claim || typeof claim.claim_uuid !== "string" || !/^[0-9a-f-]{36}$/i.test(claim.claim_uuid)
    || !Number.isSafeInteger(claim.expected_amount_minor) || Number(claim.expected_amount_minor) < 1
    || typeof claim.status !== "string") {
    throw new Error("The offline reconciliation API returned a malformed claim.");
  }
  return claim as unknown as ApiClaim;
}

function normalizedStatus(status: string): ReconciliationTransaction["reconciliationStatus"] {
  if (status === "VERIFIED") return "verified";
  if (status === "REDEEMED") return "redeemed";
  if (["REJECTED", "CONFLICT_DETECTED"].includes(status)) return "failed";
  if (status === "RECOVERY_REQUIRED") return "recovery-required";
  return "pending-verification";
}

function claimTransaction(claim: ApiClaim): ReconciliationTransaction {
  return {
    reference: claim.claim_uuid,
    claimUuid: claim.claim_uuid,
    outputUuid: claim.expected_output_uuid,
    sender: "SIMUSDT offline claim",
    amountMinor: claim.expected_amount_minor,
    localStatus: "offline-committed",
    reconciliationStatus: normalizedStatus(claim.status),
    simulationOnly: claim.simulation_only === true,
    realFunds: claim.real_funds === true,
    reasonCode: typeof claim.reason_code === "string" ? claim.reason_code : undefined,
    asset: typeof record(claim.result)?.asset === "string" ? String(record(claim.result)?.asset) : undefined,
    onlineCredit: record(claim.result)?.online_credit === true,
  };
}

function mapHistory(items: JsonRecord[]): RedemptionHistoryItem[] {
  return items.map((item) => {
    const amount = Number(item.amount_minor);
    if (!Number.isSafeInteger(amount) || amount < 0) throw new Error("The offline redemption API returned an invalid amount.");
    const status = String(item.status ?? "");
    return {
      reference: String(item.redemption_uuid ?? item.claim_uuid ?? item.output_uuid ?? "Offline redemption"),
      status: status === "REDEEMED" ? "Redeemed" : status === "REJECTED" ? "Failed" : "Pending Verification",
      timestamp: String(item.redeemed_at ?? item.created_at ?? "Status unavailable"),
      amountMinor: amount,
    };
  });
}

function emptySnapshot(): SyncRedeemSnapshot {
  return {
    localOfflineValueMinor: 0,
    pendingVerificationMinor: 0,
    redeemableMinor: 0,
    simulatedOnlineBalanceMinor: 0,
    transaction: null,
    history: [],
  };
}

export function createExaPayOfflineReconciliationApi(request: AuthenticatedApiRequest) {
  async function refreshSnapshot(): Promise<SyncRedeemSnapshot> {
    const [claimsResponse, redemptionsResponse] = await Promise.all([
      request("/api/exapay/offline/reconciliation/claims?per_page=100", { method: "GET" }),
      request("/api/exapay/offline/redemptions?per_page=100", { method: "GET" }),
    ]);
    const claims = list(claimsResponse).map(claimFrom);
    const summaryClaim = claims.find((claim) => claim.status === "VERIFIED")
      ?? claims.find((claim) => normalizedStatus(claim.status) !== "redeemed")
      ?? null;
    const transactionClaim = summaryClaim
      ? claimFrom(await request(`/api/exapay/offline/reconciliation/claims/${summaryClaim.claim_uuid}`, { method: "GET" }))
      : null;
    const transaction = transactionClaim ? claimTransaction(transactionClaim) : null;
    const pending = claims
      .filter((claim) => ["SUBMITTED", "VALIDATING", "EVIDENCE_INCOMPLETE", "PENDING", "REDEMPTION_PENDING"].includes(claim.status))
      .reduce((sum, claim) => sum + claim.expected_amount_minor, 0);
    const openClaims = claims
      .filter((claim) => !["REDEEMED", "REJECTED"].includes(claim.status))
      .reduce((sum, claim) => sum + claim.expected_amount_minor, 0);
    const redeemable = transaction
      && transaction.reconciliationStatus === "verified"
      && transaction.simulationOnly
      && !transaction.realFunds
      && transaction.asset === "SIMUSDT"
      && !transaction.onlineCredit
      ? transaction.amountMinor
      : 0;

    return {
      localOfflineValueMinor: openClaims,
      pendingVerificationMinor: pending,
      redeemableMinor: redeemable,
      simulatedOnlineBalanceMinor: 0,
      transaction,
      history: mapHistory(list(redemptionsResponse)),
    };
  }

  return {
    getConnectivityStatus: () => "authenticated-api" as const,
    getSnapshot: emptySnapshot,
    submitRecipientTransferClaim: async (input: OfflineTransferClaimRequest) => {
      validateTransferClaim(input);
      const claim = claimFrom(await request("/api/exapay/offline/reconciliation/claims", {
        method: "POST",
        headers: { "Idempotency-Key": input.idempotency_key },
        body: JSON.stringify({
          expected_output_uuid: input.expected_output_uuid.toLowerCase(),
          expected_amount_minor: input.expected_amount_minor,
          transfers: input.transfers,
        }),
      }));
      if (claim.expected_output_uuid?.toLowerCase() !== input.expected_output_uuid.toLowerCase()
        || claim.expected_amount_minor !== input.expected_amount_minor
        || claim.simulation_only !== true || claim.real_funds !== false) {
        throw new Error("Laravel did not confirm the same SIMUSDT-only recipient claim.");
      }
      return claimTransaction(claim);
    },
    refreshHistory: async () => mapHistory(list(await request("/api/exapay/offline/redemptions?per_page=100", { method: "GET" }))),
    syncOfflinePayments: async ({ onProgress = () => {} }: { onProgress?: (stage: SyncProgressStage) => void } = {}) => {
      onProgress("loading-local-queue");
      onProgress("validating-reference");
      onProgress("checking-simulation");
      const snapshot = await refreshSnapshot();
      const status = snapshot.transaction?.reconciliationStatus;
      const outcome: SyncOutcome = status === "verified" || status === "redeemed"
        ? "verified"
        : status === "recovery-required"
          ? "recovery-required"
          : status === "failed"
            ? "verification-failed"
            : status === "pending-verification" ? "pending" : "verified";
      return { outcome, snapshot };
    },
    redeemVerifiedValue: async (claimUuid: string) => {
      if (!/^[0-9a-f-]{36}$/i.test(claimUuid)) throw new Error("A valid claim reference is required.");
      const claim = claimFrom(await request(`/api/exapay/offline/reconciliation/claims/${claimUuid}`, { method: "GET" }));
      const transaction = claimTransaction(claim);
      if (claim.status !== "VERIFIED" || !transaction.simulationOnly || transaction.realFunds
        || transaction.asset !== "SIMUSDT" || transaction.onlineCredit) {
        const snapshot = await refreshSnapshot();
        return { outcome: "ineligible" as const, amountMinor: 0, simulatedOnlineBalanceMinor: 0, snapshot };
      }

      const redemption = record(unwrap(await request(`/api/exapay/offline/reconciliation/claims/${claimUuid}/redeem`, {
        method: "POST",
        headers: { "Idempotency-Key": `EXAPAY-REDEEM:${claimUuid}` },
        body: JSON.stringify({}),
      })));
      if (!redemption || redemption.status !== "REDEEMED" || redemption.real_funds !== false
        || redemption.asset !== "SIMUSDT" || Number(redemption.amount_minor) !== claim.expected_amount_minor) {
        throw new Error("The backend did not confirm exactly-once SIMUSDT redemption.");
      }
      return {
        outcome: "redeemed-simulation" as RedemptionOutcome,
        amountMinor: claim.expected_amount_minor,
        simulatedOnlineBalanceMinor: 0,
        snapshot: await refreshSnapshot(),
      };
    },
    refreshSnapshot,
  };
}
