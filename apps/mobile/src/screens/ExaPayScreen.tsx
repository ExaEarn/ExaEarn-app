import { Ionicons } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { Alert, ScrollView, StyleSheet, Text, View, useWindowDimensions } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import { colors, fonts } from "../theme/colors";

type ExaPayScreenProps = {
  fontsReady: boolean;
  onOpenHome: () => void;
  onOpenMarkets: () => void;
  onOpenTransactions: () => void;
  onOpenMore: () => void;
  onOpenTransfer: (mode?: "online" | "qr") => void;
  onOpenOfflinePay: (method?: "bluetooth" | "qr" | "nearby") => void;
  onOpenSyncRedeem: () => void;
};

type ActivityItem = {
  title: string;
  description: string;
  status: string;
  amount: string;
  direction: "debit" | "credit";
  statusTone: "pending" | "offline" | "success";
};

const activities: ActivityItem[] = [
  {
    title: "To: alice@exaearn",
    description: "Today · 10:42 AM",
    status: "Pending Sync",
    amount: "-10.00",
    direction: "debit",
    statusTone: "pending",
  },
  {
    title: "Offline Sent",
    description: "Today · 9:18 AM",
    status: "Offline Committed",
    amount: "-20.00",
    direction: "debit",
    statusTone: "offline",
  },
  {
    title: "Redeemed to Online",
    description: "Yesterday · 4:06 PM",
    status: "Redeemed",
    amount: "+30.00",
    direction: "credit",
    statusTone: "success",
  },
];

const mainActions = [
  { title: "Online Transfer", icon: "arrow-up-outline" as const, tone: "#62c7e8" },
  { title: "Offline Pay", icon: "radio-outline" as const, tone: "#aa91f0" },
  { title: "Load Offline Wallet", icon: "arrow-down-outline" as const, tone: "#aa91f0" },
  { title: "Sync & Redeem", icon: "sync-outline" as const, tone: "#72d3a0" },
];

const bottomItems = [
  { label: "Home", icon: "home-outline" as const },
  { label: "Markets", icon: "bar-chart-outline" as const },
  { label: "ExaPay", icon: "swap-horizontal-outline" as const, active: true },
  { label: "Transactions", icon: "receipt-outline" as const },
  { label: "More", icon: "apps-outline" as const },
];

function openSimulation(action: string) {
  Alert.alert(
    action,
    "This flow is shown in Simulation Mode. Live transfers and wallet operations will be available when ExaPay is connected.",
    [{ text: "OK" }],
  );
}

function SectionHeading({ title, action, onAction }: { title: string; action?: string; onAction?: () => void }) {
  return (
    <View style={styles.sectionHeading}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {action && onAction ? (
        <AnimatedPressable onPress={onAction} accessibilityLabel={`${action} ${title}`}>
          <Text style={styles.sectionAction}>{action}</Text>
        </AnimatedPressable>
      ) : null}
    </View>
  );
}

function StatusPill({ label, tone }: { label: string; tone: "connected" | "device" | "pending" | "offline" | "success" }) {
  const toneStyle = {
    connected: styles.pillBlue,
    device: styles.pillViolet,
    pending: styles.pillAmber,
    offline: styles.pillViolet,
    success: styles.pillGreen,
  }[tone];
  const iconColor = {
    connected: "#62c7e8",
    device: "#aa91f0",
    pending: "#e8b866",
    offline: "#aa91f0",
    success: "#72d3a0",
  }[tone];

  return (
    <View style={[styles.statusPill, toneStyle]} accessible accessibilityLabel={label}>
      <View style={[styles.statusDot, { backgroundColor: iconColor }]} />
      <Text style={styles.statusText}>{label}</Text>
    </View>
  );
}

function BalanceCard({
  title,
  status,
  amount,
  icon,
  tone,
  statusTone,
}: {
  title: string;
  status: string;
  amount: string;
  icon: "cloud-outline" | "phone-portrait-outline";
  tone: "blue" | "violet";
  statusTone: "connected" | "device";
}) {
  return (
    <View style={styles.balanceCard}>
      <View style={styles.balanceCardTop}>
        <View style={[styles.balanceIcon, tone === "blue" ? styles.balanceIconBlue : styles.balanceIconViolet]}>
          <Ionicons name={icon} size={17} color={tone === "blue" ? "#62c7e8" : "#aa91f0"} />
        </View>
      </View>
      <Text style={styles.balanceLabel}>{title}</Text>
      <View style={styles.balanceStatus}>
        <StatusPill label={status} tone={statusTone} />
      </View>
      <View style={styles.amountLine}>
        <Text style={styles.balanceAmount}>{amount}</Text>
      </View>
      <Text style={styles.currencyLabel}>simUSDT</Text>
    </View>
  );
}

function ActivityRow({ item, last }: { item: ActivityItem; last: boolean }) {
  return (
    <View style={[styles.activityRow, !last ? styles.activityDivider : null]}>
      <View style={styles.activityCopy}>
        <Text style={styles.activityTitle} numberOfLines={1}>{item.title}</Text>
        <Text style={styles.activityDescription}>{item.description}</Text>
        <StatusPill label={item.status} tone={item.statusTone} />
      </View>
      <Text style={[styles.activityAmount, item.direction === "credit" ? styles.creditAmount : null]}>
        {item.amount} <Text style={styles.activityCurrency}>simUSDT</Text>
      </Text>
    </View>
  );
}

export default function ExaPayScreen({
  fontsReady,
  onOpenHome,
  onOpenMarkets,
  onOpenTransactions,
  onOpenMore,
  onOpenTransfer,
  onOpenOfflinePay,
  onOpenSyncRedeem,
}: ExaPayScreenProps) {
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const shellWidth = Math.min(width - 32, 480);

  return (
    <View style={styles.fill}>
      <LinearGradient colors={["#090b12", "#0c1018", "#0a0d14"]} style={StyleSheet.absoluteFill} />
      <ScrollView
        contentContainerStyle={[
          styles.scrollContent,
          { paddingTop: insets.top + 10, paddingBottom: insets.bottom + 112 },
        ]}
        showsVerticalScrollIndicator={false}
      >
        <View style={[styles.shell, { width: shellWidth }]}>
          <View style={styles.topBar}>
            <View style={styles.brandLockup}>
              <View style={styles.brandMark}>
                <Text style={styles.brandInitial}>E</Text>
              </View>
              <View>
                <Text style={styles.brandName}>ExaEarn</Text>
                <Text style={styles.brandCaption}>PAYMENTS</Text>
              </View>
            </View>
            <AnimatedPressable
              style={styles.iconButton}
              onPress={() => openSimulation("Notifications")}
              accessibilityLabel="Notifications"
            >
              <Ionicons name="notifications-outline" size={19} color="#dce3ed" />
              <View style={styles.notificationDot} />
            </AnimatedPressable>
          </View>

          <View style={styles.hero}>
            <View style={styles.heroTitleLine}>
              <Text style={[styles.heroTitle, !fontsReady ? styles.systemFont : null]}>ExaPay</Text>
              <View style={styles.simulationChip}>
                <View style={styles.simulationDot} />
                <Text style={styles.simulationText}>Simulation Mode</Text>
              </View>
            </View>
            <Text style={styles.heroSubtitle}>Move value online or offline with confidence.</Text>
          </View>

          <SectionHeading title="Your balances" />
          <View style={styles.balanceGrid}>
            <BalanceCard title="Online Balance" status="Connected" amount="60" icon="cloud-outline" tone="blue" statusTone="connected" />
            <BalanceCard title="Offline Wallet" status="Stored on device" amount="40" icon="phone-portrait-outline" tone="violet" statusTone="device" />
          </View>

          <SectionHeading title="Pay and move" />
          <View style={styles.actionGrid}>
            {mainActions.map((action) => (
              <AnimatedPressable
                key={action.title}
                style={styles.actionCard}
                onPress={() => action.title === "Offline Pay"
                  ? onOpenOfflinePay("bluetooth")
                  : action.title === "Online Transfer"
                    ? onOpenTransfer("online")
                  : action.title === "Sync & Redeem"
                    ? onOpenSyncRedeem()
                    : openSimulation(action.title)}
                accessibilityLabel={`${action.title}, simulated`}
              >
                <View style={[styles.actionIcon, { backgroundColor: `${action.tone}16` }]}>
                  <Ionicons name={action.icon} size={20} color={action.tone} />
                </View>
                <Text style={styles.actionTitle}>{action.title}</Text>
                <Ionicons name="arrow-forward" size={15} color="#7f8998" style={styles.actionArrow} />
              </AnimatedPressable>
            ))}
          </View>

          <View style={styles.toolsRow}>
            <AnimatedPressable style={styles.toolButton} onPress={() => onOpenTransfer("qr")}>
              <Ionicons name="scan-outline" size={18} color="#aeb8c7" />
              <Text style={styles.toolText}>Scan QR</Text>
            </AnimatedPressable>
            <View style={styles.toolDivider} />
            <AnimatedPressable style={styles.toolButton} onPress={() => onOpenOfflinePay("nearby")}>
              <Ionicons name="bluetooth-outline" size={18} color="#aeb8c7" />
              <Text style={styles.toolText}>Nearby Devices</Text>
            </AnimatedPressable>
          </View>

          <AnimatedPressable style={styles.offlineCard} onPress={() => onOpenOfflinePay("bluetooth")} accessibilityLabel="Open Offline Pay">
            <View style={styles.offlineIcon}>
              <Ionicons name="bluetooth-outline" size={19} color="#b5a2ed" />
            </View>
            <View style={styles.offlineCopy}>
              <Text style={styles.offlineTitle}>Offline Pay</Text>
              <Text style={styles.offlineSubtitle}>Make payments nearby via Bluetooth or QR, without internet.</Text>
            </View>
            <Ionicons name="chevron-forward" size={17} color="#8490a0" />
          </AnimatedPressable>

          <View style={styles.activitySection}>
            <SectionHeading title="Recent Activity" action="See All" onAction={onOpenTransactions} />
            <View style={styles.activityCard}>
              {activities.map((item, index) => (
                <ActivityRow key={item.title} item={item} last={index === activities.length - 1} />
              ))}
            </View>
          </View>

          <View style={styles.trustedCard}>
            <View style={styles.trustedIcon}>
              <Ionicons name="shield-checkmark-outline" size={20} color="#72d3a0" />
            </View>
            <View style={styles.trustedCopy}>
              <Text style={styles.trustedTitle}>Trusted Device</Text>
              <Text style={styles.trustedSubtitle}>This device is enrolled and protected.</Text>
            </View>
            <StatusPill label="Enrolled" tone="success" />
          </View>
        </View>
      </ScrollView>

      <View style={[styles.bottomBarWrap, { paddingBottom: Math.max(insets.bottom, 8) }]}>
        <View style={[styles.bottomBar, { width: shellWidth }]}>
          {bottomItems.map((item) => {
            const onPress = item.label === "Home"
              ? onOpenHome
              : item.label === "Markets"
                ? onOpenMarkets
                : item.label === "Transactions"
                  ? onOpenTransactions
                  : item.label === "More"
                    ? onOpenMore
                    : () => undefined;
            return (
              <AnimatedPressable
                key={item.label}
                style={[styles.navItem, item.active ? styles.navItemActive : null]}
                onPress={onPress}
                accessibilityLabel={`${item.label}${item.active ? ", selected" : ""}`}
                accessibilityState={{ selected: Boolean(item.active) }}
              >
                <Ionicons name={item.icon} size={19} color={item.active ? colors.auric300 : "#8490a0"} />
                <Text style={[styles.navLabel, item.active ? styles.navLabelActive : null]}>{item.label}</Text>
              </AnimatedPressable>
            );
          })}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: {
    flex: 1,
    backgroundColor: "#090b12",
  },
  scrollContent: {
    alignItems: "center",
    flexGrow: 1,
  },
  shell: {
    alignSelf: "center",
  },
  topBar: {
    minHeight: 42,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 24,
  },
  brandLockup: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  brandMark: {
    width: 34,
    height: 34,
    borderRadius: 11,
    borderWidth: 1,
    borderColor: "rgba(244,207,126,0.42)",
    backgroundColor: "rgba(244,207,126,0.08)",
    alignItems: "center",
    justifyContent: "center",
  },
  brandInitial: {
    color: colors.auric300,
    fontFamily: fonts.display,
    fontSize: 17,
  },
  brandName: {
    color: "#e8edf4",
    fontFamily: fonts.semibold,
    fontSize: 13,
  },
  brandCaption: {
    marginTop: 1,
    color: "#7d8999",
    fontFamily: fonts.semibold,
    fontSize: 8,
    letterSpacing: 1.4,
  },
  iconButton: {
    width: 42,
    height: 42,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.09)",
    backgroundColor: "#111722",
    alignItems: "center",
    justifyContent: "center",
  },
  notificationDot: {
    position: "absolute",
    top: 10,
    right: 10,
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: colors.auric400,
  },
  hero: {
    marginBottom: 24,
  },
  heroTitleLine: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    flexWrap: "wrap",
  },
  heroTitle: {
    color: "#f4f6fa",
    fontFamily: fonts.display,
    fontSize: 30,
    lineHeight: 38,
    letterSpacing: -0.5,
  },
  systemFont: {
    fontFamily: undefined,
  },
  simulationChip: {
    minHeight: 25,
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    borderWidth: 1,
    borderColor: "rgba(232,184,102,0.22)",
    borderRadius: 99,
    backgroundColor: "rgba(232,184,102,0.07)",
    paddingHorizontal: 9,
  },
  simulationDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: "#e8b866",
  },
  simulationText: {
    color: "#e7c68e",
    fontFamily: fonts.medium,
    fontSize: 10,
  },
  heroSubtitle: {
    marginTop: 5,
    color: "#929dad",
    fontFamily: fonts.body,
    fontSize: 13,
    lineHeight: 19,
  },
  sectionHeading: {
    minHeight: 26,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 10,
  },
  sectionTitle: {
    color: "#e7ebf1",
    fontFamily: fonts.semibold,
    fontSize: 14,
  },
  sectionAction: {
    color: colors.auric300,
    fontFamily: fonts.medium,
    fontSize: 12,
    paddingVertical: 6,
    paddingLeft: 10,
  },
  balanceGrid: {
    flexDirection: "row",
    gap: 10,
    marginBottom: 22,
  },
  balanceCard: {
    flex: 1,
    minHeight: 155,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.085)",
    borderRadius: 16,
    backgroundColor: "#111722",
    padding: 13,
  },
  balanceCardTop: {
    minHeight: 28,
    flexDirection: "row",
    alignItems: "center",
  },
  balanceStatus: {
    alignSelf: "flex-start",
    marginTop: 6,
  },
  balanceIcon: {
    width: 29,
    height: 29,
    borderRadius: 9,
    alignItems: "center",
    justifyContent: "center",
  },
  balanceIconBlue: {
    backgroundColor: "rgba(98,199,232,0.12)",
  },
  balanceIconViolet: {
    backgroundColor: "rgba(170,145,240,0.12)",
  },
  statusPill: {
    minHeight: 21,
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    alignSelf: "flex-start",
    borderRadius: 99,
    paddingHorizontal: 7,
    paddingVertical: 3,
  },
  statusDot: {
    width: 5,
    height: 5,
    borderRadius: 3,
  },
  statusText: {
    color: "#c5ceda",
    fontFamily: fonts.medium,
    fontSize: 9,
  },
  pillBlue: {
    backgroundColor: "rgba(98,199,232,0.10)",
  },
  pillViolet: {
    backgroundColor: "rgba(170,145,240,0.10)",
  },
  pillAmber: {
    backgroundColor: "rgba(232,184,102,0.12)",
  },
  pillGreen: {
    backgroundColor: "rgba(114,211,160,0.11)",
  },
  balanceLabel: {
    color: "#aeb8c7",
    fontFamily: fonts.medium,
    fontSize: 11,
    marginTop: 8,
  },
  amountLine: {
    marginTop: 5,
  },
  balanceAmount: {
    color: "#f3f5f8",
    fontFamily: fonts.display,
    fontSize: 25,
    lineHeight: 30,
    letterSpacing: -0.5,
  },
  currencyLabel: {
    marginTop: 1,
    color: "#8995a5",
    fontFamily: fonts.medium,
    fontSize: 10,
  },
  actionGrid: {
    flexDirection: "row",
    flexWrap: "wrap",
    justifyContent: "space-between",
    rowGap: 10,
    marginBottom: 10,
  },
  actionCard: {
    width: "48.5%",
    minHeight: 94,
    position: "relative",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.085)",
    borderRadius: 15,
    backgroundColor: "#111722",
    justifyContent: "center",
    padding: 12,
    paddingRight: 27,
  },
  actionIcon: {
    width: 32,
    height: 32,
    borderRadius: 10,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 10,
  },
  actionTitle: {
    color: "#e5eaf1",
    fontFamily: fonts.semibold,
    fontSize: 11,
    lineHeight: 15,
  },
  actionArrow: {
    position: "absolute",
    top: 15,
    right: 12,
  },
  toolsRow: {
    minHeight: 51,
    flexDirection: "row",
    alignItems: "center",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.075)",
    borderRadius: 14,
    backgroundColor: "#0f151e",
    marginBottom: 17,
    paddingHorizontal: 4,
  },
  toolButton: {
    minHeight: 48,
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
  },
  toolText: {
    color: "#c2cad5",
    fontFamily: fonts.medium,
    fontSize: 11,
  },
  toolDivider: {
    width: 1,
    height: 23,
    backgroundColor: "rgba(255,255,255,0.09)",
  },
  offlineCard: {
    minHeight: 86,
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    borderWidth: 1,
    borderColor: "rgba(170,145,240,0.18)",
    borderRadius: 16,
    backgroundColor: "rgba(32,27,48,0.58)",
    marginBottom: 21,
    paddingHorizontal: 14,
    paddingVertical: 13,
  },
  offlineIcon: {
    width: 38,
    height: 38,
    borderRadius: 12,
    backgroundColor: "rgba(170,145,240,0.12)",
    alignItems: "center",
    justifyContent: "center",
  },
  offlineCopy: {
    flex: 1,
  },
  offlineTitle: {
    color: "#e8e2f5",
    fontFamily: fonts.semibold,
    fontSize: 12,
  },
  offlineSubtitle: {
    marginTop: 3,
    color: "#a6a2b3",
    fontFamily: fonts.body,
    fontSize: 10,
    lineHeight: 15,
  },
  activitySection: {
    marginBottom: 15,
  },
  activityCard: {
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.085)",
    borderRadius: 16,
    backgroundColor: "#111722",
    paddingHorizontal: 13,
  },
  activityRow: {
    minHeight: 76,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 10,
    paddingVertical: 11,
  },
  activityDivider: {
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "rgba(255,255,255,0.10)",
  },
  activityCopy: {
    flex: 1,
    alignItems: "flex-start",
    gap: 4,
  },
  activityTitle: {
    maxWidth: "100%",
    color: "#e3e8ef",
    fontFamily: fonts.medium,
    fontSize: 11,
  },
  activityDescription: {
    color: "#8490a0",
    fontFamily: fonts.body,
    fontSize: 9,
  },
  activityAmount: {
    color: "#e1e6ed",
    fontFamily: fonts.semibold,
    fontSize: 11,
    textAlign: "right",
  },
  activityCurrency: {
    color: "#929dad",
    fontFamily: fonts.body,
    fontSize: 9,
  },
  creditAmount: {
    color: "#72d3a0",
  },
  trustedCard: {
    minHeight: 72,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.085)",
    borderRadius: 16,
    backgroundColor: "#111722",
    paddingHorizontal: 12,
    paddingVertical: 11,
  },
  trustedIcon: {
    width: 36,
    height: 36,
    borderRadius: 11,
    backgroundColor: "rgba(114,211,160,0.10)",
    alignItems: "center",
    justifyContent: "center",
  },
  trustedCopy: {
    flex: 1,
  },
  trustedTitle: {
    color: "#e5eaf1",
    fontFamily: fonts.semibold,
    fontSize: 11,
  },
  trustedSubtitle: {
    marginTop: 3,
    color: "#929dad",
    fontFamily: fonts.body,
    fontSize: 9,
    lineHeight: 13,
  },
  bottomBarWrap: {
    position: "absolute",
    right: 0,
    bottom: 0,
    left: 0,
    alignItems: "center",
    borderTopWidth: 1,
    borderTopColor: "rgba(255,255,255,0.06)",
    backgroundColor: "rgba(9,11,18,0.98)",
    paddingTop: 8,
  },
  bottomBar: {
    minHeight: 61,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-around",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.08)",
    borderRadius: 16,
    backgroundColor: "#111722",
    paddingHorizontal: 4,
    paddingVertical: 5,
  },
  navItem: {
    minWidth: 53,
    minHeight: 48,
    flex: 1,
    alignItems: "center",
    justifyContent: "center",
    gap: 3,
    borderRadius: 11,
  },
  navItemActive: {
    borderWidth: 1,
    borderColor: "rgba(244,207,126,0.20)",
    backgroundColor: "rgba(244,207,126,0.07)",
  },
  navLabel: {
    color: "#8792a1",
    fontFamily: fonts.medium,
    fontSize: 9,
  },
  navLabelActive: {
    color: colors.auric300,
    fontFamily: fonts.semibold,
  },
});
