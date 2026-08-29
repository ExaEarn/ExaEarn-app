import { Ionicons } from "@expo/vector-icons";
import type { ReactNode } from "react";
import { useCallback, useEffect, useState } from "react";
import { ActivityIndicator, Image, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import { useAuth } from "../context/AuthContext";
import { colors, fonts } from "../theme/colors";

type Props = { fontsReady: boolean; onBack: () => void };

type NftRow = {
  id: number;
  name?: string;
  symbol?: string;
  utility_type?: string;
  mint_status?: string;
  moderation_status?: string;
  current_value_exa?: string;
  media_url?: string;
};

type ListingRow = {
  id: number;
  price_exa?: string;
  status?: string;
  nft?: NftRow;
};

function unwrap<T>(payload: Record<string, unknown>, key?: string): T[] {
  const data = payload.data;
  if (Array.isArray(data)) return data as T[];
  if (data && typeof data === "object" && key && Array.isArray((data as Record<string, unknown>)[key])) {
    return (data as Record<string, unknown>)[key] as T[];
  }
  return [];
}

function value(value: unknown) {
  const numeric = Number(value || 0);
  return `${numeric.toLocaleString(undefined, { maximumFractionDigits: 2 })} EXA`;
}

export default function NftMarketplaceScreen({ fontsReady, onBack }: Props) {
  const { request } = useAuth();
  const [dashboard, setDashboard] = useState<Record<string, unknown> | null>(null);
  const [listings, setListings] = useState<ListingRow[]>([]);
  const [assets, setAssets] = useState<NftRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setMessage("");
    try {
      const [dash, market, mine] = await Promise.all([
        request<Record<string, unknown>>("/api/nft/dashboard", { method: "GET" }),
        request<Record<string, unknown>>("/api/nft/marketplace", { method: "GET" }),
        request<Record<string, unknown>>("/api/nft/my-assets", { method: "GET" }),
      ]);
      setDashboard((dash.data as Record<string, unknown>) || dash);
      setListings(unwrap<ListingRow>(market));
      setAssets(unwrap<NftRow>(mine));
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "NFT marketplace could not load.");
    } finally {
      setLoading(false);
    }
  }, [request]);

  useEffect(() => { void load(); }, [load]);

  const summary = (dashboard?.summary || {}) as Record<string, unknown>;

  return (
    <SafeAreaView style={styles.safe}>
      <ScrollView contentContainerStyle={styles.content} refreshControl={<RefreshControl refreshing={loading} onRefresh={load} tintColor={colors.auric300} />}>
        <View style={styles.header}>
          <Pressable onPress={onBack} style={styles.backButton} accessibilityRole="button">
            <Ionicons name="chevron-back" size={18} color={colors.auric300} />
            <Text style={styles.backText}>Back</Text>
          </Pressable>
          <Text style={styles.title}>NFT Marketplace</Text>
          <Text style={styles.subtitle}>Collections, listings, owned NFTs, and utility access connected to your ExaEarn account.</Text>
          {!fontsReady ? <Text style={styles.meta}>Loading brand fonts...</Text> : null}
        </View>

        <View style={styles.summaryGrid}>
          <Metric label="Assets" value={String(summary.active_positions ?? assets.length)} />
          <Metric label="Listings" value={String(summary.active_listings ?? listings.length)} />
          <Metric label="Value" value={value(summary.total_assets_exa)} />
        </View>

        {message ? <Text style={styles.message}>{message}</Text> : null}
        {loading ? <ActivityIndicator color={colors.auric300} /> : null}

        <Section title="Marketplace">
          {listings.slice(0, 8).map((listing) => (
            <AnimatedPressable key={listing.id} style={styles.row}>
              {listing.nft?.media_url ? <Image source={{ uri: listing.nft.media_url }} style={styles.mediaThumb} /> : <View style={styles.iconBubble}><Ionicons name="diamond-outline" size={18} color={colors.auric300} /></View>}
              <View style={styles.rowCopy}>
                <Text style={styles.rowTitle}>{listing.nft?.name || "NFT listing"}</Text>
                <Text style={styles.meta}>{listing.nft?.utility_type || "utility"} - {listing.status || "active"}</Text>
              </View>
              <Text style={styles.price}>{value(listing.price_exa)}</Text>
            </AnimatedPressable>
          ))}
          {!listings.length ? <Text style={styles.empty}>No active NFT listings are available.</Text> : null}
        </Section>

        <Section title="My NFTs">
          {assets.slice(0, 8).map((asset) => (
            <View key={asset.id} style={styles.row}>
              {asset.media_url ? <Image source={{ uri: asset.media_url }} style={styles.mediaThumb} /> : <View style={styles.iconBubble}><Ionicons name="albums-outline" size={18} color={colors.auric300} /></View>}
              <View style={styles.rowCopy}>
                <Text style={styles.rowTitle}>{asset.name || asset.symbol || "NFT asset"}</Text>
                <Text style={styles.meta}>{asset.mint_status || "PENDING"} - {asset.moderation_status || "PENDING"}</Text>
              </View>
              <Text style={styles.price}>{value(asset.current_value_exa)}</Text>
            </View>
          ))}
          {!assets.length ? <Text style={styles.empty}>No NFTs in this account yet.</Text> : null}
        </Section>

        <View style={styles.notice}>
          <Ionicons name="shield-checkmark-outline" size={17} color="#86efac" />
          <Text style={styles.noticeText}>Purchases use canonical reservations and settlement. Real chain operations remain provider-configured.</Text>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <View style={styles.section}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {children}
    </View>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.metric}>
      <Text style={styles.metricLabel}>{label}</Text>
      <Text style={styles.metricValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.cosmic950 },
  content: { padding: 16, paddingBottom: 34, gap: 14 },
  header: { gap: 8 },
  backButton: { flexDirection: "row", alignItems: "center", gap: 6, alignSelf: "flex-start" },
  backText: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 12 },
  title: { color: "#ffffff", fontFamily: fonts.display, fontSize: 27 },
  subtitle: { color: "rgba(245,240,255,0.68)", fontFamily: fonts.body, fontSize: 12, lineHeight: 18 },
  meta: { color: "rgba(245,240,255,0.56)", fontFamily: fonts.body, fontSize: 11 },
  summaryGrid: { flexDirection: "row", gap: 8 },
  metric: { flex: 1, borderRadius: 16, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "rgba(255,255,255,0.045)", padding: 12 },
  metricLabel: { color: "rgba(245,240,255,0.58)", fontFamily: fonts.body, fontSize: 10 },
  metricValue: { marginTop: 4, color: "#ffffff", fontFamily: fonts.semibold, fontSize: 13 },
  section: { borderRadius: 20, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "rgba(12,17,30,0.78)", padding: 12, gap: 8 },
  sectionTitle: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 15 },
  row: { minHeight: 58, flexDirection: "row", alignItems: "center", gap: 10, borderRadius: 14, backgroundColor: "rgba(255,255,255,0.04)", padding: 10 },
  iconBubble: { width: 34, height: 34, alignItems: "center", justifyContent: "center", borderRadius: 13, backgroundColor: "rgba(249,226,173,0.1)" },
  mediaThumb: { width: 38, height: 38, borderRadius: 13, backgroundColor: "rgba(255,255,255,0.06)" },
  rowCopy: { flex: 1, minWidth: 0 },
  rowTitle: { color: "#ffffff", fontFamily: fonts.semibold, fontSize: 12 },
  price: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 11 },
  empty: { color: "rgba(245,240,255,0.56)", fontFamily: fonts.body, fontSize: 12, lineHeight: 18 },
  message: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 12 },
  notice: { flexDirection: "row", alignItems: "flex-start", gap: 9, borderRadius: 16, borderWidth: 1, borderColor: "rgba(134,239,172,0.2)", backgroundColor: "rgba(34,197,94,0.08)", padding: 12 },
  noticeText: { flex: 1, color: "rgba(220,252,231,0.82)", fontFamily: fonts.body, fontSize: 11, lineHeight: 17 },
});
