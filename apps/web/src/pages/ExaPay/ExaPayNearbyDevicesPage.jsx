import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  AlertCircle,
  ArrowDownLeft,
  ArrowLeft,
  ArrowUpRight,
  Bluetooth,
  Check,
  CheckCircle2,
  ChevronRight,
  Info,
  LoaderCircle,
  QrCode,
  Radio,
  RefreshCw,
  ShieldCheck,
  Smartphone,
  Wifi,
  X,
} from "lucide-react";
import exaearnLogo from "../../assets/images/exaearn-logo.png";
import { createExaPayNearbySimulator } from "../../services/exaPayNearbySimulator.js";
import "./ExaPayNearbyDevicesPage.css";

const transferStates = {
  PREPARED: "Awaiting Confirmation",
  COMMITTED: "Offline Committed",
  PENDING_RECONCILIATION: "Pending Reconciliation",
  FAILED: "Failed",
  RECOVERY_REQUIRED: "Recovery Required",
  REJECTED: "Declined",
};

function formatAmount(amountMinor) {
  return `${Math.floor(amountMinor / 100)}.${String(amountMinor % 100).padStart(2, "0")}`;
}

function formatTime(timestamp) {
  return new Date(timestamp).toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
}

function Badge({ children, tone = "muted" }) {
  return (
    <span className={`exa-nearby__badge exa-nearby__badge--${tone}`}>
      <i aria-hidden="true" />
      {children}
    </span>
  );
}

function SectionHeader({ title, id, hint, action, onAction }) {
  return (
    <div className="exa-nearby__section-heading">
      <div>
        <h2 id={id}>{title}</h2>
        {hint ? <p>{hint}</p> : null}
      </div>
      {action ? (
        <button type="button" className="exa-nearby__text-action" onClick={onAction}>
          {action}
        </button>
      ) : null}
    </div>
  );
}

function DeviceCard({ device, connectionState, connectedDeviceId, onOpen }) {
  const isConnected = connectedDeviceId === device.id && connectionState === "connected";
  const isConnecting = connectedDeviceId === device.id && connectionState === "connecting";
  const trusted = device.trust === "trusted-simulation";

  return (
    <button
      type="button"
      className="exa-nearby__device-card"
      onClick={onOpen}
      aria-label={`${device.name}, ${trusted ? "trusted sample device" : "unverified device"}, ${device.signalQuality} signal`}
    >
      <span className="exa-nearby__device-icon"><Smartphone size={19} aria-hidden="true" /></span>
      <span className="exa-nearby__device-copy">
        <strong>{device.name}</strong>
        <span className="exa-nearby__device-meta">
          <Badge tone={trusted ? "purple" : "amber"}>{trusted ? "Trusted · sample" : "Unverified"}</Badge>
          <span className="exa-nearby__signal"><Wifi size={13} aria-hidden="true" />{device.signalQuality} signal</span>
        </span>
        {isConnecting ? <small className="exa-nearby__connecting">Connecting in simulation…</small> : null}
        {isConnected ? <small className="exa-nearby__connected">Connected · simulation only</small> : null}
      </span>
      <ChevronRight className="exa-nearby__chevron" size={18} aria-hidden="true" />
    </button>
  );
}

function HistoryRow({ item, last }) {
  const tone = item.state === "COMMITTED"
    ? "green"
    : item.state === "PREPARED" || item.state === "PENDING_RECONCILIATION"
      ? "amber"
      : item.state === "FAILED" || item.state === "RECOVERY_REQUIRED"
        ? "red"
        : "muted";

  return (
    <li className={`exa-nearby__history-row${last ? " is-last" : ""}`}>
      <span className="exa-nearby__history-icon">
        {item.state === "REJECTED" || item.state === "FAILED"
          ? <ArrowDownLeft size={17} aria-hidden="true" />
          : <ArrowUpRight size={17} aria-hidden="true" />}
      </span>
      <span className="exa-nearby__history-copy">
        <strong>{item.title}</strong>
        <small>{formatTime(item.timestamp)} · {item.reference}</small>
        <Badge tone={tone}>{transferStates[item.state]}</Badge>
      </span>
      <strong className="exa-nearby__history-amount">
        {formatAmount(item.amountMinor)}
        <small>simUSDT</small>
      </strong>
    </li>
  );
}

function BottomSheet({ open, title, onClose, children }) {
  if (!open) return null;
  return (
    <div
      className="exa-nearby__scrim"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
    >
      <section
        className="exa-nearby__sheet"
        role="dialog"
        aria-modal="true"
        aria-labelledby="exa-nearby-sheet-title"
      >
        <span className="exa-nearby__sheet-handle" aria-hidden="true" />
        <header className="exa-nearby__sheet-heading">
          <h2 id="exa-nearby-sheet-title">{title}</h2>
          <button type="button" className="exa-nearby__icon-button" onClick={onClose} aria-label="Close">
            <X size={18} aria-hidden="true" />
          </button>
        </header>
        {children}
      </section>
    </div>
  );
}

export default function ExaPayNearbyDevicesPage({ onBack, onOpenQrFallback, onViewAllHistory }) {
  const simulator = useMemo(() => createExaPayNearbySimulator(), []);
  const [devices, setDevices] = useState([]);
  const [incoming, setIncoming] = useState(() => simulator.getIncomingRequest());
  const [history, setHistory] = useState(() => simulator.getHistory());
  const [permissionNote, setPermissionNote] = useState("");
  const [discoveryError, setDiscoveryError] = useState("");
  const [actionError, setActionError] = useState("");
  const [connectionState, setConnectionState] = useState("disconnected");
  const [connectedDeviceId, setConnectedDeviceId] = useState("");
  const [selectedDeviceId, setSelectedDeviceId] = useState("sim-nearby-kendrick");
  const [authenticatedReference, setAuthenticatedReference] = useState("");
  const [isDiscovering, setIsDiscovering] = useState(false);
  const [isConnecting, setIsConnecting] = useState(false);
  const [isAuthenticating, setIsAuthenticating] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [sheet, setSheet] = useState(null);
  const [lastDecision, setLastDecision] = useState(null);
  const submitLock = useRef(false);

  const selectedDevice = devices.find((device) => device.id === selectedDeviceId) || null;
  const senderConnected = Boolean(
    incoming
    && connectedDeviceId === incoming.senderDeviceId
    && connectionState === "connected",
  );
  const senderAuthenticated = Boolean(incoming && authenticatedReference === incoming.reference);

  const discoverDevices = useCallback(async () => {
    setIsDiscovering(true);
    setDiscoveryError("");
    try {
      const [found, permission] = await Promise.all([
        simulator.discoverDevices(),
        simulator.requestBluetoothPermission(),
      ]);
      setDevices(found);
      setPermissionNote(permission.message);
    } catch {
      setDiscoveryError("Sample devices could not be loaded. Try refreshing.");
    } finally {
      setIsDiscovering(false);
    }
  }, [simulator]);

  useEffect(() => {
    void discoverDevices();
  }, [discoverDevices]);

  useEffect(() => {
    const handleKeyDown = (event) => {
      if (event.key === "Escape") {
        if (sheet) setSheet(null);
        else onBack();
      }
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [onBack, sheet]);

  async function connectToDevice(deviceId, retry = false) {
    if (isConnecting) return;
    setSelectedDeviceId(deviceId);
    setConnectionState("connecting");
    setConnectedDeviceId(deviceId);
    setIsConnecting(true);
    setActionError("");
    setPermissionNote(
      "No Bluetooth permission prompt was shown: this app has no native BLE adapter. The connection state is simulated.",
    );
    try {
      const state = retry
        ? await simulator.retryConnection(deviceId)
        : await simulator.connectToDevice(deviceId);
      setConnectionState(state);
      setConnectedDeviceId(state === "connected" ? deviceId : "");
      if (state !== "connected") {
        setActionError("The simulated connection failed. Check the sample device and retry.");
      }
    } catch {
      setConnectionState("failed");
      setConnectedDeviceId("");
      setActionError("The simulated connection failed. Retry to try again.");
    } finally {
      setIsConnecting(false);
    }
  }

  function disconnect() {
    setConnectionState(simulator.disconnect());
    setConnectedDeviceId("");
    setAuthenticatedReference("");
    setActionError("");
  }

  async function authenticateSender() {
    if (!incoming || isAuthenticating || isConnecting) return;
    setActionError("");
    if (!senderConnected) {
      await connectToDevice(incoming.senderDeviceId);
      return;
    }
    setIsAuthenticating(true);
    try {
      const result = await simulator.authenticateIncomingRequest(incoming.reference);
      if (!result.authenticated) {
        setActionError("The sender was not authenticated by this simulation.");
        return;
      }
      setAuthenticatedReference(incoming.reference);
    } catch {
      setActionError("Simulated authentication failed. The request remains unaccepted.");
    } finally {
      setIsAuthenticating(false);
    }
  }

  function openAcceptReview() {
    setActionError("");
    setSheet("incoming");
  }

  async function acceptPayment() {
    if (!incoming || !senderAuthenticated || submitLock.current) return;
    submitLock.current = true;
    setIsSubmitting(true);
    setActionError("");
    try {
      const result = await simulator.acceptIncomingRequest(incoming.reference);
      setLastDecision(result.state);
      setIncoming(simulator.getIncomingRequest());
      setHistory(simulator.getHistory());
      if (result.state === "FAILED" || result.state === "RECOVERY_REQUIRED") {
        setActionError("The request was not completed. No simulated amount was credited to a balance.");
      }
      setSheet(null);
    } catch {
      setLastDecision("RECOVERY_REQUIRED");
      setActionError("The outcome is uncertain. Stop and reconcile before retrying. No balance was credited.");
      setSheet(null);
    } finally {
      submitLock.current = false;
      setIsSubmitting(false);
    }
  }

  async function rejectPayment() {
    if (!incoming || submitLock.current) return;
    submitLock.current = true;
    setIsSubmitting(true);
    setActionError("");
    try {
      const result = await simulator.rejectIncomingRequest(incoming.reference);
      setLastDecision(result.state);
      setIncoming(simulator.getIncomingRequest());
      setHistory(simulator.getHistory());
      setSheet(null);
    } catch {
      setActionError("The sample request could not be rejected. Try again.");
    } finally {
      submitLock.current = false;
      setIsSubmitting(false);
    }
  }

  const stateMessage = lastDecision === "PENDING_RECONCILIATION"
    ? "Accepted in simulation; awaiting reconciliation. No wallet balance was credited."
    : lastDecision === "REJECTED"
      ? "The sample request was declined. No value was received."
      : lastDecision === "RECOVERY_REQUIRED"
        ? "Recovery is required. Do not retry; no wallet balance was credited."
        : "When a supported incoming request is available, its details will appear here.";

  return (
    <main className="exa-nearby">
      <div className="exa-nearby__page">
        <header className="exa-nearby__topbar">
          <button type="button" className="exa-nearby__back" onClick={onBack} aria-label="Back to ExaPay">
            <ArrowLeft size={18} aria-hidden="true" />
            <span>Back</span>
          </button>
          <div className="exa-nearby__brand">
            <img src={exaearnLogo} alt="ExaEarn" />
            <span><strong>ExaEarn</strong><small>EXAPAY</small></span>
          </div>
          <span className="exa-nearby__simulation"><i aria-hidden="true" />Simulation Mode</span>
        </header>

        <section className="exa-nearby__intro" aria-labelledby="exa-nearby-title">
          <span className="exa-nearby__intro-icon"><Bluetooth size={21} aria-hidden="true" /></span>
          <div>
            <h1 id="exa-nearby-title">Nearby Devices</h1>
            <p>Discover and connect to nearby ExaPay devices for offline payments.</p>
          </div>
        </section>

        <div className="exa-nearby__layout">
          <div className="exa-nearby__main">
            <div className="exa-nearby__notice" role="note">
              <Info size={17} aria-hidden="true" />
              <p>Sample devices only. This build has no native Bluetooth adapter, so it does not scan or request Bluetooth permissions.</p>
            </div>

            <section className="exa-nearby__panel" aria-labelledby="nearby-devices-heading">
              <SectionHeader
                title="Nearby devices"
                id="nearby-devices-heading"
                hint="Signal quality shown · distance unavailable"
                action={
                  <button
                    type="button"
                    className="exa-nearby__refresh"
                    onClick={() => void discoverDevices()}
                    disabled={isDiscovering}
                    aria-label="Refresh sample devices"
                  >
                    {isDiscovering ? <LoaderCircle size={17} className="is-spinning" /> : <RefreshCw size={17} />}
                  </button>
                }
              />
              {permissionNote ? <p className="exa-nearby__permission-note">{permissionNote}</p> : null}
              {discoveryError ? (
                <p className="exa-nearby__error" role="alert">
                  <AlertCircle size={16} />{discoveryError}
                  <button type="button" onClick={() => void discoverDevices()}>Retry</button>
                </p>
              ) : null}
              {isDiscovering && devices.length === 0 ? (
                <div className="exa-nearby__empty"><LoaderCircle className="is-spinning" size={21} /><p>Loading sample devices…</p></div>
              ) : devices.length ? (
                <div className="exa-nearby__device-list">
                  {devices.map((device) => (
                    <DeviceCard
                      key={device.id}
                      device={device}
                      connectionState={connectionState}
                      connectedDeviceId={connectedDeviceId}
                      onOpen={() => {
                        setSelectedDeviceId(device.id);
                        setActionError("");
                        setSheet("device");
                      }}
                    />
                  ))}
                </div>
              ) : !discoveryError ? (
                <div className="exa-nearby__empty">
                  <Radio size={23} aria-hidden="true" />
                  <strong>No nearby devices</strong>
                  <p>Try refreshing the sample device list. No live scan is running.</p>
                  <button type="button" className="exa-nearby__outline-action" onClick={() => void discoverDevices()}>
                    Refresh devices
                  </button>
                </div>
              ) : null}
            </section>

            <section className="exa-nearby__panel exa-nearby__incoming-panel" aria-labelledby="nearby-incoming-heading">
              <SectionHeader title="Incoming transfer" id="nearby-incoming-heading" />
              {incoming ? (
                <div className="exa-nearby__incoming">
                  <div className="exa-nearby__incoming-top">
                    <Badge tone="purple">INCOMING TRANSFER</Badge>
                    <Badge tone="amber">Awaiting Confirmation</Badge>
                  </div>
                  <p className="exa-nearby__amount">{formatAmount(incoming.amountMinor)} <span>simUSDT</span></p>
                  <span className="exa-nearby__from-label">From</span>
                  <div className="exa-nearby__sender">
                    <span className="exa-nearby__sender-icon"><Smartphone size={18} aria-hidden="true" /></span>
                    <span className="exa-nearby__sender-copy">
                      <strong>{incoming.senderName}</strong>
                      <small>Bluetooth · Sample request</small>
                    </span>
                    <Badge tone={senderAuthenticated ? "purple" : "amber"}>
                      {senderAuthenticated ? "Authenticated · simulated" : "Not authenticated"}
                    </Badge>
                  </div>
                  <div className="exa-nearby__details">
                    <div><span>Transfer reference</span><strong>{incoming.reference}</strong></div>
                    <div><span>Device authentication</span><strong>{senderAuthenticated ? "Simulation only" : "Not yet performed"}</strong></div>
                    <div><span>Transfer method</span><strong>{incoming.method} · simulated</strong></div>
                  </div>
                  <div className="exa-nearby__payment-actions">
                    <button type="button" className="exa-nearby__accept" onClick={openAcceptReview}>
                      <CheckCircle2 size={17} aria-hidden="true" />Accept Payment
                    </button>
                    <button type="button" className="exa-nearby__reject" onClick={() => { setActionError(""); setSheet("reject"); }}>
                      Reject
                    </button>
                  </div>
                  <button type="button" className="exa-nearby__qr-fallback" onClick={onOpenQrFallback}>
                    <QrCode size={16} aria-hidden="true" />QR Fallback<ChevronRight size={15} aria-hidden="true" />
                  </button>
                </div>
              ) : (
                <div className="exa-nearby__empty-request">
                  <span><Check size={18} aria-hidden="true" /></span>
                  <div>
                    <strong>{lastDecision === "RECOVERY_REQUIRED" ? "Recovery required" : lastDecision === "REJECTED" ? "Request declined" : lastDecision === "PENDING_RECONCILIATION" ? "Request accepted" : "No incoming request"}</strong>
                    <p>{stateMessage}</p>
                  </div>
                </div>
              )}
              {actionError ? <p className="exa-nearby__error" role="alert"><AlertCircle size={16} />{actionError}</p> : null}
            </section>
          </div>

          <aside className="exa-nearby__side">
            <section className="exa-nearby__panel" aria-labelledby="nearby-history-heading">
              <SectionHeader title="Offline history" id="nearby-history-heading" hint="Sample records only" action="View All" onAction={onViewAllHistory} />
              <ul className="exa-nearby__history">
                {history.slice(0, 3).map((item, index) => (
                  <HistoryRow key={item.id} item={item} last={index === Math.min(history.length, 3) - 1} />
                ))}
                {!history.length ? <li className="exa-nearby__history-empty">No offline activity yet.</li> : null}
              </ul>
            </section>
            <section className="exa-nearby__safety">
              <span><ShieldCheck size={18} aria-hidden="true" /></span>
              <div>
                <strong>Payment safety</strong>
                <p>Authentication is separate from monetary commitment. Accepting a sample request never credits spendable funds.</p>
              </div>
            </section>
            <p className="exa-nearby__footnote">
              No Bluetooth device, cryptographic identity, secure hardware, wallet ledger, or real-money transfer is used.
            </p>
          </aside>
        </div>
      </div>

      <BottomSheet
        open={sheet === "device"}
        title="Device details"
        onClose={() => setSheet(null)}
      >
        {selectedDevice ? (
          <>
            <div className="exa-nearby__sheet-device">
              <span className="exa-nearby__device-icon"><Smartphone size={20} aria-hidden="true" /></span>
              <span><strong>{selectedDevice.name}</strong><small>ExaPay device · sample entry</small></span>
            </div>
            <div className="exa-nearby__details exa-nearby__details--sheet">
              <div><span>Identity status</span><strong>{selectedDevice.trust === "trusted-simulation" ? "Trusted in simulation only" : "Unverified device"}</strong></div>
              <div><span>Signal quality</span><strong>{selectedDevice.signalQuality}</strong></div>
              <div><span>Connection status</span><strong>{connectedDeviceId === selectedDevice.id && connectionState === "connected" ? "Connected · simulated" : connectionState === "failed" && selectedDeviceId === selectedDevice.id ? "Connection failed" : "Not connected"}</strong></div>
            </div>
            <p className="exa-nearby__sheet-disclosure">
              This sample does not verify a live device identity, cryptographic proof, hardware security, or radio link.
            </p>
            {actionError ? <p className="exa-nearby__error" role="alert"><AlertCircle size={16} />{actionError}</p> : null}
            {connectedDeviceId === selectedDevice.id && connectionState === "connected" ? (
              <button type="button" className="exa-nearby__sheet-secondary" onClick={disconnect}>
                <Bluetooth size={16} aria-hidden="true" />Disconnect (simulation)
              </button>
            ) : (
              <button
                type="button"
                className="exa-nearby__sheet-primary"
                onClick={() => void connectToDevice(selectedDevice.id, connectionState === "failed")}
                disabled={isConnecting}
              >
                {isConnecting ? <LoaderCircle className="is-spinning" size={17} /> : connectionState === "failed" ? <RefreshCw size={16} /> : <Bluetooth size={16} />}
                {connectionState === "failed" ? "Retry connection (simulation)" : "Connect (simulation)"}
              </button>
            )}
          </>
        ) : null}
      </BottomSheet>

      <BottomSheet
        open={sheet === "incoming"}
        title="Review incoming payment"
        onClose={() => setSheet(null)}
      >
        {incoming ? (
          <>
            <div className="exa-nearby__review-amount">
              <Badge tone="amber">SIMULATION · NO REAL FUNDS</Badge>
              <strong>{formatAmount(incoming.amountMinor)} <small>simUSDT</small></strong>
              <span>{incoming.senderName} · {incoming.method}</span>
            </div>
            <div className="exa-nearby__details exa-nearby__details--sheet">
              <div><span>Reference</span><strong>{incoming.reference}</strong></div>
              <div><span>Device identity</span><strong>{senderAuthenticated ? "Authenticated in simulation" : "Not authenticated"}</strong></div>
              <div><span>Result if accepted</span><strong>Pending reconciliation only</strong></div>
            </div>
            <p className="exa-nearby__sheet-disclosure">
              Device authentication is a separate simulated step. Accepting records a non-spendable sample event; it does not credit your wallet or complete a real payment.
            </p>
            {actionError ? <p className="exa-nearby__error" role="alert"><AlertCircle size={16} />{actionError}</p> : null}
            {!senderAuthenticated ? (
              <button
                type="button"
                className="exa-nearby__sheet-primary"
                onClick={() => void authenticateSender()}
                disabled={isAuthenticating || isConnecting}
              >
                {isAuthenticating || isConnecting ? <LoaderCircle className="is-spinning" size={17} /> : senderConnected ? <ShieldCheck size={17} /> : <Bluetooth size={17} />}
                {isAuthenticating || isConnecting
                  ? "Connecting / authenticating…"
                  : senderConnected
                    ? "Authenticate sender (simulation)"
                    : "Connect to sender (simulation)"}
              </button>
            ) : (
              <button
                type="button"
                className="exa-nearby__accept"
                onClick={() => void acceptPayment()}
                disabled={isSubmitting || !senderConnected}
              >
                {isSubmitting ? <LoaderCircle className="is-spinning" size={17} /> : <CheckCircle2 size={17} />}
                {isSubmitting ? "Recording simulated request…" : "Accept · pending reconciliation"}
              </button>
            )}
            <button type="button" className="exa-nearby__sheet-secondary" onClick={() => { setActionError(""); setSheet("reject"); }} disabled={isSubmitting}>
              Reject request
            </button>
          </>
        ) : <p className="exa-nearby__sheet-disclosure">This request is no longer available.</p>}
      </BottomSheet>

      <BottomSheet
        open={sheet === "reject"}
        title="Reject incoming payment?"
        onClose={() => setSheet(null)}
      >
        {incoming ? (
          <>
            <div className="exa-nearby__review-amount">
              <Badge tone="amber">SIMULATED REQUEST</Badge>
              <strong>{formatAmount(incoming.amountMinor)} <small>simUSDT</small></strong>
              <span>{incoming.senderName} · {incoming.method}</span>
            </div>
            <p className="exa-nearby__sheet-disclosure">
              The request will be marked declined in local simulation history. No funds or wallet balances will change.
            </p>
            {actionError ? <p className="exa-nearby__error" role="alert"><AlertCircle size={16} />{actionError}</p> : null}
            <button type="button" className="exa-nearby__sheet-danger" onClick={() => void rejectPayment()} disabled={isSubmitting}>
              {isSubmitting ? <LoaderCircle className="is-spinning" size={17} /> : <X size={17} />}
              {isSubmitting ? "Declining…" : "Confirm rejection"}
            </button>
            <button type="button" className="exa-nearby__sheet-secondary" onClick={() => setSheet(null)} disabled={isSubmitting}>
              Keep request
            </button>
          </>
        ) : <p className="exa-nearby__sheet-disclosure">This request is no longer available.</p>}
      </BottomSheet>
    </main>
  );
}
