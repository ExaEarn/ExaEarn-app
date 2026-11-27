import { ExaPayOfflineSimulationJournal, type SecureKeyValueStore } from "./exaPayOfflineJournal";

function assert(condition: unknown, message: string): asserts condition {
  if (!condition) throw new Error(message);
}

class MemorySecureStore implements SecureKeyValueStore {
  readonly values = new Map<string, string>();
  available = true;
  failNextWriteAfterPersist = false;

  async isAvailableAsync() { return this.available; }
  async getItemAsync(key: string) { return this.values.get(key) ?? null; }
  async setItemAsync(key: string, value: string) {
    this.values.set(key, value);
    if (this.failNextWriteAfterPersist) {
      this.failNextWriteAfterPersist = false;
      throw new Error("native write acknowledgement lost");
    }
  }
}

function transfer(reference: string, amountMinor: number, recipientId = "sim-device-b") {
  return { reference, recipientId, amountMinor, method: "bluetooth" as const };
}

export async function runOfflineJournalTests() {
  const store = new MemorySecureStore();
  const firstProcess = new ExaPayOfflineSimulationJournal(store, "account-17", 4_000);
  const prepared = await firstProcess.prepare(transfer("offline-1", 3_000));
  assert(prepared.status === "READY" && prepared.state.transfers[0].state === "PREPARED", "prepare is durable before commit");

  const afterRestart = new ExaPayOfflineSimulationJournal(store, "account-17", 4_000);
  const recovered = await afterRestart.recoverInterruptedOperations();
  assert(recovered.status === "READY" && recovered.state.recoveryRequired, "prepared journal survives restart in quarantine");
  const interruptedCommit = await afterRestart.commit("offline-1");
  assert(interruptedCommit.status === "READY" && interruptedCommit.state.recoveryRequired && interruptedCommit.state.balanceMinor === 4_000, "restart recovery never auto-debits or clears uncertainty");

  const liveStore = new MemorySecureStore();
  const liveJournal = new ExaPayOfflineSimulationJournal(liveStore, "account-live", 4_000);
  await liveJournal.prepare(transfer("offline-live", 3_000));
  const committed = await liveJournal.commit("offline-live");
  assert(committed.status === "READY" && committed.state.balanceMinor === 1_000, "live simulation commit debits only the simulation balance");
  const retry = await liveJournal.commit("offline-live");
  assert(retry.status === "READY" && retry.state.balanceMinor === 1_000, "identical commit retry does not debit twice");

  const conflictingRetry = await liveJournal.prepare(transfer("offline-live", 2_999));
  assert(conflictingRetry.status === "READY" && conflictingRetry.state.recoveryRequired, "conflicting idempotency payload enters recovery");

  const secondStore = new MemorySecureStore();
  const secondJournal = new ExaPayOfflineSimulationJournal(secondStore, "account-18", 4_000);
  const tooLarge = await secondJournal.prepare(transfer("offline-2", 4_001));
  assert(tooLarge.status === "RECOVERY_REQUIRED", "overspend is rejected");

  const unavailableStore = new MemorySecureStore();
  unavailableStore.available = false;
  const unsupported = await new ExaPayOfflineSimulationJournal(unavailableStore, "account-19", 4_000).load();
  assert(unsupported.status === "UNSUPPORTED", "missing secure storage fails closed");

  const uncertainStore = new MemorySecureStore();
  uncertainStore.failNextWriteAfterPersist = true;
  const uncertain = await new ExaPayOfflineSimulationJournal(uncertainStore, "account-20", 4_000).prepare(transfer("offline-3", 500));
  assert(uncertain.status === "READY", "write persisted despite a lost acknowledgement and was verified by readback");

  const corruptStore = new MemorySecureStore();
  corruptStore.values.set("account-21", "{not-json");
  const corrupt = await new ExaPayOfflineSimulationJournal(corruptStore, "account-21", 4_000).load();
  assert(corrupt.status === "RECOVERY_REQUIRED", "corrupt journal is quarantined without resetting balance");

  const cancelStore = new MemorySecureStore();
  const cancelJournal = new ExaPayOfflineSimulationJournal(cancelStore, "account-22", 4_000);
  await cancelJournal.prepare(transfer("offline-4", 100));
  await cancelJournal.cancelPrepared("offline-4");
  const reusedCancelled = await cancelJournal.prepare(transfer("offline-4", 100));
  assert(reusedCancelled.status === "RECOVERY_REQUIRED", "cancelled reference cannot be reused");

  return "ExaPay offline journal checks passed (durability, idempotency, overspend, unavailable storage, uncertain write, corruption, cancellation).";
}
