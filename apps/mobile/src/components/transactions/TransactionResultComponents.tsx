import { Ionicons } from "@expo/vector-icons";
import { Pressable, StyleSheet, Text, View } from "react-native";

import type {
  TransactionResult,
  TransactionResultStatus,
} from "../../services/transactionResultMapper";
import { formatTransactionAmount, formatTransactionDate } from "../../utils/transactionFormatting";
import { fonts } from "../../theme/colors";

const statusColors: Record<TransactionResultStatus, string> = {
  SUCCESS: "#72d3a0",
  PROCESSING: "#e8b866",
  PENDING: "#e8b866",
  FAILED: "#f08c8c",
  CANCELLED: "#a6afbc",
  OFFLINE_COMMITTED: "#67b8e8",
  RECONCILIATION_PENDING: "#e8b866",
  RECOVERY_REQUIRED: "#b5a2ed",
  REDEEMED: "#72d3a0",
  UNKNOWN: "#a6afbc",
};

export function transactionStatusLabel(status: TransactionResultStatus, simulation = false) {
  if (status === "OFFLINE_COMMITTED" && simulation) return "Offline committed · simulation";
  if (status === "REDEEMED" && simulation) return "Redeemed · simulation";
  if (status === "UNKNOWN") return "Status unavailable";
  return status.replace(/_/g, " ").toLowerCase().replace(/(^|\s)\S/g, (letter) => letter.toUpperCase());
}

export function TransactionStatusIcon({ status }: { status: TransactionResultStatus }) {
  const icon: keyof typeof Ionicons.glyphMap = status === "SUCCESS" || status === "REDEEMED"
    ? "checkmark"
    : status === "FAILED"
      ? "close"
      : status === "CANCELLED"
        ? "remove"
        : status === "RECOVERY_REQUIRED"
          ? "warning"
          : status === "UNKNOWN"
            ? "help-circle-outline"
          : status === "OFFLINE_COMMITTED"
            ? "bluetooth"
            : "time";
  const color = statusColors[status];
  return (
    <View
      style={[styles.statusIcon, { borderColor: `${color}44`, backgroundColor: `${color}12` }]}
      accessibilityLabel={transactionStatusLabel(status)}
      accessibilityRole="image"
    >
      <Ionicons name={icon} size={29} color={color} />
    </View>
  );
}

export function TransactionResultHeader({ transaction }: { transaction: TransactionResult }) {
  const title = transaction.status === "SUCCESS"
    ? "Transaction Successful"
    : transaction.status === "PROCESSING"
      ? "Transaction Processing"
      : transaction.status === "PENDING"
        ? "Transaction Pending"
        : transaction.status === "FAILED"
          ? "Transaction Failed"
          : transaction.status === "CANCELLED"
            ? "Transaction Cancelled"
            : transaction.status === "OFFLINE_COMMITTED"
              ? "Offline Committed — Simulation"
              : transaction.status === "RECONCILIATION_PENDING"
                ? "Pending Reconciliation"
                : transaction.status === "RECOVERY_REQUIRED"
                  ? "Recovery Required"
                  : transaction.status === "UNKNOWN"
                    ? "Status unavailable"
                  : "Redeemed — Simulation";
  const description = transaction.message
    ?? (transaction.status === "SUCCESS"
      ? "Your transaction has been completed successfully."
      : transaction.status === "FAILED"
        ? "The transaction was not completed. Review its status before taking further action."
        : transaction.status === "OFFLINE_COMMITTED"
          ? "This transaction is committed locally in simulation and is not settled online."
          : transaction.status === "REDEEMED"
            ? "The simulator confirmed redemption. No real funds or authoritative ledger were changed."
            : transaction.status === "RECOVERY_REQUIRED"
              ? "Do not submit again while this transaction requires recovery."
              : transaction.status === "UNKNOWN"
                ? "The source did not provide a recognized status. No success is implied."
              : "The latest transaction state is shown below. This is not a success confirmation.");

  return (
    <View style={styles.header}>
      <Text style={styles.brand}>EXAEARN</Text>
      <TransactionStatusIcon status={transaction.status} />
      <Text style={styles.title} accessibilityRole="header">{title}</Text>
      <Text style={styles.description}>{description}</Text>
    </View>
  );
}

export function TransactionStatusBadge({ transaction }: { transaction: TransactionResult }) {
  const color = statusColors[transaction.status];
  return (
    <View style={[styles.badge, { borderColor: `${color}40`, backgroundColor: `${color}12` }]}>
      <View style={[styles.badgeDot, { backgroundColor: color }]} />
      <Text style={[styles.badgeText, { color }]}>{transactionStatusLabel(transaction.status, transaction.simulation)}</Text>
    </View>
  );
}

export function TransactionDetailsRow({
  label,
  value,
  last = false,
  onCopy,
}: {
  label: string;
  value: string;
  last?: boolean;
  onCopy?: () => void;
}) {
  return (
    <View style={[styles.detailRow, last ? styles.detailRowLast : null]}>
      <Text style={styles.label}>{label}</Text>
      <View style={styles.valueGroup}>
        <Text selectable style={styles.value}>{value}</Text>
        {onCopy ? (
          <Pressable onPress={onCopy} style={styles.copyButton} accessibilityRole="button" accessibilityLabel={`Copy ${label}`}>
            <Ionicons name="copy-outline" size={14} color="#a6afbc" />
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}

export function TransactionReceiptCard({
  transaction,
  onCopyReference,
  onCopyHash,
}: {
  transaction: TransactionResult;
  onCopyReference: () => void;
  onCopyHash: () => void;
}) {
  const rows: Array<{ label: string; value: string; onCopy?: () => void }> = [
    { label: transaction.timestampLabel ?? "Date and time", value: formatTransactionDate(transaction.timestamp) },
    { label: "Transaction type", value: transaction.type },
  ];
  if (transaction.reference) {
    rows.unshift({
      label: transaction.referenceLabel ?? "Transaction reference",
      value: transaction.reference,
      onCopy: onCopyReference,
    });
  }
  if (transaction.source) rows.push({ label: "Sender / source", value: transaction.source });
  if (transaction.destination) rows.push({ label: "Recipient / destination", value: transaction.destination });
  if (transaction.network) rows.push({ label: "Network", value: transaction.network });
  if (transaction.transactionHash) rows.push({ label: "Transaction hash", value: transaction.transactionHash, onCopy: onCopyHash });
  if (transaction.amountMinor !== undefined) {
    rows.push({
      label: transaction.amountLabel ?? "Amount",
      value: formatTransactionAmount(transaction.amountMinor, transaction.asset, transaction.precision),
    });
  } else if (transaction.amountDisplay) {
    rows.push({ label: transaction.amountLabel ?? "Amount", value: transaction.amountDisplay });
  }
  if (transaction.feeMinor !== undefined) {
    rows.push({
      label: "Fee",
      value: formatTransactionAmount(transaction.feeMinor, transaction.feeAsset ?? transaction.asset, transaction.feePrecision ?? transaction.precision),
    });
  } else if (transaction.feeDisplay) {
    rows.push({ label: "Fee", value: transaction.feeDisplay });
  }
  if (transaction.totalMinor !== undefined) {
    rows.push({
      label: transaction.totalLabel ?? "Total deducted / received",
      value: formatTransactionAmount(transaction.totalMinor, transaction.asset, transaction.precision),
    });
  }
  if (transaction.confirmationStatus) rows.push({ label: "Confirmation status", value: transaction.confirmationStatus });

  return (
    <View style={styles.receiptCard}>
      <View style={styles.receiptHeading}>
        <Text style={styles.receiptTitle}>Transaction details</Text>
        <TransactionStatusBadge transaction={transaction} />
      </View>
      {rows.map((row, index) => (
        <TransactionDetailsRow
          key={`${row.label}-${index}`}
          label={row.label}
          value={row.value}
          last={index === rows.length - 1}
          onCopy={row.onCopy}
        />
      ))}
    </View>
  );
}

export function TransactionResultActions({
  returnLabel,
  onReturn,
  onViewReceipt,
  onShare,
}: {
  returnLabel: string;
  onReturn: () => void;
  onViewReceipt: () => void;
  onShare: () => void;
}) {
  return (
    <View style={styles.actions}>
      <Pressable onPress={onReturn} style={styles.primaryButton} accessibilityRole="button">
        <Text style={styles.primaryButtonText}>{returnLabel}</Text>
        <Ionicons name="arrow-forward" size={17} color="#101820" />
      </Pressable>
      <View style={styles.secondaryActions}>
        <Pressable onPress={onViewReceipt} style={styles.secondaryButton} accessibilityRole="button">
          <Ionicons name="receipt-outline" size={16} color="#dce4ec" />
          <Text style={styles.secondaryButtonText}>View receipt</Text>
        </Pressable>
        <Pressable onPress={onShare} style={styles.secondaryButton} accessibilityRole="button">
          <Ionicons name="share-outline" size={16} color="#dce4ec" />
          <Text style={styles.secondaryButtonText}>Share receipt</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  header: { alignItems: "center", marginBottom: 22 },
  brand: { color: "#8995a5", fontFamily: fonts.semibold, fontSize: 10, letterSpacing: 2.2, marginBottom: 20 },
  statusIcon: { width: 70, height: 70, alignItems: "center", justifyContent: "center", borderRadius: 35, borderWidth: 1 },
  title: { color: "#f2f5f8", fontFamily: fonts.display, fontSize: 24, lineHeight: 32, textAlign: "center", marginTop: 16 },
  description: { maxWidth: 370, color: "#a6afbc", fontFamily: fonts.body, fontSize: 13, lineHeight: 19, textAlign: "center", marginTop: 8 },
  receiptCard: { width: "100%", borderRadius: 17, borderWidth: 1, borderColor: "rgba(224,232,241,0.10)", backgroundColor: "#111720", paddingHorizontal: 15, paddingVertical: 5 },
  receiptHeading: { minHeight: 58, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 8, borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.07)" },
  receiptTitle: { color: "#e8edf2", fontFamily: fonts.semibold, fontSize: 14 },
  badge: { flexDirection: "row", alignItems: "center", gap: 6, maxWidth: "55%", borderWidth: 1, borderRadius: 99, paddingHorizontal: 9, paddingVertical: 6 },
  badgeDot: { width: 6, height: 6, borderRadius: 3 },
  badgeText: { flexShrink: 1, fontFamily: fonts.medium, fontSize: 9 },
  detailRow: { minHeight: 49, flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 12, borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.06)" },
  detailRowLast: { borderBottomWidth: 0 },
  label: { flexShrink: 0, color: "#929dad", fontFamily: fonts.body, fontSize: 11 },
  valueGroup: { flex: 1, flexDirection: "row", alignItems: "center", justifyContent: "flex-end", gap: 7 },
  value: { flexShrink: 1, color: "#dde4ec", fontFamily: fonts.medium, fontSize: 11, textAlign: "right" },
  copyButton: { width: 28, height: 28, alignItems: "center", justifyContent: "center", borderRadius: 8, backgroundColor: "rgba(255,255,255,0.05)" },
  actions: { width: "100%", marginTop: 16, gap: 10 },
  primaryButton: { minHeight: 52, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 8, borderRadius: 13, backgroundColor: "#e6edf3" },
  primaryButtonText: { color: "#101820", fontFamily: fonts.semibold, fontSize: 13 },
  secondaryActions: { flexDirection: "row", gap: 9 },
  secondaryButton: { flex: 1, minHeight: 45, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 7, borderWidth: 1, borderColor: "rgba(255,255,255,0.10)", borderRadius: 12, backgroundColor: "#111720" },
  secondaryButtonText: { color: "#dce4ec", fontFamily: fonts.medium, fontSize: 11 },
});
