import TransactionResult from "../../components/TransactionResult/TransactionResult";
import { mapExaPayPaymentResult } from "../../services/transactionResultMapper";

export default function ExaPayPaymentResultPage({ draft, result, onDone }) {
  return (
    <TransactionResult
      transaction={mapExaPayPaymentResult(draft, result)}
      returnLabel="Back to ExaPay"
      onReturn={onDone}
    />
  );
}
