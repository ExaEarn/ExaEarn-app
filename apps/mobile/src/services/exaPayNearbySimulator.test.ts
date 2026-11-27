import { createNearbyDeviceSimulator } from "./exaPayNearbySimulator";

function assert(condition: unknown, message: string): asserts condition {
  if (!condition) throw new Error(message);
}

export async function runNearbyTransportFailClosedTests() {
  const adapter = createNearbyDeviceSimulator();
  const devices = await adapter.discoverDevices();
  const permission = await adapter.requestBluetoothPermission();
  assert(devices.length === 0, "Nearby must never show fabricated devices when native BLE is absent.");
  assert(!permission.granted && permission.status === "unsupported", "A build without native BLE must report unsupported.");
  assert(adapter.getIncomingRequest() === null, "A build without native BLE must not fabricate incoming payment requests.");
  assert(adapter.getHistory().length === 0, "A build without native BLE must not fabricate transaction history.");
  assert((await adapter.authenticateIncomingRequest("reference")).authenticated === false, "Unconnected simulated peers cannot authenticate.");
  assert((await adapter.acceptIncomingRequest("reference")).balanceCredited === false, "Unavailable transport cannot mark transfers as accepted or credited.");
  return "Nearby BLE absence fail-closed checks passed.";
}
