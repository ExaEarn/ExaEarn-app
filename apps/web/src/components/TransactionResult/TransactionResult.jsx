import { useEffect, useState } from "react";
import {
  AlertTriangle,
  ArrowRight,
  Check,
  Clipboard,
  Clock3,
  CloudOff,
  ExternalLink,
  ReceiptText,
  Share2,
  X,
  XCircle,
} from "lucide-react";
import "./TransactionResult.css";

const statusLabels = {
  SUCCESS: "Transaction successful",
  PROCESSING: "Transaction processing",
  PENDING: "Transaction pending",
  FAILED: "Transaction failed",
  CANCELLED: "Transaction cancelled",
  OFFLINE_COMMITTED: "Offline committed — simulation",
  RECONCILIATION_PENDING: "Pending reconciliation",
  RECOVERY_REQUIRED: "Recovery required",
  REDEEMED: "Redeemed — simulation",
};

function formatAmount(amountMinor, asset, precision = 2) {
  if (!Number.isSafeInteger(amountMinor)) return "--";
  const safePrecision = Number.isInteger(precision) ? Math.min(8, Math.max(0, precision)) : 2;
  const amount = (amountMinor / (10 ** safePrecision)).toLocaleString("en-US", {
    minimumFractionDigits: safePrecision,
    maximumFractionDigits: safePrecision,
  });
  return asset ? `${amount} ${asset}` : amount;
}

function formatDate(value) {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "Date unavailable" : date.toLocaleString();
}

function StatusIcon({ status }) {
  const Icon = status === "SUCCESS" || status === "REDEEMED"
    ? Check
    : status === "FAILED"
      ? XCircle
      : status === "CANCELLED"
        ? X
        : status === "OFFLINE_COMMITTED"
          ? CloudOff
          : status === "RECOVERY_REQUIRED"
            ? AlertTriangle
            : Clock3;
  return (
    <span className={`transaction-result__icon is-${status.toLowerCase()}`} aria-hidden="true">
      <Icon size={30} strokeWidth={1.8} />
    </span>
  );
}

export default function TransactionResult({
  transaction,
  returnLabel = "Back to Home",
  onReturn,
}) {
  const [receiptOpen, setReceiptOpen] = useState(false);
  const [feedback, setFeedback] = useState("");
  const title = statusLabels[transaction.status] || "Transaction status";
  const description = transaction.status === "SUCCESS"
    ? "Your transaction has been completed successfully."
    : transaction.status === "FAILED"
      ? "The transaction was not completed. Review its status before taking further action."
      : transaction.status === "OFFLINE_COMMITTED"
        ? "This transaction is committed locally in simulation and is not settled online."
        : transaction.status === "REDEEMED"
          ? "The simulator confirmed redemption. No real funds or authoritative ledger were changed."
          : transaction.status === "RECOVERY_REQUIRED"
            ? "Do not submit again while this transaction requires recovery."
            : "The latest transaction state is shown below. This is not a success confirmation.";

  useEffect(() => {
    function handleKeyDown(event) {
      if (event.key === "Escape") setReceiptOpen(false);
    }
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, []);

  function receiptRows() {
    const rows = [
      [transaction.referenceLabel || "Transaction reference", transaction.reference],
      ["Date and time", formatDate(transaction.timestamp)],
      ["Transaction type", transaction.type],
    ];
    if (transaction.source) rows.push(["Sender / source", transaction.source]);
    if (transaction.destination) rows.push(["Recipient / destination", transaction.destination]);
    if (transaction.network) rows.push(["Network", transaction.network]);
    if (transaction.transactionHash) rows.push(["Transaction hash", transaction.transactionHash]);
    if (transaction.amountDisplay) {
      rows.push(["Amount", transaction.amountDisplay]);
    } else if (Number.isSafeInteger(transaction.amountMinor)) {
      rows.push(["Amount", formatAmount(transaction.amountMinor, transaction.asset, transaction.precision)]);
    }
    if (transaction.feeDisplay) {
      rows.push(["Fee", transaction.feeDisplay]);
    } else if (Number.isSafeInteger(transaction.feeMinor)) {
      rows.push(["Fee", formatAmount(transaction.feeMinor, transaction.feeAsset || transaction.asset, transaction.precision)]);
    }
    if (Number.isSafeInteger(transaction.totalMinor)) {
      rows.push(["Total deducted / received", formatAmount(transaction.totalMinor, transaction.asset, transaction.precision)]);
    }
    if (transaction.confirmationStatus) rows.push(["Confirmation status", transaction.confirmationStatus]);
    return rows;
  }

  async function copyValue(value, label) {
    try {
      await navigator.clipboard.writeText(value);
      setFeedback(`${label} copied.`);
    } catch {
      setFeedback(`Unable to copy ${label.toLowerCase()}.`);
    }
  }

  async function shareReceipt() {
    const lines = [
      `ExaEarn ${transaction.type} receipt`,
      `Status: ${title}`,
      `Reference: ${transaction.reference}`,
      `Date: ${formatDate(transaction.timestamp)}`,
      transaction.amountDisplay
        ? `Amount: ${transaction.amountDisplay}`
        : Number.isSafeInteger(transaction.amountMinor)
        ? `Amount: ${formatAmount(transaction.amountMinor, transaction.asset, transaction.precision)}`
        : null,
      transaction.network ? `Network: ${transaction.network}` : null,
      transaction.simulation ? "Simulation only; this is not a real-money settlement." : null,
    ].filter(Boolean);
    if (navigator.share) {
      try {
        await navigator.share({ title: "ExaEarn transaction receipt", text: lines.join("\n") });
        return;
      } catch {
        return;
      }
    }
    await copyValue(lines.join("\n"), "Receipt");
  }

  const rows = receiptRows();
  return (
    <main className="transaction-result">
      <section className="transaction-result__shell" aria-labelledby="transaction-result-title">
        <header className="transaction-result__header">
          <StatusIcon status={transaction.status} />
          <p className="transaction-result__brand">EXAEARN</p>
          <h1 id="transaction-result-title">{title}</h1>
          <p className="transaction-result__description">{transaction.message || description}</p>
        </header>

        <section className="transaction-result__card" aria-label="Transaction receipt">
          <div className="transaction-result__card-heading">
            <strong>Transaction details</strong>
            <span className={`transaction-result__badge is-${transaction.status.toLowerCase()}`}>
              {title}
            </span>
          </div>
          {rows.map(([label, value]) => (
            <div className="transaction-result__row" key={label}>
              <span>{label}</span>
              <div>
                <strong>{value}</strong>
                {["Transaction reference", "Payment reference", "Simulation reference", "Transaction hash"].includes(label) ? (
                  <button
                    type="button"
                    aria-label={`Copy ${label}`}
                    onClick={() => void copyValue(String(value), label)}
                  >
                    <Clipboard size={14} />
                  </button>
                ) : null}
              </div>
            </div>
          ))}
        </section>

        {transaction.explorerUrl ? (
          <a
            className="transaction-result__explorer"
            href={transaction.explorerUrl}
            target="_blank"
            rel="noopener noreferrer"
          >
            <ExternalLink size={15} /> View on supported explorer
          </a>
        ) : null}
        {feedback ? <p className="transaction-result__feedback" role="status">{feedback}</p> : null}

        <footer className="transaction-result__actions">
          <button type="button" className="transaction-result__primary" onClick={onReturn}>
            {returnLabel} <ArrowRight size={17} />
          </button>
          <div>
            <button type="button" className="transaction-result__secondary" onClick={() => setReceiptOpen(true)}>
              <ReceiptText size={16} /> View receipt
            </button>
            <button type="button" className="transaction-result__secondary" onClick={() => void shareReceipt()}>
              <Share2 size={16} /> Share receipt
            </button>
          </div>
        </footer>
      </section>

      {receiptOpen ? (
        <div className="transaction-result__modal-backdrop" role="presentation" onMouseDown={(event) => {
          if (event.target === event.currentTarget) setReceiptOpen(false);
        }}>
          <section className="transaction-result__modal" role="dialog" aria-modal="true" aria-labelledby="receipt-title">
            <header>
              <div>
                <span>EXAEARN</span>
                <h2 id="receipt-title">Transaction receipt</h2>
              </div>
              <button type="button" aria-label="Close receipt" onClick={() => setReceiptOpen(false)}><X size={18} /></button>
            </header>
            <section className="transaction-result__card transaction-result__modal-card" aria-label="Receipt details">
              {rows.map(([label, value]) => (
                <div className="transaction-result__row" key={label}>
                  <span>{label}</span>
                  <strong>{value}</strong>
                </div>
              ))}
            </section>
            <button type="button" className="transaction-result__modal-share" onClick={() => void shareReceipt()}>
              <Share2 size={16} /> Share receipt
            </button>
          </section>
        </div>
      ) : null}
    </main>
  );
}
