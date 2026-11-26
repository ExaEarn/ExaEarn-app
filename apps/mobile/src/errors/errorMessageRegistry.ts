import { AppApiError, type AppError, type AppErrorCode, type BackendErrorPayload, type ErrorSeverity, type RecoveryAction } from "./errorTypes";
import { colors } from "../theme/colors";

type Message = Pick<AppError, "severity" | "title" | "message" | "actions" | "financialOutcomeUncertain">;

const messages: Record<AppErrorCode, Message> = {
  connectivity: { severity: "connectivity", title: "Connection problem", message: "Check your connection, then try again.", actions: ["retry_connection", "close"], financialOutcomeUncertain: false },
  timeout: { severity: "connectivity", title: "Request timed out", message: "The service did not respond in time. Check the latest status before repeating a payment.", actions: ["check_status", "close"], financialOutcomeUncertain: true },
  server_error: { severity: "error", title: "Service unavailable", message: "We could not complete this request. Your transaction status may need to be checked.", actions: ["check_status", "contact_support", "close"], financialOutcomeUncertain: true },
  rate_limited: { severity: "warning", title: "Please wait a moment", message: "Too many requests were made. Wait before trying again.", actions: ["close"], financialOutcomeUncertain: false },
  session_expired: { severity: "security", title: "Session expired", message: "Sign in again to continue securely.", actions: ["sign_in", "close"], financialOutcomeUncertain: false },
  authentication_required: { severity: "security", title: "Sign-in required", message: "Check your sign-in details or sign in again to continue.", actions: ["sign_in", "close"], financialOutcomeUncertain: false },
  mfa_required: { severity: "security", title: "Verification required", message: "Complete your security verification to continue.", actions: ["sign_in", "close"], financialOutcomeUncertain: false },
  permission_denied: { severity: "security", title: "Action unavailable", message: "Your account does not have permission to perform this action.", actions: ["contact_support", "close"], financialOutcomeUncertain: false },
  insufficient_balance: { severity: "warning", title: "Insufficient balance", message: "Review the amount or add funds to continue.", actions: ["edit_amount", "add_funds", "close"], financialOutcomeUncertain: false },
  invalid_recipient: { severity: "warning", title: "Check recipient details", message: "We could not verify this recipient. Review the details before continuing.", actions: ["view_details", "close"], financialOutcomeUncertain: false },
  payment_declined: { severity: "error", title: "Payment not approved", message: "The payment was declined. Review the details or contact support.", actions: ["view_details", "contact_support", "close"], financialOutcomeUncertain: false },
  payment_pending: { severity: "warning", title: "Payment processing", message: "This payment is still being processed. Check its status before taking another action.", actions: ["check_status", "close"], financialOutcomeUncertain: true },
  payment_unknown: { severity: "warning", title: "Payment status unavailable", message: "We could not confirm the result. Check the original transaction before retrying.", actions: ["check_status", "contact_support", "close"], financialOutcomeUncertain: true },
  duplicate_transaction: { severity: "warning", title: "Transaction already received", message: "We found a request with this reference. Check its status; do not submit it again.", actions: ["check_status", "close"], financialOutcomeUncertain: true },
  blockchain_congestion: { severity: "warning", title: "Network is busy", message: "The blockchain is congested. Check the transaction status before trying again.", actions: ["check_status", "close"], financialOutcomeUncertain: true },
  transaction_reverted: { severity: "error", title: "Transaction reverted", message: "The network reported a reverted transaction. Review its details before taking another action.", actions: ["view_details", "contact_support", "close"], financialOutcomeUncertain: false },
  unsupported_network: { severity: "warning", title: "Network not supported", message: "Switch to a supported network and verify the destination before continuing.", actions: ["view_details", "close"], financialOutcomeUncertain: false },
  bluetooth_disconnected: { severity: "connectivity", title: "Device disconnected", message: "The nearby device disconnected. Check transfer status before reconnecting or creating another transfer.", actions: ["check_transfer", "reconnect_bluetooth", "close"], financialOutcomeUncertain: true },
  bluetooth_unavailable: { severity: "connectivity", title: "Bluetooth unavailable", message: "Enable Bluetooth and allow nearby-device access to continue.", actions: ["reconnect_bluetooth", "close"], financialOutcomeUncertain: false },
  offline_reconciliation_failed: { severity: "error", title: "Transfer needs attention", message: "We could not reconcile this offline transfer. Keep its reference and contact support; do not send it again.", actions: ["view_details", "contact_support", "close"], financialOutcomeUncertain: true },
  recovery_required: { severity: "warning", title: "Transfer needs attention", message: "This transfer needs reconciliation. Check its original reference in ExaPay history before taking another action.", actions: ["check_transfer", "contact_support", "close"], financialOutcomeUncertain: true },
  device_security: { severity: "security", title: "Security check required", message: "Your device or session needs an additional security check before continuing.", actions: ["sign_in", "contact_support", "close"], financialOutcomeUncertain: false },
  card_declined: { severity: "error", title: "Card transaction declined", message: "The card transaction was declined. Check the card details or contact your issuer.", actions: ["view_details", "contact_support", "close"], financialOutcomeUncertain: false },
  kyc_required: { severity: "security", title: "Identity verification required", message: "Verify your identity to access this feature.", actions: ["verify_identity", "close"], financialOutcomeUncertain: false },
  maintenance: { severity: "warning", title: "Temporarily unavailable", message: "This service is undergoing maintenance. Please try again later.", actions: ["close"], financialOutcomeUncertain: false },
  transaction_settled: { severity: "success", title: "Status confirmed", message: "The account transaction history reports this reference as settled.", actions: ["close"], financialOutcomeUncertain: false },
  transaction_failed: { severity: "error", title: "Transfer needs review", message: "Transaction history reports a failed status. Review its record and reconcile before submitting another transfer.", actions: ["view_details", "contact_support", "close"], financialOutcomeUncertain: true },
  unknown: { severity: "error", title: "Something went wrong", message: "We could not complete that action. Try again later or contact support if the issue continues.", actions: ["contact_support", "close"], financialOutcomeUncertain: true },
};

export const SUPPORTED_ERROR_CODES = Object.keys(messages) as AppErrorCode[];

const backendCodes: Record<string, AppErrorCode> = {
  unauthenticated: "authentication_required", invalid_credentials: "authentication_required", bad_credentials: "authentication_required",
  token_expired: "session_expired", session_expired: "session_expired",
  mfa_required: "mfa_required", two_factor_required: "mfa_required", forbidden: "permission_denied",
  insufficient_balance: "insufficient_balance", invalid_recipient: "invalid_recipient", recipient_not_found: "invalid_recipient",
  payment_declined: "payment_declined", transaction_pending: "payment_pending", payment_pending: "payment_pending",
  duplicate_transaction: "duplicate_transaction", idempotency_conflict: "duplicate_transaction", rate_limit_exceeded: "rate_limited",
  maintenance: "maintenance", kyc_required: "kyc_required", verification_required: "kyc_required",
  network_error: "connectivity", offline: "connectivity", request_timeout: "timeout",
  unsupported_network: "unsupported_network", transaction_reverted: "transaction_reverted", network_congestion: "blockchain_congestion",
};

export function parseRetryAfter(value: string | null | undefined, now = Date.now()): number | undefined {
  if (!value) return undefined;
  const seconds = Number(value);
  if (Number.isFinite(seconds) && seconds >= 0) return Math.min(seconds * 1000, 24 * 60 * 60 * 1000);
  const date = Date.parse(value);
  return Number.isFinite(date) ? Math.max(0, Math.min(date - now, 24 * 60 * 60 * 1000)) : undefined;
}

export function normalizeErrorCode(value: unknown, status?: number): AppErrorCode {
  const key = typeof value === "string" ? value.trim().toLowerCase().replace(/[\s.-]+/g, "_") : "";
  if (key in messages) return key as AppErrorCode;
  if (backendCodes[key]) return backendCodes[key];
  if (status === 401) return "authentication_required";
  if (status === 403) return "permission_denied";
  if (status === 408 || status === 504) return "timeout";
  if (status === 429) return "rate_limited";
  if (status && status >= 500) return "server_error";
  return "unknown";
}

export function createAppError(input: {
  code?: unknown; status?: number; method?: string; payload?: BackendErrorPayload; retryAfterMs?: number;
  reference?: string; diagnosticId?: string; safeReadRetry?: boolean;
}): AppError {
  const payload = input.payload ?? {};
  const code = normalizeErrorCode(input.code ?? payload.code ?? payload.error_code, input.status);
  const message = messages[code];
  const safeReadRetry = input.safeReadRetry === true && ["GET", "HEAD"].includes((input.method ?? "GET").toUpperCase());
  const actions: RecoveryAction[] = safeReadRetry && !message.financialOutcomeUncertain
    ? ["retry_connection", ...message.actions.filter((action) => action !== "retry_connection")]
    : message.actions.filter((action) => action !== "retry_connection");
  const reference = input.reference
    ?? (typeof payload.transaction_reference === "string" ? payload.transaction_reference : undefined)
    ?? (typeof payload.reference === "string" ? payload.reference : undefined);
  return {
    code,
    severity: message.severity,
    title: message.title,
    message: message.message,
    actions,
    ...(reference && /^[A-Za-z0-9_-]{3,100}$/.test(reference) ? { reference } : {}),
    ...(input.retryAfterMs !== undefined ? { retryAfterMs: input.retryAfterMs } : {}),
    retryEligible: safeReadRetry && actions.includes("retry_connection"),
    financialOutcomeUncertain: message.financialOutcomeUncertain,
    ...(input.diagnosticId ? { diagnosticId: input.diagnosticId } : {}),
  };
}

export function getErrorTheme(severity: ErrorSeverity) {
  const statusColors: Record<ErrorSeverity, string> = {
    error: colors.statusError, warning: colors.statusWarning, connectivity: colors.statusConnectivity, success: colors.statusSuccess, security: colors.statusSecurity,
  };
  return statusColors[severity];
}

export function isRecoveryAction(action: RecoveryAction): boolean {
  return action !== "close";
}

export function appErrorFromException(error: unknown): AppError {
  if (error instanceof AppApiError) {
    return createAppError({
      code: error.backendCode,
      status: error.status,
      method: error.requestMethod,
      payload: error.payload,
      retryAfterMs: error.retryAfterMs,
      safeReadRetry: error.retryEligible,
    });
  }
  if (error instanceof Error && error.name === "AbortError") return createAppError({ code: "timeout" });
  return createAppError({ code: "unknown" });
}
