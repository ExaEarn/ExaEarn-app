export type ReconciliationStatus = "pending-verification" | "verified" | "failed" | "recovery-required" | "redeemed";
export type SyncOutcome = "verified" | "pending" | "verification-failed" | "network-error" | "recovery-required";
export type RedemptionOutcome = "redeemed-simulation" | "failed" | "recovery-required" | "ineligible";

export type ReconciliationTransaction = {
  reference: string;
  claimUuid?: string;
  outputUuid?: string;
  sender: string;
  amountMinor: number;
  localStatus: "offline-committed";
  reconciliationStatus: ReconciliationStatus;
  simulationOnly?: boolean;
  realFunds?: boolean;
  reasonCode?: string;
  asset?: string;
  onlineCredit?: boolean;
};

export type RedemptionHistoryItem = {
  reference: string;
  status: "Redeemed" | "Failed" | "Pending Verification";
  timestamp: string;
  amountMinor: number;
};

export type SyncRedeemSnapshot = {
  localOfflineValueMinor: number;
  pendingVerificationMinor: number;
  redeemableMinor: number;
  simulatedOnlineBalanceMinor: number;
  transaction: ReconciliationTransaction | null;
  history: RedemptionHistoryItem[];
};

export type SyncRedeemSimulatorOptions = {
  syncOutcomes?: SyncOutcome[];
  redemptionOutcomes?: Exclude<RedemptionOutcome, "ineligible">[];
  latencyMs?: number;
};

export type SyncProgressStage = "loading-local-queue" | "validating-reference" | "checking-simulation";

const INITIAL_LOCAL_VALUE_MINOR = 3_000;
const INITIAL_TRANSACTION: ReconciliationTransaction = {
  reference: "N-4C2D8F9A7E01",
  sender: "Kendrick’s Device",
  amountMinor: 3_000,
  localStatus: "offline-committed",
  reconciliationStatus: "pending-verification",
};

const INITIAL_HISTORY: RedemptionHistoryItem[] = [
  { reference: "N-9A1B3C7D2E55", status: "Redeemed", timestamp: "Today, 09:21", amountMinor: 3_000 },
  { reference: "N-7F0E4D8C1B90", status: "Failed", timestamp: "Today, 08:47", amountMinor: 1_000 },
  { reference: "N-1D6C9A5E3F22", status: "Redeemed", timestamp: "Yesterday, 17:03", amountMinor: 2_500 },
];

const wait = (milliseconds: number) => new Promise<void>((resolve) => setTimeout(resolve, milliseconds));

export function formatSimulatedSyncAmount(amountMinor: number) {
  return `${Math.floor(amountMinor / 100)}.${String(Math.abs(amountMinor % 100)).padStart(2, "0")}`;
}

export function createExaPaySyncRedeemSimulator(options: SyncRedeemSimulatorOptions = {}) {
  let localOfflineValueMinor = INITIAL_LOCAL_VALUE_MINOR;
  let pendingVerificationMinor = INITIAL_LOCAL_VALUE_MINOR;
  let redeemableMinor = 0;
  let simulatedOnlineBalanceMinor = 0;
  let transaction: ReconciliationTransaction | null = { ...INITIAL_TRANSACTION };
  const history = INITIAL_HISTORY.map((item) => ({ ...item }));
  const syncOutcomes = [...(options.syncOutcomes ?? ["verified"])];
  const redemptionOutcomes = [...(options.redemptionOutcomes ?? ["redeemed-simulation"])];
  const latencyMs = options.latencyMs ?? 300;
  let syncInFlight: Promise<{ outcome: SyncOutcome; snapshot: SyncRedeemSnapshot }> | null = null;
  const redemptionOperations = new Map<string, Promise<{
    outcome: RedemptionOutcome;
    amountMinor: number;
    simulatedOnlineBalanceMinor: number;
    snapshot: SyncRedeemSnapshot;
  }>>();
  const redemptionResults = new Map<string, {
    outcome: RedemptionOutcome;
    amountMinor: number;
    simulatedOnlineBalanceMinor: number;
    snapshot: SyncRedeemSnapshot;
  }>();

  function getSnapshot(): SyncRedeemSnapshot {
    return {
      localOfflineValueMinor,
      pendingVerificationMinor,
      redeemableMinor,
      simulatedOnlineBalanceMinor,
      transaction: transaction ? { ...transaction } : null,
      history: history.map((item) => ({ ...item })),
    };
  }

  return {
    getSnapshot,

    getConnectivityStatus() {
      return "simulation-only" as const;
    },

    refreshHistory() {
      return wait(Math.min(latencyMs, 120)).then(() => history.map((item) => ({ ...item })));
    },

    async syncOfflinePayments({ onProgress = () => {} }: { onProgress?: (stage: SyncProgressStage) => void } = {}) {
      if (syncInFlight) return syncInFlight;
      const currentOperation = (async () => {
        onProgress("loading-local-queue");
        await wait(Math.max(1, Math.floor(latencyMs / 3)));
        onProgress("validating-reference");
        await wait(Math.max(1, Math.floor(latencyMs / 3)));
        onProgress("checking-simulation");
        await wait(Math.max(1, Math.ceil(latencyMs / 3)));
        if (!transaction || transaction.reconciliationStatus === "redeemed") {
          return { outcome: "verified" as const, snapshot: getSnapshot() };
        }
        if (transaction.reconciliationStatus === "verified") {
          return { outcome: "verified" as const, snapshot: getSnapshot() };
        }
        if (transaction.reconciliationStatus === "recovery-required") {
          return { outcome: "recovery-required" as const, snapshot: getSnapshot() };
        }

        const outcome = syncOutcomes.shift() ?? "verified";
        if (outcome === "verified") {
          transaction = { ...transaction, reconciliationStatus: "verified" };
          pendingVerificationMinor = 0;
          redeemableMinor = transaction.amountMinor;
        } else if (outcome === "recovery-required") {
          transaction = { ...transaction, reconciliationStatus: "recovery-required" };
        } else if (outcome === "verification-failed") {
          transaction = { ...transaction, reconciliationStatus: "failed" };
        }
        return { outcome, snapshot: getSnapshot() };
      })();
      syncInFlight = currentOperation;
      try {
        return await currentOperation;
      } finally {
        syncInFlight = null;
      }
    },

    async redeemVerifiedValue(reference: string) {
      const currentOperation = redemptionOperations.get(reference);
      if (currentOperation) return currentOperation;
      const previousResult = redemptionResults.get(reference);
      if (previousResult) return previousResult;

      if (
        !transaction
        || transaction.reference !== reference
        || transaction.reconciliationStatus !== "verified"
        || redeemableMinor <= 0
      ) {
        return {
          outcome: "ineligible" as const,
          amountMinor: 0,
          simulatedOnlineBalanceMinor,
          snapshot: getSnapshot(),
        };
      }

      const amountMinor = redeemableMinor;
      const operation = (async () => {
        await wait(latencyMs);
        const outcome = redemptionOutcomes.shift() ?? "redeemed-simulation";
        if (outcome === "redeemed-simulation") {
          simulatedOnlineBalanceMinor += amountMinor;
          localOfflineValueMinor = Math.max(0, localOfflineValueMinor - amountMinor);
          redeemableMinor = Math.max(0, redeemableMinor - amountMinor);
          pendingVerificationMinor = 0;
          transaction = { ...transaction!, reconciliationStatus: "redeemed" };
          history.unshift({
            reference,
            status: "Redeemed",
            timestamp: "Just now",
            amountMinor,
          });
        } else if (outcome === "failed") {
          history.unshift({
            reference,
            status: "Failed",
            timestamp: "Just now",
            amountMinor,
          });
        } else if (outcome === "recovery-required") {
          transaction = { ...transaction!, reconciliationStatus: "recovery-required" };
        }

        const result = {
          outcome,
          amountMinor,
          simulatedOnlineBalanceMinor,
          snapshot: getSnapshot(),
        };
        if (outcome !== "failed") redemptionResults.set(reference, result);
        return result;
      })();

      redemptionOperations.set(reference, operation);
      try {
        return await operation;
      } finally {
        redemptionOperations.delete(reference);
      }
    },
  };
}
