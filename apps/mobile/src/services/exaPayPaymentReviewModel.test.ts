import { parseSimulatedQrRequest } from "./exaPayPaymentReviewModel";

function assert(condition: unknown, message: string): asserts condition {
  if (!condition) throw new Error(message);
}

export function runOfflineQrValidationTests() {
  const now = 1_800_000_000_000;
  const valid = parseSimulatedQrRequest(
    "exaearn://pay?to=monica&amount=10.25&ref=QR-123456&expires=1800000001000&message=Lunch",
    now,
  );
  assert(valid?.username === "monica" && valid.amountMinor === 1_025, "parses a SIMUSDT simulation request with integer minor units");
  assert(parseSimulatedQrRequest("https://exaearn.com/pay?to=monica&amount=10&ref=QR-123", now) === null, "rejects untrusted QR origins");
  assert(parseSimulatedQrRequest("exaearn://pay?to=a&to=b&amount=10&ref=QR-123", now) === null, "rejects duplicate recipient parameters");
  assert(parseSimulatedQrRequest("exaearn://pay?to=monica&amount=10.001&ref=QR-123", now) === null, "rejects unsupported monetary precision");
  assert(parseSimulatedQrRequest("exaearn://pay?to=monica&amount=10&ref=QR-123&expires=1799999999999", now) === null, "rejects expired requests");
  assert(parseSimulatedQrRequest("exaearn://pay?to=monica&amount=10&ref=QR-123&signature=forged", now) === null, "does not accept an unverified signature parameter as proof");
  return "Offline QR input checks passed (origin, duplicate fields, precision, expiry, and signature-field rejection; syntax only, no identity verification).";
}
