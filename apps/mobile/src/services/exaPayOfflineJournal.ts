export type LocalSimulationTransfer = {
  reference: string;
  recipientId: string;
  amountMinor: number;
  method: "bluetooth" | "qr";
  state: "PREPARED" | "COMMITTED_SIMULATION" | "CANCELLED";
  createdAt: string;
};

export type LocalSimulationJournalState = {
  version: 1;
  simulationOnly: true;
  balanceMinor: number;
  recoveryRequired: boolean;
  transfers: LocalSimulationTransfer[];
};

export type SecureJournalResult =
  | { status: "READY"; state: LocalSimulationJournalState }
  | { status: "UNSUPPORTED" }
  | { status: "RECOVERY_REQUIRED"; state?: LocalSimulationJournalState };

export type SecureKeyValueStore = {
  isAvailableAsync?: () => Promise<boolean>;
  getItemAsync: (key: string) => Promise<string | null>;
  setItemAsync: (key: string, value: string, options?: unknown) => Promise<void>;
};

const MAX_JOURNAL_CHARS = 1_800;
const MAX_TRANSFERS = 6;

function validState(value: unknown): value is LocalSimulationJournalState {
  if (!value || typeof value !== "object") return false;
  const state = value as Partial<LocalSimulationJournalState>;
  return state.version === 1
    && state.simulationOnly === true
    && Number.isSafeInteger(state.balanceMinor)
    && Number(state.balanceMinor) >= 0
    && typeof state.recoveryRequired === "boolean"
    && Array.isArray(state.transfers)
    && state.transfers.every((transfer) => Boolean(transfer)
      && typeof transfer.reference === "string"
      && typeof transfer.recipientId === "string"
      && Number.isSafeInteger(transfer.amountMinor)
      && transfer.amountMinor > 0
      && ["bluetooth", "qr"].includes(transfer.method)
      && ["PREPARED", "COMMITTED_SIMULATION", "CANCELLED"].includes(transfer.state)
      && typeof transfer.createdAt === "string");
}

/**
 * Durable local journal for SIMUSDT-only UI simulation. SecureStore encrypts values
 * at rest using platform storage; this does not sign transfers or protect value from
 * a compromised/rolled-back device. A single-value write keeps debit and journal state
 * atomic from the JavaScript client's perspective.
 */
export class ExaPayOfflineSimulationJournal {
  private serial: Promise<unknown> = Promise.resolve();

  constructor(
    private readonly store: SecureKeyValueStore,
    private readonly storageKey: string,
    private readonly initialBalanceMinor: number,
    private readonly writeOptions?: unknown,
  ) {}

  load(): Promise<SecureJournalResult> {
    return this.enqueue(() => this.readOrInitialize());
  }

  recoverInterruptedOperations(): Promise<SecureJournalResult> {
    return this.enqueue(async () => {
      const loaded = await this.readOrInitialize();
      if (loaded.status !== "READY" || loaded.state.recoveryRequired) return loaded;
      if (!loaded.state.transfers.some((transfer) => transfer.state === "PREPARED")) return loaded;
      return this.save({ ...loaded.state, recoveryRequired: true });
    });
  }

  prepare(transfer: Omit<LocalSimulationTransfer, "state" | "createdAt">): Promise<SecureJournalResult> {
    return this.enqueue(async () => {
      const loaded = await this.readOrInitialize();
      if (loaded.status !== "READY") return loaded;
      const state = loaded.state;
      if (state.recoveryRequired) return loaded;
      const existing = state.transfers.find((item) => item.reference === transfer.reference);
      if (existing) {
        if (existing.recipientId !== transfer.recipientId || existing.amountMinor !== transfer.amountMinor || existing.method !== transfer.method) {
          return this.save({ ...state, recoveryRequired: true });
        }
        if (existing.state === "CANCELLED") return { status: "RECOVERY_REQUIRED", state };
        if (existing.state === "PREPARED") return this.save({ ...state, recoveryRequired: true });
        return { status: "READY", state };
      }
      if (!Number.isSafeInteger(transfer.amountMinor) || transfer.amountMinor <= 0) return { status: "RECOVERY_REQUIRED", state };
      const reserved = state.transfers.filter((item) => item.state === "PREPARED").reduce((sum, item) => sum + item.amountMinor, 0);
      if (transfer.amountMinor > state.balanceMinor - reserved || state.transfers.length >= MAX_TRANSFERS) {
        return { status: "RECOVERY_REQUIRED", state };
      }

      return this.save({
        ...state,
        transfers: [...state.transfers, { ...transfer, state: "PREPARED", createdAt: new Date().toISOString() }],
      });
    });
  }

  commit(reference: string): Promise<SecureJournalResult> {
    return this.enqueue(async () => {
      const loaded = await this.readOrInitialize();
      if (loaded.status !== "READY") return loaded;
      const state = loaded.state;
      if (state.recoveryRequired) return loaded;
      const index = state.transfers.findIndex((item) => item.reference === reference);
      if (index < 0) return { status: "RECOVERY_REQUIRED", state };
      const existing = state.transfers[index];
      if (existing.state === "COMMITTED_SIMULATION") return { status: "READY", state };
      if (existing.state !== "PREPARED" || existing.amountMinor > state.balanceMinor) {
        return this.save({ ...state, recoveryRequired: true });
      }

      const transfers = state.transfers.slice();
      transfers[index] = { ...existing, state: "COMMITTED_SIMULATION" };
      return this.save({ ...state, balanceMinor: state.balanceMinor - existing.amountMinor, transfers });
    });
  }

  cancelPrepared(reference: string): Promise<SecureJournalResult> {
    return this.enqueue(async () => {
      const loaded = await this.readOrInitialize();
      if (loaded.status !== "READY") return loaded;
      const state = loaded.state;
      if (state.recoveryRequired) return loaded;
      const index = state.transfers.findIndex((item) => item.reference === reference);
      if (index < 0) return { status: "READY", state };
      const existing = state.transfers[index];
      if (existing.state === "COMMITTED_SIMULATION") return { status: "RECOVERY_REQUIRED", state };
      const transfers = state.transfers.slice();
      transfers[index] = { ...existing, state: "CANCELLED" };
      return this.save({ ...state, transfers });
    });
  }

  markRecoveryRequired(): Promise<SecureJournalResult> {
    return this.enqueue(async () => {
      const loaded = await this.readOrInitialize();
      return loaded.status === "READY" ? this.save({ ...loaded.state, recoveryRequired: true }) : loaded;
    });
  }

  private async readOrInitialize(): Promise<SecureJournalResult> {
    try {
      if (this.store.isAvailableAsync && !await this.store.isAvailableAsync()) return { status: "UNSUPPORTED" };
      const raw = await this.store.getItemAsync(this.storageKey);
      if (raw === null) {
        return this.save({ version: 1, simulationOnly: true, balanceMinor: this.initialBalanceMinor, recoveryRequired: false, transfers: [] });
      }
      const parsed: unknown = JSON.parse(raw);
      return validState(parsed) ? { status: "READY", state: parsed } : { status: "RECOVERY_REQUIRED" };
    } catch {
      return { status: "RECOVERY_REQUIRED" };
    }
  }

  private async save(state: LocalSimulationJournalState): Promise<SecureJournalResult> {
    const serialized = JSON.stringify(state);
    if (serialized.length > MAX_JOURNAL_CHARS) return { status: "RECOVERY_REQUIRED", state };
    try {
      await this.store.setItemAsync(this.storageKey, serialized, this.writeOptions);
      return { status: "READY", state };
    } catch {
      // A storage error can occur after a native write. Read back before treating it as
      // absent; never restore the prior balance on an uncertain persistence outcome.
      try {
        const actual = await this.store.getItemAsync(this.storageKey);
        if (actual === serialized) return { status: "READY", state };
      } catch {
        // Keep the outcome uncertain and fail closed below.
      }
      return { status: "RECOVERY_REQUIRED", state: { ...state, recoveryRequired: true } };
    }
  }

  private enqueue<T>(operation: () => Promise<T>): Promise<T> {
    const result = this.serial.then(operation, operation);
    this.serial = result.then(() => undefined, () => undefined);
    return result;
  }
}
