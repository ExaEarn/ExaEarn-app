import { Ionicons } from "@expo/vector-icons";
import { BlurView } from "expo-blur";
import * as Haptics from "expo-haptics";
import { LinearGradient } from "expo-linear-gradient";
import { useEffect, useState } from "react";
import {
  Image,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from "react-native";
import { Gesture, GestureDetector } from "react-native-gesture-handler";
import Animated, {
  FadeInDown,
  FadeInUp,
  interpolate,
  useAnimatedKeyboard,
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withRepeat,
  withSequence,
  withSpring,
  withTiming,
} from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import { AuthButton } from "../components/AuthButton";
import { CosmicOrb } from "../components/CosmicOrb";
import { GlassField } from "../components/GlassField";
import { colors, fonts } from "../theme/colors";

type LoginScreenProps = {
  fontsReady: boolean;
};

const logo = require("../../assets/earn.jpg");

export default function LoginScreen({ fontsReady }: LoginScreenProps) {
  const insets = useSafeAreaInsets();
  const { height, width } = useWindowDimensions();
  const keyboard = useAnimatedKeyboard();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [remember, setRemember] = useState(true);
  const [loading, setLoading] = useState(false);
  const [googleLoading, setGoogleLoading] = useState(false);
  const [message, setMessage] = useState("");
  const cardTilt = useSharedValue(0);
  const orbit = useSharedValue(0);

  useEffect(() => {
    orbit.value = withDelay(
      450,
      withRepeat(
        withSequence(withTiming(1, { duration: 2600 }), withTiming(0, { duration: 2600 })),
        -1,
        true,
      ),
    );
  }, [orbit]);

  const compact = height < 760;

  const cardGesture = Gesture.Pan()
    .onUpdate((event) => {
      cardTilt.value = Math.max(-1, Math.min(1, event.translationX / 90));
    })
    .onEnd(() => {
      cardTilt.value = withSpring(0, { damping: 16, stiffness: 160 });
    });

  const keyboardStyle = useAnimatedStyle(() => ({
    transform: [{ translateY: Platform.OS === "ios" ? -keyboard.height.value * 0.18 : 0 }],
  }));

  const cardAnimatedStyle = useAnimatedStyle(() => ({
    transform: [
      { perspective: 900 },
      { rotateY: `${cardTilt.value * 2.2}deg` },
      { translateX: cardTilt.value * 4 },
    ],
  }));

  const orbitStyle = useAnimatedStyle(() => ({
    transform: [{ rotate: `${interpolate(orbit.value, [0, 1], [-8, 8])}deg` }],
  }));

  const handleLogin = async () => {
    setMessage("");
    setLoading(true);
    await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);

    setTimeout(() => {
      setLoading(false);
      setMessage(email && password ? "Login flow ready for API integration." : "Enter your email and password to continue.");
      void Haptics.notificationAsync(email && password ? Haptics.NotificationFeedbackType.Success : Haptics.NotificationFeedbackType.Warning);
    }, 850);
  };

  const handleGoogle = () => {
    setGoogleLoading(true);
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setTimeout(() => setGoogleLoading(false), 900);
  };

  const toggleRemember = () => {
    setRemember((value) => !value);
    void Haptics.selectionAsync();
  };

  if (!fontsReady) {
    return (
      <LinearGradient colors={[colors.cosmic950, "#140a24", "#220c3d"]} style={styles.fill}>
        <View className="flex-1 items-center justify-center">
          <Animated.View entering={FadeInUp.duration(500)} className="items-center">
            <View className="h-16 w-16 items-center justify-center overflow-hidden rounded-2xl border border-auric-300/60 bg-cosmic-900/80">
              <Image source={logo} className="h-10 w-10" resizeMode="cover" />
            </View>
            <Text className="mt-4 text-[14px] text-violet-100/70">Preparing secure access...</Text>
          </Animated.View>
        </View>
      </LinearGradient>
    );
  }

  return (
    <LinearGradient colors={[colors.cosmic950, "#140a24", "#220c3d"]} style={styles.fill}>
      <CosmicOrb
        size={width * 0.72}
        top={-height * 0.11}
        right={-width * 0.28}
        colors={["rgba(127,70,212,0.55)", "rgba(234,185,95,0.18)", "transparent"]}
      />
      <CosmicOrb
        size={width * 0.58}
        bottom={height * 0.08}
        left={-width * 0.32}
        delay={600}
        colors={["rgba(234,185,95,0.38)", "rgba(127,70,212,0.18)", "transparent"]}
      />
      <View style={styles.gridOverlay} pointerEvents="none" />

      <KeyboardAvoidingView
        behavior={Platform.OS === "ios" ? "padding" : undefined}
        className="flex-1"
        keyboardVerticalOffset={Platform.OS === "ios" ? 4 : 0}
      >
        <ScrollView
          bounces={false}
          contentContainerStyle={[
            styles.scrollContent,
            {
              minHeight: height,
              paddingTop: insets.top + (compact ? 18 : 30),
              paddingBottom: insets.bottom + 26,
            },
          ]}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <Animated.View style={keyboardStyle} className="w-full">
            <Animated.View entering={FadeInDown.delay(80).duration(640).springify()} className="px-5">
              <View className="mb-5 flex-row items-center justify-between">
                <View className="flex-row items-center">
                  <View className="h-11 w-11 items-center justify-center overflow-hidden rounded-2xl border border-auric-300/50 bg-cosmic-900/70">
                    <Image source={logo} className="h-8 w-8" resizeMode="cover" />
                  </View>
                  <View className="ml-3">
                    <Text className="text-[16px] text-violet-50" style={{ fontFamily: fonts.display }}>
                      ExaEarn
                    </Text>
                    <Text className="text-[11px] uppercase tracking-[2.2px] text-violet-100/45" style={{ fontFamily: fonts.medium }}>
                      Web3 finance
                    </Text>
                  </View>
                </View>
                <View className="flex-row items-center rounded-full border border-emerald-300/20 bg-emerald-400/10 px-3 py-2">
                  <View className="mr-2 h-2 w-2 rounded-full bg-emerald-300" />
                  <Text className="text-[11px] text-emerald-100/80" style={{ fontFamily: fonts.semibold }}>
                    Protected
                  </Text>
                </View>
              </View>

              <Text className="max-w-[320px] text-[34px] leading-[41px] text-violet-50" style={{ fontFamily: fonts.display }}>
                Welcome to ExaEarn
              </Text>
              <Text className="mt-3 max-w-[320px] text-[15px] leading-6 text-violet-100/68" style={{ fontFamily: fonts.body }}>
                Secure access to your Web3 finance ecosystem.
              </Text>
            </Animated.View>

            <GestureDetector gesture={cardGesture}>
              <Animated.View
                entering={FadeInUp.delay(180).duration(760).springify()}
                style={[cardAnimatedStyle, styles.cardShell]}
              >
                <BlurView intensity={34} tint="dark" style={styles.card}>
                  <Animated.View style={[styles.assetHalo, orbitStyle]} pointerEvents="none">
                    <LinearGradient
                      colors={["rgba(244,207,126,0.18)", "rgba(127,70,212,0.12)", "transparent"]}
                      style={StyleSheet.absoluteFillObject}
                    />
                  </Animated.View>

                  <View className="flex-row items-center justify-between">
                    <View>
                      <Text className="text-[13px] uppercase tracking-[2.4px] text-auric-300/70" style={{ fontFamily: fonts.semibold }}>
                        Login
                      </Text>
                      <Text className="mt-1 text-[20px] text-violet-50" style={{ fontFamily: fonts.display }}>
                        Access vault
                      </Text>
                    </View>
                    <View className="h-12 w-12 items-center justify-center rounded-2xl border border-violet-200/20 bg-violet-400/10">
                      <Ionicons name="finger-print" size={26} color={colors.auric300} />
                    </View>
                  </View>

                  <View className="mt-6 gap-4">
                    <GlassField
                      autoCapitalize="none"
                      autoComplete="email"
                      autoCorrect={false}
                      icon="mail-outline"
                      keyboardType="email-address"
                      label="Email"
                      onChangeText={setEmail}
                      placeholder="you@exaearn.io"
                      returnKeyType="next"
                      textContentType="emailAddress"
                      value={email}
                    />
                    <GlassField
                      autoCapitalize="none"
                      icon="lock-closed-outline"
                      label="Password"
                      onChangeText={setPassword}
                      onTrailingPress={() => {
                        setShowPassword((value) => !value);
                        void Haptics.selectionAsync();
                      }}
                      placeholder="********"
                      returnKeyType="done"
                      secureTextEntry={!showPassword}
                      textContentType="password"
                      trailingIcon={showPassword ? "eye-off-outline" : "eye-outline"}
                      value={password}
                    />
                  </View>

                  <View className="mt-4 flex-row items-center justify-between">
                    <AnimatedPressable className="flex-row items-center" onPress={toggleRemember}>
                      <View
                        className={`h-5 w-5 items-center justify-center rounded-md border ${
                          remember ? "border-auric-300 bg-auric-300" : "border-violet-200/35 bg-cosmic-900/60"
                        }`}
                      >
                        {remember ? <Ionicons name="checkmark" size={14} color={colors.cosmic950} /> : null}
                      </View>
                      <Text className="ml-2 text-[12px] text-violet-100/68" style={{ fontFamily: fonts.medium }}>
                        Remember me
                      </Text>
                    </AnimatedPressable>

                    <AnimatedPressable onPress={() => void Haptics.selectionAsync()}>
                      <Text className="text-[12px] text-auric-300" style={{ fontFamily: fonts.semibold }}>
                        Forgot password?
                      </Text>
                    </AnimatedPressable>
                  </View>

                  <View className="mt-6">
                    <AuthButton label="Login" loading={loading} onPress={handleLogin} />
                  </View>

                  {message ? (
                    <Animated.Text
                      entering={FadeInUp.duration(260)}
                      className="mt-3 text-[12px] text-violet-100/62"
                      style={{ fontFamily: fonts.body }}
                    >
                      {message}
                    </Animated.Text>
                  ) : null}

                  <View className="my-5 flex-row items-center">
                    <View className="h-px flex-1 bg-violet-200/15" />
                    <Text className="px-3 text-[11px] text-violet-100/42" style={{ fontFamily: fonts.semibold }}>
                      OR
                    </Text>
                    <View className="h-px flex-1 bg-violet-200/15" />
                  </View>

                  <View className="gap-3">
                    <AuthButton icon="logo-google" label="Continue with Google" loading={googleLoading} onPress={handleGoogle} variant="glass" />
                    <AuthButton icon="sparkles-outline" label="Create an ExaEarn Account" onPress={() => void Haptics.selectionAsync()} variant="outline" />
                    <AnimatedPressable className="items-center py-1" onPress={() => void Haptics.selectionAsync()}>
                      <Text className="text-[12px] text-auric-300/85" style={{ fontFamily: fonts.semibold }}>
                        Need help?
                      </Text>
                    </AnimatedPressable>
                  </View>
                </BlurView>
              </Animated.View>
            </GestureDetector>
          </Animated.View>
        </ScrollView>
      </KeyboardAvoidingView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  fill: {
    flex: 1,
  },
  scrollContent: {
    justifyContent: "center",
  },
  gridOverlay: {
    ...StyleSheet.absoluteFillObject,
    opacity: 0.18,
    backgroundColor: "transparent",
    borderColor: "rgba(249,226,173,0.08)",
    borderWidth: 1,
  },
  cardShell: {
    marginHorizontal: 20,
    marginTop: 28,
    shadowColor: colors.cosmic400,
    shadowOffset: { width: 0, height: 24 },
    shadowOpacity: 0.32,
    shadowRadius: 36,
    elevation: 18,
  },
  card: {
    overflow: "hidden",
    borderRadius: 28,
    borderWidth: 1,
    borderColor: "rgba(221,214,254,0.2)",
    backgroundColor: "rgba(15,10,29,0.74)",
    padding: 22,
  },
  assetHalo: {
    position: "absolute",
    right: -70,
    top: -80,
    height: 190,
    width: 190,
    borderRadius: 95,
  },
});
