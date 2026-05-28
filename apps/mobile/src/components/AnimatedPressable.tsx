import * as Haptics from "expo-haptics";
import type { ReactNode } from "react";
import { Pressable, type PressableProps } from "react-native";
import Animated, {
  interpolate,
  useAnimatedStyle,
  useSharedValue,
  withSpring,
} from "react-native-reanimated";

type AnimatedPressableProps = PressableProps & {
  children: ReactNode;
  haptic?: boolean;
  scaleTo?: number;
};

const AnimatedPressableView = Animated.createAnimatedComponent(Pressable);

export function AnimatedPressable({
  children,
  haptic = true,
  scaleTo = 0.97,
  onPressIn,
  onPressOut,
  onPress,
  disabled,
  ...props
}: AnimatedPressableProps) {
  const pressed = useSharedValue(0);

  const animatedStyle = useAnimatedStyle(() => ({
    transform: [{ scale: interpolate(pressed.value, [0, 1], [1, scaleTo]) }],
    opacity: disabled ? 0.62 : interpolate(pressed.value, [0, 1], [1, 0.9]),
  }));

  return (
    <AnimatedPressableView
      accessibilityRole="button"
      disabled={disabled}
      onPress={onPress}
      onPressIn={(event) => {
        pressed.value = withSpring(1, { damping: 18, stiffness: 260 });
        if (haptic && !disabled) {
          void Haptics.selectionAsync();
        }
        onPressIn?.(event);
      }}
      onPressOut={(event) => {
        pressed.value = withSpring(0, { damping: 18, stiffness: 260 });
        onPressOut?.(event);
      }}
      style={animatedStyle}
      {...props}
    >
      {children}
    </AnimatedPressableView>
  );
}
