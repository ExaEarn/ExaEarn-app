import { useEffect, useState } from "react";
import { ActivityIndicator, Modal, Pressable, StyleSheet, Text, View } from "react-native";
import { colors, fonts } from "../theme/colors";
import { getErrorTheme } from "./errorMessageRegistry";
import type { AppError, RecoveryAction } from "./errorTypes";
import { RecoveryActionHandler } from "./RecoveryActionHandler";

const actionLabels: Record<RecoveryAction, string> = {
  retry_connection: "Retry connection", edit_amount: "Edit amount", add_funds: "Add funds", check_status: "Check status",
  check_transfer: "Check transfer", sign_in: "Sign in", verify_identity: "Verify identity", reconnect_bluetooth: "Reconnect Bluetooth",
  view_details: "View details", contact_support: "Contact support", close: "Close",
};

export function ErrorStatusIcon({ severity }: { severity: AppError["severity"] }) {
  const color = getErrorTheme(severity);
  const glyph = severity === "success" ? "✓" : severity === "warning" ? "!" : severity === "security" ? "◆" : severity === "connectivity" ? "↗" : "×";
  return <View accessibilityElementsHidden style={[styles.icon, { borderColor: `${color}66`, backgroundColor: `${color}18` }]}><Text style={[styles.iconText, { color }]}>{glyph}</Text></View>;
}

export function ErrorModal({
  error, handlers, onDismiss, reduceMotion,
  appIsActive,
}: {
  error: AppError | null;
  handlers: Partial<Record<RecoveryAction, () => void | Promise<void>>>;
  onDismiss: () => void;
  reduceMotion: boolean;
  appIsActive: boolean;
}) {
  const [busyAction, setBusyAction] = useState<RecoveryAction | null>(null);
  const [remainingMs, setRemainingMs] = useState(error?.retryAfterMs ?? 0);
  useEffect(() => {
    const initialDelay = error?.retryAfterMs ?? 0;
    const deadline = Date.now() + initialDelay;
    setRemainingMs(initialDelay);
    if (!initialDelay) return;
    const timer = setInterval(() => setRemainingMs(Math.max(0, deadline - Date.now())), 250);
    return () => clearInterval(timer);
  }, [error?.code, error?.reference, error?.retryAfterMs]);
  if (!error) return null;
  const activeError = error;
  const accent = getErrorTheme(error.severity);
  const visibleActions = error.actions.filter((action) => action !== "close" && typeof handlers[action] === "function");
  const primaryAction = visibleActions[0];

  async function runAction(action: RecoveryAction) {
    if (busyAction) return;
    if (action === "close") { onDismiss(); return; }
    const handler = handlers[action];
    if (!handler) { onDismiss(); return; }
    setBusyAction(action);
    onDismiss();
    try { await RecoveryActionHandler.run(activeError, action, handler); } finally { setBusyAction(null); }
  }

  return (
    <Modal visible={appIsActive} transparent animationType={reduceMotion ? "none" : "fade"} statusBarTranslucent onRequestClose={onDismiss}>
      <View style={[styles.overlay, error.financialOutcomeUncertain ? styles.bottomSheetOverlay : null, { backgroundColor: colors.modalOverlay }]}>
        <View accessibilityViewIsModal accessibilityRole="alert" accessibilityLiveRegion="polite" style={[styles.card, error.financialOutcomeUncertain ? styles.bottomSheetCard : null, { backgroundColor: colors.surfaceCharcoal }]}>
          <ErrorStatusIcon severity={error.severity} />
          <Text accessibilityRole="header" style={styles.title}>{error.title}</Text>
          <Text style={styles.message}>{error.message}</Text>
          {error.financialOutcomeUncertain && error.reference ? (
            <Text selectable style={styles.reference}>Reference: {error.reference}</Text>
          ) : null}
          {remainingMs > 0 ? <Text style={styles.waitText}>You can try again in {Math.ceil(remainingMs / 1000)} seconds.</Text> : null}
          {primaryAction ? (
            <Pressable accessibilityRole="button" accessibilityLabel={actionLabels[primaryAction]} disabled={busyAction !== null || (primaryAction === "retry_connection" && remainingMs > 0)} onPress={() => void runAction(primaryAction)} style={[styles.primary, { backgroundColor: accent, opacity: busyAction || (primaryAction === "retry_connection" && remainingMs > 0) ? 0.55 : 1 }]}>
              {busyAction === primaryAction ? <ActivityIndicator color="#11131a" /> : <Text style={styles.primaryText}>{actionLabels[primaryAction]}</Text>}
            </Pressable>
          ) : null}
          <Pressable accessibilityRole="button" onPress={onDismiss} style={styles.secondary}><Text style={styles.secondaryText}>{primaryAction ? "Close" : "Dismiss"}</Text></Pressable>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: { flex: 1, justifyContent: "center", alignItems: "center", padding: 24 },
  bottomSheetOverlay: { justifyContent: "flex-end", paddingHorizontal: 12, paddingBottom: 12 },
  card: { width: "100%", maxWidth: 420, borderRadius: 24, padding: 24, alignItems: "center", borderWidth: StyleSheet.hairlineWidth, borderColor: "rgba(255,255,255,0.12)" },
  bottomSheetCard: { maxWidth: 560, borderRadius: 24, paddingBottom: 30 },
  icon: { width: 58, height: 58, borderWidth: 1, borderRadius: 29, alignItems: "center", justifyContent: "center", marginBottom: 18 },
  iconText: { fontFamily: fonts.display, fontSize: 27, lineHeight: 32 },
  title: { color: "#faf9fc", fontFamily: fonts.semibold, fontSize: 20, textAlign: "center" },
  message: { marginTop: 9, color: "#b9b7c1", fontFamily: fonts.body, fontSize: 14, lineHeight: 21, textAlign: "center" },
  reference: { marginTop: 12, color: "#ddd9e6", fontFamily: fonts.medium, fontSize: 12 },
  waitText: { marginTop: 10, color: "#e7ad4f", fontFamily: fonts.medium, fontSize: 12 },
  primary: { minHeight: 48, alignSelf: "stretch", justifyContent: "center", alignItems: "center", borderRadius: 14, marginTop: 22, paddingHorizontal: 16 },
  primaryText: { color: "#101116", fontFamily: fonts.semibold, fontSize: 14 },
  secondary: { minHeight: 44, justifyContent: "center", alignItems: "center", marginTop: 4, paddingHorizontal: 16 },
  secondaryText: { color: "#d3d0da", fontFamily: fonts.medium, fontSize: 14 },
});
