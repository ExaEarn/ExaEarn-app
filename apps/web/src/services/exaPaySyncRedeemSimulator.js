const LOCAL_OFFLINE_VALUE_MINOR = 3_000;

const initialTransaction = {
  reference: "N-4C2D8F9A7E01",
  sender: "Kendrick’s Device",
  amountMinor: 3_000,
  localStatus: "offline-committed",
  reconciliationStatus: "pending-verification",
};

const initialHistory = [
  { reference: "N-9A1B3C7D2E55", status: "Redeemed", timestamp: "Today, 09:21", amountMinor: 3_000 },
  { reference: "N-7F0E4D8C1B90", status: "Failed", timestamp: "Today, 08:47", amountMinor: 1_000 },
  { reference: "N-1D6C9A5E3F22", status: "Redeemed", timestamp: "Yesterday, 17:03", amountMinor: 2_500 },
];

const wait = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

export function formatSimulatedSyncAmount(amountMinor) {
  return `${Math.floor(amountMinor / 100)}.${String(Math.abs(amountMinor % 100)).padStart(2, "0")}`;
}

export function createExaPaySyncRedeemSimulator({
  syncOutcomes = ["verified"],
  redemptionOutcomes = ["redeemed-simulation"],
  latencyMs = 320,
} = {}) {
  let localOfflineValueMinor = LOCAL_OFFLINE_VALUE_MINOR;
  let pendingVerificationMinor = LOCAL_OFFLINE_VALUE_MINOR;
  let redeemableMinor = 0;
  let simulatedOnlineBalanceMinor = 0;
  let transaction = { ...initialTransaction };
  const history = initialHistory.map((item) => ({ ...item }));
  const syncQueue = [...syncOutcomes];
  const redemptionQueue = [...redemptionOutcomes];
  let syncInFlight = null;
  const redemptionInFlight = new Map();
  const redemptionResults = new Map();

  function getSnapshot() {
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
      return "simulation-only";
    },

    async refreshHistory() {
      await wait(Math.min(latencyMs, 120));
      return history.map((item) => ({ ...item }));
    },

    async syncOfflinePayments({ onProgress = () => {} } = {}) {
      if (syncInFlight) return syncInFlight;

      const operation = (async () => {
        onProgress("loading-local-queue");
        await wait(Math.max(1, Math.floor(latencyMs / 3)));
        onProgress("validating-reference");
        await wait(Math.max(1, Math.floor(latencyMs / 3)));
        onProgress("checking-simulation");
        await wait(Math.max(1, Math.ceil(latencyMs / 3)));

        if (!transaction || transaction.reconciliationStatus === "redeemed" || transaction.reconciliationStatus === "verified") {
          return { outcome: "verified", snapshot: getSnapshot() };
        }
        if (transaction.reconciliationStatus === "recovery-required") {
          return { outcome: "recovery-required", snapshot: getSnapshot() };
        }

        const outcome = syncQueue.shift() ?? "verified";
        if (outcome === "verified") {
          transaction = { ...transaction, reconciliationStatus: "verified" };
          pendingVerificationMinor = 0;
          redeemableMinor = transaction.amountMinor;
        } else if (outcome === "verification-failed") {
          transaction = { ...transaction, reconciliationStatus: "failed" };
        } else if (outcome === "recovery-required") {
          transaction = { ...transaction, reconciliationStatus: "recovery-required" };
        }
        return { outcome, snapshot: getSnapshot() };
      })();

      syncInFlight = operation;
      try {
        return await operation;
      } finally {
        syncInFlight = null;
      }
    },

    async redeemVerifiedValue(reference) {
      const currentOperation = redemptionInFlight.get(reference);
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
          outcome: "ineligible",
          amountMinor: 0,
          simulatedOnlineBalanceMinor,
          snapshot: getSnapshot(),
        };
      }

      const amountMinor = redeemableMinor;
      const operation = (async () => {
        await wait(latencyMs);
        const outcome = redemptionQueue.shift() ?? "redeemed-simulation";

        if (outcome === "redeemed-simulation") {
          simulatedOnlineBalanceMinor += amountMinor;
          localOfflineValueMinor = Math.max(0, localOfflineValueMinor - amountMinor);
          redeemableMinor = Math.max(0, redeemableMinor - amountMinor);
          pendingVerificationMinor = 0;
          transaction = { ...transaction, reconciliationStatus: "redeemed" };
          history.unshift({ reference, status: "Redeemed", timestamp: "Just now", amountMinor });
        } else if (outcome === "failed") {
          history.unshift({ reference, status: "Failed", timestamp: "Just now", amountMinor });
        } else if (outcome === "recovery-required") {
          transaction = { ...transaction, reconciliationStatus: "recovery-required" };
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

      redemptionInFlight.set(reference, operation);
      try {
        return await operation;
      } finally {
        redemptionInFlight.delete(reference);
      }
    },
  };
}
