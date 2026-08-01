import { Ionicons } from "@expo/vector-icons";
import { LinearGradient } from "expo-linear-gradient";
import { useEffect, useMemo, useState } from "react";
import { Image, Modal, RefreshControl, ScrollView, StyleSheet, Text, View, useWindowDimensions } from "react-native";
import Animated, { FadeInDown, FadeInUp } from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import { apiHelpText } from "../config/apiConfig";
import { useAuth } from "../context/AuthContext";
import { colors, fonts } from "../theme/colors";

type DashboardScreenProps = {
  fontsReady: boolean;
  onOpenGiftcard?: () => void;
  onOpenMarket?: () => void;
  onOpenStaking?: () => void;
  onOpenTrade?: () => void;
};

type Balance = {
  currency?: string;
  asset?: string;
  available?: string | number;
  balance?: string | number;
};

type MarketItem = {
  base: string;
  quote: string;
  price: string;
  change: string;
  positive: boolean;
};

type NotificationItem = {
  id?: string | number;
  title?: string;
  message?: string;
  body?: string;
  type?: string;
  created_at?: string;
};

type CheckInItem = {
  reward_points?: number;
  streak_day?: number;
  checkin_date?: string;
  created_at?: string;
};

const featureAssets = {
  agriculture: require("../../../web/src/assets/images/agriculture.png"),
  crowdfund: require("../../../web/src/assets/images/crowdfund.png"),
  edu: require("../../../web/src/assets/images/edu.png"),
  games: require("../../../web/src/assets/images/games.png"),
  giftcard: require("../../../web/src/assets/images/giftcard.png"),
  more: require("../../../web/src/assets/images/more.jpg"),
  nft: require("../../../web/src/assets/images/nft.png"),
  earn: require("../../assets/earn.jpg"),
};

const features = [
  { label: "Gift Cards", image: featureAssets.giftcard, icon: "gift-outline" as const, tone: "#f9e2ad" },
  { label: "ExaEarn Staking", image: featureAssets.earn, icon: "cash-outline" as const, tone: "#67e8f9" },
  { label: "Games", image: featureAssets.games, icon: "game-controller-outline" as const, tone: "#c4b5fd" },
  { label: "NFT Market", image: featureAssets.nft, icon: "diamond-outline" as const, tone: "#93c5fd" },
  { label: "Crowdfund", image: featureAssets.crowdfund, icon: "people-outline" as const, tone: "#facc15" },
  { label: "Agritech", image: featureAssets.agriculture, icon: "leaf-outline" as const, tone: "#86efac" },
  { label: "EdTech", image: featureAssets.edu, icon: "school-outline" as const, tone: "#5eead4" },
  { label: "More", image: featureAssets.more, icon: "apps-outline" as const, tone: "#ddd6fe" },
];

const fallbackMarkets: MarketItem[] = [
  { base: "BTC", quote: "USDT", price: "102,840.00", change: "+2.84%", positive: true },
  { base: "ETH", quote: "USDT", price: "5,418.20", change: "+1.37%", positive: true },
  { base: "XRP", quote: "USDT", price: "2.92", change: "+4.61%", positive: true },
  { base: "SOL", quote: "USDT", price: "238.70", change: "-0.42%", positive: false },
  { base: "EXA", quote: "USDT", price: "0.84", change: "+8.20%", positive: true },
];

const navItems = [
  { label: "Home", icon: "home-outline" as const, image: featureAssets.earn, active: true },
  { label: "Market", icon: "bar-chart-outline" as const },
  { label: "Trade", icon: "diamond-outline" as const },
  { label: "P2P", icon: "people-outline" as const },
  { label: "Assets", icon: "wallet-outline" as const },
];

const chartBars = [34, 48, 42, 62, 54, 74, 66];

function toNumber(value: unknown) {
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : 0;
}

function formatMoney(value: number) {
  return `$${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function getPointValue(payload: Record<string, unknown> | null) {
  if (!payload) return 0;
  const data = payload.data;
  if (data && typeof data === "object") {
    const source = data as Record<string, unknown>;
    return toNumber(source.available_points ?? source.points ?? source.balance);
  }
  return toNumber(payload.available_points ?? payload.points ?? payload.balance);
}

function getRewardData(payload: Record<string, unknown> | null) {
  const data = payload?.data && typeof payload.data === "object" ? (payload.data as Record<string, unknown>) : payload;
  const target = toNumber(data?.redemption_target_points) || 5000;
  const points = getPointValue(payload);

  return {
    points,
    target,
    streak: toNumber(data?.current_streak),
    percentage: Math.min(100, Math.max(0, toNumber(data?.progress_percentage) || (points / target) * 100)),
    usdt: toNumber(data?.redemption_value_usdt) || 5,
  };
}

function unwrapArray(payload: Record<string, unknown> | null | undefined) {
  const data = payload?.data;
  if (Array.isArray(data)) return data;
  if (data && typeof data === "object") {
    const nested = data as Record<string, unknown>;
    if (Array.isArray(nested.data)) return nested.data;
    if (Array.isArray(nested.checkins)) return nested.checkins;
  }
  return [];
}

function normalizeMarkets(payload: Record<string, unknown> | null | undefined): MarketItem[] {
  return unwrapArray(payload)
    .map((item) => {
      if (!item || typeof item !== "object") return null;
      const source = item as Record<string, unknown>;
      const symbol = String(source.symbol ?? source.pair ?? "").toUpperCase();
      const [symbolBase, symbolQuote] = symbol.includes("/") ? symbol.split("/") : symbol.split("-");
      const base = String(source.base_currency ?? source.base ?? symbolBase ?? "").toUpperCase();
      const quote = String(source.quote_currency ?? source.quote ?? symbolQuote ?? "USDT").toUpperCase();
      const priceValue = toNumber(source.last_price ?? source.price ?? source.close);
      const changeValue = toNumber(source.change24h ?? source.change_24h ?? source.price_change_percent ?? source.change);
      if (!base || !priceValue) return null;

      return {
        base,
        quote,
        price: priceValue.toLocaleString(undefined, { maximumFractionDigits: priceValue > 10 ? 2 : 4 }),
        change: `${changeValue >= 0 ? "+" : ""}${changeValue.toFixed(2)}%`,
        positive: changeValue >= 0,
      };
    })
    .filter(Boolean) as MarketItem[];
}

function DailyRewardCard({
  points,
  streak,
  target,
  percentage,
  onPress,
}: {
  points: number;
  streak: number;
  target: number;
  percentage: number;
  onPress: () => void;
}) {

  return (
    <AnimatedPressable style={styles.rewardCard} onPress={onPress}>
      <View style={styles.rewardTop}>
        <View className="flex-row items-center">
          <Ionicons name="flame" size={15} color="#facc15" />
          <Text className="ml-1 text-[10px] text-amber-100" style={{ fontFamily: fonts.semibold }}>
            {streak} Day Streak
          </Text>
        </View>
        <Text className="text-[10px] text-violet-50" style={{ fontFamily: fonts.semibold }}>
          {points.toLocaleString()} Points
        </Text>
      </View>
      <View className="mt-2 flex-row items-center justify-between">
        <Text className="text-[9px] text-violet-100/68" style={{ fontFamily: fonts.body }}>
          Progress to {target.toLocaleString()} pts
        </Text>
        <View className="rounded-full bg-auric-300 px-3 py-1">
          <Text className="text-[9px] text-cosmic-950" style={{ fontFamily: fonts.semibold }}>
            Claim
          </Text>
        </View>
      </View>
      <View className="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
        <View className="h-full rounded-full bg-auric-300" style={{ width: `${percentage}%` }} />
      </View>
    </AnimatedPressable>
  );
}

export default function DashboardScreen({ fontsReady, onOpenGiftcard, onOpenMarket, onOpenStaking, onOpenTrade }: DashboardScreenProps) {
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const { user, logout, request, apiBaseUrl } = useAuth();
  const [balances, setBalances] = useState<Balance[]>([]);
  const [points, setPoints] = useState<Record<string, unknown> | null>(null);
  const [liveMarkets, setLiveMarkets] = useState<MarketItem[]>([]);
  const [notifications, setNotifications] = useState<NotificationItem[]>([]);
  const [checkins, setCheckins] = useState<CheckInItem[]>([]);
  const [marketFilter, setMarketFilter] = useState("Top");
  const [activePanel, setActivePanel] = useState("Home");
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const [rewardOpen, setRewardOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [rewardBusy, setRewardBusy] = useState(false);
  const [lastReward, setLastReward] = useState("");
  const [message, setMessage] = useState("");

  const displayName = user?.name || user?.email || "ExaEarn user";
  const initials = useMemo(
    () =>
      String(displayName)
        .split(" ")
        .filter(Boolean)
        .map((part) => part[0])
        .join("")
        .slice(0, 2)
        .toUpperCase() || "EX",
    [displayName],
  );

  const rewardData = getRewardData(points);
  const marketData = liveMarkets.length ? liveMarkets : fallbackMarkets;
  const priceMap = useMemo(() => {
    const entries = marketData.map((market) => [market.base, toNumber(String(market.price).replace(/,/g, ""))] as const);
    return new Map(entries);
  }, [marketData]);
  const portfolioValue = useMemo(
    () =>
      balances.reduce((total, item) => {
        const symbol = String(item.currency ?? item.asset ?? "").toUpperCase();
        const amount = toNumber(item.available ?? item.balance);
        if (["USDT", "USD", "USDC"].includes(symbol)) return total + amount;
        return total + amount * (priceMap.get(symbol) || 1);
      }, 0),
    [balances, priceMap],
  );
  const shellWidth = Math.min(width - 16, width >= 1024 ? 460 : 430);
  const showDesktopAssistant = width >= 900;
  const featureColumns = 4;
  const featureGap = shellWidth < 340 ? 5 : 6;
  const featureGridWidth = shellWidth - 32;
  const featureCardWidth = Math.floor((featureGridWidth - featureGap * (featureColumns - 1)) / featureColumns);
  const compactFeatures = featureCardWidth < 72;
  const visibleMarkets = useMemo(() => {
    const sorted = [...marketData].sort((left, right) => {
      if (marketFilter === "Gainers") return Number.parseFloat(right.change) - Number.parseFloat(left.change);
      if (marketFilter === "Fav") return Number(right.base === "BTC" || right.base === "XRP") - Number(left.base === "BTC" || left.base === "XRP");
      return Number.parseFloat(right.price.replace(/,/g, "")) - Number.parseFloat(left.price.replace(/,/g, ""));
    });
    return sorted.slice(0, 4);
  }, [marketData, marketFilter]);
  const unreadCount = notifications.length;
  const activeDetails = useMemo(() => {
    const details: Record<string, { icon: keyof typeof Ionicons.glyphMap; title: string; body: string; action: string }> = {
      "Gift Cards": {
        icon: "gift-outline",
        title: "Gift Card Exchange",
        body: "Redeem, swap, and track gift card liquidity with the same wallet identity used on web.",
        action: "Open Rates",
      },
      "ExaEarn Staking": {
        icon: "cash-outline",
        title: "ExaEarn Staking",
        body: "Review eligible native PoS assets, network status, and verified reward history.",
        action: "View Pools",
      },
      Games: {
        icon: "game-controller-outline",
        title: "GameFi Arcade",
        body: "Join reward games, lotteries, and engagement quests connected to your Exa points.",
        action: "Browse Games",
      },
      "NFT Market": {
        icon: "diamond-outline",
        title: "NFT Marketplace",
        body: "Discover collections, owned assets, listings, and utility upgrades.",
        action: "Explore NFTs",
      },
      Crowdfund: {
        icon: "people-outline",
        title: "Crowdfunding Hub",
        body: "Track launch pools, back projects, and follow community-backed opportunities.",
        action: "See Projects",
      },
      Agritech: {
        icon: "leaf-outline",
        title: "Agritech Access",
        body: "Connect to farm-backed products, funding rails, and verified agricultural campaigns.",
        action: "View Farms",
      },
      EdTech: {
        icon: "school-outline",
        title: "EdTech Rewards",
        body: "Continue learning quests and unlock reward paths tied to course progress.",
        action: "Continue",
      },
      More: {
        icon: "apps-outline",
        title: "More Modules",
        body: "Access the rest of the super-app services as they come online.",
        action: "View All",
      },
      Market: {
        icon: "bar-chart-outline",
        title: "Market Desk",
        body: "Live pairs are loaded from the backend when available, with fallback data for preview mode.",
        action: "Refresh",
      },
      Trade: {
        icon: "diamond-outline",
        title: "Trade Console",
        body: "Spot orders, swaps, routing, and market execution belong here when the trade screens are expanded.",
        action: "Prepare Trade",
      },
      P2P: {
        icon: "people-outline",
        title: "P2P Exchange",
        body: "Buyer and seller flows can plug into the existing P2P API routes from this entry point.",
        action: "Find Offers",
      },
      Assets: {
        icon: "wallet-outline",
        title: "Asset Center",
        body: "Balances, funding, deposits, and withdrawals are grouped around the shared backend wallet.",
        action: "Review Wallet",
      },
      Campaigns: {
        icon: "megaphone-outline",
        title: "Campaign Engine",
        body: "Launch and monitor Web3 growth campaigns with AI scoring and reward distribution.",
        action: "Configure",
      },
      Settings: {
        icon: "settings-outline",
        title: "Account Settings",
        body: `Signed in through ${apiBaseUrl}. Manage account preferences or end this session.`,
        action: "Sign Out",
      },
    };
    const walletSymbol = activePanel.endsWith(" Wallet") ? activePanel.replace(" Wallet", "") : "";
    return (
      details[activePanel] || {
        icon: "wallet-outline",
        title: `${walletSymbol || activePanel} Wallet`,
        body: "Quick funding and watchlist actions are ready for the connected wallet flow.",
        action: "Review Asset",
      }
    );
  }, [activePanel, apiBaseUrl]);

  const loadDashboard = async () => {
    setLoading(true);
    setMessage("");
    try {
      const [walletPayload, pointsPayload, historyPayload, notificationsPayload, marketsPayload] = await Promise.allSettled([
        request<Record<string, unknown>>("/api/wallet/balances", { method: "GET" }),
        request<Record<string, unknown>>("/api/points", { method: "GET" }),
        request<Record<string, unknown>>("/api/checkin/history", { method: "GET" }),
        request<Record<string, unknown>>("/api/notifications/unread", { method: "GET" }),
        request<Record<string, unknown>>("/api/trade/markets", { method: "GET" }),
      ]);

      if (walletPayload.status === "fulfilled") {
        const data = walletPayload.value.data;
        setBalances(Array.isArray(data) ? (data as Balance[]) : []);
      }

      if (pointsPayload.status === "fulfilled") {
        setPoints(pointsPayload.value);
      }

      if (historyPayload.status === "fulfilled") {
        setCheckins(unwrapArray(historyPayload.value) as CheckInItem[]);
      }

      if (notificationsPayload.status === "fulfilled") {
        setNotifications(unwrapArray(notificationsPayload.value) as NotificationItem[]);
      }

      if (marketsPayload.status === "fulfilled") {
        const normalized = normalizeMarkets(marketsPayload.value);
        if (normalized.length) setLiveMarkets(normalized);
      }

      if (walletPayload.status === "rejected" && pointsPayload.status === "rejected" && marketsPayload.status === "rejected") {
        setMessage(walletPayload.reason instanceof Error ? walletPayload.reason.message : apiHelpText);
      }
    } finally {
      setLoading(false);
    }
  };

  const claimDailyReward = async () => {
    setRewardBusy(true);
    setLastReward("");
    try {
      const payload = await request<Record<string, unknown>>("/api/checkin", {
        method: "POST",
        body: JSON.stringify({ device_fingerprint: `mobile-dashboard-${user?.id ?? user?.email ?? "guest"}` }),
      });
      const data = payload.data && typeof payload.data === "object" ? (payload.data as Record<string, unknown>) : {};
      const rewardPoints = toNumber(data.reward_points);
      setLastReward(payload.code === "already_claimed" ? "Daily reward already claimed today" : `Claimed ${rewardPoints.toLocaleString()} points`);
      await loadDashboard();
    } catch (error) {
      setLastReward(error instanceof Error ? error.message : "Unable to claim your reward right now.");
    } finally {
      setRewardBusy(false);
    }
  };

  const markNotificationsRead = async () => {
    try {
      await request<Record<string, unknown>>("/api/notifications/mark-all-read", { method: "POST" });
      setNotifications([]);
      setNotificationsOpen(false);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Unable to update notifications.");
    }
  };

  useEffect(() => {
    void loadDashboard();
  }, []);

  if (!fontsReady) {
    return <LinearGradient colors={[colors.cosmic950, "#1a0f2e", "#0f1a3a"]} style={styles.fill} />;
  }

  return (
    <LinearGradient colors={[colors.cosmic950, "#1a0f2e", "#0f1a3a"]} style={styles.fill}>
      <View pointerEvents="none" style={styles.exaBg} />
      <ScrollView
        contentContainerStyle={[
          styles.content,
          {
            maxWidth: shellWidth,
            paddingTop: insets.top + 12,
            paddingBottom: insets.bottom + 96,
          },
        ]}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={loadDashboard} tintColor={colors.auric300} />}
        showsVerticalScrollIndicator={false}
      >
        <Animated.View entering={FadeInDown.duration(520)} style={styles.mainCard}>
          <View style={styles.profileCard}>
            <View className="min-w-0 flex-1 flex-row items-center gap-3">
              <AnimatedPressable style={styles.avatar}>
                <Text className="text-[12px] text-violet-100" style={{ fontFamily: fonts.semibold }}>
                  {initials}
                </Text>
              </AnimatedPressable>
              <View className="min-w-0 flex-1">
                <Text className="text-[13px] text-auric-300" numberOfLines={1} style={{ fontFamily: fonts.semibold }}>
                  Multi-Chain Vault
                </Text>
                <Text className="mt-1 text-[11px] text-gray-300" numberOfLines={1} style={{ fontFamily: fonts.body }}>
                  12 networks secured - {String(user?.unique_user_id || "0x4a...0c51")}
                </Text>
              </View>
            </View>
            <View style={styles.headerActions}>
              <View style={styles.notificationMenu}>
                <AnimatedPressable style={styles.iconButton} onPress={() => setNotificationsOpen((value) => !value)}>
                  <Ionicons name="notifications-outline" size={18} color="rgba(255,255,255,0.62)" />
                  {unreadCount ? (
                    <View style={styles.notificationCount}>
                      <Text style={styles.notificationCountText}>{unreadCount > 9 ? "9+" : unreadCount}</Text>
                    </View>
                  ) : null}
                </AnimatedPressable>
                {notificationsOpen ? (
                  <View style={styles.notificationTray}>
                    <View style={styles.notificationTrayHead}>
                      <View>
                        <Text style={styles.notificationTrayTitle}>Notifications</Text>
                        <Text style={styles.notificationTrayMeta}>{unreadCount} unread</Text>
                      </View>
                      <AnimatedPressable onPress={() => setNotificationsOpen(false)} style={styles.notificationClose}>
                        <Ionicons name="close" size={15} color="rgba(255,255,255,0.78)" />
                      </AnimatedPressable>
                    </View>
                    <View>
                      {notifications.length ? (
                        notifications.slice(0, 4).map((item, index) => (
                          <AnimatedPressable key={String(item.id ?? index)} style={styles.notificationItem}>
                            <View style={styles.notificationDot} />
                            <View className="min-w-0 flex-1">
                              <Text className="text-[10px] text-violet-50" numberOfLines={1} style={{ fontFamily: fonts.semibold }}>
                                {item.title || item.type || "Notification"}
                              </Text>
                              <Text className="mt-0.5 text-[9px] text-violet-100/62" numberOfLines={2} style={{ fontFamily: fonts.body }}>
                                {item.message || item.body || "New update available."}
                              </Text>
                            </View>
                            <Text style={styles.notificationTime}>Now</Text>
                          </AnimatedPressable>
                        ))
                      ) : (
                        <Text style={styles.notificationEmpty}>No notifications yet.</Text>
                      )}
                    </View>
                    {notifications.length ? (
                      <AnimatedPressable onPress={markNotificationsRead} style={styles.notificationMarkRead}>
                        <Text style={styles.notificationMarkReadText}>Mark read</Text>
                      </AnimatedPressable>
                    ) : null}
                  </View>
                ) : null}
              </View>
              <AnimatedPressable style={styles.iconButton} onPress={() => setActivePanel("Settings")}>
                <Ionicons name="settings-outline" size={18} color="rgba(255,255,255,0.62)" />
              </AnimatedPressable>
            </View>
          </View>


          <AnimatedPressable style={styles.campaignHero} onPress={() => setActivePanel("Campaigns")}>
            <View style={styles.campaignOrbit}>
              <LinearGradient
                colors={["rgba(212,175,55,0.9)", "rgba(155,89,255,0.48)"]}
                style={StyleSheet.absoluteFillObject}
              />
              <Ionicons name="sparkles" size={16} color={colors.auric300} />
            </View>
            <View className="min-w-0 flex-1">
              <Text className="text-[9px] uppercase tracking-[1.8px] text-auric-300/80" style={{ fontFamily: fonts.semibold }}>
                Live Growth Engine
              </Text>
              <Text className="mt-0.5 text-[13px] text-violet-50" style={{ fontFamily: fonts.display }}>
                Run Campaign
              </Text>
              <Text className="mt-0.5 text-[9px] text-violet-100/72" numberOfLines={1} style={{ fontFamily: fonts.body }}>
                Launch targeted Web3 campaigns instantly.
              </Text>
            </View>
            <View style={styles.campaignMetric}>
              <Text className="text-[10px] text-auric-300" style={{ fontFamily: fonts.display }}>
                AI
              </Text>
              <Text className="text-[8px] text-violet-100/70" style={{ fontFamily: fonts.body }}>
                1/5
              </Text>
            </View>
          </AnimatedPressable>

          <Animated.View entering={FadeInUp.delay(80).duration(560)} style={styles.portfolioCard}>
            <View className="flex-row items-start justify-between gap-3">
              <View className="min-w-0 flex-1">
                <Text className="text-[10px] text-[#9aa3b2]" style={{ fontFamily: fonts.body }}>
                Est. Portfolio Value
              </Text>
                <Text className="mt-1 text-[25px] leading-[31px] text-violet-50" style={{ fontFamily: fonts.display }}>
                {formatMoney(portfolioValue)}
              </Text>
                <View style={styles.balanceAccent} />
                <View className="mt-3 flex-row items-center">
                  <View style={styles.pnlPill}>
                    <Ionicons name="arrow-up" size={12} color="#4dccff" />
                    <Text className="ml-1 text-[10px] text-[#bfe9ff]" style={{ fontFamily: fonts.semibold }}>
                    +4.2% 24h
                  </Text>
                </View>
                  <Text className="ml-2 text-[10px] text-gray-400" style={{ fontFamily: fonts.body }}>
                    - Today's PNL
                </Text>
              </View>
            </View>
              <View className="w-[134px] items-stretch gap-2">
                <AnimatedPressable style={styles.addFunds} onPress={() => setActivePanel("Assets")}>
                  <Text className="text-center text-[11px] text-cosmic-950" style={{ fontFamily: fonts.semibold }}>
                    Add Funds
                </Text>
              </AnimatedPressable>
                <DailyRewardCard
                  points={rewardData.points}
                  streak={rewardData.streak}
                  target={rewardData.target}
                  percentage={rewardData.percentage}
                  onPress={() => setRewardOpen(true)}
                />
              </View>
            </View>
          </Animated.View>

          {message ? (
            <View className="mt-2 rounded-xl border border-rose-300/25 bg-rose-500/10 p-3">
              <Text className="text-[11px] leading-4 text-rose-100" style={{ fontFamily: fonts.body }}>
                {message}
              </Text>
          </View>
          ) : null}

          <View style={styles.featuresCard}>
            <View className="mb-2 flex-row items-center justify-between">
              <View>
                <Text className="text-[9px] uppercase tracking-[1.7px] text-auric-300/80" style={{ fontFamily: fonts.semibold }}>
                  Super-App Access
                </Text>
                <Text className="mt-0.5 text-[14px] text-violet-50" style={{ fontFamily: fonts.display }}>
                  Core Web3 Modules
                </Text>
              </View>
              <Ionicons name="sparkles" size={16} color={colors.auric300} />
            </View>
            <View style={[styles.featuresGrid, { gap: featureGap }]}>
              {features.map((item) => (
                <AnimatedPressable
                  key={item.label}
                  onPress={() => {
                    if (item.label === "Gift Cards" && onOpenGiftcard) {
                      onOpenGiftcard();
                      return;
                    }
                    if (item.label === "ExaEarn Staking" && onOpenStaking) {
                      onOpenStaking();
                      return;
                    }
                    if (item.label === "More" || item.label === "Games" || item.label === "NFT Market" || item.label === "Crowdfund" || item.label === "Agritech" || item.label === "EdTech") {
                      setActivePanel(item.label);
                      return;
                    }
                    setActivePanel(item.label);
                  }}
                  style={[
                    styles.featureCard,
                    {
                      width: featureCardWidth,
                      minHeight: 88,
                      paddingHorizontal: 5,
                    },
                  ]}
                >
                  <View style={styles.featureIconWrap}>
                    <Image source={item.image} style={styles.featureImage} resizeMode="cover" />
                    <View style={styles.featureLucide}>
                      <Ionicons name={item.icon} size={17} color={item.tone} />
                    </View>
                  </View>
                  <Text
                    className="text-center text-violet-50"
                    numberOfLines={2}
                    adjustsFontSizeToFit
                    minimumFontScale={0.82}
                    style={{
                      fontFamily: fonts.semibold,
                      fontSize: 10,
                      lineHeight: 12.5,
                      minHeight: 25,
                      width: "100%",
                    }}
                  >
                    {item.label}
                  </Text>
                </AnimatedPressable>
              ))}
            </View>
          </View>

          <View style={styles.marketSection}>
            <View className="mb-2 flex-row items-center justify-between">
              <View>
                <Text className="text-[9px] uppercase tracking-[1.7px] text-auric-300/80" style={{ fontFamily: fonts.semibold }}>
                  Live Market
                </Text>
                <Text className="mt-0.5 text-[14px] text-violet-50" style={{ fontFamily: fonts.display }}>
                  Exchange Markets
                </Text>
              </View>
              <AnimatedPressable style={styles.marketAdd} onPress={() => (onOpenMarket ? onOpenMarket() : setActivePanel("Market"))}>
                <Ionicons name="add" size={12} color="#fde68a" />
                <Text className="ml-1 text-[9px] text-[#fde68a]" style={{ fontFamily: fonts.semibold }}>
                  Add Crypto
              </Text>
              </AnimatedPressable>
            </View>

            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.tickerWrap}>
              {[...marketData, ...marketData].map((asset, index) => (
                <View key={`${asset.base}-${index}`} style={[styles.tickerChip, asset.positive ? styles.tickerHot : styles.tickerCool]}>
                  <Text className="text-[10px] text-violet-50" style={{ fontFamily: fonts.semibold }}>
                    {asset.base}
                  </Text>
                  <Text className="text-[10px] text-violet-100/72" style={{ fontFamily: fonts.body }}>
                    ${asset.price}
                  </Text>
                  <Text className={`text-[10px] ${asset.positive ? "text-emerald-200" : "text-rose-200"}`} style={{ fontFamily: fonts.semibold }}>
                    {asset.change}
                  </Text>
                </View>
              ))}
            </ScrollView>

            <View style={styles.exchangeCard}>
              <View className="flex-row items-center justify-between px-2 pt-2">
                <View className="flex-row rounded-full bg-white/5 p-1">
                  {["Top", "Gainers", "Fav"].map((tab) => (
                    <AnimatedPressable
                      key={tab}
                      className={`rounded-full px-3 py-1.5 ${marketFilter === tab ? "bg-auric-300/20" : ""}`}
                      onPress={() => setMarketFilter(tab)}
                    >
                      <Text className={`text-[9px] ${marketFilter === tab ? "text-auric-300" : "text-violet-100/70"}`} style={{ fontFamily: fonts.semibold }}>
                        {tab}
                      </Text>
                    </AnimatedPressable>
                  ))}
                </View>
                <View style={styles.liveBadge}>
                  <Text className="text-[9px] text-emerald-100" style={{ fontFamily: fonts.semibold }}>
                    Live
                  </Text>
                </View>
              </View>

              <View style={styles.tableHead}>
                <Text style={styles.tableHeadText}>Pair</Text>
                <Text style={[styles.tableHeadText, styles.alignRight]}>Last Price</Text>
                <Text style={[styles.tableHeadText, styles.alignRight]}>24h</Text>
              </View>

              <View>
                {visibleMarkets.map((market) => (
                  <AnimatedPressable key={market.base} style={styles.exchangeRow} onPress={() => (onOpenMarket ? onOpenMarket() : setActivePanel("Market"))}>
                    <View style={styles.exchangePair}>
                      <View style={[styles.exchangeStar, market.base === "BTC" || market.base === "XRP" ? styles.exchangeStarActive : null]}>
                        <Ionicons name="star" size={13} color={market.base === "BTC" || market.base === "XRP" ? colors.auric300 : "rgba(148,163,184,0.72)"} />
                      </View>
                      <Text style={styles.exchangeBase}>{market.base}</Text>
                      <Text style={styles.exchangeQuote}>/{market.quote}</Text>
                    </View>
                    <View style={styles.exchangePrice}>
                      <Text style={styles.exchangePriceText}>${market.price}</Text>
                      <Text style={styles.exchangePriceMeta}>Vol Live</Text>
                    </View>
                    <View style={[styles.exchangeChangePill, market.positive ? styles.exchangeChangePositive : styles.exchangeChangeNegative]}>
                      <Text style={[styles.exchangeChangeText, market.positive ? styles.exchangeChangePositiveText : styles.exchangeChangeNegativeText]}>
                        {market.change}
                      </Text>
                    </View>
                    <View style={styles.exchangeSpark}>
                      {chartBars.map((height, index) => (
                        <View key={`${market.base}-${index}`} style={[styles.exchangeSparkBar, { height: `${market.positive ? height : 92 - height}%` }]} />
                      ))}
                    </View>
                  </AnimatedPressable>
                ))}
              </View>

              <View style={styles.quickAdd}>
                <Text style={styles.quickAddLabel}>Quick add</Text>
                <View style={styles.quickAddButtons}>
                  {["BTC", "ETH", "XRP", "SOL"].map((symbol) => (
                    <AnimatedPressable key={symbol} onPress={() => (onOpenMarket ? onOpenMarket() : setActivePanel(`${symbol} Wallet`))} style={styles.quickAddButton}>
                      <Ionicons name="add" size={11} color="#fde68a" />
                      <Text style={styles.quickAddButtonText}>{symbol}</Text>
                    </AnimatedPressable>
                  ))}
                </View>
              </View>
            </View>

            <View style={styles.aiStrip}>
              <Text className="text-[9px] text-violet-100/66" style={{ fontFamily: fonts.semibold }}>
                AI Signal
              </Text>
              <Text className="text-[9px] text-emerald-200" style={{ fontFamily: fonts.semibold }}>
                87% Bullish
              </Text>
              <View className="h-1.5 flex-1 overflow-hidden rounded-full bg-white/10">
                <View className="h-full w-[87%] rounded-full bg-auric-300" />
              </View>
            </View>
          </View>
        </Animated.View>
      </ScrollView>

      {showDesktopAssistant ? (
        <AnimatedPressable style={styles.aiAssistantButton} onPress={() => setActivePanel("AI Assistant")}>
          <Text className="text-[11px] text-[#F3E8C8]" style={{ fontFamily: fonts.semibold }}>
            AI Assistant
          </Text>
        </AnimatedPressable>
      ) : null}

      <View style={[styles.bottomNavShell, { paddingBottom: insets.bottom }]}>
        <View style={[styles.bottomNav, { maxWidth: shellWidth, alignSelf: "center", width: "100%" }]}>
          {navItems.map((item) => (
            <AnimatedPressable
              key={item.label}
              onPress={() => {
                if (item.label === "Market" && onOpenMarket) {
                  onOpenMarket();
                  return;
                }
                setActivePanel(item.label);
              }}
              style={[styles.navItem, (activePanel === item.label || (item.label === "Home" && activePanel === "Home")) ? styles.navItemActive : null]}
            >
              {item.image ? (
                <Image source={item.image} style={styles.navImage} resizeMode="contain" />
              ) : (
                <Ionicons
                  name={item.icon}
                  size={21}
                  color={activePanel === item.label || (item.label === "Home" && activePanel === "Home") ? colors.auric300 : "rgba(255,255,255,0.55)"}
                />
              )}
              <Text
                className={`mt-1 text-[10px] ${activePanel === item.label || (item.label === "Home" && activePanel === "Home") ? "text-auric-300" : "text-white/55"}`}
                style={{ fontFamily: fonts.semibold }}
              >
                {item.label}
              </Text>
              {activePanel === item.label || (item.label === "Home" && activePanel === "Home") ? <View style={styles.navActiveIndicator} /> : null}
              </AnimatedPressable>
            ))}
          </View>
      </View>

      <Modal visible={rewardOpen} transparent animationType="fade" onRequestClose={() => setRewardOpen(false)}>
        <View style={styles.modalBackdrop}>
          <Animated.View entering={FadeInDown.duration(280)} style={styles.rewardModal}>
            <View className="flex-row items-start justify-between gap-3">
              <View className="min-w-0 flex-1">
                <Text className="text-[10px] uppercase tracking-[1.7px] text-auric-300/80" style={{ fontFamily: fonts.semibold }}>
                  Daily Reward
                </Text>
                <Text className="mt-1 text-[20px] text-violet-50" style={{ fontFamily: fonts.display }}>
                  {rewardData.points.toLocaleString()} points
                </Text>
                <Text className="mt-1 text-[11px] leading-4 text-violet-100/66" style={{ fontFamily: fonts.body }}>
                  {rewardData.streak} day streak toward {rewardData.usdt} USDT redemption.
                </Text>
              </View>
              <AnimatedPressable style={styles.closeButton} onPress={() => setRewardOpen(false)}>
                <Ionicons name="close" size={18} color="rgba(255,255,255,0.7)" />
              </AnimatedPressable>
            </View>

            <View className="mt-4 h-2 overflow-hidden rounded-full bg-white/10">
              <View className="h-full rounded-full bg-auric-300" style={{ width: `${rewardData.percentage}%` }} />
            </View>

            <View style={styles.historyBox}>
              <Text className="mb-2 text-[10px] text-violet-100/62" style={{ fontFamily: fonts.semibold }}>
                Recent check-ins
              </Text>
              {checkins.length ? (
                checkins.slice(0, 4).map((item, index) => (
                  <View key={`${item.created_at ?? item.checkin_date ?? index}`} style={styles.historyRow}>
                    <Text className="text-[10px] text-violet-50" style={{ fontFamily: fonts.semibold }}>
                      Day {item.streak_day ?? index + 1}
                    </Text>
                    <Text className="text-[10px] text-auric-300" style={{ fontFamily: fonts.semibold }}>
                      +{toNumber(item.reward_points || 1)} pts
                    </Text>
                  </View>
                ))
              ) : (
                <Text className="text-[10px] text-violet-100/58" style={{ fontFamily: fonts.body }}>
                  Claim today to start your mobile streak.
                </Text>
              )}
            </View>

            {lastReward ? (
              <Text className="mt-3 text-[11px] text-auric-300" style={{ fontFamily: fonts.semibold }}>
                {lastReward}
              </Text>
            ) : null}

            <AnimatedPressable style={[styles.claimButton, rewardBusy ? styles.disabledAction : null]} onPress={claimDailyReward} disabled={rewardBusy}>
              <Text className="text-center text-[12px] text-cosmic-950" style={{ fontFamily: fonts.semibold }}>
                {rewardBusy ? "Claiming..." : "Claim Daily Reward"}
              </Text>
            </AnimatedPressable>
          </Animated.View>
        </View>
      </Modal>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  fill: {
    flex: 1,
  },
  content: {
    paddingHorizontal: 8,
    width: "100%",
    alignSelf: "center",
  },
  exaBg: {
    ...StyleSheet.absoluteFillObject,
    opacity: 0.9,
    backgroundColor: "rgba(155,89,255,0.025)",
  },
  mainCard: {
    borderRadius: 22,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.08)",
    backgroundColor: "rgba(12,17,30,0.78)",
    padding: 16,
    shadowColor: "#9b59ff",
    shadowOffset: { width: 0, height: 18 },
    shadowOpacity: 0.22,
    shadowRadius: 44,
    elevation: 16,
  },
  profileCard: {
    minHeight: 58,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    borderRadius: 20,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.1)",
    backgroundColor: "rgba(255,255,255,0.04)",
    padding: 16,
  },
  headerActions: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
  },
  notificationMenu: {
    position: "relative",
  },
  notificationTray: {
    position: "absolute",
    top: 42,
    right: 0,
    zIndex: 60,
    width: 320,
    maxWidth: 320,
    overflow: "hidden",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.12)",
    borderRadius: 8,
    backgroundColor: "rgba(11,16,30,0.96)",
    shadowColor: "#000000",
    shadowOffset: { width: 0, height: 22 },
    shadowOpacity: 0.42,
    shadowRadius: 55,
    elevation: 24,
  },
  notificationTrayHead: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
    padding: 12,
    borderBottomWidth: 1,
    borderBottomColor: "rgba(255,255,255,0.08)",
  },
  notificationTrayTitle: {
    color: "#ffffff",
    fontFamily: fonts.semibold,
    fontSize: 13,
  },
  notificationTrayMeta: {
    marginTop: 2,
    color: "rgba(255,255,255,0.55)",
    fontFamily: fonts.body,
    fontSize: 11,
  },
  notificationClose: {
    width: 26,
    height: 26,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.1)",
    borderRadius: 8,
    backgroundColor: "rgba(255,255,255,0.05)",
  },
  notificationItem: {
    minHeight: 50,
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 8,
    borderTopWidth: 1,
    borderTopColor: "rgba(255,255,255,0.06)",
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  notificationDot: {
    width: 8,
    height: 8,
    borderRadius: 999,
    marginTop: 6,
    backgroundColor: colors.auric300,
  },
  notificationTime: {
    color: "rgba(255,255,255,0.52)",
    fontFamily: fonts.body,
    fontSize: 10,
  },
  notificationEmpty: {
    color: "rgba(255,255,255,0.62)",
    fontFamily: fonts.body,
    fontSize: 11,
    padding: 12,
  },
  notificationMarkRead: {
    borderTopWidth: 1,
    borderTopColor: "rgba(255,255,255,0.08)",
    paddingHorizontal: 12,
    paddingVertical: 10,
    alignItems: "flex-start",
  },
  notificationMarkReadText: {
    color: colors.auric300,
    fontFamily: fonts.semibold,
    fontSize: 10,
  },
  avatar: {
    width: 48,
    height: 48,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 24,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.22)",
    backgroundColor: "rgba(255,255,255,0.06)",
  },
  iconButton: {
    position: "relative",
    width: 32,
    height: 32,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 8,
  },
  notificationCount: {
    position: "absolute",
    top: 1,
    right: 0,
    minWidth: 16,
    height: 16,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 8,
    backgroundColor: colors.auric500,
  },
  notificationCountText: {
    color: "#090b12",
    fontSize: 10,
    fontWeight: "800",
  },
  notificationPanel: {
    marginTop: 12,
    borderRadius: 24,
    borderWidth: 1,
    borderColor: "rgba(212,175,55,0.18)",
    backgroundColor: "rgba(9,13,24,0.86)",
    padding: 10,
  },
  notificationRow: {
    minHeight: 38,
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 12,
    borderTopWidth: 1,
    borderTopColor: "rgba(255,255,255,0.06)",
    paddingVertical: 7,
  },
  miniAction: {
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "rgba(212,175,55,0.24)",
    backgroundColor: "rgba(212,175,55,0.1)",
    paddingHorizontal: 9,
    paddingVertical: 5,
  },
  campaignHero: {
    minHeight: 78,
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    marginTop: 12,
    overflow: "hidden",
    borderRadius: 24,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.1)",
    backgroundColor: "rgba(255,255,255,0.045)",
    paddingHorizontal: 12,
    paddingVertical: 12,
    shadowColor: colors.auric500,
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.16,
    shadowRadius: 24,
  },
  campaignOrbit: {
    width: 48,
    height: 48,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 24,
    overflow: "hidden",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.32)",
    backgroundColor: "rgba(212,175,55,0.8)",
  },
  campaignMetric: {
    width: 42,
    height: 42,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "rgba(212,175,55,0.24)",
    backgroundColor: "rgba(212,175,55,0.1)",
  },
  portfolioCard: {
    marginTop: 12,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.08)",
    backgroundColor: "rgba(12,17,30,0.78)",
    padding: 16,
    shadowColor: colors.auric500,
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.12,
    shadowRadius: 28,
  },
  balanceAccent: {
    width: 118,
    height: 2,
    marginTop: 2,
    borderRadius: 1,
    backgroundColor: "rgba(212,175,55,0.5)",
  },
  pnlPill: {
    flexDirection: "row",
    alignItems: "center",
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.08)",
    backgroundColor: "rgba(12,17,30,0.78)",
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  addFunds: {
    minHeight: 28,
    justifyContent: "center",
    borderRadius: 9,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.15)",
    backgroundColor: colors.auric500,
    paddingHorizontal: 10,
  },
  rewardCard: {
    minHeight: 54,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.13)",
    backgroundColor: "rgba(14,18,31,0.72)",
    paddingHorizontal: 12,
    paddingVertical: 12,
  },
  rewardTop: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 6,
  },
  featuresCard: {
    marginTop: 12,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.08)",
    backgroundColor: "rgba(12,17,30,0.78)",
    padding: 16,
    shadowColor: "#9b59ff",
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.12,
    shadowRadius: 24,
  },
  featuresGrid: {
    flexDirection: "row",
    flexWrap: "wrap",
    alignContent: "flex-start",
    justifyContent: "flex-start",
  },
  featureCard: {
    minHeight: 88,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.08)",
    backgroundColor: "rgba(12,17,30,0.78)",
    paddingHorizontal: 5,
    paddingVertical: 8,
  },
  featureIconWrap: {
    position: "relative",
    width: 40,
    height: 40,
    alignItems: "center",
    justifyContent: "center",
    marginBottom: 7,
    overflow: "hidden",
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.16)",
    backgroundColor: "rgba(255,255,255,0.04)",
  },
  featureImage: {
    width: 27,
    height: 27,
    borderRadius: 9,
  },
  featureLucide: {
    position: "absolute",
    right: -4,
    bottom: -4,
    borderRadius: 999,
    backgroundColor: "rgba(10,5,18,0.82)",
    padding: 2,
  },
  modulePanel: {
    minHeight: 72,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    marginTop: 12,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: "rgba(212,175,55,0.18)",
    backgroundColor: "rgba(255,255,255,0.045)",
    padding: 10,
  },
  moduleIcon: {
    width: 36,
    height: 36,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "rgba(212,175,55,0.26)",
    backgroundColor: "rgba(212,175,55,0.1)",
  },
  moduleAction: {
    minHeight: 30,
    justifyContent: "center",
    borderRadius: 9,
    backgroundColor: colors.auric500,
    paddingHorizontal: 10,
  },
  marketSection: {
    marginTop: 12,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.09)",
    backgroundColor: "rgba(12,16,28,0.82)",
    padding: 16,
    shadowColor: "#9b59ff",
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.16,
    shadowRadius: 32,
  },
  marketAdd: {
    minHeight: 26,
    flexDirection: "row",
    alignItems: "center",
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "rgba(212,175,55,0.36)",
    backgroundColor: "rgba(212,175,55,0.13)",
    paddingHorizontal: 8,
  },
  tickerWrap: {
    marginHorizontal: -9,
    marginBottom: 7,
  },
  tickerChip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 5,
    marginRight: 6,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "rgba(212,175,55,0.24)",
    backgroundColor: "rgba(255,255,255,0.045)",
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  tickerHot: {
    borderColor: "rgba(34,197,94,0.24)",
  },
  tickerCool: {
    borderColor: "rgba(248,113,113,0.24)",
  },
  exchangeCard: {
    overflow: "hidden",
    borderRadius: 24,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.1)",
    backgroundColor: "rgba(9,13,24,0.78)",
  },
  liveBadge: {
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "rgba(34,197,94,0.24)",
    backgroundColor: "rgba(34,197,94,0.14)",
    paddingLeft: 17,
    paddingRight: 8,
    paddingVertical: 5,
  },
  tableHead: {
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 10,
    paddingTop: 7,
    paddingBottom: 5,
  },
  tableHeadText: {
    flex: 1,
    color: "rgba(148,163,184,0.78)",
    fontSize: 9,
    fontWeight: "800",
    textTransform: "uppercase",
  },
  alignRight: {
    textAlign: "right",
  },
  exchangeRow: {
    minHeight: 50,
    flexDirection: "row",
    alignItems: "center",
    borderTopWidth: 1,
    borderTopColor: "rgba(255,255,255,0.06)",
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  exchangePair: {
    flex: 1,
    flexDirection: "row",
    alignItems: "center",
    minWidth: 0,
    gap: 3,
  },
  exchangeStar: {
    width: 21,
    height: 21,
    marginRight: 2,
    borderRadius: 7,
    backgroundColor: "rgba(255,255,255,0.04)",
    alignItems: "center",
    justifyContent: "center",
  },
  exchangeStarActive: {
    shadowColor: colors.auric500,
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.45,
    shadowRadius: 8,
  },
  exchangeBase: {
    color: "#f8fafc",
    fontFamily: fonts.semibold,
    fontSize: 12,
  },
  exchangeQuote: {
    color: "rgba(148,163,184,0.78)",
    fontFamily: fonts.body,
    fontSize: 9,
  },
  exchangePrice: {
    width: 84,
    alignItems: "flex-end",
  },
  exchangePriceText: {
    color: "#f8fafc",
    fontFamily: fonts.semibold,
    fontSize: 12,
  },
  exchangePriceMeta: {
    color: "rgba(148,163,184,0.78)",
    fontFamily: fonts.body,
    fontSize: 9,
  },
  exchangeChangePill: {
    minWidth: 58,
    borderRadius: 8,
    paddingHorizontal: 5,
    paddingVertical: 6,
    alignItems: "center",
  },
  exchangeChangePositive: {
    backgroundColor: "rgba(34,197,94,0.16)",
  },
  exchangeChangeNegative: {
    backgroundColor: "rgba(239,68,68,0.16)",
  },
  exchangeChangeText: {
    fontFamily: fonts.semibold,
    fontSize: 10,
  },
  exchangeChangePositiveText: {
    color: "#86efac",
  },
  exchangeChangeNegativeText: {
    color: "#fca5a5",
  },
  exchangeSpark: {
    position: "absolute",
    left: "48%",
    top: 10,
    flexDirection: "row",
    alignItems: "flex-end",
    gap: 2,
    width: 42,
    height: 25,
    opacity: 0.38,
    pointerEvents: "none",
  },
  exchangeSparkBar: {
    width: 4,
    borderRadius: 999,
    backgroundColor: colors.auric500,
  },
  quickAdd: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    borderTopWidth: 1,
    borderTopColor: "rgba(255,255,255,0.08)",
    paddingHorizontal: 12,
    paddingVertical: 12,
  },
  quickAddLabel: {
    color: "rgba(226,232,240,0.68)",
    fontFamily: fonts.semibold,
    fontSize: 10,
  },
  quickAddButtons: {
    flexDirection: "row",
    gap: 5,
  },
  quickAddButton: {
    flexDirection: "row",
    alignItems: "center",
    gap: 3,
    borderWidth: 1,
    borderColor: "rgba(212,175,55,0.24)",
    borderRadius: 999,
    backgroundColor: "rgba(212,175,55,0.09)",
    paddingHorizontal: 7,
    paddingVertical: 5,
  },
  quickAddButtonText: {
    color: "#fde68a",
    fontFamily: fonts.semibold,
    fontSize: 10,
  },
  aiStrip: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    marginTop: 7,
    borderRadius: 13,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.08)",
    backgroundColor: "rgba(255,255,255,0.04)",
    paddingHorizontal: 12,
    paddingVertical: 12,
  },
  bottomNavShell: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: "transparent",
  },
  aiAssistantButton: {
    position: "absolute",
    right: 20,
    bottom: 100,
    zIndex: 40,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: "rgba(212,175,55,0.6)",
    backgroundColor: "#111827",
    paddingHorizontal: 16,
    paddingVertical: 10,
    shadowColor: "#000000",
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.18,
    shadowRadius: 18,
    elevation: 10,
  },
  bottomNav: {
    minHeight: 74,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-around",
    marginHorizontal: 8,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.12)",
    backgroundColor: "rgba(11,15,26,0.96)",
    shadowColor: "#6a2cff",
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.18,
    shadowRadius: 18,
    paddingHorizontal: 14,
    paddingVertical: 11,
  },
  navItem: {
    minWidth: 56,
    alignItems: "center",
    borderRadius: 12,
    paddingHorizontal: 6,
    paddingTop: 5,
    paddingBottom: 3,
  },
  navItemActive: {
    borderWidth: 1,
    borderColor: "rgba(212,175,55,0.28)",
    backgroundColor: "rgba(212,175,55,0.1)",
  },
  navActiveIndicator: {
    marginTop: 3,
    width: 34,
    height: 2.5,
    borderRadius: 1,
    backgroundColor: colors.auric500,
    shadowColor: colors.auric500,
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 0.6,
    shadowRadius: 8,
  },
  navImage: {
    width: 24,
    height: 24,
  },
  modalBackdrop: {
    flex: 1,
    justifyContent: "flex-end",
    backgroundColor: "rgba(4,6,14,0.72)",
    padding: 12,
  },
  rewardModal: {
    width: "100%",
    maxWidth: 430,
    alignSelf: "center",
    borderRadius: 20,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.12)",
    backgroundColor: "rgba(12,16,28,0.98)",
    padding: 16,
    shadowColor: "#9b59ff",
    shadowOffset: { width: 0, height: 18 },
    shadowOpacity: 0.25,
    shadowRadius: 36,
  },
  closeButton: {
    width: 34,
    height: 34,
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 16,
    backgroundColor: "rgba(255,255,255,0.06)",
  },
  historyBox: {
    marginTop: 14,
    borderRadius: 24,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.08)",
    backgroundColor: "rgba(255,255,255,0.04)",
    padding: 10,
  },
  historyRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    borderTopWidth: 1,
    borderTopColor: "rgba(255,255,255,0.06)",
    paddingVertical: 7,
  },
  claimButton: {
    minHeight: 44,
    justifyContent: "center",
    marginTop: 14,
    borderRadius: 16,
    backgroundColor: colors.auric500,
    paddingHorizontal: 14,
  },
  disabledAction: {
    opacity: 0.62,
  },
  heroChain: {
    position: "absolute",
    right: 0,
    top: 0,
    height: 140,
    width: 160,
  },
  heroBlock: {
    position: "absolute",
    right: 24,
    top: 20,
    height: 48,
    width: 48,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "rgba(103,232,249,0.42)",
    backgroundColor: "rgba(34,211,238,0.13)",
    transform: [{ rotate: "12deg" }],
  },
  heroBlockTwo: {
    right: 68,
    top: 61,
    borderColor: "rgba(249,226,173,0.4)",
    backgroundColor: "rgba(249,226,173,0.11)",
    transform: [{ rotate: "-13deg" }],
  },
  heroBlockThree: {
    right: 14,
    top: 88,
    borderColor: "rgba(167,139,250,0.42)",
    backgroundColor: "rgba(167,139,250,0.13)",
    transform: [{ rotate: "23deg" }],
  },
});






