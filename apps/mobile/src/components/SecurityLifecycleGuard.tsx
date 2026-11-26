import * as LocalAuthentication from "expo-local-authentication";
import { useCallback, useEffect, useRef, useState, type ReactNode } from "react";
import {
  ActivityIndicator,
  AppState,
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

import { useAuth } from "../context/AuthContext";
import { useSecurityPreferences } from "../context/SecurityPreferencesContext";
import { fonts } from "../theme/colors";

export function SecurityLifecycleGuard({ children }: { children: ReactNode }) {
  const { user, request, logout } = useAuth();
  const { preferences, preferencesReady } = useSecurityPreferences();
  const [locked, setLocked] = useState(false);
  const [password, setPassword] = useState("");
  const [twoFactorCode, setTwoFactorCode] = useState("");
  const [unlockError, setUnlockError] = useState("");
  const [unlockBusy, setUnlockBusy] = useState(false);
  const [usePasswordFallback, setUsePasswordFallback] = useState(false);
  const lastInteraction = useRef(Date.now());
  const backgroundedAt = useRef<number | null>(null);
  const lockStarted = useRef(false);

  const lock = useCallback(() => {
    if (lockStarted.current || !user) return;
    lockStarted.current = true;
    setLocked(true);
    setUnlockError("");
    setPassword("");
    setTwoFactorCode("");
    setUsePasswordFallback(false);
  }, [user]);

  useEffect(() => {
    if (!user || !preferencesReady || Platform.OS === "web") {
      setLocked(false);
      lockStarted.current = false;
      return;
    }
    const subscription = AppState.addEventListener("change", (state) => {
      if (state !== "active") {
        if (backgroundedAt.current === null) backgroundedAt.current = Date.now();
        return;
      }
      const backgrounded = backgroundedAt.current;
      backgroundedAt.current = null;
      if (backgrounded === null) return;
      const elapsedSeconds = Math.floor((Date.now() - backgrounded) / 1000);
      if (preferences.autoLockSeconds === 0 || elapsedSeconds >= preferences.autoLockSeconds) lock();
      else lastInteraction.current = Date.now();
    });
    const inactivityTimer = setInterval(() => {
      if (
        AppState.currentState === "active"
        && preferences.autoLockSeconds > 0
        && (Date.now() - lastInteraction.current) / 1000 >= preferences.autoLockSeconds
      ) {
        lock();
      }
    }, 1000);
    return () => {
      subscription.remove();
      clearInterval(inactivityTimer);
    };
  }, [lock, preferences.autoLockSeconds, preferencesReady, user]);

  useEffect(() => {
    if (!locked || !preferences.biometricUnlock || Platform.OS === "web") return;
    let active = true;
    async function authenticate() {
      try {
        const result = await LocalAuthentication.authenticateAsync({
          promptMessage: "Unlock ExaEarn",
          cancelLabel: "Use password",
          disableDeviceFallback: false,
        });
        if (!active) return;
        if (result.success) {
          setLocked(false);
          lockStarted.current = false;
          lastInteraction.current = Date.now();
          setUsePasswordFallback(false);
        } else if (result.error !== "user_cancel") {
          setUnlockError("Biometric unlock did not succeed. Use your account password to continue.");
          setUsePasswordFallback(true);
        } else {
          setUsePasswordFallback(true);
        }
      } catch {
        if (active) setUnlockError("Biometric authentication is unavailable. Use your account password.");
      }
    }
    void authenticate();
    return () => {
      active = false;
    };
  }, [locked, preferences.biometricUnlock]);

  async function unlockWithPassword() {
    if (!password || unlockBusy) {
      setUnlockError("Enter your account password to unlock.");
      return;
    }
    setUnlockBusy(true);
    setUnlockError("");
    try {
      await request("/api/auth/reauthenticate", {
        method: "POST",
        body: JSON.stringify({
          password,
          two_factor_code: twoFactorCode || undefined,
        }),
      });
      setLocked(false);
      lockStarted.current = false;
      lastInteraction.current = Date.now();
      setPassword("");
      setTwoFactorCode("");
    } catch (error) {
      setUnlockError(error instanceof Error ? error.message : "Account reauthentication failed.");
    } finally {
      setUnlockBusy(false);
    }
  }

  function recordInteraction() {
    if (!locked) lastInteraction.current = Date.now();
  }

  return (
    <View
      style={styles.fill}
      onStartShouldSetResponderCapture={() => {
        recordInteraction();
        return false;
      }}
      onMoveShouldSetResponderCapture={() => {
        recordInteraction();
        return false;
      }}
    >
      {children}
      <Modal visible={locked && Boolean(user)} transparent animationType="fade" onRequestClose={() => undefined}>
        <View style={styles.backdrop}>
          <View style={styles.card}>
            <View style={styles.lockIcon}><Text style={styles.lockGlyph}>●</Text></View>
            <Text style={styles.title} accessibilityRole="header">ExaEarn is locked</Text>
            <Text style={styles.copy}>Verify your account to continue. Biometric app unlock does not authorize financial transactions.</Text>
            {!preferences.biometricUnlock || usePasswordFallback ? (
              <>
                <TextInput
                  value={password}
                  onChangeText={setPassword}
                  placeholder="Account password"
                  placeholderTextColor="#768293"
                  secureTextEntry
                  autoCapitalize="none"
                  style={styles.input}
                  accessibilityLabel="Account password"
                  returnKeyType="done"
                  onSubmitEditing={() => void unlockWithPassword()}
                />
                {Boolean(user?.two_factor_enabled) ? (
                  <TextInput
                    value={twoFactorCode}
                    onChangeText={setTwoFactorCode}
                    placeholder="2FA code"
                    placeholderTextColor="#768293"
                    keyboardType="number-pad"
                    maxLength={6}
                    style={styles.input}
                    accessibilityLabel="Two-factor authentication code"
                  />
                ) : null}
                <Pressable onPress={() => void unlockWithPassword()} disabled={unlockBusy} style={styles.primary}>
                  {unlockBusy ? <ActivityIndicator size="small" color="#101820" /> : <Text style={styles.primaryText}>Unlock securely</Text>}
                </Pressable>
              </>
            ) : (
              <Pressable onPress={() => { setUsePasswordFallback(true); setUnlockError(""); }} style={styles.secondary}>
                <Text style={styles.secondaryText}>Use account password instead</Text>
              </Pressable>
            )}
            {unlockError ? <Text style={styles.error} accessibilityRole="alert">{unlockError}</Text> : null}
            <Pressable onPress={() => void logout()} style={styles.signOut}>
              <Text style={styles.signOutText}>Sign out instead</Text>
            </Pressable>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  backdrop: { flex: 1, justifyContent: "center", padding: 22, backgroundColor: "rgba(0,0,0,0.84)" },
  card: { width: "100%", maxWidth: 430, alignSelf: "center", borderWidth: 1, borderColor: "rgba(255,255,255,0.12)", borderRadius: 20, backgroundColor: "#0d131b", padding: 22 },
  lockIcon: { width: 52, height: 52, justifyContent: "center", alignItems: "center", borderRadius: 26, borderWidth: 1, borderColor: "rgba(103,184,232,0.32)", backgroundColor: "rgba(103,184,232,0.10)" },
  lockGlyph: { color: "#67b8e8", fontSize: 13 },
  title: { marginTop: 16, color: "#f2f5f8", fontFamily: fonts.display, fontSize: 22 },
  copy: { marginTop: 8, color: "#a6afbc", fontFamily: fonts.body, fontSize: 13, lineHeight: 19 },
  input: { minHeight: 48, marginTop: 12, borderWidth: 1, borderColor: "rgba(255,255,255,0.12)", borderRadius: 11, paddingHorizontal: 12, color: "#f2f5f8", fontFamily: fonts.body, fontSize: 14 },
  primary: { minHeight: 48, marginTop: 14, alignItems: "center", justifyContent: "center", borderRadius: 11, backgroundColor: "#dce6ee" },
  primaryText: { color: "#101820", fontFamily: fonts.semibold, fontSize: 13 },
  secondary: { minHeight: 44, marginTop: 12, alignItems: "center", justifyContent: "center" },
  secondaryText: { color: "#a6afbc", fontFamily: fonts.body, fontSize: 12, textAlign: "center" },
  error: { marginTop: 10, color: "#f08c8c", fontFamily: fonts.body, fontSize: 12, lineHeight: 17 },
  signOut: { marginTop: 14, alignSelf: "center", padding: 8 },
  signOutText: { color: "#a6afbc", fontFamily: fonts.medium, fontSize: 12 },
});
