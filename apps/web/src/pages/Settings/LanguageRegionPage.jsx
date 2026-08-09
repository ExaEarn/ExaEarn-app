import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, Check, Globe2, Languages, Search } from "lucide-react";
import { useAuth } from "../../context/AuthContext";

const languages = [
  "English (Default)",
  "French",
  "Spanish",
  "Arabic",
  "Chinese",
  "Hausa",
  "Yoruba",
  "Igbo",
];

const regions = [
  { name: "Nigeria", flag: "NG", currency: "Naira (NGN)", format: "DD/MM/YYYY - 24h" },
  { name: "USA", flag: "US", currency: "US Dollar (USD)", format: "MM/DD/YYYY - 12h" },
  { name: "UK", flag: "GB", currency: "Pound Sterling (GBP)", format: "DD/MM/YYYY - 24h" },
  { name: "Canada", flag: "CA", currency: "Canadian Dollar (CAD)", format: "YYYY-MM-DD - 12h" },
  { name: "Ghana", flag: "GH", currency: "Cedi (GHS)", format: "DD/MM/YYYY - 24h" },
];

const storageKey = "exaearn-language-region-settings";

function LanguageRegionPage({ onBack }) {
  const { request, user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [selectedLanguage, setSelectedLanguage] = useState("English (Default)");
  const [selectedRegion, setSelectedRegion] = useState("Nigeria");
  const [savedSettings, setSavedSettings] = useState({
    language: "English (Default)",
    region: "Nigeria",
  });
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState("");

  useEffect(() => {
    let mounted = true;

    async function loadSettings() {
      try {
        let parsed = null;

        if (user) {
          try {
            const payload = await request("/api/preferences/language-region", { method: "GET" });
            parsed = payload.data;
          } catch {
            parsed = null;
          }
        }

        if (!parsed) {
          const raw = localStorage.getItem(storageKey);
          parsed = raw ? JSON.parse(raw) : null;
        }

        if (!mounted) return;

        const nextSettings = {
          language: parsed?.language || "English (Default)",
          region: parsed?.region || "Nigeria",
        };

        setSelectedLanguage(nextSettings.language);
        setSelectedRegion(nextSettings.region);
        setSavedSettings(nextSettings);
      } catch (error) {
        console.error("Unable to load language settings", error);
      } finally {
        if (mounted) setLoading(false);
      }
    }

    loadSettings();

    return () => {
      mounted = false;
    };
  }, [request, user]);

  const hasChanges = selectedLanguage !== savedSettings.language || selectedRegion !== savedSettings.region;

  const filteredLanguages = useMemo(() => {
    const key = search.trim().toLowerCase();
    if (!key) return languages;
    return languages.filter((item) => item.toLowerCase().includes(key));
  }, [search]);

  const selectedRegionMeta = regions.find((item) => item.name === selectedRegion) || regions[0];

  const saveChanges = async () => {
    if (!hasChanges || saving) return;
    setSaving(true);
    try {
      const payload = { language: selectedLanguage, region: selectedRegion };

      if (user) {
        await request("/api/preferences/language-region", {
          method: "PATCH",
          body: JSON.stringify(payload),
        });
      }

      localStorage.setItem(storageKey, JSON.stringify(payload));
      setSavedSettings(payload);
      setToast("Language & region updated successfully.");
      setTimeout(() => setToast(""), 2200);
    } catch (error) {
      setToast("Unable to save settings.");
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
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div>
              <h1 className="text-lg font-semibold text-[var(--exa-text-primary)] sm:text-xl">Language & Region</h1>
              <p className="text-xs text-[var(--exa-text-secondary)] sm:text-sm">Customize your app experience</p>
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
              <div className="mb-3 flex items-center gap-2">
                <Languages className="h-4 w-4 text-[var(--exa-gold-light)]" />
                <h2 className="text-base font-semibold text-[var(--exa-text-primary)]">Language Selection</h2>
              </div>

              <label className="mb-3 block">
                <div className="flex items-center gap-2 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2.5">
                  <Search className="h-4 w-4 text-[var(--exa-gold-light)]" />
                  <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search language..."
                    className="w-full bg-transparent text-sm text-white placeholder:text-[var(--exa-text-muted)] outline-none"
                  />
                </div>
              </label>

              <div className="max-h-56 space-y-2 overflow-y-auto pr-1">
                {filteredLanguages.map((language) => (
                  <button
                    key={language}
                    type="button"
                    onClick={() => setSelectedLanguage(language)}
                    className={`flex w-full items-center justify-between rounded-xl border px-3 py-2.5 text-left transition ${
                      selectedLanguage === language
                        ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] text-[var(--exa-gold-light)]"
                        : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] text-[var(--exa-text-secondary)] hover:border-[var(--exa-border-active)]"
                    }`}
                  >
                    <span className="text-sm">{language}</span>
                    {selectedLanguage === language ? <Check className="h-4 w-4" /> : null}
                  </button>
                ))}
              </div>
            </article>

            <article className="mt-4 rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
              <div className="mb-3 flex items-center gap-2">
                <Globe2 className="h-4 w-4 text-[var(--exa-gold-light)]" />
                <h2 className="text-base font-semibold text-[var(--exa-text-primary)]">Region Selection</h2>
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
                      <p className="text-sm text-[var(--exa-text-primary)]">{region.name} {flagEmoji(region.flag)}</p>
                      <p className="text-xs text-[var(--exa-text-muted)]">{region.currency}</p>
                    </div>
                    {selectedRegion === region.name ? <Check className="h-4 w-4 text-[var(--exa-gold-light)]" /> : null}
                  </button>
                ))}
              </div>

              <div className="mt-3 rounded-xl border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] p-3">
                <p className="text-xs text-[var(--exa-text-muted)]">Region-specific info</p>
                <p className="mt-1 text-sm text-[var(--exa-text-secondary)]">Default Currency: {selectedRegionMeta.currency}</p>
                <p className="text-sm text-[var(--exa-text-secondary)]">Local Format: {selectedRegionMeta.format}</p>
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
            {saving ? "Saving..." : "Save Changes"}
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
      <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
        <div className="mb-3 h-5 w-40 animate-pulse rounded bg-gradient-to-r from-[var(--exa-gold-surface)] to-transparent" />
        <div className="space-y-2">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-11 animate-pulse rounded-xl bg-gradient-to-r from-[#1C263A] via-[#243146] to-[#1C263A]" />
          ))}
        </div>
      </article>
      <article className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
        <div className="mb-3 h-5 w-36 animate-pulse rounded bg-gradient-to-r from-[var(--exa-gold-surface)] to-transparent" />
        <div className="space-y-2">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-12 animate-pulse rounded-xl bg-gradient-to-r from-[#1C263A] via-[#243146] to-[#1C263A]" />
          ))}
        </div>
      </article>
    </div>
  );
}

function flagEmoji(code) {
  return code
    .toUpperCase()
    .split("")
    .map((char) => String.fromCodePoint(127397 + char.charCodeAt()))
    .join("");
}

export default LanguageRegionPage;

