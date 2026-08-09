import React, { useMemo, useState } from "react";
import { Check, ChevronDown, Globe2, Search, X } from "lucide-react";
import { formatLanguageLabel, popularLanguages, searchLanguages, supportedLanguages } from "@exaearn/config";
import { useLanguage } from "../../context/LanguageContext.jsx";

function LanguageRow({ language, selected, onSelect }) {
  return (
    <button
      type="button"
      className={`language-row ${selected ? "is-selected" : ""}`}
      onClick={() => onSelect(language.code)}
      role="option"
      aria-selected={selected}
    >
      <span className="language-row-copy">
        <strong>{language.englishName}</strong>
        <small>{language.nativeName} - {language.locale}</small>
      </span>
      <span className="language-row-meta">
        {language.direction.toUpperCase()}
        {selected ? <Check size={15} aria-hidden="true" /> : null}
      </span>
    </button>
  );
}

function LanguageSwitcher({ compact = false, align = "right" }) {
  const { language, languageCode, recentLanguages, setLanguage, syncState, t } = useLanguage();
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");

  const recent = useMemo(
    () => recentLanguages
      .map((code) => supportedLanguages.find((item) => item.code === code))
      .filter(Boolean),
    [recentLanguages],
  );
  const results = useMemo(() => searchLanguages(query), [query]);
  const popular = useMemo(() => popularLanguages(), []);
  const hasQuery = query.trim().length > 0;

  const selectLanguage = async (code) => {
    await setLanguage(code);
    setOpen(false);
    setQuery("");
  };

  return (
    <div className={`language-switcher ${compact ? "is-compact" : ""} align-${align}`}>
      <button
        type="button"
        className="language-trigger"
        onClick={() => setOpen((value) => !value)}
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-label={`${t("language.current")}: ${formatLanguageLabel(language)}`}
      >
        <Globe2 size={17} aria-hidden="true" />
        {compact ? <span>{language.code.toUpperCase()}</span> : <span>{language.englishName}</span>}
        <ChevronDown size={14} aria-hidden="true" />
      </button>

      {open ? (
        <div className="language-popover" role="dialog" aria-label={t("language.title")}>
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
            <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder={t("language.search")} autoFocus />
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
      ) : null}
    </div>
  );
}

export default LanguageSwitcher;


