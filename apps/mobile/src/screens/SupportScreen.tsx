import { Ionicons } from "@expo/vector-icons";
import { useEffect, useState } from "react";
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from "react-native";
import { useAuth } from "../context/AuthContext";
import { colors, fonts } from "../theme/colors";

type Ticket = { id: number; ticket_number: string; subject: string; status: string; priority: string; category: string };
type ChatAvailability = { status: string; message?: string; live_chat_enabled?: boolean; queue_available?: boolean };
type ChatMessage = { id: number; message_uuid?: string; sequence: number; sender_type: string; body: string; created_at?: string };
type ChatConversation = { id: number; conversation_number?: string; status: string; messages?: ChatMessage[] };

export default function SupportScreen({ onBack }: { fontsReady?: boolean; onBack: () => void }) {
  const { request } = useAuth();
  const [categories, setCategories] = useState<string[]>(["Account", "Security", "Deposit", "Withdrawal", "Other"]);
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [form, setForm] = useState({ category: "Account", product: "", subject: "", description: "" });
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState("");
  const [chatAvailability, setChatAvailability] = useState<ChatAvailability | null>(null);
  const [chat, setChat] = useState<ChatConversation | null>(null);
  const [chatMessages, setChatMessages] = useState<ChatMessage[]>([]);
  const [chatInput, setChatInput] = useState("");
  const [chatBusy, setChatBusy] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const [meta, rows] = await Promise.all([
        request<{ data?: { categories?: string[] } }>("/api/v1/support/meta"),
        request<{ data?: { data?: Ticket[] } }>("/api/v1/support/tickets"),
      ]);
      const availability = await request<{ data?: ChatAvailability }>("/api/v1/support/chat/availability?source=MOBILE");
      if (meta.data?.categories?.length) setCategories(meta.data.categories);
      setTickets(rows.data?.data || []);
      setChatAvailability(availability.data || null);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Support could not load.");
    } finally {
      setLoading(false);
    }
  };

  const startChat = async () => {
    setChatBusy(true);
    setMessage("");
    try {
      const response = await request<{ data?: ChatConversation }>("/api/v1/support/chat/conversations", {
        method: "POST",
        body: JSON.stringify({ source: "MOBILE", topic: "Mobile support request" }),
      });
      const conversation = response.data || null;
      setChat(conversation);
      setChatMessages(conversation?.messages || []);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Live chat could not be started. Please create a ticket.");
    } finally {
      setChatBusy(false);
    }
  };

  const sendChatMessage = async () => {
    if (!chat || chatInput.trim().length === 0) return;
    setChatBusy(true);
    try {
      const response = await request<{ data?: ChatMessage }>(`/api/v1/support/chat/conversations/${chat.id}/messages`, {
        method: "POST",
        headers: { "Idempotency-Key": `mobile-chat-${chat.id}-${Date.now()}` },
        body: JSON.stringify({ body: chatInput.trim() }),
      });
      if (response.data) setChatMessages((rows) => [...rows, response.data as ChatMessage].sort((a, b) => a.sequence - b.sequence));
      setChatInput("");
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Message could not be sent.");
    } finally {
      setChatBusy(false);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  const submit = async () => {
    setSubmitting(true);
    setMessage("");
    try {
      const response = await request<{ data?: Ticket }>("/api/v1/support/tickets", {
        method: "POST",
        headers: { "Idempotency-Key": `mobile-support-${Date.now()}` },
        body: JSON.stringify({ ...form, source: "MOBILE" }),
      });
      setMessage(`Ticket ${response.data?.ticket_number || ""} created.`);
      setForm({ category: categories[0] || "Other", product: "", subject: "", description: "" });
      await load();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Ticket could not be created.");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <ScrollView style={styles.fill} contentContainerStyle={styles.content}>
      <Pressable onPress={onBack} style={styles.back}><Ionicons name="chevron-back" size={16} color={colors.auric300} /><Text style={styles.backText}>Back</Text></Pressable>
      <Text style={styles.eyebrow}>Support</Text>
      <Text style={styles.title}>How can we help?</Text>
      <Text style={styles.subtitle}>Create a secure ticket and track responses from ExaEarn support operations.</Text>

      <View style={styles.panel}>
        <Text style={styles.sectionTitle}>Live chat</Text>
        <Text style={styles.chatStatus}>{chatAvailability?.status || "Checking availability"}</Text>
        {!chat ? (
          <>
            <Text style={styles.subtitleSmall}>
              {chatAvailability?.status === "ONLINE" || chatAvailability?.status === "BUSY"
                ? "Start a persisted support conversation. Messages are saved and recoverable after reconnect."
                : chatAvailability?.message || "Live support is currently unavailable. You can submit a ticket and we will follow up."}
            </Text>
            <Pressable disabled={chatBusy || !(chatAvailability?.status === "ONLINE" || chatAvailability?.status === "BUSY")} onPress={startChat} style={[styles.secondaryAction, chatBusy || !(chatAvailability?.status === "ONLINE" || chatAvailability?.status === "BUSY") ? styles.dim : null]}>
              {chatBusy ? <ActivityIndicator color={colors.cosmic950} /> : <Text style={styles.secondaryActionText}>Start live chat</Text>}
            </Pressable>
          </>
        ) : (
          <>
            <Text style={styles.subtitleSmall}>{chat.conversation_number || "Support conversation"} · {chat.status}</Text>
            <View style={styles.chatLog}>
              {chatMessages.map((row) => <View key={row.message_uuid || row.id} style={[styles.chatBubble, row.sender_type === "USER" ? styles.chatBubbleUser : null]}><Text style={styles.chatBody}>{row.body}</Text><Text style={styles.chatMeta}>#{row.sequence}</Text></View>)}
            </View>
            <View style={styles.chatInputRow}>
              <TextInput value={chatInput} onChangeText={setChatInput} placeholder="Type your message" placeholderTextColor="rgba(245,240,255,0.38)" style={[styles.input, styles.chatInput]} />
              <Pressable disabled={chatBusy || chatInput.trim().length === 0} onPress={sendChatMessage} style={[styles.sendButton, chatBusy || chatInput.trim().length === 0 ? styles.dim : null]}><Ionicons name="send" size={16} color={colors.cosmic950} /></Pressable>
            </View>
          </>
        )}
      </View>

      <View style={styles.panel}>
        <Text style={styles.sectionTitle}>New ticket</Text>
        <Text style={styles.label}>Category</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
          {categories.map((category) => <Pressable key={category} onPress={() => setForm((v) => ({ ...v, category }))} style={[styles.chip, form.category === category ? styles.chipActive : null]}><Text style={styles.chipText}>{category}</Text></Pressable>)}
        </ScrollView>
        <TextInput value={form.subject} onChangeText={(subject) => setForm((v) => ({ ...v, subject }))} placeholder="Subject" placeholderTextColor="rgba(245,240,255,0.38)" style={styles.input} />
        <TextInput value={form.description} onChangeText={(description) => setForm((v) => ({ ...v, description }))} placeholder="Describe what happened" placeholderTextColor="rgba(245,240,255,0.38)" multiline style={[styles.input, styles.textarea]} />
        {message ? <Text style={styles.message}>{message}</Text> : null}
        <Pressable disabled={submitting || form.subject.length < 4 || form.description.length < 10} onPress={submit} style={[styles.primary, submitting || form.subject.length < 4 || form.description.length < 10 ? styles.dim : null]}>
          {submitting ? <ActivityIndicator color={colors.cosmic950} /> : <Text style={styles.primaryText}>Submit ticket</Text>}
        </Pressable>
      </View>

      <View style={styles.panel}>
        <Text style={styles.sectionTitle}>My tickets</Text>
        {loading ? <ActivityIndicator color={colors.auric300} style={{ marginTop: 16 }} /> : null}
        {tickets.map((ticket) => <View key={ticket.id} style={styles.ticket}><Text style={styles.ticketNumber}>{ticket.ticket_number}</Text><Text style={styles.ticketSubject}>{ticket.subject}</Text><Text style={styles.ticketMeta}>{ticket.category} · {ticket.priority} · {ticket.status}</Text></View>)}
        {!loading && tickets.length === 0 ? <Text style={styles.empty}>No support tickets yet.</Text> : null}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, backgroundColor: colors.cosmic950 },
  content: { padding: 16, paddingBottom: 32 },
  back: { alignSelf: "flex-start", flexDirection: "row", alignItems: "center", gap: 4, borderRadius: 999, borderWidth: 1, borderColor: "rgba(255,255,255,0.12)", paddingHorizontal: 10, paddingVertical: 7 },
  backText: { color: colors.violetText, fontFamily: fonts.semibold, fontSize: 12 },
  eyebrow: { marginTop: 18, color: colors.auric300, fontFamily: fonts.semibold, fontSize: 10, letterSpacing: 1.8, textTransform: "uppercase" },
  title: { marginTop: 6, color: colors.violetText, fontFamily: fonts.display, fontSize: 28 },
  subtitle: { marginTop: 8, color: "rgba(245,240,255,0.68)", fontFamily: fonts.body, fontSize: 13, lineHeight: 20 },
  subtitleSmall: { marginTop: 8, color: "rgba(245,240,255,0.66)", fontFamily: fonts.body, fontSize: 12, lineHeight: 18 },
  panel: { marginTop: 16, borderRadius: 22, borderWidth: 1, borderColor: "rgba(139,167,255,0.14)", backgroundColor: "rgba(10,16,28,0.88)", padding: 14 },
  sectionTitle: { color: colors.violetText, fontFamily: fonts.display, fontSize: 18 },
  label: { marginTop: 14, color: "rgba(245,240,255,0.72)", fontFamily: fonts.semibold, fontSize: 11 },
  chips: { gap: 8, paddingTop: 9 },
  chip: { borderRadius: 999, borderWidth: 1, borderColor: "rgba(139,167,255,0.14)", paddingHorizontal: 11, paddingVertical: 7 },
  chipActive: { borderColor: "rgba(249,226,173,0.42)", backgroundColor: "rgba(249,226,173,0.12)" },
  chipText: { color: colors.violetText, fontFamily: fonts.semibold, fontSize: 10 },
  input: { minHeight: 46, marginTop: 10, borderRadius: 14, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "rgba(255,255,255,0.04)", paddingHorizontal: 12, color: colors.violetText, fontFamily: fonts.body, fontSize: 13 },
  textarea: { minHeight: 110, paddingTop: 12, textAlignVertical: "top" },
  primary: { marginTop: 12, minHeight: 46, alignItems: "center", justifyContent: "center", borderRadius: 14, backgroundColor: colors.auric500 },
  primaryText: { color: colors.cosmic950, fontFamily: fonts.semibold, fontSize: 12 },
  secondaryAction: { marginTop: 12, minHeight: 42, alignItems: "center", justifyContent: "center", borderRadius: 14, backgroundColor: colors.auric500 },
  secondaryActionText: { color: colors.cosmic950, fontFamily: fonts.semibold, fontSize: 12 },
  chatStatus: { marginTop: 8, color: colors.auric300, fontFamily: fonts.semibold, fontSize: 12 },
  chatLog: { marginTop: 12, gap: 8 },
  chatBubble: { alignSelf: "flex-start", maxWidth: "86%", borderRadius: 14, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "rgba(255,255,255,0.04)", padding: 10 },
  chatBubbleUser: { alignSelf: "flex-end", backgroundColor: "rgba(249,226,173,0.16)", borderColor: "rgba(249,226,173,0.34)" },
  chatBody: { color: colors.violetText, fontFamily: fonts.body, fontSize: 12, lineHeight: 17 },
  chatMeta: { marginTop: 4, color: "rgba(245,240,255,0.42)", fontFamily: fonts.body, fontSize: 9 },
  chatInputRow: { marginTop: 10, flexDirection: "row", gap: 8, alignItems: "center" },
  chatInput: { flex: 1, marginTop: 0 },
  sendButton: { height: 44, width: 44, alignItems: "center", justifyContent: "center", borderRadius: 14, backgroundColor: colors.auric500 },
  dim: { opacity: 0.5 },
  message: { marginTop: 10, color: colors.auric300, fontFamily: fonts.body, fontSize: 12 },
  ticket: { marginTop: 10, borderRadius: 16, borderWidth: 1, borderColor: "rgba(255,255,255,0.08)", backgroundColor: "rgba(255,255,255,0.03)", padding: 12 },
  ticketNumber: { color: colors.auric300, fontFamily: fonts.semibold, fontSize: 11 },
  ticketSubject: { marginTop: 4, color: colors.violetText, fontFamily: fonts.semibold, fontSize: 13 },
  ticketMeta: { marginTop: 4, color: "rgba(245,240,255,0.58)", fontFamily: fonts.body, fontSize: 10 },
  empty: { marginTop: 12, color: "rgba(245,240,255,0.58)", fontFamily: fonts.body, fontSize: 12 },
});
