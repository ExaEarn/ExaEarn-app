import { appErrorFromException, createAppError, normalizeErrorCode, parseRetryAfter, SUPPORTED_ERROR_CODES } from "./errorMessageRegistry";
import { AppApiError } from "./errorTypes";
import { resolveRecoveryActions } from "./ErrorActionResolver";
import { RecoveryActionHandler } from "./RecoveryActionHandler";
import { resolveTransactionStatus } from "./TransactionStatusResolver";

function assert(condition: unknown, message: string): asserts condition {
  if (!condition) throw new Error(`Error system test failed: ${message}`);
}

function assertEqual<T>(actual: T, expected: T, message: string) {
  assert(actual === expected, `${message}; expected ${String(expected)}, received ${String(actual)}`);
}

export async function runErrorSystemTests() {
  const requiredCodes = [
    "connectivity", "timeout", "server_error", "rate_limited", "session_expired", "authentication_required", "mfa_required",
    "permission_denied", "insufficient_balance", "invalid_recipient", "payment_declined", "payment_pending", "payment_unknown",
    "duplicate_transaction", "blockchain_congestion", "transaction_reverted", "unsupported_network", "bluetooth_disconnected",
    "bluetooth_unavailable", "offline_reconciliation_failed", "recovery_required", "device_security", "card_declined", "kyc_required",
    "maintenance", "transaction_settled", "transaction_failed", "unknown",
  ];
  for (const code of requiredCodes) assert(SUPPORTED_ERROR_CODES.includes(code as never), `registry includes ${code}`);

  assertEqual(normalizeErrorCode("token_expired", 401), "session_expired", "backend session code maps safely");
  assertEqual(normalizeErrorCode(undefined, 401), "authentication_required", "generic 401 does not falsely claim an expired session");
  assertEqual(normalizeErrorCode("balance.insufficient", 422), "unknown", "unregistered codes stay unknown");
  assertEqual(normalizeErrorCode(undefined, 429), "rate_limited", "HTTP rate limits are mapped");
  assertEqual(parseRetryAfter("3"), 3000, "Retry-After seconds are parsed");
  assertEqual(parseRetryAfter("Thu, 01 Jan 1970 00:00:00 GMT", 1000), 0, "past Retry-After dates clamp to zero");

  const readError = createAppError({ code: "connectivity", method: "GET", safeReadRetry: true });
  assert(readError.retryEligible && readError.actions.includes("retry_connection"), "explicit read retry is permitted");
  const mutationError = createAppError({ code: "connectivity", method: "POST", safeReadRetry: true });
  assert(!mutationError.retryEligible && !mutationError.actions.includes("retry_connection"), "financial writes cannot be retried blindly");
  const uncertain = createAppError({ code: "payment_unknown", method: "POST", reference: "PAY_123" });
  assert(uncertain.financialOutcomeUncertain, "unknown payment is marked uncertain");
  assertEqual(uncertain.reference, "PAY_123", "safe transaction reference is retained");
  assert(!uncertain.message.includes("deducted") && !uncertain.message.includes("failed"), "uncertain result avoids false fund claims");
  assert(!createAppError({ code: "server_error", payload: { message: "customer@example.com" } }).message.includes("customer@example.com"), "raw backend PII is not displayed");
  assertEqual(appErrorFromException(new AppApiError({ status: 401, method: "GET", backendCode: "token_expired" })).code, "session_expired", "typed API failures use the shared registry");

  let calls = 0;
  const actions = resolveRecoveryActions(readError, { retry_connection: async () => { calls += 1; } });
  assert(typeof actions.retry_connection === "function", "supported recovery action resolves");
  assert(!resolveRecoveryActions(mutationError, { retry_connection: () => undefined }).retry_connection, "unsafe retry handler is removed");
  const singleFlight = () => RecoveryActionHandler.run(readError, "retry_connection", async () => { calls += 1; await new Promise((resolve) => setTimeout(resolve, 10)); });
  const singleFlightResults = await Promise.all([singleFlight(), singleFlight()]);
  assert(singleFlightResults.every(Boolean), "both callers receive the shared action result");
  assertEqual(calls, 1, "recovery action is single-flight");
  assertEqual(await RecoveryActionHandler.run(mutationError, "retry_connection", async () => { calls += 1; }), false, "action runner blocks unsafe retries");

  const statusRequest = async () => ({ data: [{ reference: "TX-1", status: "completed" }] });
  assertEqual(await resolveTransactionStatus("TX-1", statusRequest), "settled", "exact authoritative history match confirms status");
  assertEqual(await resolveTransactionStatus("TX-2", statusRequest), "unknown", "a different reference is never treated as a match");
  assertEqual(await resolveTransactionStatus("bad/reference", statusRequest), "unknown", "unsafe references are rejected");
  assertEqual(await resolveTransactionStatus("TX-1", async () => { throw new Error("network"); }), "unknown", "lookup failures remain uncertain");
  return "Global error system unit checks passed";
}
