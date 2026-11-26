import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { AppState, Platform } from "react-native";
import * as SecureStore from "expo-secure-store";
import * as ScreenCapture from "expo-screen-capture";

export type AutoLockSeconds = 0 | 30 | 60 | 300;

export type SecurityPreferences = {
  autoLockSeconds: AutoLockSeconds;
  biometricUnlock: boolean;
  hideBalances: boolean;
  privateNotifications: boolean;
  screenshotProtection: boolean;
};

type SecurityPreferencesContextValue = {
  preferences: SecurityPreferences;
  secureStorageAvailable: boolean | null;
  preferencesReady: boolean;
  preferencesError: string;
  isSensitiveHidden: boolean;
  screenshotNotice: boolean;
  screenshotProtectionActive: boolean;
  updatePreference: <K extends keyof SecurityPreferences>(key: K, value: SecurityPreferences[K]) => Promise<void>;
  toggleSensitiveReveal: () => void;
  dismissScreenshotNotice: () => void;
};

const STORAGE_KEY = "exaearn.security.preferences.v1";
const STORAGE_OPTIONS: SecureStore.SecureStoreOptions = {
  keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
};
const defaults: SecurityPreferences = {
  autoLockSeconds: 0,
  biometricUnlock: false,
  hideBalances: false,
  privateNotifications: true,
  screenshotProtection: false,
};
const SecurityPreferencesContext = createContext<SecurityPreferencesContextValue | null>(null);

function isAutoLockSeconds(value: unknown): value is AutoLockSeconds {
  return value === 0 || value === 30 || value === 60 || value === 300;
}

function parsePreferences(value: string | null): SecurityPreferences {
  if (!value) return defaults;
  try {
    const parsed: unknown = JSON.parse(value);
    if (!parsed || typeof parsed !== "object") return defaults;
    const source = parsed as Partial<SecurityPreferences>;
    return {
      autoLockSeconds: isAutoLockSeconds(source.autoLockSeconds) ? source.autoLockSeconds : defaults.autoLockSeconds,
      biometricUnlock: typeof source.biometricUnlock === "boolean" ? source.biometricUnlock : defaults.biometricUnlock,
      hideBalances: typeof source.hideBalances === "boolean" ? source.hideBalances : defaults.hideBalances,
      privateNotifications: typeof source.privateNotifications === "boolean" ? source.privateNotifications : defaults.privateNotifications,
      screenshotProtection: typeof source.screenshotProtection === "boolean" ? source.screenshotProtection : defaults.screenshotProtection,
    };
  } catch {
    return defaults;
  }
}

export function SecurityPreferencesProvider({ children }: { children: ReactNode }) {
  const [preferences, setPreferences] = useState(defaults);
  const [secureStorageAvailable, setSecureStorageAvailable] = useState<boolean | null>(null);
  const [preferencesReady, setPreferencesReady] = useState(false);
  const [preferencesError, setPreferencesError] = useState("");
  const [temporarilyRevealed, setTemporarilyRevealed] = useState(false);
  const [screenshotNotice, setScreenshotNotice] = useState(false);
  const [screenshotProtectionActive, setScreenshotProtectionActive] = useState(false);

  useEffect(() => {
    let active = true;
    async function load() {
      if (Platform.OS === "web") {
        if (active) {
          setSecureStorageAvailable(false);
          setPreferencesReady(true);
        }
        return;
      }
      try {
        const available = await SecureStore.isAvailableAsync();
        if (!active) return;
        setSecureStorageAvailable(available);
        if (available) {
          setPreferences(parsePreferences(await SecureStore.getItemAsync(STORAGE_KEY, STORAGE_OPTIONS)));
        } else {
          setPreferencesError("OS-backed secure storage is unavailable on this device.");
        }
      } catch {
        if (active) {
          setSecureStorageAvailable(false);
          setPreferencesError("Security preferences could not be loaded from secure device storage.");
        }
      } finally {
        if (active) setPreferencesReady(true);
      }
    }
    void load();
    return () => {
      active = false;
    };
  }, []);

  useEffect(() => {
    if (Platform.OS === "web" || !preferencesReady) return;
    let active = true;
    let screenshotSubscription: { remove: () => void } | null = null;
    async function configureCaptureProtection() {
      try {
        if (preferences.screenshotProtection) {
          if (Platform.OS === "android") {
            await ScreenCapture.preventScreenCaptureAsync("exaearn-security-center");
            if (active) setScreenshotProtectionActive(true);
          } else if (Platform.OS === "ios") {
            await ScreenCapture.enableAppSwitcherProtectionAsync(0.9);
            screenshotSubscription = ScreenCapture.addScreenshotListener(() => {
              if (active) {
                setScreenshotNotice(true);
                setTemporarilyRevealed(false);
              }
            });
            if (active) setScreenshotProtectionActive(true);
          }
        } else if (Platform.OS === "ios") {
          await ScreenCapture.disableAppSwitcherProtectionAsync();
          if (active) setScreenshotProtectionActive(false);
        } else if (Platform.OS === "android") {
          setScreenshotProtectionActive(false);
        }
      } catch {
        if (active) {
          setScreenshotProtectionActive(false);
          setPreferencesError("This device could not apply the requested screen-capture privacy protection.");
        }
      }
    }
    void configureCaptureProtection();
    return () => {
      active = false;
      setScreenshotProtectionActive(false);
      screenshotSubscription?.remove();
      if (Platform.OS === "android" && preferences.screenshotProtection) {
        void ScreenCapture.allowScreenCaptureAsync("exaearn-security-center").catch(() => {
          if (active) setPreferencesError("Screen-capture protection could not be released cleanly.");
        });
      } else if (Platform.OS === "ios" && preferences.screenshotProtection) {
        void ScreenCapture.disableAppSwitcherProtectionAsync().catch(() => {
          if (active) setPreferencesError("App-switcher privacy masking could not be disabled cleanly.");
        });
      }
    };
  }, [preferences.screenshotProtection, preferencesReady]);

  useEffect(() => {
    if (!preferences.privateNotifications || Platform.OS === "web") return;
    const subscription = AppState.addEventListener("change", (state) => {
      if (state !== "active") setTemporarilyRevealed(false);
    });
    return () => subscription.remove();
  }, [preferences.privateNotifications]);

  const updatePreference = useCallback(async <K extends keyof SecurityPreferences>(
    key: K,
    value: SecurityPreferences[K],
  ) => {
    const next = { ...preferences, [key]: value };
    if (Platform.OS !== "web") {
      if (!secureStorageAvailable) throw new Error("Secure device storage is unavailable; this preference was not saved.");
      await SecureStore.setItemAsync(STORAGE_KEY, JSON.stringify(next), STORAGE_OPTIONS);
    }
    setPreferences(next);
    setPreferencesError("");
    if (key === "hideBalances" && value === false) setTemporarilyRevealed(false);
  }, [preferences, secureStorageAvailable]);

  const toggleSensitiveReveal = useCallback(() => {
    setTemporarilyRevealed((value) => !value);
  }, []);

  const dismissScreenshotNotice = useCallback(() => setScreenshotNotice(false), []);
  const isSensitiveHidden = (preferences.hideBalances && !temporarilyRevealed) || screenshotNotice;
  const value = useMemo(
    () => ({
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
    }),
    [
      dismissScreenshotNotice,
      isSensitiveHidden,
      preferences,
      preferencesError,
      preferencesReady,
      screenshotNotice,
      screenshotProtectionActive,
      secureStorageAvailable,
      toggleSensitiveReveal,
      updatePreference,
    ],
  );
  return <SecurityPreferencesContext.Provider value={value}>{children}</SecurityPreferencesContext.Provider>;
}

export function useSecurityPreferences() {
  const context = useContext(SecurityPreferencesContext);
  if (!context) throw new Error("useSecurityPreferences must be used within SecurityPreferencesProvider");
  return context;
}
