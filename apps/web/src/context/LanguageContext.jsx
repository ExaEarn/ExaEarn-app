import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from "react";
import {
  DEFAULT_LANGUAGE_CODE,
  LANGUAGE_STORAGE_KEY,
  LEGACY_LANGUAGE_REGION_STORAGE_KEY,
  formatLanguageLabel,
  getLanguageByCode,
  getLanguageDirection,
  normalizeLanguageCode,
  resolvePreferredLanguage,
} from "@exaearn/config";
import { useAuth } from "./AuthContext.jsx";
import { getTranslationValue } from "../i18n/resources.js";

const LanguageContext = createContext(null);
const RECENT_LANGUAGES_KEY = "exaearn.language.recent";


function readStorage(key, fallback = null) {
  try {
    return localStorage.getItem(key) ?? fallback;
  } catch {
    return fallback;
  }
}

function writeStorage(key, value) {
  try {
    localStorage.setItem(key, value);
  } catch {
    // Ignore storage failures in private browsing or locked-down browsers.
  }
}

function readLegacyLanguage() {
  try {
    const raw = localStorage.getItem(LEGACY_LANGUAGE_REGION_STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    return parsed?.language || null;
  } catch {
    return null;
  }
}

function readRecentLanguages() {
  try {
    const parsed = JSON.parse(localStorage.getItem(RECENT_LANGUAGES_KEY) || "[]");
    return Array.isArray(parsed) ? parsed.map(normalizeLanguageCode).filter(Boolean).slice(0, 5) : [];
  } catch {
    return [];
  }
}

function addRecentLanguage(code) {
  const normalized = normalizeLanguageCode(code);
  const next = [normalized, ...readRecentLanguages().filter((item) => item !== normalized)].slice(0, 5);
  writeStorage(RECENT_LANGUAGES_KEY, JSON.stringify(next));
  return next;
}

function resolveUserLanguage(user) {
  const preferences = user?.preferences || {};
  return (
    user?.preferred_language ||
    preferences?.preferred_language ||
    preferences?.language_code ||
    preferences?.language_region?.language_code ||
    preferences?.language_region?.language ||
    null
  );
}

export function LanguageProvider({ children }) {
  const { user, request } = useAuth();
  const [languageCode, setLanguageCodeState] = useState(() => {
    const browserLanguages = typeof navigator !== "undefined" ? Array.from(navigator.languages || [navigator.language]) : [];
    return resolvePreferredLanguage([readStorage(LANGUAGE_STORAGE_KEY), readLegacyLanguage(), ...browserLanguages, DEFAULT_LANGUAGE_CODE]);
  });
  const [recentLanguages, setRecentLanguages] = useState(() => readRecentLanguages());
  const [syncState, setSyncState] = useState("idle");
  const appliedUserLanguageRef = useRef(null);

  const language = useMemo(() => getLanguageByCode(languageCode), [languageCode]);
  const direction = language.direction;

  const userLanguagePreference = useMemo(() => resolveUserLanguage(user), [user]);

  useEffect(() => {
    if (!userLanguagePreference) return;
    const normalized = normalizeLanguageCode(userLanguagePreference);
    if (!normalized || appliedUserLanguageRef.current === normalized) return;
    appliedUserLanguageRef.current = normalized;
    setLanguageCodeState(normalized);
    writeStorage(LANGUAGE_STORAGE_KEY, normalized);
  }, [userLanguagePreference]);

  useEffect(() => {
    if (typeof document === "undefined") return;
    document.documentElement.lang = language.locale;
    document.documentElement.dir = direction;
    document.documentElement.dataset.language = language.code;
  }, [direction, language.code, language.locale]);

  const setLanguage = useCallback(
    async (nextCode, options = {}) => {
      const normalized = normalizeLanguageCode(nextCode);
      const nextLanguage = getLanguageByCode(normalized);
      appliedUserLanguageRef.current = normalized;
      setLanguageCodeState(normalized);
      writeStorage(LANGUAGE_STORAGE_KEY, normalized);
      setRecentLanguages(addRecentLanguage(normalized));

      if (!options.skipServerSync && user && request) {
        setSyncState("syncing");
        try {
          await request("/api/preferences/language-region", {
            method: "PATCH",
            body: JSON.stringify({
              language: formatLanguageLabel(nextLanguage),
              language_code: nextLanguage.code,
              locale: nextLanguage.locale,
              direction: nextLanguage.direction,
              region: "Nigeria",
            }),
            timeoutMs: 8000,
          });
          setSyncState("synced");
          window.setTimeout(() => setSyncState("idle"), 1600);
        } catch (error) {
          console.warn("Language preference sync failed:", error?.message || error);
          setSyncState("error");
          window.setTimeout(() => setSyncState("idle"), 2200);
        }
      }
    },
    [request, user],
  );

  const t = useCallback(
    (key, values = {}) => {
      const phrase = getTranslationValue(languageCode, key);
      return Object.entries(values).reduce((text, [name, value]) => text.replace(new RegExp(`{{${name}}}`, "g"), String(value)), phrase);
    },
    [languageCode],
  );

  const value = useMemo(
    () => ({ languageCode, language, direction, recentLanguages, setLanguage, syncState, t }),
    [direction, language, languageCode, recentLanguages, setLanguage, syncState, t],
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

