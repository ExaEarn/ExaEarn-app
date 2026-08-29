import { Ionicons } from "@expo/vector-icons";
import { useCallback, useEffect, useMemo, useState } from "react";
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, TextInput, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import { AnimatedPressable } from "../components/AnimatedPressable";
import { useAuth } from "../context/AuthContext";
import { colors, fonts } from "../theme/colors";
import {
  completeExaSkillsLesson,
  enrollExaSkillsCourse,
  ExaSkillsCourse,
  fetchExaSkillsCourse,
  fetchExaSkillsCourses,
  fetchExaSkillsDashboard,
  fetchExaSkillsHome,
  fetchExaSkillsSubscription,
  purchaseExaSkillsCourse,
  subscribeExaSkills,
} from "../services/exaSkillsApi";

type Props = { fontsReady: boolean; onBack: () => void };

function money(value: unknown, asset = "USDT") {
  const amount = Number(value || 0);
  return `${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} ${asset}`;
}

export default function ExaSkillsScreen({ fontsReady, onBack }: Props) {
  const { request } = useAuth();
  const [courses, setCourses] = useState<ExaSkillsCourse[]>([]);
  const [selected, setSelected] = useState<ExaSkillsCourse | null>(null);
  const [dashboard, setDashboard] = useState<Record<string, unknown> | null>(null);
  const [subscription, setSubscription] = useState<Record<string, unknown> | null>(null);
  const [query, setQuery] = useState("");
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);
  const [activeLesson, setActiveLesson] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setMessage("");
    try {
      const [courseRows, dash, sub] = await Promise.all([
        fetchExaSkillsCourses(request, query),
        fetchExaSkillsDashboard(request),
        fetchExaSkillsSubscription(request),
        fetchExaSkillsHome(request),
      ]);
      setCourses(courseRows);
      setDashboard((dash as { data?: Record<string, unknown> }).data || null);
      setSubscription((sub as { data?: Record<string, unknown> }).data || null);
      if (!selected && courseRows[0]) setSelected(await fetchExaSkillsCourse(request, courseRows[0].slug || courseRows[0].id) || courseRows[0]);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "ExaSkills could not load.");
    } finally {
      setLoading(false);
    }
  }, [query, request, selected]);

  useEffect(() => { void load(); }, [load]);

  const openCourse = async (course: ExaSkillsCourse) => {
    setSelected(course);
    setMessage("");
    try {
      setSelected(await fetchExaSkillsCourse(request, course.slug || course.id) || course);
    } catch {
      setMessage("Course detail is temporarily unavailable.");
    }
  };

  const lessons = selected?.lessons || [];
  const currentLesson = useMemo(() => lessons.find((lesson) => lesson.id === activeLesson) || lessons[0], [activeLesson, lessons]);
  const overview = (dashboard?.overview || {}) as Record<string, unknown>;
  const credentials = (dashboard?.credentials || []) as Array<Record<string, unknown>>;

  const enrollOrBuy = async () => {
    if (!selected) return;
    try {
      if (Number(selected.price || 0) > 0) await purchaseExaSkillsCourse(request, selected.slug || selected.id);
      else await enrollExaSkillsCourse(request, selected.slug || selected.id);
      setMessage("Access confirmed. Progress will sync to your ExaEarn account.");
      await load();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Access could not be confirmed.");
    }
  };

  const completeLesson = async () => {
    if (!selected || !currentLesson?.id) return;
    try {
      await completeExaSkillsLesson(request, selected.slug || selected.id, currentLesson.id, Number(currentLesson.duration_seconds || 0));
      setMessage("Lesson progress saved.");
      await load();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Progress could not be saved.");
    }
  };

  return (
    <SafeAreaView style={styles.safe}>
      <ScrollView contentContainerStyle={styles.content} refreshControl={<RefreshControl refreshing={loading} onRefresh={load} tintColor={colors.auric300} />}>
        <View style={styles.header}>
          <Pressable onPress={onBack} style={styles.backButton} accessibilityRole="button">
            <Ionicons name="chevron-back" size={18} color={colors.auric300} />
            <Text style={styles.backText}>Back</Text>
          </Pressable>
          <Text style={styles.title}>ExaSkills</Text>
          <Text style={styles.subtitle}>Courses, credentials, challenges, opportunities, and subscriptions from your ExaEarn account.</Text>
          {!fontsReady ? <Text style={styles.meta}>Loading brand fonts...</Text> : null}
        </View>

        <View style={styles.summaryGrid}>
          <Metric label="In progress" value={String(overview.courses_in_progress || 0)} />
          <Metric label="Completed" value={String(overview.courses_completed || 0)} />
          <Metric label="Credentials" value={String(overview.credentials_earned || credentials.length || 0)} />
        </View>

        <View style={styles.subscriptionCard}>
          <View>
            <Text style={styles.sectionTitle}>Subscription</Text>
            <Text style={styles.meta}>{subscription?.status ? `${subscription.status} - ${subscription.plan_code}` : "No active subscription"}</Text>
          </View>
          <AnimatedPressable style={styles.smallButton} onPress={() => subscribeExaSkills(request, "INDIVIDUAL").then(load).catch((error) => setMessage(error instanceof Error ? error.message : "Subscription failed."))}>
            <Text style={styles.smallButtonText}>Subscribe</Text>
          </AnimatedPressable>
        </View>

        <View style={styles.searchBox}>
          <Ionicons name="search-outline" size={16} color={colors.auric300} />
          <TextInput value={query} onChangeText={setQuery} onSubmitEditing={() => void load()} placeholder="Search courses" placeholderTextColor="rgba(245,240,255,0.42)" style={styles.input} />
        </View>
        {message ? <Text style={styles.message}>{message}</Text> : null}
        {loading ? <ActivityIndicator color={colors.auric300} /> : null}

        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.courseRow}>
          {courses.map((course) => (
            <AnimatedPressable key={String(course.id)} style={[styles.courseCard, selected?.id === course.id && styles.courseCardActive]} onPress={() => openCourse(course)}>
              <Text style={styles.badge}>{course.difficulty || "course"}</Text>
              <Text style={styles.cardTitle}>{course.title || "Untitled course"}</Text>
              <Text style={styles.cardBody} numberOfLines={3}>{course.description || "No course description."}</Text>
              <Text style={styles.price}>{money(course.price, course.settlement_asset || "USDT")}</Text>
            </AnimatedPressable>
          ))}
        </ScrollView>

        {selected ? (
          <View style={styles.detail}>
            <Text style={styles.sectionTitle}>{selected.title}</Text>
            <Text style={styles.detailText}>{selected.description}</Text>
            <AnimatedPressable style={styles.primaryButton} onPress={enrollOrBuy}>
              <Text style={styles.primaryText}>{Number(selected.price || 0) > 0 ? "Review & Purchase" : "Enroll Free"}</Text>
            </AnimatedPressable>
            <Text style={styles.sectionTitle}>Course Player</Text>
            <View style={styles.lessonTabs}>
              {lessons.map((lesson) => (
                <Pressable key={lesson.id} onPress={() => setActiveLesson(lesson.id)} style={[styles.lessonPill, currentLesson?.id === lesson.id && styles.lessonPillActive]}>
                  <Text style={styles.lessonPillText}>{lesson.order_index || lesson.id}</Text>
                </Pressable>
              ))}
            </View>
            {currentLesson ? (
              <View style={styles.lessonPanel}>
                <Text style={styles.cardTitle}>{currentLesson.title}</Text>
                <Text style={styles.detailText}>{currentLesson.content || "This lesson uses attached media/resources."}</Text>
                <AnimatedPressable style={styles.secondaryButton} onPress={completeLesson}><Text style={styles.secondaryText}>Mark Complete</Text></AnimatedPressable>
              </View>
            ) : <Text style={styles.empty}>No lessons are available yet.</Text>}
          </View>
        ) : null}

        <View style={styles.detail}>
          <Text style={styles.sectionTitle}>Credentials</Text>
          {credentials.map((credential) => <Text key={String(credential.id)} style={styles.listItem}>{String(credential.title || "Credential")} - {String(credential.status || "verified")}</Text>)}
          {!credentials.length ? <Text style={styles.empty}>No credentials yet.</Text> : null}
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return <View style={styles.metric}><Text style={styles.metricValue}>{value}</Text><Text style={styles.meta}>{label}</Text></View>;
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.void900 },
  content: { padding: 18, gap: 16, paddingBottom: 38 },
  header: { gap: 8 },
  backButton: { flexDirection: "row", alignItems: "center", gap: 4, alignSelf: "flex-start", paddingVertical: 6 },
  backText: { color: colors.auric300, fontFamily: fonts.medium },
  title: { color: colors.textPrimary, fontFamily: fonts.heading, fontSize: 28 },
  subtitle: { color: colors.textSecondary, fontFamily: fonts.regular, lineHeight: 20 },
  meta: { color: colors.textMuted, fontFamily: fonts.regular, fontSize: 12 },
  summaryGrid: { flexDirection: "row", gap: 10 },
  metric: { flex: 1, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, borderRadius: 16, padding: 12 },
  metricValue: { color: colors.textPrimary, fontFamily: fonts.heading, fontSize: 20 },
  subscriptionCard: { borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, borderRadius: 18, padding: 14, flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
  searchBox: { flexDirection: "row", alignItems: "center", gap: 8, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, borderRadius: 14, paddingHorizontal: 12, minHeight: 46 },
  input: { color: colors.textPrimary, flex: 1, fontFamily: fonts.regular },
  message: { color: colors.auric300, fontFamily: fonts.medium, fontSize: 12 },
  courseRow: { gap: 12, paddingRight: 20 },
  courseCard: { width: 210, minHeight: 160, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, borderRadius: 18, padding: 14, gap: 8 },
  courseCardActive: { borderColor: colors.auric300 },
  badge: { color: colors.auric300, fontFamily: fonts.medium, fontSize: 11, textTransform: "uppercase" },
  cardTitle: { color: colors.textPrimary, fontFamily: fonts.heading, fontSize: 16 },
  cardBody: { color: colors.textSecondary, fontFamily: fonts.regular, fontSize: 12, lineHeight: 18 },
  price: { color: colors.textPrimary, fontFamily: fonts.medium, marginTop: "auto" },
  detail: { borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, borderRadius: 18, padding: 16, gap: 12 },
  detailText: { color: colors.textSecondary, fontFamily: fonts.regular, lineHeight: 20 },
  sectionTitle: { color: colors.textPrimary, fontFamily: fonts.heading, fontSize: 18 },
  primaryButton: { backgroundColor: colors.auric300, borderRadius: 14, paddingVertical: 13, alignItems: "center" },
  primaryText: { color: "#120f08", fontFamily: fonts.heading },
  smallButton: { backgroundColor: colors.auric300, borderRadius: 12, paddingHorizontal: 12, paddingVertical: 9 },
  smallButtonText: { color: "#120f08", fontFamily: fonts.heading, fontSize: 12 },
  secondaryButton: { borderWidth: 1, borderColor: colors.auric300, borderRadius: 14, paddingVertical: 12, alignItems: "center" },
  secondaryText: { color: colors.auric300, fontFamily: fonts.heading },
  lessonTabs: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  lessonPill: { width: 34, height: 34, borderRadius: 17, borderWidth: 1, borderColor: colors.border, alignItems: "center", justifyContent: "center" },
  lessonPillActive: { backgroundColor: "rgba(244,197,94,0.18)", borderColor: colors.auric300 },
  lessonPillText: { color: colors.textPrimary, fontFamily: fonts.medium },
  lessonPanel: { gap: 10 },
  listItem: { color: colors.textSecondary, fontFamily: fonts.regular, borderTopWidth: 1, borderTopColor: colors.border, paddingTop: 10 },
  empty: { color: colors.textMuted, fontFamily: fonts.regular },
});
