import { LinearGradient } from "expo-linear-gradient";
import { StyleSheet } from "react-native";
import Animated, {
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withSequence,
  withTiming,
} from "react-native-reanimated";
import { useEffect } from "react";

type CosmicOrbProps = {
  size: number;
  top?: number;
  bottom?: number;
  left?: number;
  right?: number;
  delay?: number;
  colors: [string, string, string];
};

export function CosmicOrb({ size, top, bottom, left, right, delay = 0, colors }: CosmicOrbProps) {
  const drift = useSharedValue(0);

  useEffect(() => {
    drift.value = withRepeat(
      withSequence(
        withTiming(1, { duration: 4200 + delay }),
        withTiming(0, { duration: 4200 + delay }),
      ),
      -1,
      true,
    );
  }, [delay, drift]);

  const style = useAnimatedStyle(() => ({
    transform: [
      { translateY: drift.value * 18 },
      { translateX: drift.value * -10 },
      { scale: 0.96 + drift.value * 0.08 },
    ],
  }));

  return (
    <Animated.View
      pointerEvents="none"
      style={[
        styles.orb,
        { width: size, height: size, borderRadius: size / 2, top, bottom, left, right },
        style,
      ]}
    >
      <LinearGradient colors={colors} style={StyleSheet.absoluteFillObject} />
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  orb: {
    position: "absolute",
    opacity: 0.42,
    overflow: "hidden",
  },
});
