/**
 * @typedef {"online" | "bluetooth" | "qr"} ExaPayPaymentChannel
 * @typedef {"user-entered" | "qr-parsed" | "simulation-verified"} RecipientVerification
 * @typedef {object} ExaPayPaymentDraft
 * @property {string} idempotencyKey
 * @property {string} reference
 * @property {ExaPayPaymentChannel} channel
 * @property {{ label: string, identifier?: string }} sourceAccount
 * @property {{ id: string, displayName: string, username?: string, identifier?: string, initials: string, verification: RecipientVerification }} recipient
 * @property {number} amountMinor
 * @property {number | null} feeMinor
 * @property {{ symbol: string, precision: 2, network?: string }} asset
 * @property {string} message
 * @property {number} createdAt
 * @property {number | null} expiresAt
 * @property {true} simulation
 * @property {{ recipient: object, recipientVerifiedForSimulation: true, method: "bluetooth" | "qr", scenario: string }} [offlineTransfer]
 */

export function createPaymentReference(prefix = "PAY") {
  const time = Date.now().toString(36).toUpperCase().slice(-7);
  const entropy = Math.random().toString(36).slice(2, 8).toUpperCase();
  return `${prefix}-${time}${entropy}`;
}

export function parsePaymentAmount(value) {
  const raw = String(value).trim();
  if (!raw) return { valid: false, reason: "empty" };
  if (!/^(?:\d+(?:\.\d*)?|\.\d+)$/.test(raw)) return { valid: false, reason: "invalid" };

  const [whole = "0", fraction = ""] = raw.split(".");
  if (fraction.length > 2) return { valid: false, reason: "precision" };
  const wholeValue = Number(whole || "0");
  if (!Number.isSafeInteger(wholeValue)) return { valid: false, reason: "maximum" };
  const amountMinor = wholeValue * 100 + Number(fraction.padEnd(2, "0") || "0");
  if (!Number.isSafeInteger(amountMinor)) return { valid: false, reason: "maximum" };
  if (amountMinor <= 0) return { valid: false, reason: "zero" };
  return { valid: true, amountMinor };
}

export function calculatePaymentTotalMinor(draft) {
  if (draft.feeMinor === null) return null;
  if (!Number.isSafeInteger(draft.amountMinor) || !Number.isSafeInteger(draft.feeMinor) || draft.feeMinor < 0) return null;
  const total = draft.amountMinor + draft.feeMinor;
  return Number.isSafeInteger(total) ? total : null;
}

export function validatePaymentDraft(draft, now = Date.now()) {
  if (!Number.isSafeInteger(draft.amountMinor) || draft.amountMinor <= 0) return { valid: false, reason: "invalid-amount" };
  if (draft.feeMinor !== null && (!Number.isSafeInteger(draft.feeMinor) || draft.feeMinor < 0)) {
    return { valid: false, reason: "invalid-fee" };
  }
  if (draft.feeMinor !== null && calculatePaymentTotalMinor(draft) === null) {
    return { valid: false, reason: "invalid-fee" };
  }
  if (!draft.recipient?.id?.trim() || !draft.recipient?.displayName?.trim()) {
    return { valid: false, reason: "invalid-recipient" };
  }
  if (draft.recipient.username && !/^[A-Za-z0-9._-]{3,30}$/.test(draft.recipient.username)) {
    return { valid: false, reason: "invalid-recipient" };
  }
  if (draft.expiresAt !== null && now >= draft.expiresAt) return { valid: false, reason: "expired" };
  if (
    (draft.channel === "bluetooth" || draft.channel === "qr")
    && (!draft.offlineTransfer || draft.offlineTransfer.recipient?.available !== true)
  ) {
    return { valid: false, reason: "invalid-offline-request" };
  }
  return { valid: true };
}

export function formatPaymentAmount(amountMinor, symbol = "simUSDT") {
  const major = Math.floor(amountMinor / 100);
  const minor = Math.abs(amountMinor % 100);
  const formatted = `${major.toLocaleString("en-US")}.${String(minor).padStart(2, "0")}`;
  return symbol ? `${formatted} ${symbol}` : formatted;
}

export function parseSimulatedQrRequest(payload, now = Date.now()) {
  try {
    const url = new URL(String(payload).trim());
    if (url.protocol !== "exaearn:" || url.hostname !== "pay") return null;
    const username = (url.searchParams.get("to") || "").trim().replace(/^@/, "");
    const amount = parsePaymentAmount(url.searchParams.get("amount") || "");
    const reference = (url.searchParams.get("ref") || "").trim();
    const expiryText = url.searchParams.get("expires");
    const expiresAt = expiryText ? Number(expiryText) : null;

    if (!/^[A-Za-z0-9._-]{3,30}$/.test(username) || !amount.valid || !reference) return null;
    if (expiryText && (!Number.isSafeInteger(expiresAt) || expiresAt <= now)) return null;

    return {
      username,
      amountMinor: amount.amountMinor,
      reference,
      message: url.searchParams.get("message") || "",
      expiresAt,
    };
  } catch {
    return null;
  }
}
