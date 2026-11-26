import { createExaPayOfflineSimulator } from "./exaPayOfflineSimulator.js";
import { validatePaymentDraft } from "./exaPayPaymentReviewModel.js";

const activeOperations = new Map();
const completedOperations = new Map();

function paymentResult(draft, outcome, message) {
  return {
    channel: draft.channel,
    outcome,
    reference: draft.reference,
    amountMinor: draft.amountMinor,
    simulation: true,
    onlineSettlement: false,
    message,
  };
}

export async function authorizeAndSubmitPayment(draft, { onStage = () => {}, shouldCancel = () => false } = {}) {
  if (activeOperations.has(draft.idempotencyKey)) return activeOperations.get(draft.idempotencyKey);
  if (completedOperations.has(draft.idempotencyKey)) return completedOperations.get(draft.idempotencyKey);

  const validation = validatePaymentDraft(draft);
  if (!validation.valid) {
    return paymentResult(draft, validation.reason === "expired" ? "expired" : "invalid-draft", validation.reason);
  }

  onStage("validating-payment");
  onStage("requesting-simulation-authorization");

  const operation = (async () => {
    if (draft.channel === "online") {
      return paymentResult(
        draft,
        "service-unavailable",
        "No online payment API or fee quote is connected. No transfer was submitted.",
      );
    }

    if (!draft.offlineTransfer) {
      return paymentResult(draft, "invalid-draft", "The offline transfer request is incomplete.");
    }

    const simulator = createExaPayOfflineSimulator();
    const result = await simulator.runTransfer(
      {
        recipient: draft.offlineTransfer.recipient,
        recipientVerifiedForSimulation: draft.offlineTransfer.recipientVerifiedForSimulation,
        amountMinor: draft.amountMinor,
        reference: draft.reference,
        userId: draft.accountUserId,
        method: draft.offlineTransfer.method,
      },
      {
        scenario: draft.offlineTransfer.scenario,
        shouldCancel,
        onStage,
      },
    );

    const message = result.outcome === "offline-committed-simulation"
      ? "The local simulator committed this value. This is not online settlement; reconciliation is required."
      : result.outcome === "recovery-required"
        ? "The simulated commit state is uncertain. Stop and reconcile before retrying."
        : "The simulated transfer did not commit value.";

    return paymentResult(draft, result.outcome, message);
  })();

  activeOperations.set(draft.idempotencyKey, operation);
  try {
    const result = await operation;
    completedOperations.set(draft.idempotencyKey, result);
    return result;
  } finally {
    activeOperations.delete(draft.idempotencyKey);
  }
}
