import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { I18nManager } from "react-native";
import {
  DEFAULT_LANGUAGE_CODE,
  LANGUAGE_STORAGE_KEY,
  getLanguageByCode,
  resolvePreferredLanguage,
  type SupportedLanguage,
} from "@exaearn/config";

type LanguageContextValue = {
  language: SupportedLanguage;
  languageCode: string;
  setLanguageCode: (code: string) => void;
};

const LanguageContext = createContext<LanguageContextValue | null>(null);

function readStoredLanguage(): string | null {
  try {
    const storage = (globalThis as typeof globalThis & { localStorage?: Storage }).localStorage;
    return storage?.getItem(LANGUAGE_STORAGE_KEY) ?? null;
  } catch {
    return null;
  }
}

function writeStoredLanguage(code: string): void {
  try {
    const storage = (globalThis as typeof globalThis & { localStorage?: Storage }).localStorage;
    storage?.setItem(LANGUAGE_STORAGE_KEY, code);
  } catch {
    // Native builds use the in-memory fallback until AsyncStorage is installed.
  }
}

function browserLanguageCandidates(): string[] {
  const navigatorValue = (globalThis as typeof globalThis & { navigator?: { language?: string; languages?: string[] } }).navigator;
  return Array.from(navigatorValue?.languages || [navigatorValue?.language || DEFAULT_LANGUAGE_CODE]);
}

export function LanguageProvider({ children }: { children: ReactNode }) {
  const [languageCode, setLanguageCodeState] = useState(() => resolvePreferredLanguage([readStoredLanguage(), ...browserLanguageCandidates(), DEFAULT_LANGUAGE_CODE]));
  const language = useMemo(() => getLanguageByCode(languageCode), [languageCode]);

  useEffect(() => {
    writeStoredLanguage(language.code);
    I18nManager.allowRTL(language.direction === "rtl");
  }, [language.code, language.direction]);

  const value = useMemo(
    () => ({
      language,
      languageCode,
      setLanguageCode: (code: string) => setLanguageCodeState(getLanguageByCode(code).code),
    }),
    [language, languageCode],
  );

  return <LanguageContext.Provider value={value}>{children}</LanguageContext.Provider>;
}

export function useLanguage() {
  const context = useContext(LanguageContext);
  if (!context) {
    throw new Error("useLanguage must be used within LanguageProvider");
  }
  return context;
}
