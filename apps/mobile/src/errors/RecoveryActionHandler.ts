import type { AppError, RecoveryAction } from "./errorTypes";

/** Single-flight action runner with a hard gate against unsafe or premature retries. */
export class RecoveryActionHandler {
  private static readonly inFlight = new Map<string, Promise<boolean>>();

  static run(error: AppError, action: RecoveryAction, handler: () => void | Promise<void>): Promise<boolean> {
    if (action === "retry_connection" && (!error.retryEligible || (error.retryAfterMs ?? 0) > 0)) return Promise.resolve(false);
    const key = `${error.code}:${error.reference ?? error.diagnosticId ?? "global"}:${action}`;
    const pending = this.inFlight.get(key);
    if (pending) return pending;
    const execution = Promise.resolve().then(handler).then(() => true).catch(() => false).finally(() => this.inFlight.delete(key));
    this.inFlight.set(key, execution);
    return execution;
  }
}
