/**
 * @typedef {"trusted-simulation" | "unverified"} NearbyTrustState
 * @typedef {"PREPARED" | "COMMITTED" | "PENDING_RECONCILIATION" | "FAILED" | "RECOVERY_REQUIRED" | "REJECTED"} NearbyTransferState
 * @typedef {{ id: string, name: string, trust: NearbyTrustState, signalQuality: "Excellent" | "Good" | "Fair", available: boolean, source: "simulation" }} NearbyDevice
 * @typedef {{ id: string, reference: string, amountMinor: number, senderDeviceId: string, senderName: string, method: "Bluetooth", state: NearbyTransferState }} IncomingTransferRequest
 * @typedef {{ id: string, reference: string, amountMinor: number, title: string, state: NearbyTransferState, timestamp: string }} NearbyHistoryItem
 * @typedef {"disconnected" | "connecting" | "connected" | "failed"} NearbyConnectionState
 */

const sampleDevices = [
  {
    id: "sim-nearby-mathias",
    name: "Mathias’ Device",
    trust: "trusted-simulation",
    signalQuality: "Excellent",
    available: true,
    source: "simulation",
  },
  {
    id: "sim-nearby-kendrick",
    name: "Kendrick’s Device",
    trust: "trusted-simulation",
    signalQuality: "Good",
    available: true,
    source: "simulation",
  },
  {
    id: "sim-nearby-alex",
    name: "Alex’s Device",
    trust: "unverified",
    signalQuality: "Fair",
    available: true,
    source: "simulation",
  },
];

const wait = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

function createReference() {
  const timestamp = Date.now().toString(36).toUpperCase();
  const entropy = Math.random().toString(36).slice(2, 8).toUpperCase();
  return `NP-${timestamp}-${entropy}`;
}

function createHistory(now = Date.now()) {
  return [
    {
      id: "sim-history-001",
      reference: createReference(),
      amountMinor: 2_000,
      title: "Offline payment sent",
      state: "COMMITTED",
      timestamp: new Date(now - 22 * 60_000).toISOString(),
    },
    {
      id: "sim-history-002",
      reference: createReference(),
      amountMinor: 1_250,
      title: "Incoming transfer",
      state: "PENDING_RECONCILIATION",
      timestamp: new Date(now - 3 * 60 * 60_000).toISOString(),
    },
    {
      id: "sim-history-003",
      reference: createReference(),
      amountMinor: 500,
      title: "Incoming transfer declined",
      state: "REJECTED",
      timestamp: new Date(now - 26 * 60 * 60_000).toISOString(),
    },
  ];
}

function createRequest() {
  return {
    id: "sim-incoming-kendrick-001",
    reference: createReference(),
    amountMinor: 3_000,
    senderDeviceId: "sim-nearby-kendrick",
    senderName: "Kendrick’s Device",
    method: "Bluetooth",
    state: "PREPARED",
  };
}

/**
 * Typed integration boundary for the future native/BLE adapter.
 * The current implementation never requests browser Bluetooth permissions.
 */
export function createExaPayNearbySimulator() {
  let connectionState = "disconnected";
  let connectedDeviceId = null;
  let incomingRequest = createRequest();
  const history = createHistory();
  const authenticatedReferences = new Set();
  const decisions = new Map();
  const pendingOperations = new Map();
  let connectivityAvailable = true;

  function addHistory(request, state) {
    if (history.some((item) => item.reference === request.reference)) return;
    history.unshift({
      id: `sim-history-${request.id}`,
      reference: request.reference,
      amountMinor: request.amountMinor,
      title: state === "REJECTED" ? "Incoming transfer declined" : "Incoming transfer",
      state,
      timestamp: new Date().toISOString(),
    });
  }

  async function connectToDevice(deviceId) {
    connectionState = "connecting";
    connectivityAvailable = true;
    await wait(240);
    const device = sampleDevices.find((item) => item.id === deviceId);
    if (!device?.available || !connectivityAvailable) {
      connectionState = "failed";
      connectedDeviceId = null;
      return connectionState;
    }
    connectionState = "connected";
    connectedDeviceId = deviceId;
    return connectionState;
  }

  return {
    /** @returns {Promise<NearbyDevice[]>} */
    async discoverDevices() {
      await wait(260);
      return sampleDevices.map((device) => ({ ...device }));
    },

    async requestBluetoothPermission() {
      return {
        granted: false,
        status: "unsupported",
        message: "Native Bluetooth scanning and browser Bluetooth permissions are not configured. Only simulated devices are available.",
      };
    },

    /** @returns {Promise<NearbyConnectionState>} */
    connectToDevice,

    /** @returns {Promise<NearbyConnectionState>} */
    async retryConnection(deviceId) {
      return connectToDevice(deviceId);
    },

    /** @returns {NearbyConnectionState} */
    disconnect() {
      connectivityAvailable = false;
      connectionState = "disconnected";
      connectedDeviceId = null;
      if (incomingRequest) authenticatedReferences.delete(incomingRequest.reference);
      return connectionState;
    },

    /** @returns {NearbyConnectionState} */
    getConnectionState() {
      return connectionState;
    },

    /** @returns {string | null} */
    getConnectedDeviceId() {
      return connectedDeviceId;
    },

    /** @returns {IncomingTransferRequest | null} */
    getIncomingRequest() {
      return incomingRequest ? { ...incomingRequest } : null;
    },

    /** @returns {NearbyHistoryItem[]} */
    getHistory() {
      return history.map((item) => ({ ...item }));
    },

    async authenticateIncomingRequest(reference) {
      await wait(240);
      const request = incomingRequest;
      const connected = request
        && connectionState === "connected"
        && connectedDeviceId === request.senderDeviceId
        && connectivityAvailable;
      const authenticated = Boolean(
        request
        && request.reference === reference
        && request.state === "PREPARED"
        && connected
        && sampleDevices.find((device) => device.id === request.senderDeviceId)?.trust === "trusted-simulation",
      );
      if (authenticated) authenticatedReferences.add(reference);
      return { authenticated, simulation: true };
    },

    async acceptIncomingRequest(reference) {
      const currentOperation = pendingOperations.get(reference);
      if (currentOperation) return currentOperation;

      const priorState = decisions.get(reference);
      if (priorState) return { state: priorState, simulation: true, balanceCredited: false };

      const operation = (async () => {
        const request = incomingRequest;
        if (
          !request
          || request.reference !== reference
          || request.state !== "PREPARED"
          || !authenticatedReferences.has(reference)
          || connectionState !== "connected"
          || connectedDeviceId !== request.senderDeviceId
        ) {
          decisions.set(reference, "FAILED");
          return { state: "FAILED", simulation: true, balanceCredited: false };
        }

        await wait(260);
        if (!connectivityAvailable || connectionState !== "connected") {
          request.state = "FAILED";
          decisions.set(reference, request.state);
          addHistory(request, request.state);
          incomingRequest = null;
          return { state: request.state, simulation: true, balanceCredited: false };
        }

        request.state = "COMMITTED";
        decisions.set(reference, request.state);
        await wait(180);
        if (!connectivityAvailable || connectionState !== "connected") {
          request.state = "RECOVERY_REQUIRED";
          decisions.set(reference, request.state);
          addHistory(request, request.state);
          incomingRequest = null;
          return { state: request.state, simulation: true, balanceCredited: false };
        }

        request.state = "PENDING_RECONCILIATION";
        decisions.set(reference, request.state);
        addHistory(request, request.state);
        incomingRequest = null;
        return { state: request.state, simulation: true, balanceCredited: false };
      })();

      pendingOperations.set(reference, operation);
      try {
        return await operation;
      } finally {
        pendingOperations.delete(reference);
      }
    },

    async rejectIncomingRequest(reference) {
      const currentOperation = pendingOperations.get(reference);
      if (currentOperation) return currentOperation;
      const priorState = decisions.get(reference);
      if (priorState) return { state: priorState, simulation: true, balanceCredited: false };

      const request = incomingRequest;
      if (!request || request.reference !== reference || request.state !== "PREPARED") {
        return { state: "FAILED", simulation: true, balanceCredited: false };
      }
      request.state = "REJECTED";
      decisions.set(reference, request.state);
      addHistory(request, request.state);
      incomingRequest = null;
      await wait(120);
      return { state: request.state, simulation: true, balanceCredited: false };
    },

    simulateConnectivityLoss() {
      connectivityAvailable = false;
      connectionState = "failed";
      connectedDeviceId = null;
      if (incomingRequest) authenticatedReferences.delete(incomingRequest.reference);
    },
  };
}
