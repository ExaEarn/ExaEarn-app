import { useEffect, useMemo, useState } from "react";
import { ArrowLeft, Check, Globe2, Languages, Search } from "lucide-react";

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
  { name: "Nigeria", flag: "NG", currency: "Naira (NGN)", format: "DD/MM/YYYY • 24h" },
  { name: "USA", flag: "US", currency: "US Dollar (USD)", format: "MM/DD/YYYY • 12h" },
  { name: "UK", flag: "GB", currency: "Pound Sterling (GBP)", format: "DD/MM/YYYY • 24h" },
  { name: "Canada", flag: "CA", currency: "Canadian Dollar (CAD)", format: "YYYY-MM-DD • 12h" },
  { name: "Ghana", flag: "GH", currency: "Cedi (GHS)", format: "DD/MM/YYYY • 24h" },
];

const storageKey = "exaearn-language-region-settings";

function LanguageRegionPage({ onBack }) {
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
    const timer = setTimeout(() => {
      try {
        const raw = localStorage.getItem(storageKey);
        if (raw) {
          const parsed = JSON.parse(raw);
          if (parsed.language) setSelectedLanguage(parsed.language);
          if (parsed.region) setSelectedRegion(parsed.region);
          setSavedSettings({
            language: parsed.language || "English (Default)",
            region: parsed.region || "Nigeria",
          });
        }
      } catch (error) {
        console.error("Unable to load language settings", error);
      } finally {
        setLoading(false);
      }
    }, 520);
    return () => clearTimeout(timer);
  }, []);

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
      await new Promise((resolve) => setTimeout(resolve, 700));
      const payload = { language: selectedLanguage, region: selectedRegion };
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
    <main className="relative h-[100dvh] overflow-hidden bg-[#070B14] text-white">
      <header
        className="fixed inset-x-0 top-0 z-40 border-b border-[#D4AF37]/20 bg-gradient-to-r from-[#121A2A]/95 via-[#0E1524]/95 to-[#0A0F1D]/95 backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto w-full max-w-3xl px-4 pb-3 pt-3 sm:px-6">
          <div className="flex items-start gap-3">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-white/15 bg-[#111827] p-2 text-[#E6EAF2] hover:border-[#D4AF37]/60"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div>
              <h1 className="text-lg font-semibold text-[#F8F1DE] sm:text-xl">Language & Region</h1>
              <p className="text-xs text-[#B8C0CF] sm:text-sm">Customize your app experience</p>
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
            <article className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
              <div className="mb-3 flex items-center gap-2">
                <Languages className="h-4 w-4 text-[#D4AF37]" />
                <h2 className="text-base font-semibold text-[#F8F1DE]">Language Selection</h2>
              </div>

              <label className="mb-3 block">
                <div className="flex items-center gap-2 rounded-xl border border-white/15 bg-[#0C1424] px-3 py-2.5">
                  <Search className="h-4 w-4 text-[#D4AF37]" />
                  <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search language..."
                    className="w-full bg-transparent text-sm text-white placeholder:text-[#8F99AB] outline-none"
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
                        ? "border-[#D4AF37]/65 bg-[#D4AF37]/12 text-[#F3D88F]"
                        : "border-white/10 bg-[#0C1424] text-[#D7DDEA] hover:border-[#D4AF37]/35"
                    }`}
                  >
                    <span className="text-sm">{language}</span>
                    {selectedLanguage === language ? <Check className="h-4 w-4" /> : null}
                  </button>
                ))}
              </div>
            </article>

            <article className="mt-4 rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
              <div className="mb-3 flex items-center gap-2">
                <Globe2 className="h-4 w-4 text-[#D4AF37]" />
                <h2 className="text-base font-semibold text-[#F8F1DE]">Region Selection</h2>
              </div>
              <div className="space-y-2">
                {regions.map((region) => (
                  <button
                    key={region.name}
                    type="button"
                    onClick={() => setSelectedRegion(region.name)}
                    className={`flex w-full items-center justify-between rounded-xl border px-3 py-2.5 text-left transition ${
                      selectedRegion === region.name
                        ? "border-[#D4AF37]/70 bg-gradient-to-r from-[#D4AF37]/20 to-[#D4AF37]/5"
                        : "border-white/10 bg-[#0C1424] hover:border-[#D4AF37]/35"
                    }`}
                  >
                    <div>
                      <p className="text-sm text-[#E6EAF2]">{region.name} {flagEmoji(region.flag)}</p>
                      <p className="text-xs text-[#98A1B2]">{region.currency}</p>
                    </div>
                    {selectedRegion === region.name ? <Check className="h-4 w-4 text-[#F3D88F]" /> : null}
                  </button>
                ))}
              </div>

              <div className="mt-3 rounded-xl border border-white/10 bg-[#0C1424] p-3">
                <p className="text-xs text-[#98A1B2]">Region-specific info</p>
                <p className="mt-1 text-sm text-[#D7DDEA]">Default Currency: {selectedRegionMeta.currency}</p>
                <p className="text-sm text-[#D7DDEA]">Local Format: {selectedRegionMeta.format}</p>
              </div>
            </article>
          </>
        )}
      </section>

      <section
        className="fixed inset-x-0 bottom-0 z-40 border-t border-[#D4AF37]/20 bg-[#0A0F1D]/95 p-3 backdrop-blur"
        style={{ paddingBottom: "max(12px, env(safe-area-inset-bottom))" }}
      >
        <div className="mx-auto w-full max-w-3xl">
          <button
            type="button"
            disabled={!hasChanges || saving || loading}
            onClick={saveChanges}
            className="w-full rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] py-3 text-sm font-semibold text-[#111827] shadow-[0_10px_24px_rgba(212,175,55,0.28)] disabled:cursor-not-allowed disabled:opacity-45"
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
      <article className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
        <div className="mb-3 h-5 w-40 animate-pulse rounded bg-gradient-to-r from-[#D4AF37]/25 to-transparent" />
        <div className="space-y-2">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-11 animate-pulse rounded-xl bg-gradient-to-r from-[#1C263A] via-[#243146] to-[#1C263A]" />
          ))}
        </div>
      </article>
      <article className="rounded-2xl border border-white/10 bg-[#101827]/85 p-4">
        <div className="mb-3 h-5 w-36 animate-pulse rounded bg-gradient-to-r from-[#D4AF37]/25 to-transparent" />
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
