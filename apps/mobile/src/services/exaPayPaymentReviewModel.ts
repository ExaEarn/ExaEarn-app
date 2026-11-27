import type {
  OfflinePaymentScenario,
  SimulatedRecipientDevice,
} from "./exaPayOfflineSimulator";

export type ExaPayPaymentChannel = "online" | "bluetooth" | "qr";
export type RecipientVerification = "user-entered" | "qr-parsed" | "simulation-verified";

export type ExaPayPaymentDraft = {
  idempotencyKey: string;
  reference: string;
  channel: ExaPayPaymentChannel;
  sourceAccount: {
    label: string;
    identifier?: string;
  };
  recipient: {
    id: string;
    displayName: string;
    username?: string;
    identifier?: string;
    initials: string;
    verification: RecipientVerification;
  };
  amountMinor: number;
  feeMinor: number | null;
  asset: {
    symbol: string;
    precision: 2;
    network?: string;
  };
  message: string;
  createdAt: number;
  expiresAt: number | null;
  simulation: true;
  offlineTransfer?: {
    accountId: string;
    recipient: SimulatedRecipientDevice;
    recipientVerifiedForSimulation: true;
    method: "bluetooth" | "qr";
    scenario: OfflinePaymentScenario;
  };
};

export type ExaPayPaymentResult = {
  channel: ExaPayPaymentChannel;
  outcome: string;
  reference: string;
  amountMinor: number;
  simulation: true;
  onlineSettlement: false;
  message: string;
};

export type AmountParseResult =
  | { valid: true; amountMinor: number }
  | { valid: false; reason: "empty" | "invalid" | "precision" | "zero" | "maximum" };

export type DraftValidation =
  | { valid: true }
  | { valid: false; reason: "invalid-amount" | "invalid-recipient" | "invalid-fee" | "expired" | "invalid-offline-request" };

export function createPaymentReference(prefix = "P") {
  const timestamp = Date.now().toString(36).toUpperCase().slice(-7);
  const entropy = Math.random().toString(36).slice(2, 8).toUpperCase();
  return `${prefix}-${timestamp}${entropy}`;
}

export function parsePaymentAmount(value: string): AmountParseResult {
  const raw = value.trim();
  if (!raw) return { valid: false, reason: "empty" };
  if (!/^(?:\d+(?:\.\d*)?|\.\d+)$/.test(raw)) return { valid: false, reason: "invalid" };

  const [whole = "0", fraction = ""] = raw.split(".");
  if (fraction.length > 2) return { valid: false, reason: "precision" };
  const wholeValue = Number(whole || "0");
  if (!Number.isSafeInteger(wholeValue)) return { valid: false, reason: "maximum" };

  const amountMinor = wholeValue * 100 + Number(fraction.padEnd(2, "0") || "0");
  if (!Number.isSafeInteger(amountMinor) || amountMinor > Number.MAX_SAFE_INTEGER) {
    return { valid: false, reason: "maximum" };
  }
  if (amountMinor <= 0) return { valid: false, reason: "zero" };
  return { valid: true, amountMinor };
}

export function calculatePaymentTotalMinor(draft: ExaPayPaymentDraft) {
  if (draft.feeMinor === null) return null;
  if (!Number.isSafeInteger(draft.amountMinor) || !Number.isSafeInteger(draft.feeMinor)) return null;
  const totalMinor = draft.amountMinor + draft.feeMinor;
  return Number.isSafeInteger(totalMinor) ? totalMinor : null;
}

export function validatePaymentDraft(draft: ExaPayPaymentDraft, now = Date.now()): DraftValidation {
  if (!Number.isSafeInteger(draft.amountMinor) || draft.amountMinor <= 0) {
    return { valid: false, reason: "invalid-amount" };
  }
  if (draft.feeMinor !== null && (!Number.isSafeInteger(draft.feeMinor) || draft.feeMinor < 0)) {
    return { valid: false, reason: "invalid-fee" };
  }
  if (draft.feeMinor !== null && calculatePaymentTotalMinor(draft) === null) {
    return { valid: false, reason: "invalid-fee" };
  }
  if (!draft.recipient.displayName.trim() || !draft.recipient.id.trim()) {
    return { valid: false, reason: "invalid-recipient" };
  }
  if (draft.recipient.username && !/^[A-Za-z0-9._-]{3,30}$/.test(draft.recipient.username)) {
    return { valid: false, reason: "invalid-recipient" };
  }
  if (draft.expiresAt !== null && now >= draft.expiresAt) {
    return { valid: false, reason: "expired" };
  }
  if (
    (draft.channel === "bluetooth" || draft.channel === "qr")
    && (!draft.offlineTransfer || draft.offlineTransfer.recipient.available !== true)
  ) {
    return { valid: false, reason: "invalid-offline-request" };
  }
  return { valid: true };
}

export function formatPaymentAmount(amountMinor: number, symbol = "simUSDT") {
  const whole = Math.floor(amountMinor / 100);
  const fraction = Math.abs(amountMinor % 100);
  return `${whole.toLocaleString("en-US")}.${String(fraction).padStart(2, "0")} ${symbol}`;
}

export function parseSimulatedQrRequest(payload: string, now = Date.now()) {
  try {
    const url = new URL(payload.trim());
    if (url.protocol !== "exaearn:" || url.hostname !== "pay" || url.port || url.hash || url.username || url.password) return null;
    const allowedParameters = new Set(["to", "amount", "ref", "expires", "message"]);
    const parameterCounts = new Map<string, number>();
    url.searchParams.forEach((_value, key) => parameterCounts.set(key, (parameterCounts.get(key) ?? 0) + 1));
    for (const [key, count] of parameterCounts) if (!allowedParameters.has(key) || count !== 1) return null;
    const username = url.searchParams.get("to")?.trim().replace(/^@/, "") ?? "";
    const amount = parsePaymentAmount(url.searchParams.get("amount") ?? "");
    const reference = url.searchParams.get("ref")?.trim() ?? "";
    const message = url.searchParams.get("message") ?? "";
    const expiryText = url.searchParams.get("expires");
    const expiresAt = expiryText ? Number(expiryText) : null;

    if (!/^[A-Za-z0-9._-]{3,30}$/.test(username) || !amount.valid
      || !/^[A-Za-z0-9_-]{3,80}$/.test(reference) || message.length > 100) return null;
    if (expiryText && (!Number.isSafeInteger(expiresAt) || (expiresAt as number) <= now)) return null;

    return {
      username,
      amountMinor: amount.amountMinor,
      reference,
      message,
      expiresAt,
    };
  } catch {
    return null;
  }
}
