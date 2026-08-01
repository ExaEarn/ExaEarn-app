import { Ionicons } from "@expo/vector-icons";
import { useEffect } from "react";
import { StyleSheet, View } from "react-native";
import Animated, {
  interpolate,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withSequence,
  withTiming,
} from "react-native-reanimated";

import { colors } from "../theme/colors";

const nodes = [
  { left: "12%", top: "16%", delay: 0 },
  { left: "74%", top: "13%", delay: 120 },
  { left: "88%", top: "35%", delay: 240 },
  { left: "18%", top: "48%", delay: 360 },
  { left: "68%", top: "61%", delay: 480 },
  { left: "34%", top: "78%", delay: 600 },
] as const;

const links = [
  { left: "17%", top: "20%", width: "58%", rotate: "9deg" },
  { left: "24%", top: "51%", width: "48%", rotate: "-19deg" },
  { left: "37%", top: "76%", width: "33%", rotate: "-31deg" },
  { left: "70%", top: "18%", width: "22%", rotate: "71deg" },
  { left: "15%", top: "49%", width: "28%", rotate: "77deg" },
] as const;

export function Web3NetworkBackdrop() {
  const pulse = useSharedValue(0);
  const scan = useSharedValue(0);

  useEffect(() => {
    pulse.value = withRepeat(
      withSequence(withTiming(1, { duration: 1800 }), withTiming(0, { duration: 1800 })),
      -1,
      true,
    );
    scan.value = withRepeat(withTiming(1, { duration: 5200 }), -1, false);
  }, [pulse, scan]);

  const scanStyle = useAnimatedStyle(() => ({
    transform: [{ translateY: interpolate(scan.value, [0, 1], [-120, 760]) }],
    opacity: interpolate(scan.value, [0, 0.16, 0.84, 1], [0, 0.34, 0.34, 0]),
  }));

  const ringStyle = useAnimatedStyle(() => ({
    transform: [{ rotate: `${interpolate(pulse.value, [0, 1], [-10, 10])}deg` }, { scale: 0.98 + pulse.value * 0.04 }],
  }));

  const nodeStyle = useAnimatedStyle(() => ({
    opacity: 0.62 + pulse.value * 0.32,
    transform: [{ scale: 0.94 + pulse.value * 0.1 }],
  }));

  return (
    <View pointerEvents="none" style={StyleSheet.absoluteFillObject}>
      <View style={styles.matrix} />
      {links.map((link) => (
        <View key={`${link.left}-${link.top}`} style={[styles.link, link]} />
      ))}
      {nodes.map((node) => (
        <Animated.View key={`${node.left}-${node.top}`} style={[styles.node, node, nodeStyle]}>
          <View style={styles.nodeCore} />
        </Animated.View>
      ))}
      <Animated.View style={[styles.scanLine, scanStyle]} />
      <Animated.View style={[styles.tokenRing, ringStyle]}>
        <View style={styles.tokenInner}>
          <Ionicons name="cube-outline" size={30} color={colors.auric300} />
        </View>
      </Animated.View>
    </View>
  );
}

const styles = StyleSheet.create({
  matrix: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "rgba(6, 10, 24, 0.38)",
    borderColor: "rgba(56, 189, 248, 0.14)",
    borderWidth: 1,
  },
  link: {
    position: "absolute",
    height: 1,
    backgroundColor: "rgba(34, 211, 238, 0.24)",
    shadowColor: "#22d3ee",
    shadowOpacity: 0.7,
    shadowRadius: 12,
  },
  node: {
    position: "absolute",
    height: 22,
    width: 22,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 11,
    borderWidth: 1,
    borderColor: "rgba(249, 226, 173, 0.64)",
    backgroundColor: "rgba(15, 23, 42, 0.9)",
  },
  nodeCore: {
    height: 8,
    width: 8,
    borderRadius: 4,
    backgroundColor: "#22d3ee",
  },
  scanLine: {
    position: "absolute",
    left: 0,
    right: 0,
    height: 120,
    backgroundColor: "rgba(34, 211, 238, 0.08)",
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderColor: "rgba(34, 211, 238, 0.2)",
  },
  tokenRing: {
    position: "absolute",
    right: -46,
    top: 84,
    height: 168,
    width: 168,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 84,
    borderWidth: 1,
    borderColor: "rgba(249, 226, 173, 0.32)",
    backgroundColor: "rgba(34, 211, 238, 0.06)",
  },
  tokenInner: {
    height: 82,
    width: 82,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 24,
    borderWidth: 1,
    borderColor: "rgba(34, 211, 238, 0.36)",
    backgroundColor: "rgba(7, 12, 28, 0.88)",
  },
});
