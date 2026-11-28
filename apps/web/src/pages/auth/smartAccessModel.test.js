import test from "node:test";
import assert from "node:assert/strict";
import {
  passcodeIndicatorState,
  smartAccessMethodCanCreateSession,
  visibleSmartAccessMethods,
} from "./smartAccessModel.js";

test("passcode indicators always contain seven masked positions", () => {
  assert.deepEqual(passcodeIndicatorState(3), [true, true, true, false, false, false, false]);
  assert.equal(passcodeIndicatorState(99).length, 7);
  assert.ok(passcodeIndicatorState(-1).every((value) => !value));
});

test("offline access hides network-dependent methods", () => {
  assert.deepEqual(visibleSmartAccessMethods(true), ["biometric", "device-pin"]);
  assert.deepEqual(visibleSmartAccessMethods(false), ["passkey", "biometric", "device-pin", "password"]);
});

test("only the existing account-password flow can create a session", () => {
  assert.equal(smartAccessMethodCanCreateSession("password"), true);
  for (const method of ["passkey", "biometric", "device-pin", "passcode"]) {
    assert.equal(smartAccessMethodCanCreateSession(method), false);
  }
});
