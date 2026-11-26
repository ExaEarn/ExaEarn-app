import { useEffect, useMemo, useRef, useState } from "react";
import {
  ArrowLeft,
  ArrowUpRight,
  CheckCircle2,
  ChevronRight,
  CircleAlert,
  Clock3,
  CloudOff,
  Info,
  LoaderCircle,
  RefreshCw,
  ShieldCheck,
  X,
} from "lucide-react";
import exaearnLogo from "../../assets/images/exaearn-logo.png";
import {
  createExaPaySyncRedeemSimulator,
  formatSimulatedSyncAmount,
} from "../../services/exaPaySyncRedeemSimulator.js";
import TransactionResult from "../../components/TransactionResult/TransactionResult";
import { mapExaPayRedemptionResult } from "../../services/transactionResultMapper";
import "./ExaPaySyncRedeemPage.css";

const syncStages = {
  "loading-local-queue": "Loading local offline queue",
  "validating-reference": "Validating transaction reference",
  "checking-simulation": "Checking simulated reconciliation",
};

function Badge({ children, tone = "muted" }) {
  return (
    <span className={`exa-sync__badge exa-sync__badge--${tone}`}>
      <i aria-hidden="true" />
      {children}
    </span>
  );
}

function SectionHeader({ title, hint, action, onAction, busy = false }) {
  return (
    <div className="exa-sync__section-heading">
      <div>
        <h2>{title}</h2>
        {hint ? <p>{hint}</p> : null}
      </div>
      {action ? (
        <button type="button" className="exa-sync__text-action" onClick={onAction} disabled={busy}>
          {busy ? <LoaderCircle size={15} className="exa-sync__spin" aria-hidden="true" /> : null}
          {action}
        </button>
      ) : null}
    </div>
  );
}

function ValueOverview({ snapshot }) {
  const values = [
    { label: "Local Offline Value", amount: snapshot.localOfflineValueMinor, tone: "purple" },
    { label: "Pending Verification", amount: snapshot.pendingVerificationMinor, tone: "amber" },
    { label: "Redeemable", amount: snapshot.redeemableMinor, tone: "green" },
  ];

  return (
    <section className="exa-sync__overview" aria-labelledby="exa-sync-overview-title">
      <SectionHeader title="Offline value overview" />
      <div className="exa-sync__value-card">
        <div className="exa-sync__value-grid">
          {values.map((item) => (
            <article key={item.label} className="exa-sync__value-tile">
              <span className={`exa-sync__value-label exa-sync__value-label--${item.tone}`}>{item.label}</span>
              <strong>{formatSimulatedSyncAmount(item.amount)} <small>simUSDT</small></strong>
            </article>
          ))}
        </div>
        <p className="exa-sync__value-note">
          <Info size={16} aria-hidden="true" />
          Pending value is included in local offline value, not added to it. Only verified value can become redeemable.
        </p>
      </div>
    </section>
  );
}

function QueueCard({ transaction, onOpen }) {
  const status = transaction.reconciliationStatus;
  const label = status === "verified"
    ? "Verified · Ready to redeem"
    : status === "failed"
      ? "Verification failed"
      : status === "recovery-required"
        ? "Recovery required"
        : status === "redeemed"
          ? "Redeemed"
          : "Pending Verification";
  const tone = status === "verified" || status === "redeemed"
    ? "green"
    : status === "failed" || status === "recovery-required"
      ? "red"
      : "amber";

  return (
    <button
      type="button"
      className="exa-sync__queue-card"
      onClick={onOpen}
      aria-label={`Transaction ${transaction.reference}, ${label}. View details`}
    >
      <span className="exa-sync__queue-icon"><RefreshCw size={17} aria-hidden="true" /></span>
      <span className="exa-sync__queue-main">
        <strong>{transaction.reference}</strong>
        <small>{transaction.sender} · Offline Committed</small>
        <span className="exa-sync__queue-status"><Badge tone={tone}>{label}</Badge></span>
      </span>
      <span className="exa-sync__queue-amount">
        <strong>{formatSimulatedSyncAmount(transaction.amountMinor)}</strong>
        <small>simUSDT</small>
      </span>
      <ChevronRight className="exa-sync__chevron" size={18} aria-hidden="true" />
    </button>
  );
}

function RedemptionPanel({ snapshot, canRedeem, previousOutcome, busy, onRedeem }) {
  const status = snapshot.transaction?.reconciliationStatus;
  const reason = status === "recovery-required"
    ? "Recovery is required before this value can be redeemed."
    : status === "failed"
      ? "Verification failed. Retry synchronization before redeeming."
      : snapshot.pendingVerificationMinor > 0
        ? "Pending value is not available in the online balance."
        : "No verified value is currently available to redeem.";

  return (
    <section className="exa-sync__redemption" aria-labelledby="exa-sync-redemption-title">
      <div className="exa-sync__redemption-heading">
        <span className="exa-sync__redemption-icon"><ArrowUpRight size={19} aria-hidden="true" /></span>
        <div>
          <h2 id="exa-sync-redemption-title">Redeem to Online Balance</h2>
          <p>Only successfully reconciled simulated value is eligible.</p>
        </div>
      </div>
      <div className="exa-sync__redeem-amount">
        <span>
          <small>Verified redeemable amount</small>
          <strong>{formatSimulatedSyncAmount(snapshot.redeemableMinor)} <em>simUSDT</em></strong>
        </span>
        <Badge tone={canRedeem ? "green" : "muted"}>{canRedeem ? "Verified" : "Not eligible"}</Badge>
      </div>
      {!canRedeem ? <p className="exa-sync__redeem-reason">{reason}</p> : null}
      <button
        type="button"
        className="exa-sync__redeem-button"
        disabled={!canRedeem || busy}
        onClick={onRedeem}
      >
        {previousOutcome === "failed" ? <RefreshCw size={16} aria-hidden="true" /> : <ArrowUpRight size={17} aria-hidden="true" />}
        {previousOutcome === "failed" ? "Retry Redemption" : "Redeem Now"}
      </button>
      <p className="exa-sync__disclosure">
        <ShieldCheck size={15} aria-hidden="true" />
        Redemption is simulated locally. No API, wallet ledger, or real online balance is changed.
      </p>
    </section>
  );
}

function HistoryList({ history, refreshing, onRefresh, onViewAll }) {
  return (
    <section className="exa-sync__history" aria-labelledby="exa-sync-history-title">
      <SectionHeader title="Redemption history" />
      <ul className="exa-sync__history-list">
        {history.map((item, index) => {
          const tone = item.status === "Redeemed" ? "green" : item.status === "Failed" ? "red" : "amber";
          return (
            <li key={`${item.reference}-${index}`} className="exa-sync__history-row">
              <span className={`exa-sync__history-icon exa-sync__history-icon--${tone}`}>
                {item.status === "Redeemed"
                  ? <CheckCircle2 size={16} aria-hidden="true" />
                  : item.status === "Failed"
                    ? <CircleAlert size={16} aria-hidden="true" />
                    : <Clock3 size={16} aria-hidden="true" />}
              </span>
              <span className="exa-sync__history-copy">
                <strong>{item.reference}</strong>
                <small>{item.timestamp}</small>
              </span>
              <span className="exa-sync__history-result">
                <Badge tone={tone}>{item.status}</Badge>
                <small>{formatSimulatedSyncAmount(item.amountMinor)} simUSDT</small>
              </span>
            </li>
          );
        })}
        {!history.length ? (
          <li className="exa-sync__history-empty">No redemption activity yet.</li>
        ) : null}
      </ul>
      <div className="exa-sync__history-actions">
        <button type="button" onClick={onRefresh} disabled={refreshing}>
          {refreshing ? <LoaderCircle size={15} className="exa-sync__spin" aria-hidden="true" /> : <RefreshCw size={15} aria-hidden="true" />}
          {refreshing ? "Refreshing…" : "Refresh"}
        </button>
        <button type="button" onClick={onViewAll}>
          View All <ChevronRight size={15} aria-hidden="true" />
        </button>
      </div>
    </section>
  );
}

function Sheet({ title, onClose, children }) {
  useEffect(() => {
    const handleKeyDown = (event) => {
      if (event.key === "Escape") onClose();
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [onClose]);

  return (
    <div
      className="exa-sync__scrim"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
    >
      <section className="exa-sync__sheet" role="dialog" aria-modal="true" aria-label={title}>
        <header className="exa-sync__sheet-header">
          <h2>{title}</h2>
          <button type="button" onClick={onClose} aria-label="Close dialog"><X size={18} aria-hidden="true" /></button>
        </header>
        {children}
      </section>
    </div>
  );
}

function SyncProgress({ stage }) {
  const stages = Object.keys(syncStages);
  const activeIndex = stages.indexOf(stage);
  return (
    <div className="exa-sync__progress-backdrop" role="status" aria-live="polite">
      <section className="exa-sync__progress">
        <div className="exa-sync__progress-title">
          <LoaderCircle size={18} className="exa-sync__spin" aria-hidden="true" />
          <span><strong>Syncing offline activity</strong><small>Local simulation · no server request</small></span>
        </div>
        <ol>
          {stages.map((key, index) => (
            <li key={key} className={index === activeIndex ? "is-active" : index < activeIndex ? "is-complete" : ""}>
              <span>{index < activeIndex ? "✓" : index + 1}</span>{syncStages[key]}
            </li>
          ))}
        </ol>
      </section>
    </div>
  );
}

export default function ExaPaySyncRedeemPage({ onBack, onOpenSupport, onViewAllHistory }) {
  const simulator = useMemo(() => createExaPaySyncRedeemSimulator(), []);
  const [snapshot, setSnapshot] = useState(() => simulator.getSnapshot());
  const [selectedTransaction, setSelectedTransaction] = useState(null);
  const [confirmationOpen, setConfirmationOpen] = useState(false);
  const [syncStatus, setSyncStatus] = useState("idle");
  const [syncStage, setSyncStage] = useState(null);
  const [feedback, setFeedback] = useState("");
  const [redemptionOutcome, setRedemptionOutcome] = useState(null);
  const [redemptionResult, setRedemptionResult] = useState(null);
  const [isRedeeming, setIsRedeeming] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const syncLock = useRef(false);
  const redemptionLock = useRef(false);

  const queueTransaction = snapshot.transaction?.reconciliationStatus === "redeemed"
    ? null
    : snapshot.transaction;
  const canRedeem = Boolean(
    snapshot.transaction?.reconciliationStatus === "verified"
    && snapshot.redeemableMinor > 0
    && snapshot.pendingVerificationMinor === 0,
  );
  const syncBadge = syncStatus === "verified"
    ? { label: "Sync checked", tone: "green" }
    : syncStatus === "failed" || syncStatus === "network-error"
      ? { label: "Sync needs attention", tone: "red" }
      : syncStatus === "recovery-required"
        ? { label: "Recovery required", tone: "red" }
        : { label: "Ready to sync", tone: "amber" };

  useEffect(() => {
    const handleKeyDown = (event) => {
      if (event.key === "Escape" && (syncLock.current || redemptionLock.current)) {
        event.preventDefault();
        event.stopPropagation();
      }
    };
    window.addEventListener("keydown", handleKeyDown, true);
    return () => window.removeEventListener("keydown", handleKeyDown, true);
  }, []);

  async function synchronize() {
    if (syncLock.current || !queueTransaction || queueTransaction.reconciliationStatus === "recovery-required") return;
    syncLock.current = true;
    setSyncStatus("syncing");
    setSyncStage("loading-local-queue");
    setFeedback("");
    setRedemptionOutcome(null);
    try {
      const result = await simulator.syncOfflinePayments({ onProgress: setSyncStage });
      setSnapshot(result.snapshot);
      setSyncStatus(result.outcome === "verification-failed" ? "failed" : result.outcome);
      if (result.outcome === "network-error") {
        setFeedback("The simulation could not reach its reconciliation service. Pending value remains local and is not redeemable.");
      } else if (result.outcome === "verification-failed") {
        setFeedback("Verification did not complete. Retry synchronization before attempting redemption.");
      } else if (result.outcome === "recovery-required") {
        setFeedback("This transaction requires recovery. Redemption is blocked until its state is resolved.");
      }
    } catch {
      setSyncStatus("network-error");
      setFeedback("Synchronization failed unexpectedly. The local queue was not marked as verified.");
    } finally {
      syncLock.current = false;
      setSyncStage(null);
    }
  }

  async function confirmRedemption() {
    if (redemptionLock.current || !canRedeem || !snapshot.transaction) return;
    const transaction = snapshot.transaction;
    redemptionLock.current = true;
    setIsRedeeming(true);
    setFeedback("");
    setConfirmationOpen(false);
    try {
      const result = await simulator.redeemVerifiedValue(transaction.reference);
      setSnapshot(result.snapshot);
      setRedemptionResult(mapExaPayRedemptionResult(transaction, result.outcome));
      if (result.outcome === "redeemed-simulation") {
        setRedemptionOutcome("success");
      } else if (result.outcome === "failed") {
        setRedemptionOutcome("failed");
      } else if (result.outcome === "recovery-required") {
        setRedemptionOutcome("recovery-required");
        setSyncStatus("recovery-required");
        setFeedback("The redemption outcome needs recovery. Do not retry until its state is resolved.");
      } else {
        setRedemptionOutcome("failed");
        setFeedback("The simulator rejected this redemption because the transaction is not eligible.");
      }
    } catch {
      setRedemptionOutcome("recovery-required");
      setSyncStatus("recovery-required");
      setRedemptionResult(mapExaPayRedemptionResult(transaction, "recovery-required"));
      setFeedback("The redemption outcome is uncertain. Stop and reconcile before retrying.");
    } finally {
      redemptionLock.current = false;
      setIsRedeeming(false);
    }
  }

  async function refreshHistory() {
    if (isRefreshing) return;
    setIsRefreshing(true);
    try {
      const history = await simulator.refreshHistory();
      setSnapshot((previous) => ({ ...previous, history }));
    } catch {
      setFeedback("Redemption history could not be refreshed. Try again.");
    } finally {
      setIsRefreshing(false);
    }
  }

  const dismissSheet = () => setSelectedTransaction(null);

  if (redemptionResult) {
    return (
      <TransactionResult
        transaction={redemptionResult}
        returnLabel="Back to Sync & Redeem"
        onReturn={() => setRedemptionResult(null)}
      />
    );
  }

  return (
    <main className="exa-sync">
      <div className="exa-sync__page">
        <header className="exa-sync__topbar">
          <button type="button" className="exa-sync__back" onClick={onBack} aria-label="Back to ExaPay">
            <ArrowLeft size={17} aria-hidden="true" /> Back to ExaPay
          </button>
          <div className="exa-sync__brand">
            <img src={exaearnLogo} alt="ExaEarn" />
            <span><strong>ExaEarn</strong><small>EXAPAY</small></span>
          </div>
          <span className="exa-sync__simulation"><i aria-hidden="true" />Simulation Mode</span>
        </header>

        <section className="exa-sync__intro" aria-labelledby="exa-sync-title">
          <div>
            <p className="exa-sync__eyebrow">OFFLINE PAYMENTS</p>
            <h1 id="exa-sync-title">Sync &amp; Redeem</h1>
            <p className="exa-sync__subtitle">Reconcile offline payments and redeem eligible value to your online balance.</p>
          </div>
          <div className="exa-sync__connectivity">
            <span><CloudOff size={17} aria-hidden="true" /></span>
            <div><strong>Local simulation only</strong><small>Connectivity: {simulator.getConnectivityStatus()}</small></div>
          </div>
        </section>

        <div className="exa-sync__notice" role="note">
          <Info size={17} aria-hidden="true" />
          <p>Illustrative simUSDT values only. This screen does not call a reconciliation API or change your real wallet.</p>
        </div>

        <ValueOverview snapshot={snapshot} />

        <div className="exa-sync__columns">
          <div className="exa-sync__main-column">
            <section className="exa-sync__queue" aria-labelledby="exa-sync-queue-title">
              <div className="exa-sync__queue-heading">
                <div>
                  <h2 id="exa-sync-queue-title">Reconciliation Queue ({queueTransaction ? 1 : 0})</h2>
                  <p>Sync checks local records; it does not create online funds.</p>
                </div>
                <button
                  type="button"
                  className="exa-sync__sync-button"
                  onClick={() => void synchronize()}
                  disabled={syncStatus === "syncing" || syncStatus === "recovery-required" || !queueTransaction}
                  aria-label={syncStatus === "failed" || syncStatus === "network-error" ? "Retry synchronization" : "Synchronize offline payments"}
                >
                  {syncStatus === "failed" || syncStatus === "network-error"
                    ? <RefreshCw size={16} aria-hidden="true" />
                    : <RefreshCw size={16} aria-hidden="true" />}
                  {syncStatus === "syncing" ? "Syncing…" : syncStatus === "failed" || syncStatus === "network-error" ? "Retry Sync" : "Sync Now"}
                </button>
              </div>

              <div className="exa-sync__queue-meta">
                <Badge tone={syncBadge.tone}>{syncBadge.label}</Badge>
                <span>simUSDT · 2 decimal places</span>
              </div>
              {feedback ? <div className={`exa-sync__feedback${syncStatus === "recovery-required" ? " is-danger" : ""}`} role="alert"><CircleAlert size={16} aria-hidden="true" />{feedback}</div> : null}
              {queueTransaction ? (
                <QueueCard transaction={queueTransaction} onOpen={() => setSelectedTransaction(queueTransaction)} />
              ) : (
                <div className="exa-sync__empty">
                  <CheckCircle2 size={21} aria-hidden="true" />
                  <span><strong>Queue is clear</strong><small>No offline transactions are waiting for reconciliation.</small></span>
                </div>
              )}
            </section>

            <RedemptionPanel
              snapshot={snapshot}
              canRedeem={canRedeem}
              previousOutcome={redemptionOutcome}
              busy={isRedeeming}
              onRedeem={() => setConfirmationOpen(true)}
            />

            {redemptionOutcome ? (
              <div className={`exa-sync__outcome exa-sync__outcome--${redemptionOutcome}`} role="status">
                {redemptionOutcome === "success" ? <CheckCircle2 size={19} aria-hidden="true" /> : <CircleAlert size={19} aria-hidden="true" />}
                <span>
                  <strong>{redemptionOutcome === "success" ? "Simulated redemption complete" : redemptionOutcome === "failed" ? "Redemption failed" : "Recovery required"}</strong>
                  <small>
                    {redemptionOutcome === "success"
                      ? `Simulator confirmed this state update. Simulated online balance: ${formatSimulatedSyncAmount(snapshot.simulatedOnlineBalanceMinor)} simUSDT.`
                      : redemptionOutcome === "failed"
                        ? "Verified value remains eligible in simulation. You can retry."
                        : "Do not retry until this transaction is reconciled."}
                  </small>
                </span>
              </div>
            ) : null}
          </div>

          <aside className="exa-sync__side-column">
            <HistoryList
              history={snapshot.history}
              refreshing={isRefreshing}
              onRefresh={() => void refreshHistory()}
              onViewAll={onViewAllHistory}
            />
            <section className="exa-sync__security">
              <span><ShieldCheck size={19} aria-hidden="true" /></span>
              <div>
                <strong>Security &amp; reconciliation</strong>
                <p>Offline payments must pass reconciliation checks before redemption into an online balance.</p>
              </div>
            </section>
            <p className="exa-sync__footer-note">
              Pending offline value is not spendable online. Real-money offline redemption is disabled.
            </p>
          </aside>
        </div>
      </div>

      {selectedTransaction ? (
        <Sheet title="Transaction details" onClose={dismissSheet}>
          <div className="exa-sync__detail-head">
            <span className="exa-sync__queue-icon"><RefreshCw size={17} aria-hidden="true" /></span>
            <span><strong>{selectedTransaction.sender}</strong><small>{formatSimulatedSyncAmount(selectedTransaction.amountMinor)} simUSDT</small></span>
            <Badge tone={selectedTransaction.reconciliationStatus === "verified" ? "green" : selectedTransaction.reconciliationStatus === "failed" || selectedTransaction.reconciliationStatus === "recovery-required" ? "red" : "amber"}>
              {selectedTransaction.reconciliationStatus === "pending-verification" ? "Pending Verification" : selectedTransaction.reconciliationStatus === "verified" ? "Verified" : selectedTransaction.reconciliationStatus === "failed" ? "Verification failed" : selectedTransaction.reconciliationStatus === "recovery-required" ? "Recovery required" : "Redeemed"}
            </Badge>
          </div>
          <dl className="exa-sync__details">
            <div><dt>Transaction reference</dt><dd>{selectedTransaction.reference}</dd></div>
            <div><dt>Local status</dt><dd>Offline Committed</dd></div>
            <div><dt>Transfer status</dt><dd>{selectedTransaction.reconciliationStatus.replaceAll("-", " ")}</dd></div>
            <div><dt>Asset precision</dt><dd>simUSDT · 2 decimal places</dd></div>
          </dl>
          <p className="exa-sync__sheet-disclosure">This local simulator does not verify a backend ledger or guarantee settlement.</p>
          {selectedTransaction.reconciliationStatus === "failed" ? (
            <button type="button" className="exa-sync__sheet-primary" onClick={() => { dismissSheet(); void synchronize(); }}>
              <RefreshCw size={16} aria-hidden="true" /> Retry synchronization
            </button>
          ) : selectedTransaction.reconciliationStatus === "recovery-required" ? (
            <button type="button" className="exa-sync__sheet-primary" onClick={() => { dismissSheet(); onOpenSupport(); }}>
              Contact Support <ChevronRight size={16} aria-hidden="true" />
            </button>
          ) : null}
        </Sheet>
      ) : null}

      {confirmationOpen ? (
        <Sheet title="Confirm simulated redemption" onClose={() => setConfirmationOpen(false)}>
          <div className="exa-sync__confirm-summary">
            <Badge tone="green">Verified value · Simulation</Badge>
            <strong>{formatSimulatedSyncAmount(snapshot.redeemableMinor)} <small>simUSDT</small></strong>
            <p>This amount has been verified by the local simulator only.</p>
          </div>
          <dl className="exa-sync__details">
            <div><dt>Transaction</dt><dd>{snapshot.transaction?.reference ?? "—"}</dd></div>
            <div><dt>Real wallet effect</dt><dd>None</dd></div>
          </dl>
          <p className="exa-sync__sheet-disclosure">Confirming updates only the simulator. No API, ledger, or production online balance is called or credited.</p>
          <div className="exa-sync__confirm-actions">
            <button type="button" className="exa-sync__cancel" onClick={() => setConfirmationOpen(false)}>Cancel</button>
            <button type="button" className="exa-sync__sheet-primary" onClick={() => void confirmRedemption()} disabled={!canRedeem || isRedeeming}>
              {isRedeeming ? <LoaderCircle size={16} className="exa-sync__spin" aria-hidden="true" /> : <CheckCircle2 size={16} aria-hidden="true" />}
              Confirm Redeem
            </button>
          </div>
        </Sheet>
      ) : null}

      {syncStatus === "syncing" ? <SyncProgress stage={syncStage} /> : null}
    </main>
  );
}
