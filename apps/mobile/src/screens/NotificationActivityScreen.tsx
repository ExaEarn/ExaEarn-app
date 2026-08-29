import { Ionicons } from "@expo/vector-icons";
import { useEffect, useState } from "react";
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from "react-native";
import { useAuth } from "../context/AuthContext";
import { colors, fonts } from "../theme/colors";

type FeedItem = {
  id: string;
  title?: string;
  description?: string;
  category?: string;
  product?: string;
  source?: string;
  unread?: boolean;
  timestamp?: string;
};

type Props = {
  fontsReady: boolean;
  onBack: () => void;
};

const FILTERS = ["all", "money", "trading", "payments", "earn", "ecosystem", "security"];

export default function NotificationActivityScreen({ fontsReady, onBack }: Props) {
  const { request } = useAuth();
  const [tab, setTab] = useState<"notifications" | "activity">("notifications");
  const [filter, setFilter] = useState("all");
  const [items, setItems] = useState<FeedItem[]>([]);
  const [unread, setUnread] = useState(0);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState("");

  const load = async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError("");
    try {
      const path = tab === "activity"
        ? `/api/activity-center/activity?per_page=30${filter !== "all" ? `&category=${filter}` : ""}`
        : "/api/activity-center/notifications?per_page=30";
      const payload = await request<Record<string, any>>(path, { method: "GET" });
      setItems((payload.data?.items || []) as FeedItem[]);
      const stats = await request<Record<string, any>>("/api/notifications/stats", { method: "GET" }).catch(() => null);
      setUnread(Number(stats?.data?.unread || 0));
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : "Could not load Activity Center.");
      setItems([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    void load();
  }, [tab, filter]);

  const markAllRead = async () => {
    await request("/api/notifications/mark-all-read", { method: "POST" });
    await load();
  };

  const archive = async (item: FeedItem) => {
    const id = String(item.id || "").replace("notification:", "");
    if (!id) return;
    await request(`/api/notifications/${id}`, { method: "DELETE" });
    await load();
  };

  return (
    <View style={styles.screen}>
      <View style={styles.header}>
        <Pressable onPress={onBack} style={styles.iconButton} accessibilityRole="button" accessibilityLabel="Back">
          <Ionicons name="chevron-back" size={20} color={colors.violetText} />
        </Pressable>
        <View style={styles.headerCopy}>
          <Text style={[styles.title, fontsReady && fonts.soraSemi]}>Activity Center</Text>
          <Text style={styles.subtitle}>Notifications and account history</Text>
        </View>
      </View>

      <View style={styles.tabs}>
        <Pressable onPress={() => setTab("notifications")} style={[styles.tab, tab === "notifications" && styles.tabActive]}>
          <Text style={[styles.tabText, tab === "notifications" && styles.tabTextActive]}>Notifications{unread ? ` (${unread > 9 ? "9+" : unread})` : ""}</Text>
        </Pressable>
        <Pressable onPress={() => setTab("activity")} style={[styles.tab, tab === "activity" && styles.tabActive]}>
          <Text style={[styles.tabText, tab === "activity" && styles.tabTextActive]}>Activity</Text>
        </Pressable>
      </View>

      {tab === "activity" ? (
        <FlatList
          horizontal
          data={FILTERS}
          keyExtractor={(item) => item}
          contentContainerStyle={styles.filters}
          showsHorizontalScrollIndicator={false}
          renderItem={({ item }) => (
            <Pressable onPress={() => setFilter(item)} style={[styles.filter, filter === item && styles.filterActive]}>
              <Text style={[styles.filterText, filter === item && styles.filterTextActive]}>{item === "all" ? "All" : item}</Text>
            </Pressable>
          )}
        />
      ) : (
        <Pressable onPress={markAllRead} disabled={!unread} style={[styles.markRead, !unread && styles.disabled]}>
          <Ionicons name="checkmark-done" size={16} color={colors.auric300} />
          <Text style={styles.markReadText}>Mark all read</Text>
        </Pressable>
      )}

      {loading ? (
        <View style={styles.loading}><ActivityIndicator color={colors.auric300} /><Text style={styles.emptyText}>Loading...</Text></View>
      ) : error ? (
        <View style={styles.empty}><Text style={styles.error}>{error}</Text><Pressable onPress={() => load()} style={styles.retry}><Text style={styles.retryText}>Try again</Text></Pressable></View>
      ) : (
        <FlatList
          data={items}
          keyExtractor={(item) => item.id}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.auric300} />}
          contentContainerStyle={items.length ? styles.list : styles.emptyList}
          renderItem={({ item }) => <FeedRow item={item} tab={tab} onArchive={() => archive(item)} />}
          ListEmptyComponent={<View style={styles.empty}><Ionicons name="notifications-outline" size={30} color="rgba(245,240,255,0.42)" /><Text style={styles.emptyTitle}>Nothing here yet</Text><Text style={styles.emptyText}>{tab === "activity" ? "Account activity will appear here." : "Notifications that need attention will appear here."}</Text></View>}
        />
      )}
    </View>
  );
}

function FeedRow({ item, tab, onArchive }: { item: FeedItem; tab: "notifications" | "activity"; onArchive: () => void }) {
  return (
    <View style={styles.row}>
      <View style={[styles.dot, item.unread && styles.dotUnread]} />
      <View style={styles.rowCopy}>
        <Text style={styles.rowTitle}>{item.title || "Account update"}</Text>
        <Text style={styles.rowDesc} numberOfLines={2}>{item.description || "Open ExaEarn for details."}</Text>
        <Text style={styles.rowMeta}>{item.product || item.category || item.source || "ExaEarn"} - {item.timestamp ? new Date(item.timestamp).toLocaleString() : "Recently"}</Text>
      </View>
      {tab === "notifications" ? (
        <Pressable onPress={onArchive} style={styles.archive} accessibilityRole="button" accessibilityLabel="Archive notification">
          <Ionicons name="archive-outline" size={17} color="rgba(245,240,255,0.68)" />
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.cosmic950, paddingTop: 52 },
  header: { flexDirection: "row", alignItems: "center", gap: 12, paddingHorizontal: 18, paddingBottom: 14 },
  iconButton: { width: 40, height: 40, borderRadius: 14, alignItems: "center", justifyContent: "center", backgroundColor: "rgba(255,255,255,0.06)", borderWidth: 1, borderColor: "rgba(255,255,255,0.1)" },
  headerCopy: { flex: 1 },
  title: { color: colors.violetText, fontSize: 20, fontWeight: "700" },
  subtitle: { color: "rgba(245,240,255,0.58)", fontSize: 12, marginTop: 2 },
  tabs: { flexDirection: "row", marginHorizontal: 18, padding: 4, borderRadius: 18, backgroundColor: "rgba(255,255,255,0.05)", borderWidth: 1, borderColor: "rgba(255,255,255,0.08)" },
  tab: { flex: 1, minHeight: 40, alignItems: "center", justifyContent: "center", borderRadius: 14 },
  tabActive: { backgroundColor: colors.auric300 },
  tabText: { color: "rgba(245,240,255,0.66)", fontSize: 12, fontWeight: "700" },
  tabTextActive: { color: colors.cosmic950 },
  filters: { gap: 8, paddingHorizontal: 18, paddingVertical: 12 },
  filter: { height: 34, paddingHorizontal: 14, borderRadius: 17, alignItems: "center", justifyContent: "center", borderWidth: 1, borderColor: "rgba(255,255,255,0.1)", backgroundColor: "rgba(255,255,255,0.05)" },
  filterActive: { borderColor: colors.auric300, backgroundColor: "rgba(239,199,94,0.12)" },
  filterText: { color: "rgba(245,240,255,0.62)", fontSize: 12, textTransform: "capitalize" },
  filterTextActive: { color: colors.auric300, fontWeight: "700" },
  markRead: { alignSelf: "flex-end", flexDirection: "row", alignItems: "center", gap: 6, margin: 18, minHeight: 36, paddingHorizontal: 12, borderRadius: 12, borderWidth: 1, borderColor: "rgba(239,199,94,0.28)", backgroundColor: "rgba(239,199,94,0.08)" },
  markReadText: { color: colors.auric300, fontSize: 12, fontWeight: "700" },
  disabled: { opacity: 0.45 },
  list: { paddingHorizontal: 18, paddingBottom: 32 },
  emptyList: { flexGrow: 1, justifyContent: "center", paddingHorizontal: 18 },
  row: { flexDirection: "row", alignItems: "center", gap: 12, minHeight: 76, padding: 12, marginBottom: 10, borderRadius: 18, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "rgba(255,255,255,0.045)" },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: "rgba(245,240,255,0.25)" },
  dotUnread: { backgroundColor: colors.auric300 },
  rowCopy: { flex: 1, minWidth: 0 },
  rowTitle: { color: colors.violetText, fontSize: 14, fontWeight: "700" },
  rowDesc: { color: "rgba(245,240,255,0.62)", fontSize: 12, lineHeight: 17, marginTop: 3 },
  rowMeta: { color: "rgba(245,240,255,0.38)", fontSize: 10, marginTop: 5 },
  archive: { width: 36, height: 36, borderRadius: 12, alignItems: "center", justifyContent: "center", backgroundColor: "rgba(255,255,255,0.05)" },
  loading: { flex: 1, alignItems: "center", justifyContent: "center", gap: 10 },
  empty: { alignItems: "center", justifyContent: "center", padding: 24 },
  emptyTitle: { color: colors.violetText, fontSize: 15, fontWeight: "700", marginTop: 10 },
  emptyText: { color: "rgba(245,240,255,0.52)", fontSize: 12, textAlign: "center", marginTop: 4 },
  error: { color: "#fecdd3", fontSize: 13, textAlign: "center" },
  retry: { marginTop: 12, paddingHorizontal: 16, minHeight: 38, borderRadius: 13, alignItems: "center", justifyContent: "center", backgroundColor: colors.auric300 },
  retryText: { color: colors.cosmic950, fontWeight: "700" },
});
