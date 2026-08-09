export type LanguageDirection = "ltr" | "rtl";

export type SupportedLanguage = {
  code: string;
  locale: string;
  englishName: string;
  nativeName: string;
  direction: LanguageDirection;
  popular?: boolean;
  aliases?: string[];
};

export const LANGUAGE_STORAGE_KEY = "exaearn.language";
export const LEGACY_LANGUAGE_REGION_STORAGE_KEY = "exaearn-language-region-settings";
export const DEFAULT_LANGUAGE_CODE = "en";

export const supportedLanguages = [
  { code: "en", locale: "en", englishName: "English", nativeName: "English", direction: "ltr", popular: true, aliases: ["us", "uk", "nigeria", "ng"] },
  { code: "fr", locale: "fr", englishName: "French", nativeName: "Français", direction: "ltr", popular: true, aliases: ["france"] },
  { code: "es", locale: "es", englishName: "Spanish", nativeName: "Español", direction: "ltr", popular: true, aliases: ["spain", "latin america"] },
  { code: "pt", locale: "pt", englishName: "Portuguese", nativeName: "Português", direction: "ltr", popular: true, aliases: ["brazil", "portugal"] },
  { code: "de", locale: "de", englishName: "German", nativeName: "Deutsch", direction: "ltr" },
  { code: "it", locale: "it", englishName: "Italian", nativeName: "Italiano", direction: "ltr" },
  { code: "nl", locale: "nl", englishName: "Dutch", nativeName: "Nederlands", direction: "ltr" },
  { code: "pl", locale: "pl", englishName: "Polish", nativeName: "Polski", direction: "ltr" },
  { code: "ru", locale: "ru", englishName: "Russian", nativeName: "Русский", direction: "ltr" },
  { code: "uk", locale: "uk", englishName: "Ukrainian", nativeName: "Українська", direction: "ltr" },
  { code: "tr", locale: "tr", englishName: "Turkish", nativeName: "Türkçe", direction: "ltr" },
  { code: "ar", locale: "ar", englishName: "Arabic", nativeName: "العربية", direction: "rtl", popular: true, aliases: ["uae", "saudi"] },
  { code: "hi", locale: "hi", englishName: "Hindi", nativeName: "हिन्दी", direction: "ltr", popular: true, aliases: ["india"] },
  { code: "ur", locale: "ur", englishName: "Urdu", nativeName: "اردو", direction: "rtl" },
  { code: "bn", locale: "bn", englishName: "Bengali", nativeName: "বাংলা", direction: "ltr" },
  { code: "id", locale: "id", englishName: "Indonesian", nativeName: "Bahasa Indonesia", direction: "ltr", popular: true },
  { code: "ms", locale: "ms", englishName: "Malay", nativeName: "Bahasa Melayu", direction: "ltr" },
  { code: "vi", locale: "vi", englishName: "Vietnamese", nativeName: "Tiếng Việt", direction: "ltr" },
  { code: "th", locale: "th", englishName: "Thai", nativeName: "ไทย", direction: "ltr" },
  { code: "zh-CN", locale: "zh-CN", englishName: "Chinese (Simplified)", nativeName: "简体中文", direction: "ltr", popular: true, aliases: ["mandarin", "china", "simplified chinese"] },
  { code: "zh-TW", locale: "zh-TW", englishName: "Chinese (Traditional)", nativeName: "繁體中文", direction: "ltr", aliases: ["taiwan", "traditional chinese"] },
  { code: "ja", locale: "ja", englishName: "Japanese", nativeName: "日本語", direction: "ltr", popular: true },
  { code: "ko", locale: "ko", englishName: "Korean", nativeName: "한국어", direction: "ltr", popular: true },
  { code: "el", locale: "el", englishName: "Greek", nativeName: "Ελληνικά", direction: "ltr" },
  { code: "sv", locale: "sv", englishName: "Swedish", nativeName: "Svenska", direction: "ltr" },
  { code: "no", locale: "no", englishName: "Norwegian", nativeName: "Norsk", direction: "ltr" },
  { code: "da", locale: "da", englishName: "Danish", nativeName: "Dansk", direction: "ltr" },
  { code: "fi", locale: "fi", englishName: "Finnish", nativeName: "Suomi", direction: "ltr" },
  { code: "ro", locale: "ro", englishName: "Romanian", nativeName: "Română", direction: "ltr" },
  { code: "cs", locale: "cs", englishName: "Czech", nativeName: "Čeština", direction: "ltr" },
  { code: "hu", locale: "hu", englishName: "Hungarian", nativeName: "Magyar", direction: "ltr" },
  { code: "bg", locale: "bg", englishName: "Bulgarian", nativeName: "Български", direction: "ltr" },
  { code: "he", locale: "he", englishName: "Hebrew", nativeName: "עברית", direction: "rtl" },
  { code: "fa", locale: "fa", englishName: "Persian", nativeName: "فارسی", direction: "rtl" },
] satisfies SupportedLanguage[];

export function normalizeLanguageSearch(value: string): string {
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

export function normalizeLanguageCode(value?: string | null): string {
  const candidate = String(value || "").trim();
  if (!candidate) return DEFAULT_LANGUAGE_CODE;

  const exact = supportedLanguages.find(
    (language) => language.code.toLowerCase() === candidate.toLowerCase() || language.locale.toLowerCase() === candidate.toLowerCase(),
  );
  if (exact) return exact.code;

  const shortCode = candidate.split(/[-_]/)[0]?.toLowerCase();
  const shortMatch = supportedLanguages.find((language) => language.code.toLowerCase() === shortCode || language.locale.toLowerCase() === shortCode);
  return shortMatch?.code ?? DEFAULT_LANGUAGE_CODE;
}

export function getLanguageByCode(code?: string | null): SupportedLanguage {
  const normalized = normalizeLanguageCode(code);
  return supportedLanguages.find((language) => language.code === normalized) ?? supportedLanguages[0];
}

export function getLanguageDirection(code?: string | null): LanguageDirection {
  return getLanguageByCode(code).direction;
}

export function resolvePreferredLanguage(candidates: Array<string | null | undefined>): string {
  for (const candidate of candidates) {
    if (!candidate) continue;
    const normalized = normalizeLanguageCode(candidate);
    if (normalized) return normalized;
  }

  return DEFAULT_LANGUAGE_CODE;
}

export function searchLanguages(query: string, languages = supportedLanguages): SupportedLanguage[] {
  const needle = normalizeLanguageSearch(query);
  if (!needle) return languages;

  return languages.filter((language) => {
    const haystack = normalizeLanguageSearch(
      [language.code, language.locale, language.englishName, language.nativeName, ...(language.aliases ?? [])].join(" "),
    );
    return haystack.includes(needle);
  });
}

export function popularLanguages(): SupportedLanguage[] {
  return supportedLanguages.filter((language) => language.popular);
}

export function formatLanguageLabel(language: SupportedLanguage): string {
  return language.englishName === language.nativeName
    ? language.englishName
    : `${language.englishName} - ${language.nativeName}`;
}
