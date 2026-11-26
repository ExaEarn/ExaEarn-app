/**
 * @typedef {"connecting" | "authenticating" | "preparing-transfer" | "awaiting-recipient" | "committing-offline"} OfflineStage
 * @typedef {"success" | "recipient-rejected" | "authentication-failed" | "connection-interrupted" | "timeout" | "recovery-required"} OfflineScenario
 * @typedef {"offline-committed-simulation" | "cancelled" | "invalid-amount" | "insufficient-balance" | "recipient-unverified" | "recipient-rejected" | "authentication-failed" | "interrupted" | "timed-out" | "recovery-required"} OfflineOutcome
 * @typedef {{ id: string, name: string, product: string, shortId: string, available: boolean }} SampleRecipient
 * @typedef {{ recipient: SampleRecipient, recipientVerifiedForSimulation: boolean, amountMinor: number, reference: string, method: "bluetooth" | "qr" }} OfflineTransferRequest
 * @typedef {{ outcome: OfflineOutcome, reference: string, amountMinor: number, simulation: true, reconciliationRequired: boolean }} OfflineTransferResult
 * @typedef {{ onStage: (stage: OfflineStage) => void, shouldCancel: () => boolean, scenario: OfflineScenario }} OfflineRunOptions
 * @typedef {{ discoverNearbyDevices: () => Promise<SampleRecipient[]>, verifyRecipient: (device: SampleRecipient) => Promise<{ deviceId: string, verified: boolean, simulation: true }>, runTransfer: (request: OfflineTransferRequest, options: OfflineRunOptions) => Promise<OfflineTransferResult> }} ExaPayOfflineAdapter
 */

export const SIMULATED_OFFLINE_BALANCE_MINOR = 4_000;
export const MAX_SIMULATED_PAYMENT_MINOR = 10_000;

let simulatedBalanceMinor = SIMULATED_OFFLINE_BALANCE_MINOR;
let recoveryRequired = false;
const committedReferences = new Map();

const sampleRecipients = [
  {
    id: "sim-device-mathias",
    name: "Mathias’ Device",
    product: "ExaPay Device",
    shortId: "0x8F3A...9D21",
    available: true,
  },
  {
    id: "sim-device-alice",
    name: "Alice’s Device",
    product: "ExaPay Device",
    shortId: "0x2B71...A402",
    available: true,
  },
  {
    id: "sim-device-offline",
    name: "Offline Device",
    product: "ExaPay Device",
    shortId: "0x6C04...1E88",
    available: false,
  },
];

export function getSimulatedOfflineBalanceMinor() {
  return simulatedBalanceMinor;
}

export function hasSimulatedRecoveryRequired() {
  return recoveryRequired;
}

export function getSimulatedOfflineHistory(userId) {
  if (userId === null || userId === undefined || String(userId).trim() === "") return [];
  const scope = `${String(userId)}:`;
  return [...committedReferences.entries()]
    .filter(([key]) => key.startsWith(scope))
    .map(([key, record]) => ({
    id: `exapay-simulation:${key.slice(scope.length)}`,
    __history_source: "exapay",
    type: "offline_payment",
    status: record.status || "offline_committed_simulation",
    reference: key.slice(scope.length),
    amount: `${Math.floor(record.amountMinor / 100)}.${String(record.amountMinor % 100).padStart(2, "0")}`,
    currency: "simUSDT",
    recipient: record.recipientName || "Verified recipient",
    payment_method: record.method,
    created_at: record.createdAt,
    reconciliation_status: record.status === "recovery_required"
      ? "Recovery required · simulation only; no settlement"
      : "Simulation only · not settled online",
    simulation: true,
  }));
}

export function markSimulatedRecoveryRequired() {
  recoveryRequired = true;
}

export function parseSimulatedAmount(value, availableMinor = simulatedBalanceMinor) {
  const raw = String(value).trim();
  if (!raw) return { valid: false, reason: "empty" };
  if (raw.startsWith("-")) return { valid: false, reason: "negative" };
  if (!/^(?:\d+(?:\.\d*)?|\.\d+)$/.test(raw)) return { valid: false, reason: "invalid" };

  const [whole = "0", fraction = ""] = raw.split(".");
  if (fraction.length > 2) return { valid: false, reason: "precision" };

  const wholeValue = Number(whole || "0");
  if (!Number.isSafeInteger(wholeValue)) return { valid: false, reason: "maximum" };
  const amountMinor = wholeValue * 100 + Number(fraction.padEnd(2, "0") || "0");
  if (!Number.isSafeInteger(amountMinor)) return { valid: false, reason: "maximum" };
  if (amountMinor <= 0) return { valid: false, reason: "zero" };
  if (amountMinor > MAX_SIMULATED_PAYMENT_MINOR) return { valid: false, reason: "maximum" };
  if (amountMinor > availableMinor) return { valid: false, reason: "insufficient" };

  return { valid: true, amountMinor };
}

export function formatSimulatedAmount(amountMinor) {
  const major = Math.floor(amountMinor / 100);
  const minor = Math.abs(amountMinor % 100);
  return `${major.toLocaleString("en-US")}.${String(minor).padStart(2, "0")}`;
}

export function createSimulatedTransferReference() {
  const timePart = Date.now().toString(36).toUpperCase().slice(-7);
  const randomPart = Math.random().toString(36).slice(2, 7).toUpperCase();
  return `N-${timePart}${randomPart}`;
}

function waitForStep() {
  return new Promise((resolve) => setTimeout(resolve, 320));
}

/** @returns {ExaPayOfflineAdapter} */
export function createExaPayOfflineSimulator() {
  return {
    async discoverNearbyDevices() {
      await new Promise((resolve) => setTimeout(resolve, 180));
      return sampleRecipients.map((device) => ({ ...device }));
    },

    async verifyRecipient(device) {
      await waitForStep();
      return {
        deviceId: device.id,
        verified: device.available,
        simulation: true,
      };
    },

    async runTransfer(request, options) {
      const finish = (outcome) => {
        const scopedReference = `${String(request.userId || "anonymous")}:${request.reference}`;
        if (outcome === "recovery-required") {
          recoveryRequired = true;
          committedReferences.set(scopedReference, {
            ...(committedReferences.get(scopedReference) || {}),
            userId: String(request.userId || "anonymous"),
            reference: request.reference,
            recipientId: request.recipient?.id,
            recipientName: request.recipient?.name,
            amountMinor: request.amountMinor || 0,
            method: request.method,
            status: "recovery_required",
            createdAt: new Date().toISOString(),
          });
        }
        return {
          outcome,
          reference: request.reference,
          amountMinor: request.amountMinor,
          simulation: true,
          reconciliationRequired: outcome === "offline-committed-simulation" || outcome === "recovery-required",
        };
      };

      if (recoveryRequired) return finish("recovery-required");
      const scopedReference = `${String(request.userId || "anonymous")}:${request.reference}`;
      const previous = committedReferences.get(scopedReference);
      if (previous) {
        const sameRequest = previous.recipientId === request.recipient.id
          && previous.amountMinor === request.amountMinor
          && previous.method === request.method;
        if (!sameRequest) {
          recoveryRequired = true;
          return finish("recovery-required");
        }
        return finish("offline-committed-simulation");
      }

      if (
        !Number.isSafeInteger(request.amountMinor)
        || request.amountMinor <= 0
        || request.amountMinor > MAX_SIMULATED_PAYMENT_MINOR
      ) {
        return finish("invalid-amount");
      }
      if (!request.recipientVerifiedForSimulation) return finish("recipient-unverified");
      if (!request.recipient.available) return finish("interrupted");
      if (request.amountMinor > simulatedBalanceMinor) return finish("insufficient-balance");

      const stages = ["connecting", "authenticating", "preparing-transfer", "awaiting-recipient"];
      for (const stage of stages) {
        if (options.shouldCancel()) return finish("cancelled");
        options.onStage(stage);
        await waitForStep();

        if (stage === "connecting" && options.scenario === "connection-interrupted") {
          return finish("interrupted");
        }
        if (stage === "authenticating" && options.scenario === "authentication-failed") {
          return finish("authentication-failed");
        }
        if (stage === "awaiting-recipient" && options.scenario === "recipient-rejected") {
          return finish("recipient-rejected");
        }
        if (stage === "awaiting-recipient" && options.scenario === "timeout") {
          return finish("timed-out");
        }
      }

      if (options.shouldCancel()) return finish("cancelled");
      options.onStage("committing-offline");
      await waitForStep();
      if (options.shouldCancel() || options.scenario === "recovery-required") {
        recoveryRequired = true;
        return finish("recovery-required");
      }
      if (request.amountMinor > simulatedBalanceMinor) return finish("insufficient-balance");

      simulatedBalanceMinor -= request.amountMinor;
      committedReferences.set(scopedReference, {
        userId: String(request.userId || "anonymous"),
        reference: request.reference,
        recipientId: request.recipient.id,
        recipientName: request.recipient.name,
        amountMinor: request.amountMinor,
        method: request.method,
        status: "offline_committed_simulation",
        createdAt: new Date().toISOString(),
      });
      return finish("offline-committed-simulation");
    },
  };
}
