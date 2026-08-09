import "./global.css";

import { Sora_600SemiBold } from "@expo-google-fonts/sora";
import {
  SpaceGrotesk_400Regular,
  SpaceGrotesk_500Medium,
  SpaceGrotesk_600SemiBold,
} from "@expo-google-fonts/space-grotesk";
import { Ionicons } from "@expo/vector-icons";
import { formatLanguageLabel, searchLanguages } from "@exaearn/config";
import { useFonts } from "expo-font";
import { LinearGradient } from "expo-linear-gradient";
import { StatusBar } from "expo-status-bar";
import { useMemo, useState } from "react";
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { GestureHandlerRootView } from "react-native-gesture-handler";
import { SafeAreaProvider } from "react-native-safe-area-context";

import { AuthProvider, useAuth } from "./src/context/AuthContext";
import { LanguageProvider, useLanguage } from "./src/context/LanguageContext";
import DashboardScreen from "./src/screens/DashboardScreen";
import GiftcardScreen from "./src/screens/GiftcardScreen";
import MarketScreen from "./src/screens/MarketScreen";
import StakingScreen from "./src/screens/StakingScreen";
import TradeScreen from "./src/screens/TradeScreen";
import { colors, fonts } from "./src/theme/colors";

type RouteName = "dashboard" | "staking" | "giftcard" | "market" | "trade";

export default function App() {
  const [fontsReady] = useFonts({
    Sora_600SemiBold,
    SpaceGrotesk_400Regular,
    SpaceGrotesk_500Medium,
    SpaceGrotesk_600SemiBold,
  });

  return (
    <GestureHandlerRootView style={styles.fill}>
      <SafeAreaProvider>
        <LanguageProvider>
          <AuthProvider>
            <StatusBar style="light" />
            <RootShell fontsReady={fontsReady} />
          </AuthProvider>
        </LanguageProvider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}

function RootShell({ fontsReady }: { fontsReady: boolean }) {
  const { user } = useAuth();
  const [route, setRoute] = useState<RouteName>("dashboard");

  if (!user) {
    return <AuthScreen fontsReady={fontsReady} />;
  }

  if (route === "staking") {
    return <StakingScreen fontsReady={fontsReady} onBack={() => setRoute("dashboard")} />;
  }

  if (route === "giftcard") {
    return <GiftcardScreen fontsReady={fontsReady} onBack={() => setRoute("dashboard")} />;
  }

  if (route === "market") {
    return <MarketScreen fontsReady={fontsReady} onBack={() => setRoute("dashboard")} onOpenTrade={() => setRoute("trade")} />;
  }

  if (route === "trade") {
    return <TradeScreen fontsReady={fontsReady} onBack={() => setRoute("dashboard")} />;
  }

  return (
    <DashboardScreen
      fontsReady={fontsReady}
      onOpenGiftcard={() => setRoute("giftcard")}
      onOpenMarket={() => setRoute("market")}
      onOpenStaking={() => setRoute("staking")}
      onOpenTrade={() => setRoute("trade")}
    />
  );
}

function AuthScreen({ fontsReady }: { fontsReady: boolean }) {
  const {
    login,
    register,
    checkAccountAvailability,
    authError,
    authLoading,
    apiBaseUrl,
    startGoogleLogin,
    isGoogleAuthLoading,
    googleAuthError,
    isGoogleConfigured,
  } = useAuth();
  const [mode, setMode] = useState<"login" | "register">("login");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [referralCode, setReferralCode] = useState("");
  const [passwordVisible, setPasswordVisible] = useState(false);
  const [confirmVisible, setConfirmVisible] = useState(false);
  const [remember, setRemember] = useState(false);
  const [localMessage, setLocalMessage] = useState("");
  const { language, languageCode, setLanguageCode } = useLanguage();
  const [languagePickerOpen, setLanguagePickerOpen] = useState(false);
  const [languageSearch, setLanguageSearch] = useState("");
  const languageResults = useMemo(() => searchLanguages(languageSearch), [languageSearch]);

  const actionTitle = mode === "login" ? "Welcome to ExaEarn" : "Create your ExaEarn account";
  const helperCopy =
    mode === "login"
      ? "Secure access to your Web3 finance ecosystem."
      : "Create your account and continue into the ExaEarn mobile experience with the same backend and wallet flow.";

  const canSubmit = useMemo(() => {
    if (!email.trim() || !password.trim()) return false;
    if (mode === "register") {
      return Boolean(name.trim() && passwordConfirmation.trim() && password === passwordConfirmation);
    }
    return true;
  }, [email, mode, name, password, passwordConfirmation]);

  const handleSubmit = async () => {
    setLocalMessage("");

    if (mode === "login") {
      const result = await login({ email, password });
      if (!result.success) {
        setLocalMessage(result.message || "We could not sign you in right now.");
      }
      return;
    }

    const availability = await checkAccountAvailability({
      name,
      email,
      password,
      passwordConfirmation,
      referralCode,
    });

    if (!availability.success) {
      setLocalMessage(availability.message || "We could not validate those account details.");
      return;
    }

    const result = await register({
      name,
      email,
      password,
      passwordConfirmation,
      referralCode,
    });

    if (!result.success) {
      setLocalMessage(result.message || "We could not create your account right now.");
    }
  };

  return (
    <LinearGradient colors={["#000000", "#140a24", "#220c3d"]} style={styles.fill}>
      <KeyboardAvoidingView
        style={styles.fill}
        behavior={Platform.OS === "ios" ? "padding" : undefined}
      >
        <ScrollView
          contentContainerStyle={styles.authContent}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.authShell}>
            <View style={styles.authBrandStack}>
              <View style={styles.brandMark}>
                <Ionicons name="shield-checkmark-outline" size={26} color={colors.auric300} />
              </View>
              <Text style={styles.authTitle}>{actionTitle}</Text>
              <Text style={styles.authText}>{helperCopy}</Text>
            </View>

            <View style={styles.languagePickerWrap}>
              <Pressable style={styles.languagePickerButton} onPress={() => setLanguagePickerOpen((open) => !open)} accessibilityRole="button">
                <Ionicons name="language-outline" size={16} color={colors.auric300} />
                <Text style={styles.languagePickerButtonText}>{formatLanguageLabel(language)}</Text>
                <Ionicons name={languagePickerOpen ? "chevron-up-outline" : "chevron-down-outline"} size={16} color="rgba(245,240,255,0.72)" />
              </Pressable>
              {languagePickerOpen ? (
                <View style={styles.languagePickerPanel}>
                  <View style={styles.languageSearchRow}>
                    <Ionicons name="search-outline" size={15} color={colors.auric300} />
                    <TextInput
                      value={languageSearch}
                      onChangeText={setLanguageSearch}
                      placeholder="Search language..."
                      placeholderTextColor="rgba(245,240,255,0.42)"
                      style={styles.languageSearchInput}
                    />
                  </View>
                  <ScrollView style={styles.languageResultList} nestedScrollEnabled keyboardShouldPersistTaps="handled">
                    {languageResults.map((item) => (
                      <Pressable
                        key={item.code}
                        style={[styles.languageResultItem, item.code === languageCode && styles.languageResultItemActive]}
                        onPress={() => {
                          setLanguageCode(item.code);
                          setLanguagePickerOpen(false);
                          setLanguageSearch("");
                        }}
                      >
                        <View style={styles.languageResultCopy}>
                          <Text style={styles.languageResultTitle}>{item.englishName}</Text>
                          <Text style={styles.languageResultMeta}>{item.nativeName} - {item.locale} - {item.direction.toUpperCase()}</Text>
                        </View>
                        {item.code === languageCode ? <Ionicons name="checkmark-circle" size={17} color={colors.auric300} /> : null}
                      </Pressable>
                    ))}
                  </ScrollView>
                  <Text style={styles.languageFallbackText}>English is used wherever a translation is not ready yet.</Text>
                </View>
              ) : null}
            </View>

            {mode === "register" ? (
              <Pressable
                onPress={() => {
                  setMode("login");
                  setLocalMessage("");
                }}
                style={styles.inlineBackAction}
              >
                <Ionicons name="chevron-back-outline" size={16} color={colors.auric300} />
                <Text style={styles.inlineBackText}>Back to login</Text>
              </Pressable>
            ) : null}

            <View style={styles.formStack}>
              {mode === "register" ? (
                <AuthInput
                  label="Full name"
                  placeholder="Your full name"
                  value={name}
                  onChangeText={setName}
                  autoCapitalize="words"
                />
              ) : null}

              <AuthInput
                label="Email"
                placeholder="you@exaearn.io"
                value={email}
                onChangeText={setEmail}
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
              />

              <AuthInput
                label="Password"
                placeholder="........"
                value={password}
                onChangeText={setPassword}
                autoCapitalize="none"
                autoCorrect={false}
                secureTextEntry={!passwordVisible}
                trailingIcon={passwordVisible ? "eye-off-outline" : "eye-outline"}
                onTrailingPress={() => setPasswordVisible((value) => !value)}
              />

              {mode === "register" ? (
                <>
                  <AuthInput
                    label="Confirm password"
                    placeholder="........"
                    value={passwordConfirmation}
                    onChangeText={setPasswordConfirmation}
                    autoCapitalize="none"
                    autoCorrect={false}
                    secureTextEntry={!confirmVisible}
                    trailingIcon={confirmVisible ? "eye-off-outline" : "eye-outline"}
                    onTrailingPress={() => setConfirmVisible((value) => !value)}
                  />

                  <AuthInput
                    label="Referral code"
                    placeholder="Optional"
                    value={referralCode}
                    onChangeText={setReferralCode}
                    autoCapitalize="characters"
                    autoCorrect={false}
                  />
                </>
              ) : null}
            </View>

            {mode === "login" ? (
              <View style={styles.authOptionsRow}>
                <Pressable
                  onPress={() => setRemember((value) => !value)}
                  style={styles.rememberToggle}
                >
                  <View style={[styles.checkbox, remember ? styles.checkboxActive : null]}>
                    {remember ? <Ionicons name="checkmark" size={12} color="#ffffff" /> : null}
                  </View>
                  <Text style={styles.rememberText}>Remember me</Text>
                </Pressable>
                <Pressable
                  onPress={() => setLocalMessage("Password recovery will be connected here using the same ExaEarn account flow.")}
                >
                  <Text style={styles.linkText}>Forgot password?</Text>
                </Pressable>
              </View>
            ) : null}

            <Pressable
              onPress={handleSubmit}
              disabled={!canSubmit || authLoading}
              style={[
                styles.primaryButton,
                (!canSubmit || authLoading) ? styles.primaryButtonDisabled : null,
              ]}
            >
              {authLoading ? (
                <View style={styles.buttonSpinnerRow}>
                  <ActivityIndicator color="#ffffff" size="small" />
                  <Text style={styles.primaryButtonText}>{mode === "login" ? "Logging in..." : "Creating account..."}</Text>
                </View>
              ) : (
                <Text style={styles.primaryButtonText}>{mode === "login" ? "Login" : "Create an ExaEarn Account"}</Text>
              )}
            </Pressable>

            {mode === "register" && password !== passwordConfirmation ? (
              <Text style={styles.errorText}>Password confirmation does not match.</Text>
            ) : null}
            {authError || localMessage ? (
              <Text style={styles.errorText}>{localMessage || authError}</Text>
            ) : null}

            {mode === "login" ? (
              <>
                <View style={styles.dividerRow}>
                  <View style={styles.dividerLine} />
                  <Text style={styles.dividerText}>OR</Text>
                  <View style={styles.dividerLine} />
                </View>

                <Pressable
                  onPress={startGoogleLogin}
                  disabled={isGoogleAuthLoading}
                  style={[styles.secondaryButton, isGoogleAuthLoading ? styles.secondaryButtonDisabled : null]}
                >
                  {isGoogleAuthLoading ? (
                    <View style={styles.buttonSpinnerRow}>
                      <ActivityIndicator color={colors.violetText} size="small" />
                      <Text style={styles.secondaryButtonText}>Connecting Google...</Text>
                    </View>
                  ) : (
                    <Text style={styles.secondaryButtonText}>Continue with Google</Text>
                  )}
                </Pressable>

                {googleAuthError ? <Text style={styles.errorText}>{googleAuthError}</Text> : null}
                {!isGoogleConfigured ? (
                  <Text style={styles.metaFoot}>Google sign-in requires `VITE_GOOGLE_CLIENT_ID` in `.env`.</Text>
                ) : null}

                <Pressable
                  onPress={() => {
                    setMode("register");
                    setLocalMessage("");
                  }}
                  style={styles.outlineButton}
                >
                  <Text style={styles.outlineButtonText}>Create an ExaEarn Account</Text>
                </Pressable>

                <Pressable onPress={() => setLocalMessage("Need help? Reach support from the shared ExaEarn help flow.")}>
                  <Text style={styles.helpLinkText}>Need help?</Text>
                </Pressable>
              </>
            ) : null}

            <View style={styles.metaPanel}>
              <View style={styles.metaRow}>
                <Ionicons name="server-outline" size={15} color={colors.auric300} />
                <Text style={styles.metaText}>API: {apiBaseUrl}</Text>
              </View>
              {!fontsReady ? <Text style={styles.metaFoot}>Loading brand fonts...</Text> : null}
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </LinearGradient>
  );
}

function AuthInput({
  label,
  trailingIcon,
  onTrailingPress,
  ...props
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  autoCapitalize?: "none" | "sentences" | "words" | "characters";
  autoCorrect?: boolean;
  keyboardType?: "default" | "email-address";
  secureTextEntry?: boolean;
  trailingIcon?: keyof typeof Ionicons.glyphMap;
  onTrailingPress?: () => void;
}) {
  return (
    <View style={styles.fieldGroup}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <View style={styles.fieldWrap}>
        <TextInput
          placeholderTextColor="rgba(245,240,255,0.4)"
          style={styles.fieldInput}
          {...props}
        />
        {trailingIcon ? (
          <Pressable onPress={onTrailingPress} style={styles.trailingAction}>
            <Ionicons name={trailingIcon} size={18} color="rgba(245,240,255,0.68)" />
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: {
    flex: 1,
  },
  authContent: {
    flexGrow: 1,
    justifyContent: "center",
    paddingHorizontal: 16,
    paddingVertical: 28,
  },
  authShell: {
    width: "100%",
    maxWidth: 420,
    alignSelf: "center",
    borderRadius: 28,
    borderWidth: 1,
    borderColor: "rgba(196,181,253,0.2)",
    backgroundColor: "rgba(15,10,29,0.72)",
    paddingHorizontal: 22,
    paddingVertical: 24,
    shadowColor: "#000000",
    shadowOffset: { width: 0, height: 22 },
    shadowOpacity: 0.34,
    shadowRadius: 40,
    elevation: 18,
  },
  authBrandStack: {
    alignItems: "center",
  },
  brandMark: {
    width: 56,
    height: 56,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "rgba(249,226,173,0.6)",
    backgroundColor: "rgba(15,10,29,0.72)",
    alignItems: "center",
    justifyContent: "center",
  },
  authTitle: {
    marginTop: 16,
    color: colors.violetText,
    fontFamily: fonts.display,
    fontSize: 30,
    lineHeight: 36,
    textAlign: "center",
  },
  authText: {
    marginTop: 8,
    color: "rgba(245,240,255,0.7)",
    fontFamily: fonts.body,
    fontSize: 13,
    lineHeight: 20,
    textAlign: "center",
  },
  inlineBackAction: {
    marginTop: 18,
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    alignSelf: "flex-start",
  },
  inlineBackText: {
    color: colors.auric300,
    fontFamily: fonts.medium,
    fontSize: 13,
  },
  formStack: {
    gap: 14,
    marginTop: 22,
  },
  fieldGroup: {
    gap: 8,
  },
  fieldLabel: {
    color: "rgba(249,226,173,0.7)",
    fontFamily: fonts.semibold,
    fontSize: 11,
    textTransform: "uppercase",
    letterSpacing: 1.8,
  },
  fieldWrap: {
    minHeight: 52,
    flexDirection: "row",
    alignItems: "center",
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "rgba(196,181,253,0.25)",
    backgroundColor: "rgba(15,10,29,0.7)",
    paddingLeft: 16,
    paddingRight: 10,
  },
  fieldInput: {
    flex: 1,
    color: colors.violetText,
    fontFamily: fonts.body,
    fontSize: 14,
    paddingVertical: 14,
  },
  trailingAction: {
    padding: 6,
  },
  authOptionsRow: {
    marginTop: 16,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
  },
  rememberToggle: {
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
  },
  checkbox: {
    width: 18,
    height: 18,
    borderRadius: 5,
    borderWidth: 1,
    borderColor: "rgba(196,181,253,0.45)",
    backgroundColor: "rgba(15,10,29,0.7)",
    alignItems: "center",
    justifyContent: "center",
  },
  checkboxActive: {
    borderColor: "rgba(249,226,173,0.72)",
    backgroundColor: "rgba(168,85,247,0.95)",
  },
  rememberText: {
    color: "rgba(245,240,255,0.72)",
    fontFamily: fonts.body,
    fontSize: 12,
  },
  linkText: {
    color: colors.auric300,
    fontFamily: fonts.medium,
    fontSize: 12,
    textDecorationLine: "underline",
  },
  primaryButton: {
    marginTop: 18,
    minHeight: 52,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "rgba(249,226,173,0.72)",
    backgroundColor: "#8b5cf6",
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 18,
  },
  primaryButtonDisabled: {
    opacity: 0.7,
  },
  primaryButtonText: {
    color: "#ffffff",
    fontFamily: fonts.semibold,
    fontSize: 14,
  },
  buttonSpinnerRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  dividerRow: {
    marginVertical: 20,
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
  },
  dividerLine: {
    flex: 1,
    height: 1,
    backgroundColor: "rgba(196,181,253,0.2)",
  },
  dividerText: {
    color: "rgba(245,240,255,0.5)",
    fontFamily: fonts.medium,
    fontSize: 11,
  },
  secondaryButton: {
    minHeight: 52,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "rgba(196,181,253,0.3)",
    backgroundColor: "rgba(15,10,29,0.7)",
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 18,
  },
  secondaryButtonDisabled: {
    opacity: 0.76,
  },
  secondaryButtonText: {
    color: "rgba(245,240,255,0.82)",
    fontFamily: fonts.semibold,
    fontSize: 14,
  },
  outlineButton: {
    marginTop: 12,
    minHeight: 52,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "rgba(249,226,173,0.6)",
    backgroundColor: "rgba(15,10,29,0.72)",
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 18,
  },
  outlineButtonText: {
    color: colors.auric300,
    fontFamily: fonts.semibold,
    fontSize: 14,
  },
  helpLinkText: {
    marginTop: 14,
    textAlign: "center",
    color: colors.auric300,
    fontFamily: fonts.medium,
    fontSize: 12,
    textDecorationLine: "underline",
  },
  metaPanel: {
    marginTop: 18,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "rgba(148,163,184,0.12)",
    backgroundColor: "rgba(255,255,255,0.04)",
    padding: 12,
    gap: 10,
  },
  metaRow: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 8,
  },
  metaText: {
    flex: 1,
    color: "rgba(245,240,255,0.7)",
    fontFamily: fonts.body,
    fontSize: 11,
    lineHeight: 16,
  },
  metaFoot: {
    color: "rgba(245,240,255,0.48)",
    fontFamily: fonts.body,
    fontSize: 11,
  },
  languagePickerWrap: {
    marginTop: 16,
  },
  languagePickerButton: {
    minHeight: 46,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 10,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "rgba(249,226,173,0.28)",
    backgroundColor: "rgba(15,10,29,0.68)",
    paddingHorizontal: 14,
  },
  languagePickerButtonText: {
    flex: 1,
    color: "rgba(245,240,255,0.86)",
    fontFamily: fonts.semibold,
    fontSize: 12,
  },
  languagePickerPanel: {
    marginTop: 10,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "rgba(249,226,173,0.22)",
    backgroundColor: "rgba(8,6,18,0.96)",
    padding: 10,
  },
  languageSearchRow: {
    minHeight: 42,
    flexDirection: "row",
    alignItems: "center",
    gap: 8,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "rgba(196,181,253,0.2)",
    backgroundColor: "rgba(255,255,255,0.055)",
    paddingHorizontal: 10,
  },
  languageSearchInput: {
    flex: 1,
    color: colors.violetText,
    fontFamily: fonts.body,
    fontSize: 12,
    paddingVertical: 9,
  },
  languageResultList: {
    maxHeight: 220,
    marginTop: 8,
  },
  languageResultItem: {
    minHeight: 48,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 10,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "transparent",
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  languageResultItemActive: {
    borderColor: "rgba(249,226,173,0.36)",
    backgroundColor: "rgba(249,226,173,0.1)",
  },
  languageResultCopy: {
    flex: 1,
  },
  languageResultTitle: {
    color: colors.violetText,
    fontFamily: fonts.semibold,
    fontSize: 12,
  },
  languageResultMeta: {
    marginTop: 2,
    color: "rgba(245,240,255,0.52)",
    fontFamily: fonts.body,
    fontSize: 10,
  },
  languageFallbackText: {
    marginTop: 8,
    color: "rgba(245,240,255,0.5)",
    fontFamily: fonts.body,
    fontSize: 10,
    lineHeight: 15,
  },  errorText: {
    marginTop: 12,
    color: colors.danger,
    fontFamily: fonts.semibold,
    fontSize: 11,
    lineHeight: 16,
  },
});






