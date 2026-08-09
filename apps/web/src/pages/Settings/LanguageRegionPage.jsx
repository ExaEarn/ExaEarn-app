import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, Check, Globe2, Languages, Search } from "lucide-react";
import { formatLanguageLabel, getLanguageByCode, searchLanguages, supportedLanguages } from "@exaearn/config";
import { useLanguage } from "../../context/LanguageContext.jsx";

const regions = [
  { name: "Nigeria", code: "NG", currency: "Naira (NGN)", format: "DD/MM/YYYY - 24h", defaultLanguage: "English" },
  { name: "United States", code: "US", currency: "US Dollar (USD)", format: "MM/DD/YYYY - 12h", defaultLanguage: "English" },
  { name: "United Kingdom", code: "GB", currency: "Pound Sterling (GBP)", format: "DD/MM/YYYY - 24h", defaultLanguage: "English" },
  { name: "Canada", code: "CA", currency: "Canadian Dollar (CAD)", format: "YYYY-MM-DD - 12h", defaultLanguage: "English / French" },
  { name: "European Union", code: "EU", currency: "Euro (EUR)", format: "DD/MM/YYYY - 24h", defaultLanguage: "Country default" },
  { name: "Ghana", code: "GH", currency: "Cedi (GHS)", format: "DD/MM/YYYY - 24h", defaultLanguage: "English" },
];

const storageKey = "exaearn-language-region-settings";

function LanguageRegionPage({ onBack }) {
  const { languageCode, setLanguage, syncState, t } = useLanguage();
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [selectedLanguage, setSelectedLanguage] = useState(languageCode);
  const [selectedRegion, setSelectedRegion] = useState("Nigeria");
  const [savedSettings, setSavedSettings] = useState({ language: languageCode, region: "Nigeria" });
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState("");

  useEffect(() => {
    try {
      const raw = localStorage.getItem(storageKey);
      const parsed = raw ? JSON.parse(raw) : null;
      const nextLanguage = parsed?.language_code || parsed?.language || languageCode;
      const nextRegion = parsed?.region || "Nigeria";
      setSelectedLanguage(getLanguageByCode(nextLanguage).code);
      setSelectedRegion(nextRegion);
      setSavedSettings({ language: getLanguageByCode(nextLanguage).code, region: nextRegion });
    } catch (error) {
      console.error("Unable to load language settings", error);
    } finally {
      setLoading(false);
    }
  }, [languageCode]);

  const hasChanges = selectedLanguage !== savedSettings.language || selectedRegion !== savedSettings.region;
  const filteredLanguages = useMemo(() => searchLanguages(search), [search]);
  const selectedLanguageMeta = getLanguageByCode(selectedLanguage);
  const selectedRegionMeta = regions.find((item) => item.name === selectedRegion) || regions[0];

  const saveChanges = async () => {
    if (!hasChanges || saving) return;
    setSaving(true);
    try {
      await setLanguage(selectedLanguage);
      const payload = {
        language: formatLanguageLabel(selectedLanguageMeta),
        language_code: selectedLanguageMeta.code,
        locale: selectedLanguageMeta.locale,
        direction: selectedLanguageMeta.direction,
        region: selectedRegion,
      };
      localStorage.setItem(storageKey, JSON.stringify(payload));
      setSavedSettings({ language: selectedLanguageMeta.code, region: selectedRegion });
      setToast(t("language.updated"));
      setTimeout(() => setToast(""), 2200);
    } catch (error) {
      setToast(t("language.unableToSave"));
      setTimeout(() => setToast(""), 2200);
    } finally {
      setSaving(false);
    }
  };

  return (
    <main className="relative h-[100dvh] overflow-hidden bg-[var(--exa-bg-primary)] text-white">
      <header
        className="fixed inset-x-0 top-0 z-40 border-b border-[var(--exa-border-active)] bg-[var(--exa-surface)] backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-3xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-start gap-3">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-2 text-[var(--exa-text-primary)] hover:border-[var(--exa-border-active)]"
              aria-label={t("common.back")}
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div>
              <h1 className="text-lg font-semibold text-[var(--exa-text-primary)] sm:text-xl">{t("settings.languageRegion")}</h1>
              <p className="text-xs text-[var(--exa-text-secondary)] sm:text-sm">{t("settings.languageRegionDescription")}</p>
            </div>
          </div>
        </div>
      </header>

      <section
        className="mx-auto h-full w-full max-w-3xl overflow-y-auto px-4 pb-28 pt-[90px] sm:px-6"
        style={{ paddingBottom: "calc(96px + env(safe-area-inset-bottom))" }}
      >
        {loading ? (
          <LoadingState />
        ) : (
          <>
            <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
              <div className="mb-3 flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                  <Languages className="h-4 w-4 text-[var(--exa-gold-light)]" />
                  <h2 className="text-base font-semibold text-[var(--exa-text-primary)]">{t("language.title")}</h2>
                </div>
                <span className="rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-[var(--exa-gold-light)]">
                  {selectedLanguageMeta.code}
                </span>
              </div>

              <label className="mb-3 block">
                <span className="sr-only">{t("language.search")}</span>
                <div className="flex items-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2.5">
                  <Search className="h-4 w-4 text-[var(--exa-gold-light)]" />
                  <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={t("language.searchSettings")}
                    className="w-full bg-transparent text-sm text-white placeholder:text-[var(--exa-text-muted)] outline-none"
                  />
                </div>
              </label>

              <div className="max-h-72 space-y-2 overflow-y-auto pr-1" role="listbox" aria-label={t("language.all")}>
                {filteredLanguages.map((language) => (
                  <button
                    key={language.code}
                    type="button"
                    onClick={() => setSelectedLanguage(language.code)}
                    className={`flex w-full items-center justify-between rounded-xl border px-3 py-2.5 text-left transition ${
                      selectedLanguage === language.code
                        ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]"
                        : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)]"
                    }`}
                    role="option"
                    aria-selected={selectedLanguage === language.code}
                  >
                    <span>
                      <span className="block text-sm font-semibold text-[var(--exa-text-primary)]">{language.englishName}</span>
                      <span className="block text-xs text-[var(--exa-text-muted)]">{language.nativeName} - {language.locale} - {language.direction.toUpperCase()}</span>
                    </span>
                    {selectedLanguage === language.code ? <Check className="h-4 w-4" /> : null}
                  </button>
                ))}
              </div>

              <p className="mt-3 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3 text-xs leading-5 text-[var(--exa-text-muted)]">
                {t("language.nigeriaEnglish")}
              </p>
            </article>

            <article className="mt-4 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
              <div className="mb-3 flex items-center gap-2">
                <Globe2 className="h-4 w-4 text-[var(--exa-gold-light)]" />
                <h2 className="text-base font-semibold text-[var(--exa-text-primary)]">{t("language.regionTitle")}</h2>
              </div>
              <div className="space-y-2">
                {regions.map((region) => (
                  <button
                    key={region.name}
                    type="button"
                    onClick={() => setSelectedRegion(region.name)}
                    className={`flex w-full items-center justify-between rounded-xl border px-3 py-2.5 text-left transition ${
                      selectedRegion === region.name
                        ? "border-[var(--exa-border-active)] bg-gradient-to-r from-[var(--exa-gold-surface)] to-transparent"
                        : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] hover:border-[var(--exa-border-active)]"
                    }`}
                  >
                    <div>
                      <p className="text-sm text-[var(--exa-text-primary)]">{region.name}</p>
                      <p className="text-xs text-[var(--exa-text-muted)]">{region.currency} - {region.defaultLanguage}</p>
                    </div>
                    {selectedRegion === region.name ? <Check className="h-4 w-4 text-[var(--exa-gold-light)]" /> : null}
                  </button>
                ))}
              </div>

              <div className="mt-3 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3">
                <p className="text-xs text-[var(--exa-text-muted)]">{t("language.regionInfo")}</p>
                <p className="mt-1 text-sm text-[var(--exa-text-secondary)]">{t("language.defaultCurrency")}: {selectedRegionMeta.currency}</p>
                <p className="text-sm text-[var(--exa-text-secondary)]">{t("language.localFormat")}: {selectedRegionMeta.format}</p>
              </div>
            </article>
          </>
        )}
      </section>

      <section
        className="fixed inset-x-0 bottom-0 z-40 border-t border-[var(--exa-border-active)] bg-[var(--exa-surface)] p-3 backdrop-blur"
        style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}
      >
        <div className="mx-auto w-full max-w-3xl">
          <button
            type="button"
            disabled={!hasChanges || saving || loading}
            onClick={saveChanges}
            className="w-full rounded-xl bg-gradient-to-r from-[var(--exa-gold-dark)] via-[var(--exa-gold)] to-[var(--exa-gold-light)] py-3 text-sm font-semibold text-[var(--exa-gold-contrast)] shadow-[var(--exa-shadow-gold)] disabled:cursor-not-allowed disabled:opacity-45"
          >
            {saving || syncState === "syncing" ? t("common.saving") : t("common.saveChanges")}
          </button>
        </div>
      </section>

      {toast ? (
        <div className="fixed right-4 top-24 z-50 rounded-xl border border-[#22C55E]/35 bg-[#22C55E]/12 px-3 py-2 text-xs text-[#BBF7D0] shadow-lg">
          {toast}
        </div>
      ) : null}
    </main>
  );
}

function LoadingState() {
  return (
    <div className="space-y-4">
      {[1, 2].map((card) => (
        <article key={card} className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
          <div className="mb-3 h-5 w-40 animate-pulse rounded bg-gradient-to-r from-[var(--exa-gold-surface)] to-transparent" />
          <div className="space-y-2">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="h-11 animate-pulse rounded-xl bg-gradient-to-r from-[#1C263A] via-[#243146] to-[#1C263A]" />
            ))}
          </div>
        </article>
      ))}
    </div>
  );
}

export default LanguageRegionPage;


