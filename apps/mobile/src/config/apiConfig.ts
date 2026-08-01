export function getApiBaseUrl() {
  // Check for environment variables (Expo sets these as EXPO_PUBLIC_*)
  const env = (
    globalThis as typeof globalThis & {
      process?: {
        env?: Record<string, string | undefined>;
      };
    }
  ).process?.env;
  const baseUrl = env?.EXPO_PUBLIC_API_URL || env?.VITE_API_URL || "http://127.0.0.1:8000";
  
  return String(baseUrl || "").trim().replace(/\/+$/, "");
}

export const apiHelpText =
  "Set EXPO_PUBLIC_API_URL to your Laravel backend URL. Android emulator usually uses http://10.0.2.2:8000.";
