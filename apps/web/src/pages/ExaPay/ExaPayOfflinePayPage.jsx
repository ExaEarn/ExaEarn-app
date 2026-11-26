import { useEffect, useMemo, useState } from "react";
import {
  AlertCircle,
  ArrowLeft,
  ArrowRight,
  Bluetooth,
  Check,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  Info,
  LoaderCircle,
  QrCode,
  Radio,
  RefreshCw,
  ShieldCheck,
  Smartphone,
  X,
} from "lucide-react";
import exaearnLogo from "../../assets/images/exaearn-logo.png";
import {
  createExaPayOfflineSimulator,
  createSimulatedTransferReference,
  formatSimulatedAmount,
  getSimulatedOfflineBalanceMinor,
  hasSimulatedRecoveryRequired,
  MAX_SIMULATED_PAYMENT_MINOR,
  parseSimulatedAmount,
} from "../../services/exaPayOfflineSimulator.js";
import { createPaymentReference } from "../../services/exaPayPaymentReviewModel.js";
import "./ExaPayOfflinePayPage.css";

const methods = [
  { id: "bluetooth", label: "Bluetooth", icon: Bluetooth },
  { id: "qr", label: "QR", icon: QrCode },
  { id: "nearby", label: "Nearby Devices", icon: Radio },
];

const scenarios = [
  { id: "success", label: "Complete successfully", note: "Simulation only" },
  { id: "recipient-rejected", label: "Recipient rejects", note: "No value committed" },
  { id: "authentication-failed", label: "Authentication fails", note: "No value committed" },
  { id: "connection-interrupted", label: "Connection interrupted", note: "Before commitment" },
  { id: "timeout", label: "Recipient times out", note: "No value committed" },
  { id: "recovery-required", label: "Commit becomes uncertain", note: "Recovery required" },
];

const amountErrors = {
  empty: "Enter an amount to continue.",
  invalid: "Enter a valid amount using numbers and a decimal point.",
  negative: "Amount must be greater than zero.",
  zero: "Amount must be greater than zero.",
  precision: "Use no more than 2 decimal places for simUSDT.",
  maximum: "The simulation limit is 100.00 simUSDT per transfer.",
  insufficient: "Your available offline balance is not enough for this amount.",
};

function StatusPill({ children, tone = "neutral" }) {
  return (
    <span className={`exa-offline__pill exa-offline__pill--${tone}`}>
      <span className="exa-offline__pill-dot" aria-hidden="true" />
      {children}
    </span>
  );
}

function SectionHeading({ title, id, hint, action }) {
  return (
    <div className="exa-offline__section-heading">
      <div>
        <h2 id={id}>{title}</h2>
        {hint ? <p>{hint}</p> : null}
      </div>
      {action}
    </div>
  );
}

function DetailRow({ label, value, last = false }) {
  return (
    <div className={`exa-offline__detail-row${last ? " is-last" : ""}`}>
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

function ModalSheet({ open, title, onClose, children }) {
  if (!open) return null;
  return (
    <div
      className="exa-offline__scrim"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
    >
      <section
        className="exa-offline__sheet"
        role="dialog"
        aria-modal="true"
        aria-labelledby="exa-offline-sheet-title"
      >
        <div className="exa-offline__sheet-handle" aria-hidden="true" />
        <div className="exa-offline__sheet-header">
          <h2 id="exa-offline-sheet-title">{title}</h2>
          <button type="button" className="exa-offline__icon-button" onClick={onClose} aria-label="Close">
            <X size={18} />
          </button>
        </div>
        {children}
      </section>
    </div>
  );
}

function RecipientRow({ device, selected, onSelect, onInfo }) {
  return (
    <div className={`exa-offline__device-row${selected ? " is-selected" : ""}`}>
      <button
        type="button"
        className="exa-offline__device-select"
        onClick={onSelect}
        disabled={!device.available}
        aria-label={`Select ${device.name}${device.available ? "" : ", unavailable"}`}
      >
        <span className="exa-offline__device-icon"><Smartphone size={18} /></span>
        <span className="exa-offline__device-copy">
          <strong>{device.name}</strong>
          <small>{device.shortId} · Simulation</small>
        </span>
        <StatusPill tone={device.available ? "simulation" : "unavailable"}>
          {device.available ? "Sample" : "Unavailable"}
        </StatusPill>
      </button>
      <button type="button" className="exa-offline__device-info" onClick={onInfo} aria-label={`Details for ${device.name}`}>
        <Info size={18} />
      </button>
    </div>
  );
}

export default function ExaPayOfflinePayPage({ initialMethod = "bluetooth", initialDraft, onBack, onReview }) {
  const simulator = useMemo(() => createExaPayOfflineSimulator(), []);
  const initialOfflineDraft = initialDraft?.channel === "bluetooth" ? initialDraft : null;
  const [method, setMethod] = useState(initialMethod);
  const [devices, setDevices] = useState([]);
  const [recipientId, setRecipientId] = useState(
    initialOfflineDraft?.offlineTransfer?.recipient.id || "sim-device-mathias",
  );
  const [verifiedDevices, setVerifiedDevices] = useState(() => (
    initialOfflineDraft?.offlineTransfer
      ? { [initialOfflineDraft.offlineTransfer.recipient.id]: true }
      : {}
  ));
  const [verifying, setVerifying] = useState(false);
  const [deviceError, setDeviceError] = useState("");
  const [discovering, setDiscovering] = useState(false);
  const [discoveryError, setDiscoveryError] = useState("");
  const [amount, setAmount] = useState(
    initialOfflineDraft ? formatSimulatedAmount(initialOfflineDraft.amountMinor) : "30.00",
  );
  const [amountTouched, setAmountTouched] = useState(false);
  const [attemptedReview, setAttemptedReview] = useState(false);
  const [availableMinor] = useState(getSimulatedOfflineBalanceMinor);
  const [recoveryBlocked] = useState(hasSimulatedRecoveryRequired);
  const [detailsOpen, setDetailsOpen] = useState(false);
  const [sheet, setSheet] = useState(null);
  const [scenario, setScenario] = useState(initialOfflineDraft?.offlineTransfer?.scenario || "success");
  const [reference, setReference] = useState(
    () => initialOfflineDraft?.reference || createSimulatedTransferReference(),
  );
  const [formError, setFormError] = useState("");

  const recipient = useMemo(
    () => devices.find((device) => device.id === recipientId) ?? null,
    [devices, recipientId],
  );
  const recipientVerified = Boolean(recipient && verifiedDevices[recipient.id]);
  const validation = useMemo(
    () => parseSimulatedAmount(amount, availableMinor),
    [amount, availableMinor],
  );
  const remainingMinor = validation.valid ? availableMinor - validation.amountMinor : null;
  const showAmountError = amountTouched || attemptedReview;
  const amountError = showAmountError && !validation.valid
    ? amountErrors[validation.reason] || "Check the amount and try again."
    : "";
  const mustStopForRecovery = recoveryBlocked;
  const selectedScenario = scenarios.find((item) => item.id === scenario) || scenarios[0];

  useEffect(() => {
    let active = true;
    simulator.discoverNearbyDevices()
      .then((found) => {
        if (active) setDevices(found);
      })
      .catch(() => {
        if (active) setDeviceError("Sample recipient devices could not be loaded. Try again.");
      });
    return () => {
      active = false;
    };
  }, [simulator]);

  useEffect(() => {
    const handleKeyDown = (event) => {
      if (event.key !== "Escape") return;
      if (sheet) {
        setSheet(null);
        return;
      }
      onBack();
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [onBack, sheet]);

  async function discoverDevices() {
    setDiscovering(true);
    setDiscoveryError("");
    try {
      setDevices(await simulator.discoverNearbyDevices());
    } catch {
      setDiscoveryError("Sample devices could not be loaded. Try again.");
    } finally {
      setDiscovering(false);
    }
  }

  async function verifyRecipient() {
    if (!recipient || !recipient.available || verifying) return;
    setVerifying(true);
    setDeviceError("");
    try {
      const verification = await simulator.verifyRecipient(recipient);
      if (!verification.verified) {
        setDeviceError("This sample device is unavailable and cannot be verified.");
        return;
      }
      setVerifiedDevices((previous) => ({ ...previous, [recipient.id]: true }));
    } catch {
      setDeviceError("The simulation could not verify this recipient.");
    } finally {
      setVerifying(false);
    }
  }

  function chooseRecipient(device) {
    setRecipientId(device.id);
    setFormError("");
    setSheet(null);
  }

  function openReview() {
    setAttemptedReview(true);
    setFormError("");
    if (method === "qr") {
      setFormError("QR payments are not connected in this web build. No scanner or real payment flow was started.");
      return;
    }
    if (!recipient) {
      setFormError("Select a sample recipient before continuing.");
      return;
    }
    if (!recipient.available) {
      setFormError("This recipient is unavailable. Choose an available sample device.");
      return;
    }
    if (!recipientVerified) {
      setFormError("Verify the recipient for this simulation before continuing.");
      return;
    }
    if (!validation.valid) {
      setFormError(amountErrors[validation.reason] || "Check the amount and try again.");
      return;
    }
    if (mustStopForRecovery) {
      setFormError("A previous transfer requires recovery. Do not submit another transfer.");
      return;
    }
    const paymentReference = createSimulatedTransferReference();
    setReference(paymentReference);
    onReview({
      idempotencyKey: createPaymentReference("IDEM"),
      reference: paymentReference,
      channel: "bluetooth",
      sourceAccount: { label: "ExaEarn Offline Wallet" },
      recipient: {
        id: recipient.id,
        displayName: recipient.name,
        identifier: recipient.shortId,
        initials: recipient.name.split(/\s+/).map((part) => part[0]?.toUpperCase() || "").join("").slice(0, 2),
        verification: "simulation-verified",
      },
      amountMinor: validation.amountMinor,
      feeMinor: 0,
      asset: { symbol: "simUSDT", precision: 2, network: "Bluetooth · simulation" },
      message: "",
      createdAt: Date.now(),
      expiresAt: null,
      simulation: true,
      offlineTransfer: {
        recipient,
        recipientVerifiedForSimulation: recipientVerified,
        method: "bluetooth",
        scenario,
      },
    });
  }

  function selectMethod(nextMethod) {
    setMethod(nextMethod);
    setFormError("");
    if (nextMethod === "nearby") void discoverDevices();
  }

  const connectivityText = "Not connected";

  return (
    <main className="exa-offline">
      <div className="exa-offline__page">
        <header className="exa-offline__header">
          <button type="button" className="exa-offline__back" onClick={onBack}>
            <ArrowLeft size={18} aria-hidden="true" />
            <span>Back</span>
          </button>
          <div className="exa-offline__brand">
            <img src={exaearnLogo} alt="ExaEarn" />
            <span><strong>ExaEarn</strong><small>EXAPAY</small></span>
          </div>
          <span className="exa-offline__simulation"><i aria-hidden="true" /> Simulation Mode</span>
        </header>

        <section className="exa-offline__intro" aria-labelledby="offline-pay-title">
          <div className="exa-offline__intro-icon"><Bluetooth size={21} aria-hidden="true" /></div>
          <div>
            <h1 id="offline-pay-title">Offline Pay</h1>
            <p>Explore a simulated nearby-device transfer; no live device connection is used.</p>
          </div>
        </section>

        <div className="exa-offline__layout">
          <div className="exa-offline__main-column">
            <section className="exa-offline__panel" aria-labelledby="offline-method-heading">
              <SectionHeading title="Payment method" id="offline-method-heading" />
              <div className="exa-offline__methods" role="tablist" aria-label="Offline payment method">
                {methods.map((item) => {
                  const Icon = item.icon;
                  return (
                    <button
                      type="button"
                      key={item.id}
                      role="tab"
                      aria-selected={method === item.id}
                      className={`exa-offline__method${method === item.id ? " is-active" : ""}`}
                      onClick={() => selectMethod(item.id)}
                    >
                      <Icon size={17} aria-hidden="true" />
                      <span>{item.label}</span>
                    </button>
                  );
                })}
              </div>
              {method === "qr" ? (
                <div className="exa-offline__method-notice" role="status">
                  <QrCode size={18} aria-hidden="true" />
                  <p>QR payments are not connected in this web build. No camera or real payment flow will start.</p>
                </div>
              ) : null}
            </section>

            {method === "nearby" ? (
              <section className="exa-offline__panel" aria-labelledby="offline-nearby-heading">
                <SectionHeading
                  title="Nearby Devices"
                  id="offline-nearby-heading"
                  hint="Simulation list · no Bluetooth scan is running"
                  action={
                    <button
                      type="button"
                      className="exa-offline__refresh"
                      onClick={() => void discoverDevices()}
                      disabled={discovering}
                      aria-label="Refresh simulated devices"
                    >
                      {discovering ? <LoaderCircle size={17} className="is-spinning" /> : <RefreshCw size={17} />}
                    </button>
                  }
                />
                {discoveryError ? <p className="exa-offline__error">{discoveryError}</p> : null}
                <div className="exa-offline__device-list">
                  {devices.map((device) => (
                    <RecipientRow
                      key={device.id}
                      device={device}
                      selected={device.id === recipientId}
                      onSelect={() => chooseRecipient(device)}
                      onInfo={() => {
                        setRecipientId(device.id);
                        setSheet("deviceInfo");
                      }}
                    />
                  ))}
                  {!devices.length && discovering ? <p className="exa-offline__loading">Loading sample devices…</p> : null}
                </div>
              </section>
            ) : null}

            <section className="exa-offline__panel" aria-labelledby="offline-recipient-heading">
              <SectionHeading
                title="Recipient Device"
                id="offline-recipient-heading"
                hint="Sample recipient · simulation only"
                action={
                  <button type="button" className="exa-offline__change" onClick={() => setSheet("recipient")}>
                    Change <span aria-hidden="true">›</span>
                  </button>
                }
              />
              {recipient ? (
                <div className="exa-offline__recipient-card">
                  <span className="exa-offline__recipient-icon"><Smartphone size={20} aria-hidden="true" /></span>
                  <div className="exa-offline__recipient-copy">
                    <strong>{recipient.name}</strong>
                    <span>{recipient.product}</span>
                    <small>ID: {recipient.shortId}</small>
                    <div className="exa-offline__recipient-status">
                      <StatusPill tone={recipientVerified ? "simulation" : "pending"}>
                        {recipientVerified ? "Verified for simulation" : "Not verified"}
                      </StatusPill>
                      {!recipient.available ? <StatusPill tone="unavailable">Unavailable</StatusPill> : null}
                    </div>
                  </div>
                  <button
                    type="button"
                    className="exa-offline__info-button"
                    onClick={() => setSheet("deviceInfo")}
                    aria-label="Device information"
                  >
                    <Info size={19} />
                  </button>
                </div>
              ) : (
                <p className="exa-offline__loading">Preparing sample recipient…</p>
              )}
              {deviceError ? <p className="exa-offline__error">{deviceError}</p> : null}
              {recipient && !recipientVerified ? (
                <button
                  type="button"
                  className="exa-offline__verify"
                  onClick={() => void verifyRecipient()}
                  disabled={!recipient.available || verifying}
                >
                  {verifying ? <LoaderCircle size={16} className="is-spinning" /> : <ShieldCheck size={16} />}
                  Verify for simulation
                </button>
              ) : null}
              <p className="exa-offline__disclaimer">This sample identity has not been verified by a live device session.</p>
            </section>

            <section className="exa-offline__panel" aria-labelledby="offline-amount-heading">
              <div className="exa-offline__section-heading">
                <h2 id="offline-amount-heading">Payment Amount</h2>
                <span className="exa-offline__asset-tag">SIMULATED ASSET</span>
              </div>
              <div className={`exa-offline__amount-input${amountError ? " has-error" : ""}`}>
                <input
                  type="text"
                  inputMode="decimal"
                  autoComplete="off"
                  value={amount}
                  onChange={(event) => {
                    setAmount(event.target.value);
                    setAmountTouched(true);
                    setFormError("");
                  }}
                  onBlur={() => setAmountTouched(true)}
                  disabled={mustStopForRecovery}
                  aria-label="Payment amount in simulated USDT"
                  aria-describedby={amountError ? "offline-amount-error" : undefined}
                  placeholder="0.00"
                />
                <span>simUSDT</span>
                <button
                  type="button"
                  onClick={() => {
                    setAmount(formatSimulatedAmount(Math.min(availableMinor, MAX_SIMULATED_PAYMENT_MINOR)));
                    setAmountTouched(true);
                  }}
                  disabled={!availableMinor || mustStopForRecovery}
                >
                  MAX
                </button>
              </div>
              {amountError ? <p className="exa-offline__error" id="offline-amount-error">{amountError}</p> : null}
              <div className="exa-offline__balance-summary">
                <div>
                  <span>Offline available balance</span>
                  <strong>{formatSimulatedAmount(availableMinor)} simUSDT</strong>
                </div>
                <i aria-hidden="true" />
                <div>
                  <span>Remaining after payment</span>
                  <strong className={remainingMinor === null ? "is-muted" : "is-blue"}>
                    {remainingMinor === null ? "--" : `${formatSimulatedAmount(remainingMinor)} simUSDT`}
                  </strong>
                </div>
              </div>
              <p className="exa-offline__limit">Maximum per simulated transfer: 100.00 simUSDT</p>
            </section>

            <section className="exa-offline__panel" aria-labelledby="offline-details-heading">
              <button
                type="button"
                className="exa-offline__expand"
                onClick={() => setDetailsOpen((open) => !open)}
                aria-expanded={detailsOpen}
              >
                <span><strong id="offline-details-heading">Transfer Details</strong><small>Reference and transfer state</small></span>
                {detailsOpen ? <ChevronUp size={18} /> : <ChevronDown size={18} />}
              </button>
              {detailsOpen ? (
                <div className="exa-offline__detail-list">
                  <DetailRow label="Transfer Reference" value={reference} />
                  <DetailRow label="Device Authentication" value="Not configured for simulation" />
                  <DetailRow label="Transfer Type" value="Offline device-to-device" />
                  <DetailRow
                    label="Remaining Offline Balance"
                    value={remainingMinor === null ? "--" : `${formatSimulatedAmount(remainingMinor)} simUSDT`}
                  />
                  <DetailRow label="Commit status" value="Not started" />
                  <div className="exa-offline__scenario">
                    <span><small>Simulation scenario</small><strong>{selectedScenario.label}</strong></span>
                    <button type="button" className="exa-offline__change" onClick={() => setSheet("scenario")}>Change</button>
                  </div>
                  <p className="exa-offline__technical-note">
                    Simulation references are temporary and do not represent a signed or settled ledger record.
                  </p>
                </div>
              ) : null}
            </section>

            <section className="exa-offline__connection">
              <div className="exa-offline__connection-heading">
                <span className="exa-offline__connection-icon"><Bluetooth size={19} aria-hidden="true" /></span>
                <div><h2>Offline Transfer</h2><p>Bluetooth can communicate without internet. This screen uses a simulator; no Bluetooth transfer is active.</p></div>
              </div>
              <div className="exa-offline__connection-states">
                <DetailRow label="Bluetooth status" value={connectivityText} />
                <DetailRow label="Recipient identity" value={recipientVerified ? "Verified for simulation only" : "Not verified"} last />
              </div>
              <p className="exa-offline__reconciliation">
                Device communication is not monetary finality. Any future real transfer would require the supported protocol and reconciliation before value becomes available online.
              </p>
            </section>

            {formError ? <p className="exa-offline__form-error" role="alert"><AlertCircle size={16} />{formError}</p> : null}
            {mustStopForRecovery ? (
              <p className="exa-offline__form-error" role="alert">
                <AlertCircle size={16} />Further transfers are blocked until the uncertain state is reconciled.
              </p>
            ) : null}
            <button
              type="button"
              className="exa-offline__confirm"
              onClick={openReview}
              disabled={mustStopForRecovery}
            >
              <ArrowRight size={18} aria-hidden="true" />
              Review Payment
            </button>
            <button type="button" className="exa-offline__cancel-page" onClick={onBack}>
              Cancel
            </button>
            <p className="exa-offline__footnote">Simulation only. No real USDT, wallet ledger, or Bluetooth device is used.</p>
          </div>

          <aside className="exa-offline__side-column">
            <section className="exa-offline__side-card">
              <div className="exa-offline__side-icon exa-offline__side-icon--violet"><Bluetooth size={19} /></div>
              <h2>Bluetooth transfer</h2>
              <p>This simulator models a nearby-device flow without starting a Bluetooth connection.</p>
              <ul>
                <li><Check size={14} /> No internet connection required for Bluetooth communication</li>
                <li><Check size={14} /> Recipient identity must be verified</li>
                <li><Check size={14} /> Reconciliation is required before online availability</li>
              </ul>
            </section>
            <section className="exa-offline__side-card exa-offline__side-card--notice">
              <div className="exa-offline__side-icon exa-offline__side-icon--amber"><Info size={18} /></div>
              <h2>Simulation safeguards</h2>
              <p>Displayed devices and value are local sample data. This browser does not request Bluetooth or camera permissions.</p>
              <StatusPill tone="pending">No real funds</StatusPill>
            </section>
          </aside>
        </div>
      </div>

      <ModalSheet open={sheet === "recipient"} title="Choose a recipient" onClose={() => setSheet(null)}>
        <p className="exa-offline__sheet-description">These sample entries do not come from Bluetooth discovery.</p>
        <div className="exa-offline__device-list">
          {devices.map((device) => (
            <RecipientRow
              key={device.id}
              device={device}
              selected={device.id === recipientId}
              onSelect={() => chooseRecipient(device)}
              onInfo={() => {
                setRecipientId(device.id);
                setSheet("deviceInfo");
              }}
            />
          ))}
        </div>
      </ModalSheet>

      <ModalSheet open={sheet === "deviceInfo"} title="Device information" onClose={() => setSheet(null)}>
        {recipient ? (
          <div className="exa-offline__device-info-card">
            <strong>{recipient.name}</strong>
            <span>{recipient.product}</span>
            <DetailRow label="Sample device ID" value={recipient.shortId} />
            <DetailRow label="Availability" value={recipient.available ? "Available in simulation" : "Unavailable in simulation"} />
            <DetailRow label="Verification" value={recipientVerified ? "Verified for simulation" : "Not verified"} last />
            <p>This is sample data. Device availability and verification are not backed by a live Bluetooth session.</p>
          </div>
        ) : null}
      </ModalSheet>

      <ModalSheet open={sheet === "scenario"} title="Simulation outcome" onClose={() => setSheet(null)}>
        <p className="exa-offline__sheet-description">Choose a test result to preview transfer handling. These outcomes do not contact a device.</p>
        <div className="exa-offline__scenario-list">
          {scenarios.map((option) => (
            <button
              type="button"
              key={option.id}
              className={`exa-offline__scenario-option${scenario === option.id ? " is-selected" : ""}`}
              onClick={() => {
                setScenario(option.id);
                setSheet(null);
              }}
              aria-pressed={scenario === option.id}
            >
              <span><strong>{option.label}</strong><small>{option.note}</small></span>
              {scenario === option.id ? <CheckCircle2 size={19} /> : null}
            </button>
          ))}
        </div>
      </ModalSheet>

    </main>
  );
}
