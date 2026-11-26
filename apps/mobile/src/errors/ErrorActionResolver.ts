import type { AppError, RecoveryAction } from "./errorTypes";

export type RecoveryHandlers = Partial<Record<RecoveryAction, () => void | Promise<void>>>;

/** Produces only actions the host screen explicitly supports. No mutation retry is synthesized. */
export function resolveRecoveryActions(error: AppError, handlers: RecoveryHandlers): RecoveryHandlers {
  const available: RecoveryHandlers = {};
  for (const action of error.actions) {
    if (action === "retry_connection" && !error.retryEligible) continue;
    const handler = handlers[action];
    if (handler) available[action] = handler;
  }
  return available;
}

