export type NearbyTrustState = "trusted-simulation" | "unverified";
export type NearbyTransferState =
  | "PREPARED"
  | "COMMITTED"
  | "PENDING_RECONCILIATION"
  | "FAILED"
  | "RECOVERY_REQUIRED"
  | "REJECTED";

export type NearbyDevice = {
  id: string;
  name: string;
  trust: NearbyTrustState;
  signalQuality: "Excellent" | "Good" | "Fair";
  isAvailable: boolean;
  source: "simulation";
};

export type IncomingTransferRequest = {
  id: string;
  reference: string;
  amountMinor: number;
  senderDeviceId: string;
  senderName: string;
  method: "Bluetooth";
  state: NearbyTransferState;
};

export type NearbyHistoryItem = {
  id: string;
  reference: string;
  amountMinor: number;
  title: string;
  state: NearbyTransferState;
  timestamp: string;
};

export type NearbyConnectionState = "disconnected" | "connecting" | "connected" | "failed";

export type NearbyDeviceAdapter = {
  discoverDevices: () => Promise<NearbyDevice[]>;
  requestBluetoothPermission: () => Promise<{
    granted: false;
    status: "unsupported";
    message: string;
  }>;
  connectToDevice: (deviceId: string) => Promise<NearbyConnectionState>;
  disconnect: () => NearbyConnectionState;
  retryConnection: (deviceId: string) => Promise<NearbyConnectionState>;
  getIncomingRequest: () => IncomingTransferRequest | null;
  getHistory: () => NearbyHistoryItem[];
  authenticateIncomingRequest: (reference: string) => Promise<{
    authenticated: boolean;
    simulation: true;
  }>;
  acceptIncomingRequest: (reference: string) => Promise<{
    state: NearbyTransferState;
    simulation: true;
    balanceCredited: false;
  }>;
  rejectIncomingRequest: (reference: string) => Promise<{
    state: NearbyTransferState;
    simulation: true;
    balanceCredited: false;
  }>;
  simulateConnectivityLoss: () => void;
  restoreConnectivityForSimulation: () => void;
};

const sampleDevices: NearbyDevice[] = [];

function createReference() {
  const timestamp = Date.now().toString(36).toUpperCase();
  const entropy = Math.random().toString(36).slice(2, 8).toUpperCase();
  return `NP-${timestamp}-${entropy}`;
}

function createIncomingRequest(): IncomingTransferRequest | null { return null; }

function createInitialHistory(): NearbyHistoryItem[] { return []; }

const wait = (milliseconds: number) => new Promise<void>((resolve) => setTimeout(resolve, milliseconds));

export function createNearbyDeviceSimulator(): NearbyDeviceAdapter {
  let connectionState: NearbyConnectionState = "disconnected";
  let connectedDeviceId: string | null = null;
  let incomingRequest: IncomingTransferRequest | null = createIncomingRequest();
  const history = createInitialHistory();
  const authenticatedReferences = new Set<string>();
  const decisions = new Map<string, NearbyTransferState>();
  const pendingOperations = new Map<string, Promise<{
    state: NearbyTransferState;
    simulation: true;
    balanceCredited: false;
  }>>();
  let connectivityAvailable = true;

  const addHistory = (request: IncomingTransferRequest, state: NearbyTransferState) => {
    if (history.some((item) => item.reference === request.reference)) return;
    history.unshift({
      id: `sim-history-${request.id}`,
      reference: request.reference,
      amountMinor: request.amountMinor,
      title: state === "REJECTED" ? "Incoming transfer declined" : "Incoming transfer",
      state,
      timestamp: new Date().toISOString(),
    });
  };

  return {
    async discoverDevices() {
      await wait(260);
      return sampleDevices.map((device) => ({ ...device }));
    },

    async requestBluetoothPermission() {
      return {
        granted: false,
        status: "unsupported",
        message: "Native Bluetooth transport is not available in this build. Nearby payments are disabled; use the signed QR flow instead.",
      };
    },

    async connectToDevice(deviceId) {
      connectionState = "connecting";
      connectivityAvailable = true;
      await wait(240);
      const device = sampleDevices.find((item) => item.id === deviceId);
      if (!device || !device.isAvailable || !connectivityAvailable) {
        connectedDeviceId = null;
        connectionState = "failed";
        return connectionState;
      }
      connectedDeviceId = deviceId;
      connectionState = "connected";
      return connectionState;
    },

    disconnect() {
      connectivityAvailable = false;
      connectedDeviceId = null;
      connectionState = "disconnected";
      if (incomingRequest) authenticatedReferences.delete(incomingRequest.reference);
      return connectionState;
    },

    async retryConnection(deviceId) {
      return this.connectToDevice(deviceId);
    },

    getIncomingRequest() {
      return incomingRequest ? { ...incomingRequest } : null;
    },

    getHistory() {
      return history.map((item) => ({ ...item }));
    },

    async authenticateIncomingRequest(reference) {
      await wait(240);
      const request = incomingRequest;
      const isConnected = connectionState === "connected"
        && connectedDeviceId === request?.senderDeviceId
        && connectivityAvailable;
      const authenticated = Boolean(
        request
        && request.reference === reference
        && request.state === "PREPARED"
        && isConnected
        && sampleDevices.find((device) => device.id === request.senderDeviceId)?.trust === "trusted-simulation",
      );
      if (authenticated) authenticatedReferences.add(reference);
      return { authenticated, simulation: true };
    },

    async acceptIncomingRequest(reference) {
      const existingOperation = pendingOperations.get(reference);
      if (existingOperation) return existingOperation;
      const previousDecision = decisions.get(reference);
      if (previousDecision) {
        return { state: previousDecision, simulation: true, balanceCredited: false };
      }

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
          const state: NearbyTransferState = "FAILED";
          decisions.set(reference, state);
          return { state, simulation: true as const, balanceCredited: false as const };
        }

        request.state = "PREPARED";
        await wait(260);
        if (!connectivityAvailable || connectionState !== "connected") {
          request.state = "FAILED";
          decisions.set(reference, request.state);
          addHistory(request, request.state);
          incomingRequest = null;
          return { state: request.state, simulation: true as const, balanceCredited: false as const };
        }

        request.state = "COMMITTED";
        decisions.set(reference, request.state);
        await wait(180);
        if (!connectivityAvailable || connectionState !== "connected") {
          request.state = "RECOVERY_REQUIRED";
          decisions.set(reference, request.state);
          addHistory(request, request.state);
          incomingRequest = null;
          return { state: request.state, simulation: true as const, balanceCredited: false as const };
        }
        request.state = "PENDING_RECONCILIATION";
        addHistory(request, request.state);
        incomingRequest = null;
        return { state: request.state, simulation: true as const, balanceCredited: false as const };
      })();

      pendingOperations.set(reference, operation);
      try {
        return await operation;
      } finally {
        pendingOperations.delete(reference);
      }
    },

    async rejectIncomingRequest(reference) {
      const existingOperation = pendingOperations.get(reference);
      if (existingOperation) return existingOperation;
      const previousDecision = decisions.get(reference);
      if (previousDecision) {
        return { state: previousDecision, simulation: true, balanceCredited: false };
      }
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

    restoreConnectivityForSimulation() {
      connectivityAvailable = true;
      connectionState = "disconnected";
      connectedDeviceId = null;
    },
  };
}
