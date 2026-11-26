import { Ionicons } from "@expo/vector-icons";
import * as LocalAuthentication from "expo-local-authentication";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
  useWindowDimensions,
} from "react-native";
import type { ReactNode } from "react";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { useAuth } from "../context/AuthContext";
import { useSecurityPreferences, type AutoLockSeconds } from "../context/SecurityPreferencesContext";
import { fonts } from "../theme/colors";

type Props = {
  fontsReady: boolean;
  onBack: () => void;
  onOpenExaPay: () => void;
  onOpenExaCard: () => void;
};

type Session = {
  id: string | number;
  name?: string;
  last_used_at?: string | null;
  created_at?: string | null;
  risk_state?: string;
  is_current?: boolean;
};

type LoginDevice = {
  id: string | number;
  device_name?: string;
  browser?: string;
  approximate_region?: string | null;
  last_active?: string | null;
};

type SecurityEvent = {
  id: string | number;
  action?: string;
  type?: string;
  status?: string;
  created_at?: string;
  device?: string | null;
};

type SecurityPayload = {
  data?: {
    sessions?: Session[];
    devices?: LoginDevice[];
  } | Session[] | SecurityEvent[];
  sessions?: Session[];
  devices?: LoginDevice[];
};

type SecurityPage = "center" | "sessions" | "history";
type SensitiveAction = { kind: "revoke-session"; session: Session } | { kind: "revoke-others" } | { kind: "disable-biometric" };

const lockChoices: Array<{ label: string; seconds: AutoLockSeconds }> = [
  { label: "Immediately", seconds: 0 },
  { label: "30 sec", seconds: 30 },
  { label: "1 min", seconds: 60 },
  { label: "5 min", seconds: 300 },
];

function messageFrom(error: unknown, fallback: string) {
  return error instanceof Error ? error.message : fallback;
}

function formatWhen(value?: string | null) {
  if (!value) return "Not available";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "Not available" : date.toLocaleString();
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <View style={styles.section}>
      <Text style={styles.sectionTitle}>{title}</Text>
      <View style={styles.group}>{children}</View>
    </View>
  );
}

function Row({
  icon,
  title,
  detail,
  status,
  actionLabel,
  onPress,
  last = false,
  disabled = false,
  right,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  title: string;
  detail: string;
  status?: { label: string; tone?: "green" | "amber" | "muted" | "red" };
  actionLabel?: string;
  onPress?: () => void;
  last?: boolean;
  disabled?: boolean;
  right?: ReactNode;
}) {
  const content = (
    <>
      <View style={styles.rowIcon}><Ionicons name={icon} size={17} color="#a6afbc" /></View>
      <View style={styles.rowCopy}>
        <Text style={styles.rowTitle}>{title}</Text>
        <Text style={styles.rowDetail}>{detail}</Text>
        {status ? <Text style={[styles.rowStatus, status.tone ? styles[`tone_${status.tone}`] : null]}>{status.label}</Text> : null}
      </View>
      {right ?? (onPress ? (
        <View style={styles.rowAction}>
          {actionLabel ? <Text style={[styles.rowActionText, disabled ? styles.rowActionDisabled : null]}>{actionLabel}</Text> : null}
          <Ionicons name="chevron-forward" size={16} color={disabled ? "#515c6a" : "#7f8a99"} />
        </View>
      ) : null)}
    </>
  );
  return onPress ? (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      style={[styles.row, !last ? styles.rowDivider : null, disabled ? styles.rowDisabled : null]}
      accessibilityRole="button"
    >
      {content}
    </Pressable>
  ) : (
    <View style={[styles.row, !last ? styles.rowDivider : null]}>{content}</View>
  );
}

export default function SecurityCenterScreen({ fontsReady, onBack, onOpenExaPay, onOpenExaCard }: Props) {
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const shellWidth = Math.min(width - 28, 620);
  const { user, request, logout } = useAuth();
  const {
    preferences,
    secureStorageAvailable,
    preferencesReady,
    preferencesError,
    isSensitiveHidden,
    screenshotNotice,
    screenshotProtectionActive,
    updatePreference,
    toggleSensitiveReveal,
    dismissScreenshotNotice,
  } = useSecurityPreferences();
  const [page, setPage] = useState<SecurityPage>("center");
  const [sessions, setSessions] = useState<Session[]>([]);
  const [devices, setDevices] = useState<LoginDevice[]>([]);
  const [events, setEvents] = useState<SecurityEvent[]>([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [biometricStatus, setBiometricStatus] = useState("Checking device...");
  const [biometricAvailable, setBiometricAvailable] = useState(false);
  const [twoFactorSetupOpen, setTwoFactorSetupOpen] = useState(false);
  const [reauthOpen, setReauthOpen] = useState(false);
  const [reauthPassword, setReauthPassword] = useState("");
  const [reauthCode, setReauthCode] = useState("");
  const [pendingSensitiveAction, setPendingSensitiveAction] = useState<SensitiveAction | null>(null);

  const twoFactorEnabled = user?.two_factor_enabled === true || user?.two_factor_enabled === 1;
  const isWeb = Platform.OS === "web";
  const autoLockLabel = useMemo(
    () => lockChoices.find((item) => item.seconds === preferences.autoLockSeconds)?.label ?? "Immediately",
    [preferences.autoLockSeconds],
  );

  const loadSessions = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const payload = await request<SecurityPayload>("/api/auth/sessions", { method: "GET" });
      const data = payload.data && !Array.isArray(payload.data) ? payload.data : {};
      setSessions(Array.isArray(data.sessions) ? data.sessions : Array.isArray(payload.sessions) ? payload.sessions : []);
      setDevices(Array.isArray(data.devices) ? data.devices : Array.isArray(payload.devices) ? payload.devices : []);
    } catch (requestError) {
      setError(messageFrom(requestError, "Active sessions could not be loaded."));
    } finally {
      setLoading(false);
    }
  }, [request]);

  const loadHistory = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const payload = await request<SecurityPayload>("/api/logs/my-activity?type=security&per_page=20", { method: "GET" });
      setEvents(Array.isArray(payload.data) ? payload.data as SecurityEvent[] : []);
    } catch (requestError) {
      setError(messageFrom(requestError, "Security activity could not be loaded."));
    } finally {
      setLoading(false);
    }
  }, [request]);

  useEffect(() => {
    if (page === "sessions") void loadSessions();
    if (page === "history") void loadHistory();
  }, [loadHistory, loadSessions, page]);

  useEffect(() => {
    let active = true;
    async function checkBiometrics() {
      if (Platform.OS === "web") {
        setBiometricStatus("Unavailable in web preview");
        setBiometricAvailable(false);
        return;
      }
      try {
        const [hardware, enrolled, types] = await Promise.all([
          LocalAuthentication.hasHardwareAsync(),
          LocalAuthentication.isEnrolledAsync(),
          LocalAuthentication.supportedAuthenticationTypesAsync(),
        ]);
        if (!active) return;
        const available = hardware && enrolled && types.length > 0;
        setBiometricAvailable(available);
        setBiometricStatus(!hardware ? "No biometric hardware" : !enrolled ? "No biometrics enrolled on device" : available ? "Available on this device" : "Unavailable");
      } catch {
        if (active) {
          setBiometricAvailable(false);
          setBiometricStatus("Device capability check failed");
        }
      }
    }
    void checkBiometrics();
    return () => {
      active = false;
    };
  }, []);

  async function changePreference<K extends keyof typeof preferences>(
    key: K,
    value: (typeof preferences)[K],
  ) {
    setError("");
    setSuccess("");
    try {
      await updatePreference(key, value);
      setSuccess("Security preference saved on this device.");
    } catch (updateError) {
      setError(messageFrom(updateError, "Security preference could not be saved."));
    }
  }

  async function toggleBiometrics(value: boolean) {
    setError("");
    if (!value) {
      if (preferences.biometricUnlock) {
        try {
          const result = await LocalAuthentication.authenticateAsync({
            promptMessage: "Confirm biometric unlock change",
            disableDeviceFallback: false,
          });
          if (result.success) {
            await changePreference("biometricUnlock", false);
          } else {
            beginSensitiveAction({ kind: "disable-biometric" });
          }
        } catch {
          beginSensitiveAction({ kind: "disable-biometric" });
        }
        return;
      }
      await changePreference("biometricUnlock", false);
      return;
    }
    if (isWeb || !biometricAvailable) {
      setError(`Biometric unlock is unavailable: ${biometricStatus.toLowerCase()}.`);
      return;
    }
    try {
      const result = await LocalAuthentication.authenticateAsync({
        promptMessage: "Confirm biometric unlock enrollment",
        disableDeviceFallback: false,
      });
      if (!result.success) {
        setError("Biometric enrollment was not confirmed. No setting was changed.");
        return;
      }
      await changePreference("biometricUnlock", true);
    } catch (authenticationError) {
      setError(messageFrom(authenticationError, "Device authentication could not be completed."));
    }
  }

  function beginSensitiveAction(action: SensitiveAction) {
    setPendingSensitiveAction(action);
    setReauthPassword("");
    setReauthCode("");
    setError("");
    setReauthOpen(true);
  }

  function cancelSensitiveAction() {
    if (saving) return;
    setReauthOpen(false);
    setPendingSensitiveAction(null);
    setReauthPassword("");
    setReauthCode("");
    setError("");
  }

  async function confirmSensitiveAction() {
    if (!pendingSensitiveAction || !reauthPassword || saving) {
      setError("Enter your account password to continue.");
      return;
    }
    setSaving(true);
    setError("");
    try {
      await request("/api/auth/reauthenticate", {
        method: "POST",
        body: JSON.stringify({ password: reauthPassword, two_factor_code: reauthCode || undefined }),
      });
      if (pendingSensitiveAction.kind === "disable-biometric") {
        await updatePreference("biometricUnlock", false);
        setSuccess("Biometric app unlock disabled.");
      } else if (pendingSensitiveAction.kind === "revoke-session") {
        await request(`/api/auth/sessions/${encodeURIComponent(String(pendingSensitiveAction.session.id))}`, { method: "DELETE" });
        if (pendingSensitiveAction.session.is_current) {
          await logout();
          return;
        }
        setSuccess("Session revoked.");
        await loadSessions();
      } else {
        const response = await request<{ revoked_count?: number }>("/api/auth/logout-others", { method: "POST" });
        setSuccess(`${Number(response.revoked_count ?? 0)} other session(s) revoked.`);
        await loadSessions();
      }
      setReauthOpen(false);
      setPendingSensitiveAction(null);
      setReauthPassword("");
      setReauthCode("");
    } catch (actionError) {
      setError(messageFrom(actionError, "The sensitive security action could not be completed."));
    } finally {
      setSaving(false);
    }
  }

  const headerTitle = page === "sessions" ? "Devices & sessions" : page === "history" ? "Security activity" : "Security";

  return (
    <View style={styles.fill}>
      <ScrollView
        contentContainerStyle={[
          styles.content,
          { width: shellWidth, paddingTop: insets.top + 10, paddingBottom: insets.bottom + 28 },
        ]}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.header}>
          <Pressable onPress={page === "center" ? onBack : () => setPage("center")} style={styles.backButton} accessibilityRole="button" accessibilityLabel="Back">
            <Ionicons name="arrow-back" size={18} color="#dce4ec" />
          </Pressable>
          <View style={styles.headerCopy}>
            <Text style={[styles.title, !fontsReady ? styles.systemFont : null]}>{headerTitle}</Text>
            <Text style={styles.subtitle}>Protect your ExaEarn account and device.</Text>
          </View>
          <View style={styles.headerShield}><Ionicons name="shield-checkmark-outline" size={19} color="#72d3a0" /></View>
        </View>

        {error || preferencesError ? (
          <View style={styles.feedbackError} accessibilityRole="alert">
            <Ionicons name="alert-circle-outline" size={17} color="#f08c8c" />
            <Text style={styles.feedbackText}>{error || preferencesError}</Text>
          </View>
        ) : null}
        {success ? (
          <View style={styles.feedbackSuccess} accessibilityLiveRegion="polite">
            <Ionicons name="checkmark-circle-outline" size={17} color="#72d3a0" />
            <Text style={styles.feedbackText}>{success}</Text>
          </View>
        ) : null}
        {screenshotNotice ? (
          <Pressable style={styles.captureNotice} onPress={dismissScreenshotNotice} accessibilityRole="button">
            <Ionicons name="eye-off-outline" size={17} color="#e8b866" />
            <Text style={styles.feedbackText}>Screenshot detected. Sensitive values are hidden until you dismiss this notice.</Text>
            <Ionicons name="close" size={16} color="#a6afbc" />
          </Pressable>
        ) : null}

        {page === "center" ? (
          <>
            <View style={styles.statusBanner}>
              <View style={[styles.statusDot, { backgroundColor: secureStorageAvailable ? "#72d3a0" : "#e8b866" }]} />
              <View style={styles.statusBannerCopy}>
                <Text style={styles.statusBannerTitle}>
                  {preferencesReady
                    ? secureStorageAvailable
                      ? "Device security storage ready"
                      : "Secure storage unavailable"
                    : "Checking device security"}
                </Text>
                <Text style={styles.statusBannerDetail}>
                  {isWeb
                    ? "Web preview stores preferences in memory only."
                    : secureStorageAvailable
                      ? "Preferences use the platform's protected keychain / keystore."
                      : "Security preferences cannot be persisted securely on this device."}
                </Text>
              </View>
              {!preferencesReady ? <ActivityIndicator size="small" color="#a6afbc" /> : null}
            </View>

            <Section title="Vault & encryption">
              <Row
                icon="lock-closed-outline"
                title="Financial vault encryption"
                detail="Required protection is not a user-disableable setting. Financial credentials are not persisted locally by this app."
                status={{ label: "No local credential vault configured", tone: "amber" }}
              />
              <Row
                icon="key-outline"
                title="Secure device storage"
                detail="Protects the API session token and local preferences with OS-backed storage. Passwords, PINs, and private keys are never stored."
                status={{ label: secureStorageAvailable ? "OS-backed storage available" : "Not available", tone: secureStorageAvailable ? "green" : "amber" }}
              />
              <Row
                icon="refresh-outline"
                title="Account recovery"
                detail="Recovery secrets are not stored or displayed in this app."
                status={{ label: "Account recovery flow", tone: "muted" }}
                last
              />
            </Section>

            <Section title="Authentication">
              <Row
                icon="finger-print-outline"
                title="Biometric app unlock"
                detail={`${biometricStatus}. Biometrics or device passcode unlock this app only; transaction authorization remains separate.`}
                right={(
                  <Switch
                    value={preferences.biometricUnlock}
                    onValueChange={(value) => void toggleBiometrics(value)}
                    disabled={!preferencesReady || (isWeb && !preferences.biometricUnlock) || (preferences.biometricUnlock && isWeb)}
                    trackColor={{ false: "#36404d", true: "#39795d" }}
                    thumbColor={preferences.biometricUnlock ? "#72d3a0" : "#c2ccd6"}
                    accessibilityLabel="Biometric app unlock"
                  />
                )}
              />
              <View style={[styles.lockRow, styles.rowDivider]}>
                <View style={styles.rowIcon}><Ionicons name="timer-outline" size={17} color="#a6afbc" /></View>
                <View style={styles.lockCopy}>
                  <Text style={styles.rowTitle}>App auto-lock</Text>
                  <Text style={styles.rowDetail}>Lock after inactivity or when the app returns from background.</Text>
                </View>
                <Text style={styles.lockCurrent}>{autoLockLabel}</Text>
              </View>
              <View style={styles.segmentRow}>
                {lockChoices.map((choice) => (
                  <Pressable
                    key={choice.seconds}
                    onPress={() => void changePreference("autoLockSeconds", choice.seconds)}
                    disabled={saving || !secureStorageAvailable}
                    style={[styles.segment, preferences.autoLockSeconds === choice.seconds ? styles.segmentActive : null]}
                    accessibilityRole="button"
                    accessibilityState={{ selected: preferences.autoLockSeconds === choice.seconds }}
                  >
                    <Text style={[styles.segmentText, preferences.autoLockSeconds === choice.seconds ? styles.segmentTextActive : null]}>{choice.label}</Text>
                  </Pressable>
                ))}
              </View>
              <Row
                icon="keypad-outline"
                title="App PIN"
                detail="A separate local PIN enrollment and secure recovery flow is not implemented."
                status={{ label: "Unavailable", tone: "amber" }}
              />
              <Row
                icon="key-outline"
                title="Change password"
                detail="Password changes require an authenticated backend endpoint; none is exposed to this mobile client."
                status={{ label: "Unavailable", tone: "amber" }}
              />
              <Row
                icon="shield-outline"
                title="Two-factor authentication"
                detail={twoFactorEnabled
                  ? "The account API flag is enabled, but this client cannot confirm authenticator enrollment."
                  : "Backend setup and enrollment are not currently available."}
                status={{ label: twoFactorEnabled ? "Reported enabled by API" : "Not enabled", tone: "amber" }}
                actionLabel="Details"
                onPress={() => setTwoFactorSetupOpen(true)}
              />
              <Row
                icon="finger-print-outline"
                title="Passkeys"
                detail="Passkey registration is not exposed by the current authentication API."
                status={{ label: "Unavailable", tone: "muted" }}
                last
              />
            </Section>

            <Section title="Privacy">
              <PreferenceRow icon="eye-off-outline" title="Hide sensitive balances" detail="Mask the dashboard portfolio value. Tap the masked value to reveal it temporarily." value={preferences.hideBalances} disabled={!secureStorageAvailable} onChange={(value) => void changePreference("hideBalances", value)} />
              <Row
                icon="eye-outline"
                title="Tap to reveal"
                detail={isSensitiveHidden ? "Sensitive information is currently hidden." : "Sensitive information is visible."}
                actionLabel={isSensitiveHidden ? "Reveal" : "Hide"}
                onPress={toggleSensitiveReveal}
              />
              <PreferenceRow
                icon="phone-portrait-outline"
                title="Screen-capture privacy"
                detail={Platform.OS === "android"
                  ? "Android secure-window protection blocks screenshots and screen recording while enabled."
                  : Platform.OS === "ios"
                    ? "iOS app-switcher content is masked; screenshots are detected but cannot be fully prevented."
                    : "Native capture protection is unavailable in web preview."}
                value={preferences.screenshotProtection}
                disabled={!secureStorageAvailable || isWeb}
                onChange={(value) => void changePreference("screenshotProtection", value)}
                status={preferences.screenshotProtection
                  ? screenshotProtectionActive
                    ? { label: Platform.OS === "android" ? "Android secure window active" : "iOS masking and detection active", tone: "green" }
                    : { label: "Protection is enabled in settings but not active", tone: "red" }
                  : undefined}
              />
              <PreferenceRow
                icon="notifications-outline"
                title="Notification privacy"
                detail="Hide notification message previews inside this app's dashboard tray."
                value={preferences.privateNotifications}
                disabled={!secureStorageAvailable}
                onChange={(value) => void changePreference("privateNotifications", value)}
                last
              />
              <Row
                icon="copy-outline"
                title="Clipboard privacy"
                detail="The app does not monitor clipboard history. Automatic clearing of external clipboard data is unavailable."
                status={{ label: "Not supported", tone: "muted" }}
                last={false}
              />
            </Section>

            <Section title="Account & device security">
              <Row
                icon="phone-portrait-outline"
                title="Devices & active sessions"
                detail="Review active API sessions and login-device records."
                actionLabel="Manage"
                onPress={() => setPage("sessions")}
              />
              <Row
                icon="time-outline"
                title="Security activity"
                detail="View security events recorded by the account API."
                actionLabel="View"
                onPress={() => setPage("history")}
              />
              <Row
                icon="notifications-outline"
                title="New login alerts"
                detail="Delivery preferences and alert history are managed by the backend."
                status={{ label: "No mobile configuration endpoint", tone: "amber" }}
              />
              <Row
                icon="shield-checkmark-outline"
                title="Trusted device enrollment"
                detail="Device history is read-only. ExaPay offline simulation enrollment does not secure monetary value."
                status={{ label: "No trusted-device enrollment API", tone: "amber" }}
                last
              />
            </Section>

            <Section title="Financial security">
              <Row icon="swap-horizontal-outline" title="ExaPay security" detail="Review payment security and offline simulation disclosures." actionLabel="Open" onPress={onOpenExaPay} />
              <Row icon="card-outline" title="ExaCard security" detail="Card controls and card freeze remain in the ExaCard module." actionLabel="Open" onPress={onOpenExaCard} />
              <Row
                icon="cash-outline"
                title="Withdrawal & transfer controls"
                detail="Limits and confirmation rules must be enforced by the financial API."
                status={{ label: "No mobile preference endpoint", tone: "amber" }}
              />
              <Row
                icon="list-outline"
                title="Address allowlisting"
                detail="An address allowlist management endpoint is not available in this app."
                status={{ label: "Unavailable", tone: "muted" }}
                last
              />
            </Section>
          </>
        ) : page === "sessions" ? (
          <>
            <Section title="Active API sessions">
              {loading ? <ActivityIndicator style={styles.loading} color="#a6afbc" /> : null}
              {!loading && !sessions.length ? <Text style={styles.emptyText}>No session records returned by the account API.</Text> : null}
              {sessions.map((session, index) => (
                <Row
                  key={String(session.id)}
                  icon={session.is_current ? "phone-portrait-outline" : "desktop-outline"}
                  title={session.name || "ExaEarn session"}
                  detail={`${session.is_current ? "This device · " : ""}Last active ${formatWhen(session.last_used_at || session.created_at)}`}
                  status={{ label: session.risk_state || "Status not reported", tone: session.risk_state === "NORMAL" ? "green" : "muted" }}
                  actionLabel={session.is_current ? "This device" : "Revoke"}
                  disabled={Boolean(session.is_current)}
                  onPress={() => beginSensitiveAction({ kind: "revoke-session", session })}
                  last={index === sessions.length - 1 && !devices.length}
                />
              ))}
            </Section>
            <Section title="Login-device records">
              {devices.length ? devices.map((device, index) => (
                <Row
                  key={String(device.id)}
                  icon="phone-portrait-outline"
                  title={device.device_name || "Device"}
                  detail={`${device.approximate_region || "Region unavailable"} · Last login ${formatWhen(device.last_active)}`}
                  status={{ label: "Recorded by account API", tone: "muted" }}
                  last={index === devices.length - 1}
                />
              )) : <Text style={styles.emptyText}>No login-device records returned.</Text>}
            </Section>
            <Pressable style={styles.dangerAction} onPress={() => beginSensitiveAction({ kind: "revoke-others" })}>
              <Ionicons name="log-out-outline" size={17} color="#f08c8c" />
              <Text style={styles.dangerActionText}>Sign out other sessions</Text>
            </Pressable>
            <Text style={styles.footnote}>Session revocation and recent-auth checks are enforced by the account API. Device records cannot be revoked independently yet.</Text>
          </>
        ) : (
          <Section title="Recent security events">
            {loading ? <ActivityIndicator style={styles.loading} color="#a6afbc" /> : null}
            {!loading && !events.length ? <Text style={styles.emptyText}>No security activity records were returned.</Text> : null}
            {events.map((event, index) => (
              <Row
                key={String(event.id)}
                icon={event.status === "failed" ? "alert-circle-outline" : "shield-checkmark-outline"}
                title={String(event.action || "Security event").replace(/[_-]/g, " ")}
                detail={`${formatWhen(event.created_at)}${event.device ? ` · ${event.device}` : ""}`}
                status={{ label: event.status || "Recorded", tone: event.status === "failed" ? "red" : "muted" }}
                last={index === events.length - 1}
              />
            ))}
          </Section>
        )}

        {page !== "center" ? (
          <Pressable onPress={page === "sessions" ? () => void loadSessions() : () => void loadHistory()} style={styles.refreshButton} disabled={loading}>
            {loading ? <ActivityIndicator size="small" color="#dce4ec" /> : <Ionicons name="refresh-outline" size={16} color="#dce4ec" />}
            <Text style={styles.refreshText}>Refresh</Text>
          </Pressable>
        ) : null}
      </ScrollView>

      <Modal visible={twoFactorSetupOpen} transparent animationType="fade" onRequestClose={() => setTwoFactorSetupOpen(false)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Two-factor authentication</Text>
            <Text style={styles.modalCopy}>
              {twoFactorEnabled
                ? "The account API flag is enabled, but this client cannot confirm an authenticator is enrolled. Server-side OTP/TOTP setup and verification must be available before relying on this protection."
                : "This mobile client has no setup endpoint. The current backend verification route returns HTTP 501 until an OTP/TOTP provider is integrated. No local or simulated enrollment is offered."}
            </Text>
            <Pressable onPress={() => setTwoFactorSetupOpen(false)} style={styles.modalPrimary}><Text style={styles.modalPrimaryText}>Understood</Text></Pressable>
          </View>
        </View>
      </Modal>

      <Modal visible={reauthOpen} transparent animationType="fade" onRequestClose={cancelSensitiveAction}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>Confirm it’s you</Text>
            <Text style={styles.modalCopy}>This sensitive change requires a fresh account-password check. App biometrics alone do not authorize session revocation.</Text>
            <TextInput value={reauthPassword} onChangeText={setReauthPassword} placeholder="Account password" placeholderTextColor="#768293" secureTextEntry autoCapitalize="none" style={styles.modalInput} accessibilityLabel="Account password" />
            {twoFactorEnabled ? <TextInput value={reauthCode} onChangeText={setReauthCode} placeholder="2FA code" placeholderTextColor="#768293" keyboardType="number-pad" maxLength={6} style={styles.modalInput} accessibilityLabel="Two-factor authentication code" /> : null}
            {error ? <Text style={styles.modalError} accessibilityRole="alert">{error}</Text> : null}
            <View style={styles.modalActions}>
              <Pressable onPress={cancelSensitiveAction} disabled={saving} style={styles.modalCancel}><Text style={styles.modalCancelText}>Cancel</Text></Pressable>
              <Pressable onPress={() => void confirmSensitiveAction()} disabled={saving} style={styles.modalPrimary}>
                {saving ? <ActivityIndicator size="small" color="#111720" /> : <Text style={styles.modalPrimaryText}>Continue</Text>}
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

function PreferenceRow({
  icon,
  title,
  detail,
  value,
  disabled = false,
  onChange,
  last = false,
  status,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  title: string;
  detail: string;
  value: boolean;
  disabled?: boolean;
  onChange: (value: boolean) => void;
  last?: boolean;
  status?: { label: string; tone?: "green" | "amber" | "muted" | "red" };
}) {
  return (
    <View style={[styles.row, !last ? styles.rowDivider : null, disabled ? styles.rowDisabled : null]}>
      <View style={styles.rowIcon}><Ionicons name={icon} size={17} color="#a6afbc" /></View>
      <View style={styles.rowCopy}>
        <Text style={styles.rowTitle}>{title}</Text>
        <Text style={styles.rowDetail}>{detail}</Text>
        {status ? <Text style={[styles.rowStatus, status.tone ? styles[`tone_${status.tone}`] : null]}>{status.label}</Text> : null}
      </View>
      <Switch
        value={value}
        onValueChange={onChange}
        disabled={disabled}
        trackColor={{ false: "#36404d", true: "#39795d" }}
        thumbColor={value ? "#72d3a0" : "#c2ccd6"}
        accessibilityLabel={title}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: "#090b12" },
  content: { alignSelf: "center", paddingHorizontal: 2 },
  header: { flexDirection: "row", alignItems: "center", gap: 12, marginBottom: 19 },
  backButton: { width: 40, height: 40, alignItems: "center", justifyContent: "center", borderWidth: 1, borderColor: "rgba(255,255,255,0.10)", borderRadius: 13, backgroundColor: "#111720" },
  headerCopy: { flex: 1 },
  title: { color: "#f2f5f8", fontFamily: fonts.display, fontSize: 24 },
  systemFont: { fontFamily: undefined },
  subtitle: { marginTop: 3, color: "#929dad", fontFamily: fonts.body, fontSize: 11 },
  headerShield: { width: 38, height: 38, alignItems: "center", justifyContent: "center", borderRadius: 12, borderWidth: 1, borderColor: "rgba(114,211,160,0.18)", backgroundColor: "rgba(114,211,160,0.06)" },
  statusBanner: { flexDirection: "row", alignItems: "center", gap: 11, marginBottom: 5, padding: 13, borderRadius: 14, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "#0f151e" },
  statusDot: { width: 9, height: 9, borderRadius: 5 },
  statusBannerCopy: { flex: 1 },
  statusBannerTitle: { color: "#e5ebf1", fontFamily: fonts.semibold, fontSize: 12 },
  statusBannerDetail: { marginTop: 3, color: "#929dad", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  section: { marginTop: 19 },
  sectionTitle: { marginBottom: 8, color: "#dce4ec", fontFamily: fonts.semibold, fontSize: 12, letterSpacing: 0.3 },
  group: { overflow: "hidden", borderWidth: 1, borderColor: "rgba(224,232,241,0.09)", borderRadius: 15, backgroundColor: "#111720" },
  row: { minHeight: 68, flexDirection: "row", alignItems: "center", gap: 11, paddingHorizontal: 12, paddingVertical: 11 },
  rowDivider: { borderBottomWidth: 1, borderBottomColor: "rgba(255,255,255,0.06)" },
  rowDisabled: { opacity: 0.72 },
  rowIcon: { width: 31, height: 31, alignItems: "center", justifyContent: "center", borderRadius: 10, backgroundColor: "rgba(255,255,255,0.045)" },
  rowCopy: { flex: 1 },
  rowTitle: { color: "#e7edf3", fontFamily: fonts.medium, fontSize: 12 },
  rowDetail: { marginTop: 3, color: "#929dad", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  rowStatus: { marginTop: 4, fontFamily: fonts.medium, fontSize: 9 },
  tone_green: { color: "#72d3a0" },
  tone_amber: { color: "#e8b866" },
  tone_muted: { color: "#8e99a7" },
  tone_red: { color: "#f08c8c" },
  rowAction: { flexDirection: "row", alignItems: "center", gap: 3 },
  rowActionText: { color: "#c5ced8", fontFamily: fonts.medium, fontSize: 10 },
  rowActionDisabled: { color: "#657180" },
  lockRow: { minHeight: 62, flexDirection: "row", alignItems: "center", gap: 11, paddingHorizontal: 12, paddingTop: 10 },
  lockCopy: { flex: 1 },
  lockCurrent: { color: "#e8edf2", fontFamily: fonts.medium, fontSize: 10 },
  segmentRow: { flexDirection: "row", gap: 5, paddingHorizontal: 11, paddingVertical: 10 },
  segment: { flex: 1, minHeight: 33, alignItems: "center", justifyContent: "center", borderRadius: 9, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "#0d131b" },
  segmentActive: { borderColor: "rgba(220,230,238,0.35)", backgroundColor: "rgba(220,230,238,0.12)" },
  segmentText: { color: "#98a3b2", fontFamily: fonts.medium, fontSize: 9 },
  segmentTextActive: { color: "#edf2f6" },
  feedbackError: { flexDirection: "row", alignItems: "flex-start", gap: 8, marginBottom: 8, padding: 11, borderRadius: 11, borderWidth: 1, borderColor: "rgba(240,140,140,0.22)", backgroundColor: "rgba(240,140,140,0.07)" },
  feedbackSuccess: { flexDirection: "row", alignItems: "flex-start", gap: 8, marginBottom: 8, padding: 11, borderRadius: 11, borderWidth: 1, borderColor: "rgba(114,211,160,0.22)", backgroundColor: "rgba(114,211,160,0.07)" },
  feedbackText: { flex: 1, color: "#cbd5df", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  captureNotice: { flexDirection: "row", alignItems: "center", gap: 8, marginBottom: 8, padding: 11, borderRadius: 11, backgroundColor: "rgba(232,184,102,0.08)" },
  emptyText: { padding: 14, color: "#929dad", fontFamily: fonts.body, fontSize: 11, lineHeight: 16 },
  loading: { padding: 15 },
  dangerAction: { minHeight: 48, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 8, marginTop: 14, borderWidth: 1, borderColor: "rgba(240,140,140,0.22)", borderRadius: 12, backgroundColor: "rgba(240,140,140,0.06)" },
  dangerActionText: { color: "#f08c8c", fontFamily: fonts.semibold, fontSize: 12 },
  footnote: { marginTop: 10, color: "#8792a0", fontFamily: fonts.body, fontSize: 10, lineHeight: 15 },
  refreshButton: { alignSelf: "center", flexDirection: "row", alignItems: "center", gap: 7, marginTop: 14, padding: 10 },
  refreshText: { color: "#dce4ec", fontFamily: fonts.medium, fontSize: 11 },
  modalBackdrop: { flex: 1, justifyContent: "center", padding: 20, backgroundColor: "rgba(0,0,0,0.76)" },
  modalCard: { width: "100%", maxWidth: 430, alignSelf: "center", borderWidth: 1, borderColor: "rgba(255,255,255,0.12)", borderRadius: 18, backgroundColor: "#111720", padding: 18 },
  modalTitle: { color: "#eef2f7", fontFamily: fonts.display, fontSize: 19 },
  modalCopy: { marginTop: 9, color: "#a6afbc", fontFamily: fonts.body, fontSize: 12, lineHeight: 18 },
  modalInput: { minHeight: 46, marginTop: 12, borderWidth: 1, borderColor: "rgba(255,255,255,0.12)", borderRadius: 10, paddingHorizontal: 12, color: "#eef2f7", fontFamily: fonts.body, fontSize: 13 },
  modalError: { marginTop: 9, color: "#f08c8c", fontFamily: fonts.body, fontSize: 11, lineHeight: 16 },
  modalActions: { flexDirection: "row", gap: 9, marginTop: 16 },
  modalCancel: { flex: 1, minHeight: 44, alignItems: "center", justifyContent: "center", borderWidth: 1, borderColor: "rgba(255,255,255,0.10)", borderRadius: 11 },
  modalCancelText: { color: "#dce4ec", fontFamily: fonts.medium, fontSize: 12 },
  modalPrimary: { flex: 1, minHeight: 44, alignItems: "center", justifyContent: "center", borderRadius: 11, backgroundColor: "#dce6ee" },
  modalPrimaryText: { color: "#101820", fontFamily: fonts.semibold, fontSize: 12 },
});
