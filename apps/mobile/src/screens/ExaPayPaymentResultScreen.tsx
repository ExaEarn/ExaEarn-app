import type {
  ExaPayPaymentDraft,
  ExaPayPaymentResult,
} from "../services/exaPayPaymentReviewModel";
import { mapExaPayPaymentResult } from "../services/transactionResultMapper";
import TransactionResultScreen from "./TransactionResultScreen";

type Props = {
  fontsReady: boolean;
  draft: ExaPayPaymentDraft;
  result: ExaPayPaymentResult;
  onDone: () => void;
};

function outcomePresentation(result: ExaPayPaymentResult) {
  if (result.outcome === "offline-committed-simulation") {
    return {
      icon: "checkmark" as const,
      title: "Committed locally",
      tone: "#72d3a0",
      summary: "This simulated transfer is pending reconciliation. It is not online settlement.",
    };
  }
  if (result.outcome === "service-unavailable") {
    return {
      icon: "time-outline" as const,
      title: "Payment not submitted",
      tone: "#e8b866",
      summary: "The online payment service is not connected. No transfer was submitted.",
    };
  }
  if (result.outcome === "recovery-required") {
    return {
      icon: "alert-outline" as const,
      title: "Recovery required",
      tone: "#e8b866",
      summary: "The simulated commitment could not be confirmed. Do not retry until this reference is reconciled.",
    };
  }
  return {
    icon: "close" as const,
    title: result.outcome === "expired" ? "Request expired" : "Payment not completed",
    tone: "#f08c8c",
    summary: result.message,
  };
}

export default function ExaPayPaymentResultScreen({ fontsReady, draft, result, onDone }: Props) {
  return (
    <TransactionResultScreen
      fontsReady={fontsReady}
      transaction={mapExaPayPaymentResult(draft, result)}
      returnLabel="Back to ExaPay"
      onReturn={onDone}
    />
  );
}