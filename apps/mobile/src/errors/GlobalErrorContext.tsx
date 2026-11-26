import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { AccessibilityInfo, AppState } from "react-native";
import { ErrorModal } from "./ErrorModal";
import type { AppError, RecoveryAction } from "./errorTypes";

type ErrorContextValue = {
  presentError: (error: AppError, handlers?: Partial<Record<RecoveryAction, () => void | Promise<void>>>) => void;
  dismissError: () => void;
};

const GlobalErrorContext = createContext<ErrorContextValue | null>(null);

export function GlobalErrorProvider({ children }: { children: ReactNode }) {
  const [activeError, setActiveError] = useState<AppError | null>(null);
  const [handlers, setHandlers] = useState<Partial<Record<RecoveryAction, () => void | Promise<void>>>>({});
  const [reduceMotion, setReduceMotion] = useState(false);
  const [appIsActive, setAppIsActive] = useState(AppState.currentState === "active");
  const shown = useRef(new Map<string, number>());

  useEffect(() => {
    let mounted = true;
    AccessibilityInfo.isReduceMotionEnabled().then((value) => { if (mounted) setReduceMotion(value); }).catch(() => undefined);
    const subscription = AccessibilityInfo.addEventListener("reduceMotionChanged", setReduceMotion);
    const appSubscription = AppState.addEventListener("change", (state) => setAppIsActive(state === "active"));
    return () => { mounted = false; subscription.remove(); appSubscription.remove(); };
  }, []);

  const dismissError = useCallback(() => {
    setActiveError(null);
    setHandlers({});
  }, []);

  const presentError = useCallback((error: AppError, nextHandlers: Partial<Record<RecoveryAction, () => void | Promise<void>>> = {}) => {
    const now = Date.now();
    for (const [key, timestamp] of shown.current) if (now - timestamp > 10_000) shown.current.delete(key);
    const key = `${error.code}:${error.reference ?? error.diagnosticId ?? "global"}`;
    if (shown.current.has(key)) return;
    shown.current.set(key, now);
    setHandlers(nextHandlers);
    setActiveError(error);
  }, []);

  const value = useMemo(() => ({ presentError, dismissError }), [presentError, dismissError]);
  return (
    <GlobalErrorContext.Provider value={value}>
      {children}
      <ErrorModal error={activeError} handlers={handlers} onDismiss={dismissError} reduceMotion={reduceMotion} appIsActive={appIsActive} />
    </GlobalErrorContext.Provider>
  );
}

export function useGlobalError() {
  const value = useContext(GlobalErrorContext);
  if (!value) throw new Error("useGlobalError must be used within GlobalErrorProvider");
  return value;
}
