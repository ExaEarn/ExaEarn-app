export type AppErrorCode =
  | "connectivity" | "timeout" | "server_error" | "rate_limited" | "session_expired"
  | "authentication_required" | "mfa_required" | "permission_denied" | "insufficient_balance"
  | "invalid_recipient" | "payment_declined" | "payment_pending" | "payment_unknown"
  | "duplicate_transaction" | "blockchain_congestion" | "transaction_reverted"
  | "unsupported_network" | "bluetooth_disconnected" | "bluetooth_unavailable"
  | "offline_reconciliation_failed" | "recovery_required" | "device_security"
  | "card_declined" | "kyc_required" | "maintenance" | "transaction_settled" | "transaction_failed" | "unknown";

export type ErrorSeverity = "error" | "warning" | "connectivity" | "success" | "security";
export type RecoveryAction =
  | "retry_connection" | "edit_amount" | "add_funds" | "check_status" | "check_transfer"
  | "sign_in" | "verify_identity" | "reconnect_bluetooth" | "view_details" | "contact_support" | "close";

export type AppError = {
  code: AppErrorCode;
  severity: ErrorSeverity;
  title: string;
  message: string;
  actions: RecoveryAction[];
  reference?: string;
  retryAfterMs?: number;
  /** True only for an explicitly safe read/connection action. Never infer this for financial writes. */
  retryEligible: boolean;
  financialOutcomeUncertain: boolean;
  diagnosticId?: string;
};

export type BackendErrorPayload = {
  code?: unknown;
  error_code?: unknown;
  message?: unknown;
  reference?: unknown;
  transaction_reference?: unknown;
  retry_after?: unknown;
  errors?: unknown;
};

export class AppApiError extends Error {
  readonly status: number;
  readonly backendCode?: string;
  readonly retryAfterMs?: number;
  readonly requestMethod: string;
  readonly payload: BackendErrorPayload;
  readonly retryEligible: boolean;

  constructor(input: { status: number; backendCode?: string; retryAfterMs?: number; method: string; payload?: BackendErrorPayload }) {
    super("The request could not be completed.");
    this.name = "AppApiError";
    this.status = input.status;
    this.backendCode = input.backendCode;
    this.retryAfterMs = input.retryAfterMs;
    this.requestMethod = input.method.toUpperCase();
    this.retryEligible = ["GET", "HEAD"].includes(this.requestMethod);
    this.payload = input.payload ?? {};
  }
}
