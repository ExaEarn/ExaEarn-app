import {
  createExaPayOfflineSimulator,
  type OfflinePaymentStage,
} from "./exaPayOfflineSimulator";
import {
  validatePaymentDraft,
  type ExaPayPaymentDraft,
  type ExaPayPaymentResult,
} from "./exaPayPaymentReviewModel";

export type PaymentAuthorizationStage =
  | "validating-payment"
  | "requesting-simulation-authorization"
  | "connecting"
  | "authenticating"
  | "preparing-transfer"
  | "awaiting-recipient"
  | "committing-offline";

type AuthorizationOptions = {
  onStage?: (stage: PaymentAuthorizationStage) => void;
  shouldCancel?: () => boolean;
};

const operations = new Map<string, Promise<ExaPayPaymentResult>>();
const completed = new Map<string, ExaPayPaymentResult>();

function createResult(
  draft: ExaPayPaymentDraft,
  outcome: string,
  message: string,
): ExaPayPaymentResult {
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

function mapProgressStage(stage: OfflinePaymentStage): PaymentAuthorizationStage {
  return stage;
}

export async function authorizeAndSubmitPayment(
  draft: ExaPayPaymentDraft,
  { onStage = () => {}, shouldCancel = () => false }: AuthorizationOptions = {},
): Promise<ExaPayPaymentResult> {
  const activeOperation = operations.get(draft.idempotencyKey);
  if (activeOperation) return activeOperation;

  const priorResult = completed.get(draft.idempotencyKey);
  if (priorResult) return priorResult;

  const validation = validatePaymentDraft(draft);
  if (!validation.valid) {
    const outcome = validation.reason === "expired" ? "expired" : "invalid-draft";
    return createResult(draft, outcome, validation.reason);
  }

  onStage("validating-payment");
  onStage("requesting-simulation-authorization");

  const operation = (async () => {
    if (draft.channel === "online") {
      return createResult(
        draft,
        "service-unavailable",
        "No online payment API or fee quote is connected. No transfer was submitted.",
      );
    }

    const offlineTransfer = draft.offlineTransfer;
    if (!offlineTransfer) {
      return createResult(draft, "invalid-draft", "The offline transfer request is incomplete.");
    }

    const simulator = createExaPayOfflineSimulator();
    const result = await simulator.runTransfer(
      {
        recipient: offlineTransfer.recipient,
        recipientVerifiedForSimulation: offlineTransfer.recipientVerifiedForSimulation,
        amountMinor: draft.amountMinor,
        reference: draft.reference,
        method: offlineTransfer.method,
      },
      {
        scenario: offlineTransfer.scenario,
        shouldCancel,
        onStage: (stage) => onStage(mapProgressStage(stage)),
      },
    );

    const message = result.outcome === "offline-committed-simulation"
      ? "The local simulator committed this value. It is not online settlement and requires reconciliation."
      : result.outcome === "recovery-required"
        ? "The simulated commit state is uncertain. Stop and recover before retrying."
        : "The simulated transfer did not commit value.";

    return createResult(draft, result.outcome, message);
  })();

  operations.set(draft.idempotencyKey, operation);
  try {
    const result = await operation;
    completed.set(draft.idempotencyKey, result);
    return result;
  } finally {
    operations.delete(draft.idempotencyKey);
  }
}
