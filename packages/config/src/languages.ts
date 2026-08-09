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
  { code: "fr", locale: "fr", englishName: "French", nativeName: "Fran\u00e7ais", direction: "ltr", popular: true, aliases: ["france"] },
  { code: "es", locale: "es", englishName: "Spanish", nativeName: "Espa\u00f1ol", direction: "ltr", popular: true, aliases: ["spain", "latin america"] },
  { code: "pt", locale: "pt", englishName: "Portuguese", nativeName: "Portugu\u00eas", direction: "ltr", popular: true, aliases: ["brazil", "portugal"] },
  { code: "de", locale: "de", englishName: "German", nativeName: "Deutsch", direction: "ltr" },
  { code: "it", locale: "it", englishName: "Italian", nativeName: "Italiano", direction: "ltr" },
  { code: "nl", locale: "nl", englishName: "Dutch", nativeName: "Nederlands", direction: "ltr" },
  { code: "pl", locale: "pl", englishName: "Polish", nativeName: "Polski", direction: "ltr" },
  { code: "ru", locale: "ru", englishName: "Russian", nativeName: "\u0420\u0443\u0441\u0441\u043a\u0438\u0439", direction: "ltr" },
  { code: "uk", locale: "uk", englishName: "Ukrainian", nativeName: "\u0423\u043a\u0440\u0430\u0457\u043d\u0441\u044c\u043a\u0430", direction: "ltr" },
  { code: "tr", locale: "tr", englishName: "Turkish", nativeName: "T\u00fcrk\u00e7e", direction: "ltr" },
  { code: "ar", locale: "ar", englishName: "Arabic", nativeName: "\u0627\u0644\u0639\u0631\u0628\u064a\u0629", direction: "rtl", popular: true, aliases: ["uae", "saudi"] },
  { code: "hi", locale: "hi", englishName: "Hindi", nativeName: "\u0939\u093f\u0928\u094d\u0926\u0940", direction: "ltr", popular: true, aliases: ["india"] },
  { code: "ur", locale: "ur", englishName: "Urdu", nativeName: "\u0627\u0631\u062f\u0648", direction: "rtl" },
  { code: "bn", locale: "bn", englishName: "Bengali", nativeName: "\u09ac\u09be\u0982\u09b2\u09be", direction: "ltr" },
  { code: "id", locale: "id", englishName: "Indonesian", nativeName: "Bahasa Indonesia", direction: "ltr", popular: true },
  { code: "ms", locale: "ms", englishName: "Malay", nativeName: "Bahasa Melayu", direction: "ltr" },
  { code: "vi", locale: "vi", englishName: "Vietnamese", nativeName: "Ti\u1ebfng Vi\u1ec7t", direction: "ltr" },
  { code: "th", locale: "th", englishName: "Thai", nativeName: "\u0e44\u0e17\u0e22", direction: "ltr" },
  { code: "zh-CN", locale: "zh-CN", englishName: "Chinese (Simplified)", nativeName: "\u7b80\u4f53\u4e2d\u6587", direction: "ltr", popular: true, aliases: ["mandarin", "china", "simplified chinese"] },
  { code: "zh-TW", locale: "zh-TW", englishName: "Chinese (Traditional)", nativeName: "\u7e41\u9ad4\u4e2d\u6587", direction: "ltr", aliases: ["taiwan", "traditional chinese"] },
  { code: "ja", locale: "ja", englishName: "Japanese", nativeName: "\u65e5\u672c\u8a9e", direction: "ltr", popular: true },
  { code: "ko", locale: "ko", englishName: "Korean", nativeName: "\ud55c\uad6d\uc5b4", direction: "ltr", popular: true },
  { code: "el", locale: "el", englishName: "Greek", nativeName: "\u0395\u03bb\u03bb\u03b7\u03bd\u03b9\u03ba\u03ac", direction: "ltr" },
  { code: "sv", locale: "sv", englishName: "Swedish", nativeName: "Svenska", direction: "ltr" },
  { code: "no", locale: "no", englishName: "Norwegian", nativeName: "Norsk", direction: "ltr" },
  { code: "da", locale: "da", englishName: "Danish", nativeName: "Dansk", direction: "ltr" },
  { code: "fi", locale: "fi", englishName: "Finnish", nativeName: "Suomi", direction: "ltr" },
  { code: "ro", locale: "ro", englishName: "Romanian", nativeName: "Rom\u00e2n\u0103", direction: "ltr" },
  { code: "cs", locale: "cs", englishName: "Czech", nativeName: "\u010ce\u0161tina", direction: "ltr" },
  { code: "hu", locale: "hu", englishName: "Hungarian", nativeName: "Magyar", direction: "ltr" },
  { code: "bg", locale: "bg", englishName: "Bulgarian", nativeName: "\u0411\u044a\u043b\u0433\u0430\u0440\u0441\u043a\u0438", direction: "ltr" },
  { code: "he", locale: "he", englishName: "Hebrew", nativeName: "\u05e2\u05d1\u05e8\u05d9\u05ea", direction: "rtl" },
  { code: "fa", locale: "fa", englishName: "Persian", nativeName: "\u0641\u0627\u0631\u0633\u06cc", direction: "rtl" },
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
