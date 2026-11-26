export const SECURITY_PREFERENCES_KEY = "exaearn-security-preferences-v1";
export const SECURITY_PREFERENCES_EVENT = "exaearn-security-preferences-change";

export const defaultSecurityPreferences = {
  autoLockSeconds: 0,
  hideBalances: false,
  privateNotifications: true,
};

const validAutoLockSeconds = new Set([0, 30, 60, 300]);

export function readSecurityPreferences() {
  try {
    const raw = window.localStorage.getItem(SECURITY_PREFERENCES_KEY);
    if (!raw) return { preferences: { ...defaultSecurityPreferences }, error: "" };

    const value = JSON.parse(raw);
    if (!value || typeof value !== "object" || Array.isArray(value)) {
      throw new Error("Stored security preferences are invalid.");
    }

    return {
      preferences: {
        autoLockSeconds: validAutoLockSeconds.has(value.autoLockSeconds) ? value.autoLockSeconds : 0,
        hideBalances: typeof value.hideBalances === "boolean" ? value.hideBalances : false,
        privateNotifications: typeof value.privateNotifications === "boolean" ? value.privateNotifications : true,
      },
      error: "",
    };
  } catch {
    return {
      preferences: { ...defaultSecurityPreferences, hideBalances: true },
      error: "Saved security preferences could not be read. Sensitive balances remain hidden until you review them.",
    };
  }
}

export function saveSecurityPreferences(preferences) {
  const normalized = {
    autoLockSeconds: validAutoLockSeconds.has(preferences.autoLockSeconds) ? preferences.autoLockSeconds : 0,
    hideBalances: Boolean(preferences.hideBalances),
    privateNotifications: Boolean(preferences.privateNotifications),
  };

  window.localStorage.setItem(SECURITY_PREFERENCES_KEY, JSON.stringify(normalized));
  window.dispatchEvent(new CustomEvent(SECURITY_PREFERENCES_EVENT, { detail: normalized }));
  return normalized;
}
