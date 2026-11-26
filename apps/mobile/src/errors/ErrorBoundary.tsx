import { Component, type ErrorInfo, type ReactNode } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { colors, fonts } from "../theme/colors";

type Props = { children: ReactNode };
type State = { failed: boolean; diagnosticId: string; recoveryAttempts: number };

export class ErrorBoundary extends Component<Props, State> {
  state: State = { failed: false, diagnosticId: "", recoveryAttempts: 0 };

  static getDerivedStateFromError(): Partial<State> {
    return { failed: true, diagnosticId: `mobile-${Date.now().toString(36)}` };
  }

  componentDidCatch(_error: Error, _info: ErrorInfo) {
    // Do not log raw exception messages or component props: they may contain user data.
    this.setState((state) => ({ recoveryAttempts: state.recoveryAttempts + 1 }));
  }

  render() {
    if (!this.state.failed) return this.props.children;
    return (
      <View accessibilityRole="alert" style={[styles.container, { backgroundColor: colors.cosmic950 }]}>
        <View style={[styles.icon, { backgroundColor: `${colors.statusError}24`, borderColor: `${colors.statusError}66` }]}><Text style={[styles.iconText, { color: colors.statusError }]}>!</Text></View>
        <Text accessibilityRole="header" style={styles.title}>Something went wrong</Text>
        <Text style={styles.message}>This screen could not be displayed. If you were completing a payment, check its status before trying again.</Text>
        <Text style={styles.reference}>Reference: {this.state.diagnosticId}</Text>
        {this.state.recoveryAttempts < 2 ? (
          <Pressable accessibilityRole="button" onPress={() => this.setState({ failed: false, diagnosticId: "" })} style={styles.button}><Text style={styles.buttonText}>Try again</Text></Pressable>
        ) : <Text style={styles.reference}>Please close and reopen the app or contact support.</Text>}
      </View>
    );
  }
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: "center", justifyContent: "center", padding: 28 },
  icon: { width: 54, height: 54, borderRadius: 27, borderWidth: 1, alignItems: "center", justifyContent: "center" },
  iconText: { fontFamily: fonts.display, fontSize: 24 },
  title: { color: "#fff", fontFamily: fonts.semibold, fontSize: 20, marginTop: 18 },
  message: { color: "#b9b7c1", fontFamily: fonts.body, fontSize: 14, lineHeight: 21, textAlign: "center", marginTop: 8 },
  reference: { color: "#888591", fontFamily: fonts.body, fontSize: 12, marginTop: 12 },
  button: { minHeight: 46, minWidth: 150, borderRadius: 14, backgroundColor: "#ef5a67", alignItems: "center", justifyContent: "center", marginTop: 22, paddingHorizontal: 18 },
  buttonText: { color: "#171116", fontFamily: fonts.semibold, fontSize: 14 },
});
