import * as Clipboard from "expo-clipboard";
import { Share } from "react-native";

import type { TransactionResult } from "./transactionResultMapper";
import { formatTransactionAmount } from "../utils/transactionFormatting";

export async function copyReceiptValue(value: string) {
  await Clipboard.setStringAsync(value);
}

export async function shareTransactionReceipt(transaction: TransactionResult) {
  const amount = transaction.amountMinor === undefined
    ? null
    : formatTransactionAmount(transaction.amountMinor, transaction.asset, transaction.precision);
  const lines = [
    `ExaEarn ${transaction.type} receipt`,
    `Status: ${transaction.status.replace(/_/g, " ")}`,
    transaction.reference ? `Reference: ${transaction.reference}` : null,
    `Date: ${new Date(transaction.timestamp).toLocaleString()}`,
    amount ? `${transaction.amountLabel ?? "Amount"}: ${amount}` : null,
    transaction.network ? `Network: ${transaction.network}` : null,
    transaction.simulation ? "Simulation only; this is not a real-money settlement." : null,
  ].filter((line): line is string => Boolean(line));

  return Share.share({ message: lines.join("\n") });
}
