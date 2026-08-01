import { Ionicons } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { ActivityIndicator, Text, View } from "react-native";

import { colors, fonts } from "../theme/colors";
import { AnimatedPressable } from "./AnimatedPressable";

type AuthButtonProps = {
  label: string;
  onPress: () => void;
  variant?: "primary" | "glass" | "outline";
  loading?: boolean;
  icon?: keyof typeof Ionicons.glyphMap;
};

export function AuthButton({ label, onPress, variant = "primary", loading, icon }: AuthButtonProps) {
  if (variant === "primary") {
    return (
      <AnimatedPressable disabled={loading} onPress={onPress} scaleTo={0.985}>
        <LinearGradient
          colors={["rgba(14,165,233,0.96)", "rgba(127,70,212,0.96)", colors.auric500]}
          start={{ x: 0, y: 0.5 }}
          end={{ x: 1, y: 0.5 }}
          className="h-[58px] flex-row items-center justify-center rounded-2xl border border-cyan-200/45"
          style={{
            shadowColor: "#22d3ee",
            shadowOffset: { width: 0, height: 12 },
            shadowOpacity: 0.32,
            shadowRadius: 24,
            elevation: 10,
          }}
        >
          {loading ? <ActivityIndicator color="#fff" /> : null}
          {!loading && icon ? <Ionicons name={icon} size={19} color="#fff" /> : null}
          <Text
            className={loading || icon ? "ml-2 text-[15px] text-white" : "text-[15px] text-white"}
            style={{ fontFamily: fonts.semibold }}
          >
            {loading ? "Authenticating..." : label}
          </Text>
        </LinearGradient>
      </AnimatedPressable>
    );
  }

  const border = variant === "outline" ? "border-auric-300/75" : "border-cyan-200/25";
  const text = variant === "outline" ? "text-auric-300" : "text-violet-50";

  return (
    <AnimatedPressable
      className={`h-[56px] flex-row items-center justify-center rounded-2xl border bg-cosmic-900/90 ${border}`}
      disabled={loading}
      onPress={onPress}
    >
      {loading ? <ActivityIndicator color={colors.auric400} /> : null}
      {!loading && icon ? <Ionicons name={icon} size={19} color={variant === "outline" ? colors.auric300 : colors.muted} /> : null}
      <Text
        className={`${loading || icon ? "ml-2" : ""} text-[14px] ${text}`}
        style={{ fontFamily: fonts.semibold }}
      >
        {loading ? "Connecting Google..." : label}
      </Text>
    </AnimatedPressable>
  );
}
