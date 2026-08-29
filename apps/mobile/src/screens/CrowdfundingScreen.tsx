import { Ionicons } from "@expo/vector-icons";
import { useCallback, useEffect, useMemo, useState } from "react";
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, TextInput, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import { useAuth } from "../context/AuthContext";
import { colors, fonts } from "../theme/colors";

type Props = {
  fontsReady: boolean;
  onBack: () => void;
  onOpenSupport?: () => void;
};

type Campaign = {
  id: number | string;
  title?: string;
  summary?: string;
  description?: string;
  category?: string;
  classification?: string;
  status?: string;
  asset?: string;
  funding_goal?: string | number;
  raised_amount?: string | number;
  milestones?: Array<Record<string, unknown>>;
  updates?: Array<Record<string, unknown>>;
};

function rows(payload: unknown): Campaign[] {
  const data = (payload as { data?: unknown })?.data;
  if (Array.isArray((data as { data?: unknown })?.data)) return (data as { data: Campaign[] }).data;
  if (Array.isArray(data)) return data as Campaign[];
  return [];
}

export default function CrowdfundingScreen({ fontsReady, onBack, onOpenSupport }: Props) {
  const { request } = useAuth();
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [selected, setSelected] = useState<Campaign | null>(null);
  const [history, setHistory] = useState<Array<Record<string, unknown>>>([]);
  const [comments, setComments] = useState<Array<Record<string, unknown>>>([]);
  const [query, setQuery] = useState("");
  const [amount, setAmount] = useState("25");
  const [comment, setComment] = useState("");
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setMessage("");
    try {
      const [campaignPayload, historyPayload] = await Promise.all([
        request("/api/crowdfunding/campaigns?per_page=30"),
        request("/api/crowdfunding/backer/dashboard"),
      ]);
      const list = rows(campaignPayload);
      setCampaigns(list);
      setSelected((current) => current || list[0] || null);
      const pledgeRows = (((historyPayload as { data?: { pledges?: { data?: unknown[] } } })?.data?.pledges?.data) || []) as Array<Record<string, unknown>>;
      setHistory(pledgeRows);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Crowdfunding could not load.");
    } finally {
      setLoading(false);
    }
  }, [request]);

  const loadComments = useCallback(async (campaignId: string | number) => {
    try {
      const payload = await request(`/api/crowdfunding/campaigns/${campaignId}/comments`);
      const data = (payload as { data?: { data?: unknown[] } })?.data;
      setComments((Array.isArray(data?.data) ? data.data : []) as Array<Record<string, unknown>>);
    } catch {
      setComments([]);
    }
  }, [request]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (selected?.id) void loadComments(selected.id);
  }, [loadComments, selected?.id]);

  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (!needle) return campaigns;
    return campaigns.filter((campaign) => `${campaign.title || ""} ${campaign.category || ""} ${campaign.status || ""}`.toLowerCase().includes(needle));
  }, [campaigns, query]);

  const pledge = async () => {
    if (!selected) return;
    setMessage("");
    try {
      await request(`/api/crowdfunding/campaigns/${selected.id}/pledges`, {
        method: "POST",
        headers: { "Idempotency-Key": `mobile-crowdfunding-${selected.id}-${Date.now()}` },
        body: JSON.stringify({ amount }),
      });
      setMessage("Pledge confirmed and held in escrow.");
      await load();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Pledge failed.");
    }
  };

  const postComment = async () => {
    if (!selected || !comment.trim()) return;
    try {
      await request(`/api/crowdfunding/campaigns/${selected.id}/comments`, {
        method: "POST",
        body: JSON.stringify({ body: comment.trim(), type: comment.trim().endsWith("?") ? "QUESTION" : "COMMENT" }),
      });
      setComment("");
      await loadComments(selected.id);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Comment failed.");
    }
  };

  const progress = selected ? Math.min((Number(selected.raised_amount || 0) / Math.max(Number(selected.funding_goal || 1), 1)) * 100, 100) : 0;

  return (
    <SafeAreaView style={styles.safe}>
      <ScrollView contentContainerStyle={styles.content} refreshControl={<RefreshControl refreshing={loading} onRefresh={load} tintColor={colors.auric300} />}>
        <View style={styles.header}>
          <Pressable onPress={onBack} style={styles.backButton} accessibilityRole="button">
            <Ionicons name="chevron-back" size={18} color={colors.auric300} />
            <Text style={styles.backText}>Back</Text>
          </Pressable>
          <Text style={styles.title}>Crowdfunding</Text>
          <Text style={styles.subtitle}>Discover non-investment campaigns, pledge through ExaEarn escrow, and track updates.</Text>
          {!fontsReady ? <Text style={styles.meta}>Loading brand fonts...</Text> : null}
        </View>

        <View style={styles.searchBox}>
          <Ionicons name="search-outline" size={16} color={colors.auric300} />
          <TextInput value={query} onChangeText={setQuery} placeholder="Search campaigns" placeholderTextColor="rgba(245,240,255,0.42)" style={styles.input} />
        </View>

        {message ? <Text style={styles.message}>{message}</Text> : null}
        {loading ? <ActivityIndicator color={colors.auric300} /> : null}

        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.campaignRow}>
          {filtered.map((campaign) => (
            <AnimatedPressable key={String(campaign.id)} onPress={() => setSelected(campaign)} style={[styles.campaignCard, selected?.id === campaign.id ? styles.campaignCardActive : null]}>
              <Text style={styles.badge}>{campaign.status || "LIVE"}</Text>
              <Text style={styles.cardTitle}>{campaign.title || "Untitled campaign"}</Text>
              <Text style={styles.cardBody} numberOfLines={3}>{campaign.summary || campaign.description || "No campaign summary."}</Text>
              <Text style={styles.cardMeta}>{campaign.raised_amount || 0} / {campaign.funding_goal || 0} {campaign.asset || "USDT"}</Text>
            </AnimatedPressable>
          ))}
          {!filtered.length ? <Text style={styles.empty}>No campaigns found.</Text> : null}
        </ScrollView>

        {selected ? (
          <View style={styles.detail}>
            <Text style={styles.sectionTitle}>{selected.title}</Text>
            <Text style={styles.detailText}>{selected.description || selected.summary || "No campaign detail yet."}</Text>
            <View style={styles.progressTrack}><View style={[styles.progressFill, { width: `${progress}%` }]} /></View>
            <Text style={styles.meta}>{progress.toFixed(1)}% funded - {selected.classification || "PROJECT_SUPPORT"} - {selected.status}</Text>

            <View style={styles.pledgeBox}>
              <Text style={styles.sectionTitle}>Pledge</Text>
              <TextInput value={amount} onChangeText={setAmount} keyboardType="decimal-pad" style={styles.amountInput} placeholder="Amount" placeholderTextColor="rgba(245,240,255,0.42)" />
              <AnimatedPressable onPress={pledge} style={styles.primaryButton}><Text style={styles.primaryText}>Confirm Pledge</Text></AnimatedPressable>
            </View>

            <InfoList title="Milestones" rows={selected.milestones || []} empty="No milestones submitted." />
            <InfoList title="Updates" rows={selected.updates || []} empty="No campaign updates yet." />

            <View style={styles.pledgeBox}>
              <Text style={styles.sectionTitle}>Comments & Questions</Text>
              <TextInput value={comment} onChangeText={setComment} style={styles.commentInput} placeholder="Ask a question or leave a comment" placeholderTextColor="rgba(245,240,255,0.42)" multiline />
              <AnimatedPressable onPress={postComment} style={styles.secondaryButton}><Text style={styles.secondaryText}>Post</Text></AnimatedPressable>
              {comments.map((item) => <Text key={String(item.id)} style={styles.commentText}>{String(item.body || "")}</Text>)}
              {!comments.length ? <Text style={styles.empty}>No comments yet.</Text> : null}
            </View>
          </View>
        ) : null}

        <InfoList title="My Contributions" rows={history} empty="No contributions yet." />
        <AnimatedPressable onPress={onOpenSupport} style={styles.supportButton}><Text style={styles.secondaryText}>Need help with a campaign?</Text></AnimatedPressable>
      </ScrollView>
    </SafeAreaView>
  );
}

function InfoList({ title, rows, empty }: { title: string; rows: Array<Record<string, unknown>>; empty: string }) {
  return (
    <View style={styles.listBox}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {rows.map((row, index) => {
        const campaign = row.campaign && typeof row.campaign === "object" ? row.campaign as Record<string, unknown> : null;
        return (
          <View key={String(row.id || index)} style={styles.listItem}>
            <Text style={styles.listTitle}>{String(row.title || row.status || campaign?.title || `Item ${index + 1}`)}</Text>
            <Text style={styles.meta}>{String(row.amount || row.body || row.description || row.asset || "")}</Text>
          </View>
        );
      })}
      {!rows.length ? <Text style={styles.empty}>{empty}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: "#060914" },
  content: { padding: 16, paddingBottom: 36, gap: 14 },
  header: { borderRadius: 24, borderWidth: 1, borderColor: "rgba(249,226,173,0.22)", backgroundColor: "rgba(15,20,35,0.88)", padding: 16 },
  backButton: { flexDirection: "row", alignItems: "center", gap: 6, marginBottom: 12 },
  backText: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 12 },
  title: { color: colors.violetText, fontFamily: fonts.display, fontSize: 28 },
  subtitle: { color: "rgba(245,240,255,0.68)", fontFamily: fonts.body, fontSize: 13, lineHeight: 19, marginTop: 6 },
  searchBox: { minHeight: 48, borderRadius: 16, borderWidth: 1, borderColor: "rgba(249,226,173,0.18)", backgroundColor: "rgba(255,255,255,0.05)", paddingHorizontal: 12, flexDirection: "row", alignItems: "center", gap: 8 },
  input: { flex: 1, color: colors.violetText, fontFamily: fonts.body, fontSize: 13 },
  campaignRow: { gap: 12, paddingRight: 12 },
  campaignCard: { width: 238, minHeight: 156, borderRadius: 22, borderWidth: 1, borderColor: "rgba(196,181,253,0.18)", backgroundColor: "rgba(15,20,35,0.88)", padding: 14 },
  campaignCardActive: { borderColor: "rgba(249,226,173,0.68)", backgroundColor: "rgba(249,226,173,0.08)" },
  badge: { alignSelf: "flex-start", borderRadius: 999, borderWidth: 1, borderColor: "rgba(249,226,173,0.35)", color: colors.auric300, fontFamily: fonts.semibold, fontSize: 10, paddingHorizontal: 8, paddingVertical: 4 },
  cardTitle: { color: colors.violetText, fontFamily: fonts.display, fontSize: 16, marginTop: 10 },
  cardBody: { color: "rgba(245,240,255,0.62)", fontFamily: fonts.body, fontSize: 12, lineHeight: 17, marginTop: 6 },
  cardMeta: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 11, marginTop: 10 },
  detail: { borderRadius: 24, borderWidth: 1, borderColor: "rgba(249,226,173,0.22)", backgroundColor: "rgba(15,20,35,0.88)", padding: 16, gap: 12 },
  sectionTitle: { color: colors.violetText, fontFamily: fonts.display, fontSize: 18 },
  detailText: { color: "rgba(245,240,255,0.66)", fontFamily: fonts.body, fontSize: 13, lineHeight: 19 },
  progressTrack: { height: 8, borderRadius: 999, backgroundColor: "rgba(255,255,255,0.08)", overflow: "hidden" },
  progressFill: { height: "100%", borderRadius: 999, backgroundColor: colors.auric300 },
  pledgeBox: { borderRadius: 18, borderWidth: 1, borderColor: "rgba(196,181,253,0.16)", backgroundColor: "rgba(255,255,255,0.04)", padding: 12, gap: 10 },
  amountInput: { minHeight: 46, borderRadius: 14, borderWidth: 1, borderColor: "rgba(196,181,253,0.2)", color: colors.violetText, paddingHorizontal: 12 },
  commentInput: { minHeight: 72, borderRadius: 14, borderWidth: 1, borderColor: "rgba(196,181,253,0.2)", color: colors.violetText, padding: 12, textAlignVertical: "top" },
  primaryButton: { minHeight: 46, borderRadius: 15, backgroundColor: colors.auric300, alignItems: "center", justifyContent: "center" },
  primaryText: { color: "#111827", fontFamily: fonts.semibold, fontSize: 13 },
  secondaryButton: { minHeight: 42, borderRadius: 14, borderWidth: 1, borderColor: "rgba(249,226,173,0.35)", alignItems: "center", justifyContent: "center" },
  secondaryText: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 12 },
  supportButton: { minHeight: 44, borderRadius: 16, borderWidth: 1, borderColor: "rgba(249,226,173,0.28)", alignItems: "center", justifyContent: "center" },
  listBox: { borderRadius: 22, borderWidth: 1, borderColor: "rgba(196,181,253,0.16)", backgroundColor: "rgba(15,20,35,0.88)", padding: 14, gap: 10 },
  listItem: { borderRadius: 14, backgroundColor: "rgba(255,255,255,0.05)", padding: 10 },
  listTitle: { color: colors.violetText, fontFamily: fonts.semibold, fontSize: 13 },
  commentText: { color: "rgba(245,240,255,0.72)", fontFamily: fonts.body, fontSize: 12, lineHeight: 18, borderTopWidth: 1, borderTopColor: "rgba(255,255,255,0.08)", paddingTop: 8 },
  meta: { color: "rgba(245,240,255,0.5)", fontFamily: fonts.body, fontSize: 11 },
  message: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 12, lineHeight: 18 },
  empty: { color: "rgba(245,240,255,0.48)", fontFamily: fonts.body, fontSize: 12 },
});
