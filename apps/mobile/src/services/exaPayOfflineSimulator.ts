import { ExaPayOfflineSimulationJournal } from "./exaPayOfflineJournal";
import { exaPaySqlCipherStore } from "./exaPaySqlCipherStore";

export type OfflinePaymentMethod = "bluetooth" | "qr" | "nearby";

export type OfflinePaymentStage =
  | "connecting"
  | "authenticating"
  | "preparing-transfer"
  | "awaiting-recipient"
  | "committing-offline";

export type OfflinePaymentOutcome =
  | "offline-committed-simulation"
  | "cancelled"
  | "invalid-amount"
  | "insufficient-balance"
  | "recipient-unverified"
  | "recipient-rejected"
  | "authentication-failed"
  | "secure-storage-unavailable"
  | "interrupted"
  | "timed-out"
  | "recovery-required";

export type OfflinePaymentScenario =
  | "success"
  | "recipient-rejected"
  | "authentication-failed"
  | "connection-interrupted"
  | "timeout"
  | "recovery-required";

export type SimulatedRecipientDevice = {
  id: string;
  name: string;
  product: string;
  shortId: string;
  available: boolean;
};

export type OfflineTransferRequest = {
  recipient: SimulatedRecipientDevice;
  recipientVerifiedForSimulation: boolean;
  amountMinor: number;
  reference: string;
  method: Exclude<OfflinePaymentMethod, "nearby">;
};

export type OfflineTransferResult = {
  outcome: OfflinePaymentOutcome;
  reference: string;
  amountMinor: number;
  simulation: true;
  reconciliationRequired: boolean;
};

export type OfflinePaymentProgressOptions = {
  onStage: (stage: OfflinePaymentStage) => void;
  shouldCancel: () => boolean;
  scenario: OfflinePaymentScenario;
};

export type ExaPayOfflineAdapter = {
  discoverNearbyDevices: () => Promise<SimulatedRecipientDevice[]>;
  verifyRecipient: (device: SimulatedRecipientDevice) => Promise<{
    deviceId: string;
    verified: boolean;
    simulation: true;
  }>;
  runTransfer: (
    request: OfflineTransferRequest,
    options: OfflinePaymentProgressOptions,
  ) => Promise<OfflineTransferResult>;
};

export type AmountValidation =
  | { valid: true; amountMinor: number }
  | { valid: false; reason: "empty" | "invalid" | "negative" | "zero" | "precision" | "maximum" | "insufficient" };

export const SIMULATED_OFFLINE_BALANCE_MINOR = 4_000;
export const MAX_SIMULATED_PAYMENT_MINOR = 10_000;

const simulatedDevices: SimulatedRecipientDevice[] = [
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

export function parseSimulatedAmount(value: string, availableMinor: number): AmountValidation {
  const raw = value.trim();
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

export function formatSimulatedAmount(amountMinor: number) {
  const major = Math.floor(amountMinor / 100);
  const minor = Math.abs(amountMinor % 100);
  return `${major.toLocaleString("en-US")}.${String(minor).padStart(2, "0")}`;
}

export function createSimulatedTransferReference() {
  const timePart = Date.now().toString(36).toUpperCase().slice(-7);
  const randomPart = Math.random().toString(36).slice(2, 7).toUpperCase();
  return `N-${timePart}${randomPart}`;
}

function waitForSimulationStep() {
  return new Promise<void>((resolve) => setTimeout(resolve, 360));
}

const journalByAccount = new Map<string, ExaPayOfflineSimulationJournal>();

function accountJournal(accountId: string | undefined) {
  if (!accountId || !/^[A-Za-z0-9_-]{1,100}$/.test(accountId)) return null;
  const existing = journalByAccount.get(accountId);
  if (existing) return existing;
  const journal = new ExaPayOfflineSimulationJournal(
    exaPaySqlCipherStore,
    `exaearn.exapay.sim.v1.${accountId}`,
    SIMULATED_OFFLINE_BALANCE_MINOR,
  );
  journalByAccount.set(accountId, journal);
  return journal;
}

export function createExaPayOfflineSimulator(accountId?: string): ExaPayOfflineAdapter & {
  loadState: () => Promise<{ balanceMinor: number; recoveryRequired: boolean; supported: boolean }>;
} {
  const journal = accountJournal(accountId);
  return {
    async loadState() {
      if (!journal) return { balanceMinor: 0, recoveryRequired: true, supported: false };
      const loaded = await journal.recoverInterruptedOperations();
      if (loaded.status !== "READY") return { balanceMinor: 0, recoveryRequired: true, supported: loaded.status !== "UNSUPPORTED" };
      return { balanceMinor: loaded.state.balanceMinor, recoveryRequired: loaded.state.recoveryRequired, supported: true };
    },
    async discoverNearbyDevices() {
      await new Promise<void>((resolve) => setTimeout(resolve, 240));
      return simulatedDevices.map((device) => ({ ...device }));
    },

    async verifyRecipient(device: SimulatedRecipientDevice) {
      await waitForSimulationStep();
      return {
        deviceId: device.id,
        verified: device.available,
        simulation: true as const,
      };
    },

    async runTransfer(
      request: OfflineTransferRequest,
      options: OfflinePaymentProgressOptions,
    ): Promise<OfflineTransferResult> {
      const finish = (outcome: OfflinePaymentOutcome): OfflineTransferResult => ({
        outcome,
        reference: request.reference,
        amountMinor: request.amountMinor,
        simulation: true,
        reconciliationRequired: outcome === "offline-committed-simulation" || outcome === "recovery-required",
      });

      if (!journal || !accountId) return finish("recovery-required");
      const loaded = await journal.load();
      if (loaded.status === "UNSUPPORTED") return finish("secure-storage-unavailable");
      if (loaded.status !== "READY" || loaded.state.recoveryRequired) return finish("recovery-required");
      const committedTransfer = loaded.state.transfers.find((item) => item.reference === request.reference);
      if (committedTransfer?.state === "COMMITTED_SIMULATION") {
        if (
          committedTransfer.recipientId !== request.recipient.id
          || committedTransfer.amountMinor !== request.amountMinor
          || committedTransfer.method !== request.method
        ) {
          await journal.markRecoveryRequired();
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
      if (request.amountMinor > loaded.state.balanceMinor) return finish("insufficient-balance");

      const prepared = await journal.prepare({
        reference: request.reference,
        recipientId: request.recipient.id,
        amountMinor: request.amountMinor,
        method: request.method,
      });
      if (prepared.status !== "READY" || prepared.state.recoveryRequired) {
        return finish("recovery-required");
      }

      const earlyStages: OfflinePaymentStage[] = [
        "connecting",
        "authenticating",
        "preparing-transfer",
        "awaiting-recipient",
      ];

      for (const stage of earlyStages) {
        if (options.shouldCancel()) { await journal.cancelPrepared(request.reference); return finish("cancelled"); }
        options.onStage(stage);
        await waitForSimulationStep();

        if (stage === "connecting" && options.scenario === "connection-interrupted") {
          await journal.cancelPrepared(request.reference); return finish("interrupted");
        }
        if (stage === "authenticating" && options.scenario === "authentication-failed") {
          await journal.cancelPrepared(request.reference); return finish("authentication-failed");
        }
        if (stage === "awaiting-recipient" && options.scenario === "recipient-rejected") {
          await journal.cancelPrepared(request.reference); return finish("recipient-rejected");
        }
        if (stage === "awaiting-recipient" && options.scenario === "timeout") {
          await journal.cancelPrepared(request.reference); return finish("timed-out");
        }
      }

      if (options.shouldCancel()) { await journal.cancelPrepared(request.reference); return finish("cancelled"); }
      options.onStage("committing-offline");
      await waitForSimulationStep();

      if (options.shouldCancel() || options.scenario === "recovery-required") {
        await journal.markRecoveryRequired();
        return finish("recovery-required");
      }

      const committed = await journal.commit(request.reference);
      if (committed.status !== "READY" || committed.state.recoveryRequired) {
        return finish("recovery-required");
      }
      return finish("offline-committed-simulation");
    },
  };
}

export async function getCommittedOfflineSimulationTransactions(accountId?: string) {
  const journal = accountJournal(accountId);
  if (!journal) return [];
  const loaded = await journal.load();
  if (loaded.status === "UNSUPPORTED" || !loaded.state) return [];
  return loaded.state.transfers
    .filter((record) => record.state === "COMMITTED_SIMULATION")
    .map((record) => ({
      reference: record.reference,
      recipientLabel: record.recipientId,
      amountMinor: record.amountMinor,
      method: record.method,
      createdAt: record.createdAt,
    }));
}
