import { Ionicons } from "@expo/vector-icons";
import { BlurView } from "expo-blur";
import { Text, TextInput, View, type TextInputProps } from "react-native";

import { colors, fonts } from "../theme/colors";
import { AnimatedPressable } from "./AnimatedPressable";

type IconName = keyof typeof Ionicons.glyphMap;

type GlassFieldProps = TextInputProps & {
  label: string;
  icon: IconName;
  trailingIcon?: IconName;
  onTrailingPress?: () => void;
};

export function GlassField({
  label,
  icon,
  trailingIcon,
  onTrailingPress,
  style,
  ...props
}: GlassFieldProps) {
  return (
    <View className="gap-2">
      <Text
        className="text-[11px] uppercase tracking-[2.6px] text-auric-300/75"
        style={{ fontFamily: fonts.semibold }}
      >
        {label}
      </Text>
      <BlurView
        intensity={24}
        tint="dark"
        className="overflow-hidden rounded-2xl border border-violet-200/20 bg-cosmic-900/70"
      >
        <View className="min-h-[58px] flex-row items-center px-4">
          <Ionicons name={icon} size={18} color="rgba(249, 226, 173, 0.78)" />
          <TextInput
            className="ml-3 flex-1 text-[15px] text-violet-50"
            cursorColor={colors.auric400}
            placeholderTextColor="rgba(245, 240, 255, 0.36)"
            selectionColor="rgba(244, 207, 126, 0.34)"
            style={[{ fontFamily: fonts.body }, style]}
            {...props}
          />
          {trailingIcon ? (
            <AnimatedPressable
              accessibilityLabel="Toggle secure text"
              className="ml-2 h-10 w-10 items-center justify-center rounded-full"
              onPress={onTrailingPress}
            >
              <Ionicons name={trailingIcon} size={20} color="rgba(245, 240, 255, 0.62)" />
            </AnimatedPressable>
          ) : null}
        </View>
      </BlurView>
    </View>
  );
}
