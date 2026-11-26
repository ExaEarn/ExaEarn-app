import { useMemo, useState } from "react";
import {
  AlertCircle,
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  Info,
  QrCode,
  Send,
} from "lucide-react";
import exaearnLogo from "../../assets/images/exaearn-logo.png";
import {
  createPaymentReference,
  formatPaymentAmount,
  parsePaymentAmount,
  parseSimulatedQrRequest,
} from "../../services/exaPayPaymentReviewModel.js";
import "./ExaPayTransferDraftPage.css";

function initialsFor(username) {
  return username
    .replace(/^@/, "")
    .split(/[._\s-]+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() || "")
    .join("") || "EX";
}

export default function ExaPayTransferDraftPage({ mode, initialDraft, onBack, onContinue }) {
  const isQr = mode === "qr";
  const initialQr = isQr && initialDraft?.channel === "qr"
    ? {
        username: initialDraft.recipient.username || "",
        amountMinor: initialDraft.amountMinor,
        reference: initialDraft.reference,
        message: initialDraft.message,
        expiresAt: initialDraft.expiresAt,
      }
    : null;
  const [recipient, setRecipient] = useState(
    !isQr && initialDraft?.channel === "online" ? initialDraft.recipient.username || "" : "",
  );
  const [amount, setAmount] = useState(
    initialDraft ? formatPaymentAmount(initialDraft.amountMinor, "") : "",
  );
  const [message, setMessage] = useState(initialDraft?.message || "");
  const [qrPayload, setQrPayload] = useState("");
  const [validatedQr, setValidatedQr] = useState(initialQr);
  const [error, setError] = useState("");
  const amountResult = useMemo(() => parsePaymentAmount(amount), [amount]);

  function submitOnline(event) {
    event.preventDefault();
    const username = recipient.trim().replace(/^@/, "");
    if (!/^[A-Za-z0-9._-]{3,30}$/.test(username)) {
      setError("Enter a valid ExaEarn username. Identity still requires server verification.");
      return;
    }
    if (!amountResult.valid) {
      setError(amountResult.reason === "precision"
        ? "Use no more than 2 decimal places."
        : amountResult.reason === "maximum"
          ? "Enter a smaller amount."
          : "Enter an amount greater than zero.");
      return;
    }

    const reference = createPaymentReference("PAY");
    onContinue({
      idempotencyKey: createPaymentReference("IDEM"),
      reference,
      channel: "online",
      sourceAccount: { label: "ExaEarn Wallet" },
      recipient: {
        id: `entered:${username.toLowerCase()}`,
        displayName: `@${username}`,
        username,
        initials: initialsFor(username),
        verification: "user-entered",
      },
      amountMinor: amountResult.amountMinor,
      feeMinor: null,
      asset: { symbol: "simUSDT", precision: 2 },
      message: message.trim(),
      createdAt: Date.now(),
      expiresAt: null,
      simulation: true,
    });
  }

  function validateQr() {
    const request = parseSimulatedQrRequest(qrPayload);
    setError("");
    if (!request) {
      setValidatedQr(null);
      setError("This sample payment request is malformed or expired. No scanner or live service is connected.");
      return;
    }
    setValidatedQr(request);
  }

  function continueQr() {
    if (!validatedQr) {
      setError("Validate the payment request before continuing.");
      return;
    }
    if (validatedQr.expiresAt !== null && Date.now() >= validatedQr.expiresAt) {
      setValidatedQr(null);
      setError("This request has expired. Ask the sender for a new one.");
      return;
    }

    const reference = validatedQr.reference;
    const simulatedRecipient = {
      id: `sim-qr-${reference}`,
      name: `@${validatedQr.username}`,
      product: "Parsed QR request · simulation",
      shortId: reference,
      available: true,
    };
    onContinue({
      idempotencyKey: createPaymentReference("IDEM"),
      reference,
      channel: "qr",
      sourceAccount: { label: "ExaEarn Offline Wallet" },
      recipient: {
        id: `qr:${reference}`,
        displayName: `@${validatedQr.username}`,
        username: validatedQr.username,
        identifier: reference,
        initials: initialsFor(validatedQr.username),
        verification: "qr-parsed",
      },
      amountMinor: validatedQr.amountMinor,
      feeMinor: 0,
      asset: { symbol: "simUSDT", precision: 2, network: "QR · simulation" },
      message: validatedQr.message,
      createdAt: Date.now(),
      expiresAt: validatedQr.expiresAt,
      simulation: true,
      offlineTransfer: {
        recipient: simulatedRecipient,
        recipientVerifiedForSimulation: true,
        method: "qr",
        scenario: "success",
      },
    });
  }

  function loadSampleRequest() {
    const reference = createPaymentReference("QR");
    const expiresAt = Date.now() + 5 * 60 * 1000;
    setQrPayload(`exaearn://pay?to=monica&amount=10.00&ref=${reference}&expires=${expiresAt}`);
    setValidatedQr(null);
    setError("");
  }

  return (
    <main className="exa-pay-draft">
      <div className="exa-pay-draft__shell">
        <header className="exa-pay-draft__header">
          <button type="button" className="exa-pay-draft__back" onClick={onBack} aria-label="Back to ExaPay">
            <ArrowLeft size={18} />
          </button>
          <div className="exa-pay-draft__brand">
            <img src={exaearnLogo} alt="" />
            <span><strong>ExaEarn</strong><small>EXAPAY</small></span>
          </div>
          <span className="exa-pay-draft__simulation"><i />Simulation</span>
        </header>

        <div className="exa-pay-draft__heading">
          <span className={isQr ? "is-violet" : ""}>{isQr ? <QrCode size={21} /> : <Send size={20} />}</span>
          <div>
            <h1>{isQr ? "QR Payment" : "Online Transfer"}</h1>
            <p>{isQr ? "Validate a request before reviewing its details." : "Enter the recipient and amount for review."}</p>
          </div>
        </div>

        {isQr ? (
          <section className="exa-pay-draft__card">
            <div className="exa-pay-draft__notice">
              <Info size={18} />
              <p>Camera scanning is not configured in this web build. Paste a request or load a sample. Parsing does not verify recipient identity.</p>
            </div>
            <label htmlFor="exa-pay-qr-payload">Payment request payload</label>
            <textarea
              id="exa-pay-qr-payload"
              value={qrPayload}
              onChange={(event) => {
                setQrPayload(event.target.value);
                setValidatedQr(null);
                setError("");
              }}
              placeholder="exaearn://pay?to=...&amount=...&ref=..."
              autoCapitalize="none"
              spellCheck="false"
            />
            <div className="exa-pay-draft__qr-actions">
              <button type="button" className="exa-pay-draft__secondary" onClick={loadSampleRequest}>Load sample request</button>
              <button type="button" className="exa-pay-draft__small-primary" onClick={validateQr}>Validate request</button>
            </div>
            {validatedQr ? (
              <div className="exa-pay-draft__validated" role="status">
                <CheckCircle2 size={20} />
                <div>
                  <strong>Request syntax validated · simulation</strong>
                  <span>@{validatedQr.username} · {formatPaymentAmount(validatedQr.amountMinor)} </span>
                  <small>Recipient identity is not verified.</small>
                </div>
              </div>
            ) : null}
          </section>
        ) : (
          <form className="exa-pay-draft__card" onSubmit={submitOnline}>
            <label htmlFor="exa-pay-recipient">Recipient ExaEarn username</label>
            <input
              id="exa-pay-recipient"
              value={recipient}
              onChange={(event) => { setRecipient(event.target.value); setError(""); }}
              placeholder="@username"
              autoComplete="off"
              autoCapitalize="none"
            />
            <label htmlFor="exa-pay-amount">Payment amount · simUSDT</label>
            <input
              id="exa-pay-amount"
              value={amount}
              onChange={(event) => { setAmount(event.target.value); setError(""); }}
              placeholder="0.00"
              inputMode="decimal"
              autoComplete="off"
            />
            <label htmlFor="exa-pay-message">Message · optional</label>
            <input
              id="exa-pay-message"
              value={message}
              onChange={(event) => setMessage(event.target.value)}
              placeholder="Add a note"
              maxLength={100}
            />
            <p className="exa-pay-draft__helper">Recipient identity and transfer fees require an online payment service. Neither is verified or quoted in this simulation.</p>
          </form>
        )}

        {error ? (
          <div className="exa-pay-draft__error" role="alert">
            <AlertCircle size={17} />
            <span>{error}</span>
          </div>
        ) : null}

        <footer className="exa-pay-draft__footer">
          <button
            type="button"
            className="exa-pay-draft__continue"
            onClick={isQr ? continueQr : submitOnline}
          >
            Review payment <ArrowRight size={17} />
          </button>
          <p>Simulation only · No real funds will move</p>
        </footer>
      </div>
    </main>
  );
}
