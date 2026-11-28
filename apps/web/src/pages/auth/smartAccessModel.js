export const SMART_ACCESS_PASSCODE_LENGTH = 7;

export function visibleSmartAccessMethods(offline) {
  return offline
    ? ["biometric", "device-pin"]
    : ["passkey", "biometric", "device-pin", "password"];
}

export function passcodeIndicatorState(digitCount) {
  const count = Number.isFinite(digitCount)
    ? Math.max(0, Math.min(SMART_ACCESS_PASSCODE_LENGTH, Math.floor(digitCount)))
    : 0;
  return Array.from({ length: SMART_ACCESS_PASSCODE_LENGTH }, (_, index) => index < count);
}

/** Phase 1 has no WebAuthn or PIN API; only the existing password flow can authorize a session. */
export function smartAccessMethodCanCreateSession(method) {
  return method === "password";
}

