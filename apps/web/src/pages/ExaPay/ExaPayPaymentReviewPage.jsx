import { useEffect, useRef, useState } from "react";
import {
  AlertCircle,
  ArrowLeft,
  ArrowUp,
  Check,
  CheckCircle2,
  Info,
  LoaderCircle,
  Radio,
  Wallet,
} from "lucide-react";
import exaearnLogo from "../../assets/images/exaearn-logo.png";
import { authorizeAndSubmitPayment } from "../../services/exaPayPaymentAuthorizationAdapter.js";
import {
  calculatePaymentTotalMinor,
  formatPaymentAmount,
  validatePaymentDraft,
} from "../../services/exaPayPaymentReviewModel.js";
import "./ExaPayPaymentReviewPage.css";

function DetailRow({ label, value, last = false }) {
  return (
    <div className={`exa-review__detail-row${last ? " is-last" : ""}`}>
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

function PaymentAmountCard({ draft }) {
  return (
    <section className="exa-review__amount-card" aria-label="Payment amount">
      <span className="exa-review__amount-icon"><ArrowUp size={21} /></span>
      <strong>{formatPaymentAmount(draft.amountMinor, "")}</strong>
      <small>{draft.asset.symbol}{draft.asset.network ? ` · ${draft.asset.network}` : ""}</small>
      <span className="exa-review__simulation"><i />Simulation Mode</span>
    </section>
  );
}

function PaymentParticipantCard({ draft }) {
  const recipientDetail = draft.recipient.identifier
    || (draft.recipient.verification === "simulation-verified"
      ? "Verified in simulation only"
      : "Identity verification is not provided");

  return (
    <section className="exa-review__card exa-review__participants" aria-label="Sender and recipient">
      <div className="exa-review__eyebrow">FROM</div>
      <div className="exa-review__participant">
        <span className="exa-review__wallet-icon"><Wallet size={19} /></span>
        <div><strong>{draft.sourceAccount.label}</strong><small>{draft.sourceAccount.identifier || "ExaEarn wallet · simulation"}</small></div>
      </div>
      <div className="exa-review__participant-divider" />
      <div className="exa-review__eyebrow">TO</div>
      <div className="exa-review__participant">
        <span className="exa-review__avatar">{draft.recipient.initials || "EX"}</span>
        <div><strong>{draft.recipient.displayName}</strong><small>{recipientDetail}</small></div>
      </div>
    </section>
  );
}

function PaymentDetailsCard({ draft, totalMinor, message, onMessageChange, disabled }) {
  const isOffline = draft.channel !== "online";
  return (
    <section className="exa-review__card exa-review__details" aria-label="Payment details">
      <DetailRow
        label={draft.channel === "online" ? "Network/payment fee" : "Payment fee"}
        value={draft.feeMinor === null ? "Not quoted" : formatPaymentAmount(draft.feeMinor, draft.asset.symbol)}
      />
      <DetailRow
        label="Total deducted"
        value={totalMinor === null ? "Unavailable until fee is quoted" : formatPaymentAmount(totalMinor, draft.asset.symbol)}
      />
      <DetailRow
        label="Processing"
        value={isOffline ? "Local commitment · reconciliation required" : "Backend authorization and settlement required"}
      />
      {draft.asset.network ? <DetailRow label="Network / protocol" value={draft.asset.network} /> : null}
      <div className="exa-review__message-row">
        <label htmlFor="exa-review-message">Payment message</label>
        <input
          id="exa-review-message"
          value={message}
          onChange={(event) => onMessageChange(event.target.value)}
          placeholder="Optional"
          maxLength={100}
          disabled={disabled}
        />
      </div>
      <DetailRow label="Reference" value={draft.reference} last />
    </section>
  );
}

function PaymentConfirmationButton({ canConfirm, submitting, offline, onClick }) {
  return (
    <button
      type="button"
      className="exa-review__confirm"
      disabled={!canConfirm}
      onClick={onClick}
      aria-label={offline ? "Confirm simulated offline payment" : "Confirm online payment"}
    >
      {submitting ? <LoaderCircle size={17} className="is-spinning" /> : <Check size={18} />}
      {submitting ? "Confirming…" : "Confirm"}
    </button>
  );
}

function AuthorizationProgress({ stage, onCancel }) {
  const stages = [
    ["validating-payment", "Validating payment details"],
    ["requesting-simulation-authorization", "Requesting simulation authorization"],
    ["connecting", "Connecting to sample device"],
    ["authenticating", "Authenticating simulation"],
    ["preparing-transfer", "Preparing transfer"],
    ["awaiting-recipient", "Awaiting recipient"],
    ["committing-offline", "Preparing local commitment"],
  ];
  const activeIndex = stages.findIndex(([id]) => id === stage);
  return (
    <div className="exa-review__overlay" role="presentation">
      <section className="exa-review__progress" role="dialog" aria-modal="true" aria-labelledby="exa-review-progress-title">
        <div className="exa-review__progress-title">
          <LoaderCircle size={20} className="is-spinning" />
          <div><h2 id="exa-review-progress-title">Authorizing payment</h2><p>Local simulation · no real funds</p></div>
        </div>
        <ol>
          {stages.map(([id, label], index) => (
            <li key={id} className={index === activeIndex ? "is-current" : index < activeIndex ? "is-complete" : ""}>
              <span>{index < activeIndex ? <Check size={12} /> : null}</span>{label}
            </li>
          ))}
        </ol>
        <button type="button" className="exa-review__cancel-progress" onClick={onCancel}>Cancel authorization</button>
      </section>
    </div>
  );
}

export default function ExaPayPaymentReviewPage({ draft, onBack, onCancel, onResult }) {
  const [message, setMessage] = useState(draft.message || "");
  const [submitting, setSubmitting] = useState(false);
  const [stage, setStage] = useState(null);
  const [error, setError] = useState("");
  const [now, setNow] = useState(Date.now);
  const submissionLock = useRef(false);
  const cancellationRequested = useRef(false);

  const validation = validatePaymentDraft(draft, now);
  const totalMinor = calculatePaymentTotalMinor(draft);
  const offline = draft.channel !== "online";

  useEffect(() => {
    if (draft.expiresAt === null) return undefined;
    const timer = window.setTimeout(
      () => setNow(Date.now()),
      Math.min(Math.max(0, draft.expiresAt - Date.now()), 2_147_000_000),
    );
    return () => window.clearTimeout(timer);
  }, [draft.expiresAt, now]);

  useEffect(() => {
    function handleKeyDown(event) {
      if (event.key !== "Escape") return;
      if (submitting) {
        cancellationRequested.current = true;
        return;
      }
      onBack();
    }
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [onBack, submitting]);

  async function confirmPayment() {
    if (submissionLock.current) return;
    const currentValidation = validatePaymentDraft(draft);
    if (!currentValidation.valid) {
      setError(currentValidation.reason === "expired"
        ? "This payment request has expired."
        : "This payment draft is invalid. Review it before continuing.");
      return;
    }

    submissionLock.current = true;
    cancellationRequested.current = false;
    setError("");
    setSubmitting(true);
    setStage("validating-payment");
    try {
      const effectiveDraft = { ...draft, message: message.trim() };
      const result = await authorizeAndSubmitPayment(effectiveDraft, {
        onStage: setStage,
        shouldCancel: () => cancellationRequested.current,
      });
      onResult(effectiveDraft, result);
    } catch {
      onResult(draft, {
        channel: draft.channel,
        outcome: "recovery-required",
        reference: draft.reference,
        amountMinor: draft.amountMinor,
        simulation: true,
        onlineSettlement: false,
        message: "Authorization outcome is uncertain. Stop and reconcile before retrying.",
      });
    } finally {
      submissionLock.current = false;
      setSubmitting(false);
      setStage(null);
    }
  }

  const canConfirm = validation.valid && !submitting && draft.channel !== "online";

  return (
    <main className="exa-review">
      <div className="exa-review__shell">
        <header className="exa-review__header">
          <button type="button" className="exa-review__back" onClick={onBack} disabled={submitting} aria-label="Back">
            <ArrowLeft size={19} />
          </button>
          <h1>Review and confirm</h1>
          <div className="exa-review__brand"><img src={exaearnLogo} alt="" /><span>EXAPAY</span></div>
        </header>

        <PaymentAmountCard draft={draft} />
        <PaymentParticipantCard draft={draft} />
        <PaymentDetailsCard
          draft={draft}
          totalMinor={totalMinor}
          message={message}
          onMessageChange={setMessage}
          disabled={submitting}
        />

        {!offline ? (
          <div className="exa-review__notice exa-review__notice--amber">
            <Info size={17} />
            <p>Online transfers are unavailable in this build. No live fee quote or backend payment service is connected, so confirmation is disabled.</p>
          </div>
        ) : (
          <div className="exa-review__notice exa-review__notice--violet">
            <Radio size={18} />
            <p>A successful local commit remains pending reconciliation and is not online settlement.</p>
          </div>
        )}

        {!validation.valid || error ? (
          <div className="exa-review__error" role="alert">
            <AlertCircle size={17} />
            <span>{error || (validation.reason === "expired"
              ? "This payment request has expired."
              : "This payment draft is invalid and cannot be confirmed.")}</span>
          </div>
        ) : null}
      </div>

      <footer className="exa-review__footer">
        <div className="exa-review__footer-inner">
          <PaymentConfirmationButton
            canConfirm={canConfirm}
            submitting={submitting}
            offline={offline}
            onClick={() => void confirmPayment()}
          />
          <button type="button" className="exa-review__cancel-flow" disabled={submitting} onClick={onCancel}>Cancel</button>
        </div>
      </footer>

      {submitting ? (
        <AuthorizationProgress
          stage={stage}
          onCancel={() => { cancellationRequested.current = true; }}
        />
      ) : null}
    </main>
  );
}
