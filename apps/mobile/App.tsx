import "react-native-gesture-handler";
import "./global.css";

import { Sora_600SemiBold, useFonts as useSoraFonts } from "@expo-google-fonts/sora";
import {
  SpaceGrotesk_400Regular,
  SpaceGrotesk_500Medium,
  SpaceGrotesk_600SemiBold,
  useFonts as useSpaceFonts,
} from "@expo-google-fonts/space-grotesk";
import { StatusBar } from "expo-status-bar";
import { GestureHandlerRootView } from "react-native-gesture-handler";
import { SafeAreaProvider } from "react-native-safe-area-context";

import LoginScreen from "./src/screens/LoginScreen";

export default function App() {
  const [spaceLoaded] = useSpaceFonts({
    SpaceGrotesk_400Regular,
    SpaceGrotesk_500Medium,
    SpaceGrotesk_600SemiBold,
  });
  const [soraLoaded] = useSoraFonts({
    Sora_600SemiBold,
  });

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <SafeAreaProvider>
        <StatusBar style="light" translucent />
        <LoginScreen fontsReady={spaceLoaded && soraLoaded} />
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}
