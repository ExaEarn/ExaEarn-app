import React, { useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { Check, ChevronDown, Search, X } from "lucide-react";
import { formatLanguageLabel, popularLanguages, searchLanguages, supportedLanguages } from "@exaearn/config";
import { useLanguage } from "../../context/LanguageContext.jsx";
import "./LanguageSwitcher.css";

const languageCountries = {
  en: ["US", "United States"], fr: ["FR", "France"], es: ["ES", "Spain"], pt: ["BR", "Brazil"], de: ["DE", "Germany"], it: ["IT", "Italy"], nl: ["NL", "Netherlands"], pl: ["PL", "Poland"], ru: ["RU", "Russia"], uk: ["UA", "Ukraine"], tr: ["TR", "Turkiye"], ar: ["SA", "Saudi Arabia"], hi: ["IN", "India"], ur: ["PK", "Pakistan"], bn: ["BD", "Bangladesh"], id: ["ID", "Indonesia"], ms: ["MY", "Malaysia"], vi: ["VN", "Vietnam"], th: ["TH", "Thailand"], "zh-CN": ["CN", "China"], "zh-TW": ["TW", "Taiwan"], ja: ["JP", "Japan"], ko: ["KR", "South Korea"], el: ["GR", "Greece"], sv: ["SE", "Sweden"], no: ["NO", "Norway"], da: ["DK", "Denmark"], fi: ["FI", "Finland"], ro: ["RO", "Romania"], cs: ["CZ", "Czechia"], hu: ["HU", "Hungary"], bg: ["BG", "Bulgaria"], he: ["IL", "Israel"], fa: ["IR", "Iran"],
};

function languageCountry(language) {
  return languageCountries[language.code] || [language.code.slice(0, 2).toUpperCase(), "International"];
}

function LanguageRow({ language, selected, onSelect }) {
  const [flag, country] = languageCountry(language);
  return (
    <button
      type="button"
      className={`language-row ${selected ? "is-selected" : ""}`}
      onClick={() => onSelect(language.code)}
      role="option"
      aria-selected={selected}
    >
      <span className="language-flag" aria-hidden="true">{flag}</span>
      <span className="language-row-copy">
        <strong>{language.englishName}</strong>
        <small>{language.nativeName} - {country}</small>
      </span>
      <span className="language-row-meta">
        {language.direction.toUpperCase()}
        {selected ? <Check size={15} aria-hidden="true" /> : null}
      </span>
    </button>
  );
}

function getPopoverPosition(trigger, align) {
  if (!trigger || typeof window === "undefined") return { top: 72, left: 16 };
  const rect = trigger.getBoundingClientRect();
  const width = Math.min(380, Math.max(300, window.innerWidth - 24));
  const top = Math.min(rect.bottom + 10, window.innerHeight - 40);
  const preferredLeft = align === "left" ? rect.left : rect.right - width;
  const left = Math.min(Math.max(12, preferredLeft), Math.max(12, window.innerWidth - width - 12));
  return { top, left, width };
}

function LanguageSwitcher({ compact = false, align = "right" }) {
  const { language, languageCode, recentLanguages, setLanguage, syncState, t } = useLanguage();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [position, setPosition] = useState({ top: 72, left: 16, width: 360 });
  const triggerRef = useRef(null);
  const inputRef = useRef(null);

  const recent = useMemo(
    () => recentLanguages
      .map((code) => supportedLanguages.find((item) => item.code === code))
      .filter(Boolean),
    [recentLanguages],
  );
  const results = useMemo(() => searchLanguages(query), [query]);
  const popular = useMemo(() => popularLanguages(), []);
  const hasQuery = query.trim().length > 0;
  const [activeFlag] = languageCountry(language);

  const updatePosition = () => setPosition(getPopoverPosition(triggerRef.current, align));

  useLayoutEffect(() => {
    if (!open) return undefined;
    updatePosition();
    const handleUpdate = () => updatePosition();
    window.addEventListener("resize", handleUpdate);
    window.addEventListener("scroll", handleUpdate, true);
    return () => {
      window.removeEventListener("resize", handleUpdate);
      window.removeEventListener("scroll", handleUpdate, true);
    };
  }, [align, open]);

  useEffect(() => {
    if (!open) return undefined;
    const handleKey = (event) => {
      if (event.key === "Escape") setOpen(false);
    };
    window.addEventListener("keydown", handleKey);
    const focusTimer = window.setTimeout(() => inputRef.current?.focus(), 50);
    return () => {
      window.removeEventListener("keydown", handleKey);
      window.clearTimeout(focusTimer);
    };
  }, [open]);

  const selectLanguage = async (code) => {
    await setLanguage(code);
    setOpen(false);
    setQuery("");
  };

  const popover = open ? (
    <>
      <button type="button" className="language-backdrop" onClick={() => setOpen(false)} aria-label={t("language.close")} tabIndex={-1} />
      <div className="language-popover" role="dialog" aria-modal="true" aria-label={t("language.title")} style={{ "--language-popover-top": `${position.top}px`, "--language-popover-left": `${position.left}px`, "--language-popover-width": `${position.width}px` }}>
        <div className="language-sheet-handle" aria-hidden="true" />
        <div className="language-popover-head">
          <div>
            <strong>{t("language.title")}</strong>
            <span>{formatLanguageLabel(language)}</span>
          </div>
          <button type="button" onClick={() => setOpen(false)} aria-label={t("language.close")}>
            <X size={16} aria-hidden="true" />
          </button>
        </div>

        <label className="language-search">
          <Search size={15} aria-hidden="true" />
          <input ref={inputRef} value={query} onChange={(event) => setQuery(event.target.value)} placeholder={t("language.search")} inputMode="search" />
        </label>

        <div className="language-list" role="listbox" aria-label={t("language.all")}>
          {!hasQuery && recent.length ? (
            <section>
              <p>{t("language.recent")}</p>
              {recent.map((item) => <LanguageRow key={item.code} language={item} selected={item.code === languageCode} onSelect={selectLanguage} />)}
            </section>
          ) : null}

          {!hasQuery ? (
            <section>
              <p>{t("language.popular")}</p>
              {popular.map((item) => <LanguageRow key={item.code} language={item} selected={item.code === languageCode} onSelect={selectLanguage} />)}
            </section>
          ) : null}

          <section>
            <p>{hasQuery ? t("common.searchResults") : t("language.all")}</p>
            {results.map((item) => <LanguageRow key={item.code} language={item} selected={item.code === languageCode} onSelect={selectLanguage} />)}
            {!results.length ? <div className="language-empty">{t("language.noLanguage")}</div> : null}
          </section>
        </div>

        <div className="language-note">
          {syncState === "syncing" ? t("language.syncSaving") : syncState === "error" ? t("language.syncError") : t("language.fallback")}
        </div>
      </div>
    </>
  ) : null;

  return (
    <div className={`language-switcher ${compact ? "is-compact" : ""} align-${align}`}>
      <button
        ref={triggerRef}
        type="button"
        className="language-trigger"
        onClick={() => setOpen((value) => !value)}
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-label={`${t("language.current")}: ${formatLanguageLabel(language)}`}
      >
        <span className="language-trigger-flag" aria-hidden="true">{activeFlag}</span>
        <span className="language-trigger-copy"><b>{language.code.split("-")[0].toUpperCase()}</b><small>{language.englishName}</small></span>
        <ChevronDown size={14} aria-hidden="true" />
      </button>

      {typeof document !== "undefined" && popover ? createPortal(popover, document.body) : popover}
    </div>
  );
}

export default LanguageSwitcher;
