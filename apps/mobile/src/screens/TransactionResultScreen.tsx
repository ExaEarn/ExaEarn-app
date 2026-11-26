import { Ionicons } from "@expo/vector-icons";
import { useEffect, useState } from "react";
import {
  AccessibilityInfo,
  ActivityIndicator,
  BackHandler,
  Linking,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import {
  TransactionReceiptCard,
  TransactionResultHeader,
  TransactionResultActions,
} from "../components/transactions/TransactionResultComponents";
import { copyReceiptValue, shareTransactionReceipt } from "../services/receiptShareService";
import type { TransactionResult } from "../services/transactionResultMapper";
import { fonts } from "../theme/colors";

type Props = {
  fontsReady: boolean;
  transaction: TransactionResult;
  returnLabel?: string;
  onReturn: () => void;
};

export default function TransactionResultScreen({
  fontsReady,
  transaction,
  returnLabel = "Back to Home",
  onReturn,
}: Props) {
  const insets = useSafeAreaInsets();
  const [receiptVisible, setReceiptVisible] = useState(false);
  const [reduceMotion, setReduceMotion] = useState(false);
  const [busyAction, setBusyAction] = useState<"copy-reference" | "copy-hash" | "share" | "explorer" | null>(null);
  const [feedback, setFeedback] = useState("");

  useEffect(() => {
    let active = true;
    void AccessibilityInfo.isReduceMotionEnabled().then((enabled) => {
      if (active) setReduceMotion(enabled);
    });
    const subscription = AccessibilityInfo.addEventListener("reduceMotionChanged", setReduceMotion);
    return () => {
      active = false;
      subscription.remove();
    };
  }, []);

  useEffect(() => {
    const subscription = BackHandler.addEventListener("hardwareBackPress", () => {
      if (receiptVisible) {
        setReceiptVisible(false);
        return true;
      }
      onReturn();
      return true;
    });
    return () => subscription.remove();
  }, [onReturn, receiptVisible]);

  async function copyValue(value: string, label: string, action: "copy-reference" | "copy-hash") {
    setBusyAction(action);
    setFeedback("");
    try {
      await copyReceiptValue(value);
      setFeedback(`${label} copied.`);
    } catch (error) {
      setFeedback(error instanceof Error ? error.message : `Unable to copy ${label.toLowerCase()}.`);
    } finally {
      setBusyAction(null);
    }
  }

  async function shareReceipt() {
    setBusyAction("share");
    setFeedback("");
    try {
      await shareTransactionReceipt(transaction);
    } catch (error) {
      setFeedback(error instanceof Error ? error.message : "Unable to share this receipt.");
    } finally {
      setBusyAction(null);
    }
  }

  async function openExplorer() {
    if (!transaction.explorerUrl) return;
    setBusyAction("explorer");
    setFeedback("");
    try {
      await Linking.openURL(transaction.explorerUrl);
    } catch (error) {
      setFeedback(error instanceof Error ? error.message : "Unable to open the transaction explorer.");
    } finally {
      setBusyAction(null);
    }
  }

  const animationType = reduceMotion ? "none" : "slide";
  return (
    <View style={styles.fill}>
      <ScrollView
        contentContainerStyle={[
          styles.content,
          { paddingTop: insets.top + 20, paddingBottom: Math.max(insets.bottom, 14) + 24 },
        ]}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.shell}>
          <TransactionResultHeader transaction={transaction} />
          <TransactionReceiptCard
            transaction={transaction}
            onCopyReference={() => void copyValue(transaction.reference, "Reference", "copy-reference")}
            onCopyHash={() => transaction.transactionHash ? void copyValue(transaction.transactionHash, "Transaction hash", "copy-hash") : undefined}
          />
          {transaction.explorerUrl ? (
            <Pressable onPress={() => void openExplorer()} disabled={busyAction === "explorer"} style={styles.explorerButton}>
              {busyAction === "explorer" ? <ActivityIndicator size="small" color="#a6afbc" /> : <Ionicons name="open-outline" size={15} color="#a6afbc" />}
              <Text style={styles.explorerText}>View on supported explorer</Text>
            </Pressable>
          ) : null}
          {feedback ? <Text style={styles.feedback} accessibilityRole="alert">{feedback}</Text> : null}
          {!fontsReady ? <Text style={styles.systemFontHint} accessibilityElementsHidden>System typography active</Text> : null}
        </View>
      </ScrollView>
      <View style={[styles.footer, { paddingBottom: Math.max(insets.bottom, 12) }]}>
        <View style={styles.footerContent}>
          <TransactionResultActions
            returnLabel={returnLabel}
            onReturn={onReturn}
            onViewReceipt={() => setReceiptVisible(true)}
            onShare={() => void shareReceipt()}
          />
          {busyAction === "share" ? <ActivityIndicator style={styles.shareIndicator} size="small" color="#a6afbc" /> : null}
        </View>
      </View>

      <Modal visible={receiptVisible} transparent animationType={animationType} onRequestClose={() => setReceiptVisible(false)}>
        <View style={styles.modalBackdrop}>
          <View style={[styles.receiptModal, { paddingBottom: Math.max(insets.bottom, 16) }]}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalKicker}>EXAEARN</Text>
                <Text style={styles.modalTitle}>Transaction receipt</Text>
              </View>
              <Pressable onPress={() => setReceiptVisible(false)} style={styles.closeButton} accessibilityRole="button" accessibilityLabel="Close receipt">
                <Ionicons name="close" size={18} color="#dce4ec" />
              </Pressable>
            </View>
            <ScrollView showsVerticalScrollIndicator={false}>
              <TransactionReceiptCard
                transaction={transaction}
                onCopyReference={() => void copyValue(transaction.reference, "Reference", "copy-reference")}
                onCopyHash={() => transaction.transactionHash ? void copyValue(transaction.transactionHash, "Transaction hash", "copy-hash") : undefined}
              />
              {feedback ? <Text style={styles.feedback} accessibilityRole="alert">{feedback}</Text> : null}
              <Pressable onPress={() => void shareReceipt()} style={styles.modalShareButton} accessibilityRole="button">
                <Ionicons name="share-outline" size={16} color="#dce4ec" />
                <Text style={styles.modalShareText}>Share receipt</Text>
              </Pressable>
            </ScrollView>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: "#090b12" },
  content: { flexGrow: 1, alignItems: "center", justifyContent: "center", paddingHorizontal: 20 },
  shell: { width: "100%", maxWidth: 520 },
  footer: { width: "100%", alignItems: "center", paddingHorizontal: 20, paddingTop: 9, backgroundColor: "#090b12", borderTopWidth: 1, borderTopColor: "rgba(255,255,255,0.06)" },
  footerContent: { width: "100%", maxWidth: 520 },
  explorerButton: { alignSelf: "center", flexDirection: "row", alignItems: "center", gap: 7, padding: 10, marginTop: 4 },
  explorerText: { color: "#a6afbc", fontFamily: fonts.medium, fontSize: 11 },
  feedback: { color: "#e8b866", fontFamily: fonts.body, fontSize: 11, lineHeight: 16, textAlign: "center", marginTop: 8 },
  shareIndicator: { marginTop: 8 },
  systemFontHint: { position: "absolute", width: 1, height: 1, opacity: 0 },
  modalBackdrop: { flex: 1, justifyContent: "flex-end", backgroundColor: "rgba(0,0,0,0.72)" },
  receiptModal: { maxHeight: "90%", paddingHorizontal: 18, paddingTop: 20, borderTopLeftRadius: 22, borderTopRightRadius: 22, borderWidth: 1, borderColor: "rgba(255,255,255,0.10)", backgroundColor: "#090b12" },
  modalHeader: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginBottom: 16 },
  modalKicker: { color: "#8995a5", fontFamily: fonts.semibold, fontSize: 9, letterSpacing: 2 },
  modalTitle: { color: "#f2f5f8", fontFamily: fonts.display, fontSize: 19, marginTop: 4 },
  closeButton: { width: 36, height: 36, alignItems: "center", justifyContent: "center", borderRadius: 18, backgroundColor: "#111720" },
  modalShareButton: { minHeight: 46, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 8, marginTop: 12, borderRadius: 12, borderWidth: 1, borderColor: "rgba(255,255,255,0.10)" },
  modalShareText: { color: "#dce4ec", fontFamily: fonts.medium, fontSize: 12 },
});
