import { useEffect, useMemo, useRef, useState } from "react";
import { ArrowLeft, LifeBuoy, MessageCircle, RefreshCcw, Send, Shield, Ticket, WifiOff } from "lucide-react";

function nowLabel(value) {
  const date = value ? new Date(value) : new Date();
  return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

function normalizeMessages(rows = []) {
  return rows.map((message) => ({
    id: message.message_uuid || message.id,
    sequence: Number(message.sequence || 0),
    sender: message.sender_type,
    body: message.body,
    createdAt: message.created_at,
  }));
}

function LiveSupportChat({ request, onBack, onOpenTicketCenter }) {
  const [availability, setAvailability] = useState(null);
  const [conversation, setConversation] = useState(null);
  const [messages, setMessages] = useState([]);
  const [inputValue, setInputValue] = useState("");
  const [loading, setLoading] = useState(true);
  const [starting, setStarting] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState("");
  const chatBodyRef = useRef(null);

  const canStart = availability?.status === "ONLINE" || availability?.status === "BUSY";
  const lastSequence = useMemo(() => messages.reduce((max, row) => Math.max(max, row.sequence || 0), 0), [messages]);

  const loadAvailability = async () => {
    setLoading(true);
    setError("");
    try {
      const response = await request("/api/v1/support/chat/availability?source=WEB");
      setAvailability(response?.data || response);
    } catch (err) {
      setError(err?.message || "Live support availability could not be checked.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void loadAvailability();
  }, []);

  useEffect(() => {
    if (!chatBodyRef.current) return;
    chatBodyRef.current.scrollTo({ top: chatBodyRef.current.scrollHeight, behavior: "smooth" });
  }, [messages]);

  const startChat = async () => {
    setStarting(true);
    setError("");
    try {
      const response = await request("/api/v1/support/chat/conversations", {
        method: "POST",
        body: JSON.stringify({ source: "WEB", topic: "Support request" }),
      });
      const chat = response?.data || response;
      setConversation(chat);
      setMessages(normalizeMessages(chat.messages || []));
    } catch (err) {
      setError(err?.message || "Live chat could not be started. Please create a support ticket.");
    } finally {
      setStarting(false);
    }
  };

  const replay = async () => {
    if (!conversation?.id) return;
    setError("");
    try {
      const response = await request(`/api/v1/support/chat/conversations/${conversation.id}/replay?after_sequence=${lastSequence}`);
      const rows = normalizeMessages(response?.data?.messages || []);
      setMessages((current) => {
        const seen = new Set(current.map((message) => message.id));
        return [...current, ...rows.filter((message) => !seen.has(message.id))].sort((a, b) => a.sequence - b.sequence);
      });
    } catch (err) {
      setError(err?.message || "Messages could not be refreshed.");
    }
  };

  const sendMessage = async () => {
    const body = inputValue.trim();
    if (!body || !conversation?.id || sending) return;
    setSending(true);
    setError("");
    try {
      const response = await request(`/api/v1/support/chat/conversations/${conversation.id}/messages`, {
        method: "POST",
        headers: { "Idempotency-Key": `web-chat-${conversation.id}-${Date.now()}` },
        body: JSON.stringify({ body }),
      });
      const message = normalizeMessages([response?.data || response])[0];
      setMessages((current) => [...current, message].sort((a, b) => a.sequence - b.sequence));
      setInputValue("");
    } catch (err) {
      setError(err?.message || "Message could not be sent.");
    } finally {
      setSending(false);
    }
  };

  const endChat = async () => {
    if (!conversation?.id) return;
    try {
      await request(`/api/v1/support/chat/conversations/${conversation.id}/end`, { method: "POST" });
      setConversation((current) => (current ? { ...current, status: "ENDED" } : current));
      await replay();
    } catch (err) {
      setError(err?.message || "Conversation could not be ended.");
    }
  };

  const unavailableMessage =
    availability?.message || "Live support is currently unavailable. You can submit a support ticket and we will follow up.";

  if (!conversation) {
    return (
      <main className="min-h-[100dvh] bg-[#070B14] px-4 py-5 text-white sm:px-6">
        <section className="mx-auto flex w-full max-w-3xl flex-col gap-5">
          <button type="button" onClick={onBack} className="inline-flex w-fit items-center gap-2 rounded-full border border-white/15 px-3 py-2 text-sm text-[#D7DDEA]">
            <ArrowLeft className="h-4 w-4" /> Back
          </button>

          <article className="rounded-3xl border border-white/10 bg-[#101827] p-5 shadow-[0_18px_50px_rgba(0,0,0,0.28)]">
            <div className="flex items-start gap-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-2xl border border-[#D4AF37]/30 bg-[#D4AF37]/10">
                <MessageCircle className="h-6 w-6 text-[#D4AF37]" />
              </div>
              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#D4AF37]">Support</p>
                <h1 className="mt-2 text-2xl font-semibold text-[#F8F1DE]">Chat with ExaEarn Support</h1>
                <p className="mt-2 text-sm leading-6 text-[#AAB4C4]">
                  Start a persisted support conversation when live chat is enabled. If the team is offline, ticket support remains available.
                </p>
              </div>
            </div>

            {loading ? (
              <div className="mt-5 rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-sm text-[#AAB4C4]">Checking availability...</div>
            ) : canStart ? (
              <div className="mt-5 rounded-2xl border border-[#22C55E]/25 bg-[#22C55E]/10 p-4">
                <p className="font-semibold text-[#B8F7CE]">Live chat is {availability.status === "BUSY" ? "busy but accepting the queue" : "online"}.</p>
                <p className="mt-1 text-sm text-[#AAB4C4]">Your transcript is saved, ordered and recoverable if the connection drops.</p>
              </div>
            ) : (
              <div className="mt-5 rounded-2xl border border-[#F59E0B]/25 bg-[#F59E0B]/10 p-4">
                <p className="flex items-center gap-2 font-semibold text-[#FDE68A]">
                  <WifiOff className="h-4 w-4" /> {availability?.status || "Unavailable"}
                </p>
                <p className="mt-1 text-sm text-[#D7DDEA]">{unavailableMessage}</p>
              </div>
            )}

            {error ? <p className="mt-4 rounded-xl border border-red-400/25 bg-red-500/10 p-3 text-sm text-red-100">{error}</p> : null}

            <div className="mt-5 grid gap-3 sm:grid-cols-2">
              <button
                type="button"
                onClick={startChat}
                disabled={!canStart || starting}
                className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-[#D4AF37] px-4 text-sm font-semibold text-[#111827] disabled:cursor-not-allowed disabled:opacity-45"
              >
                <MessageCircle className="h-4 w-4" /> {starting ? "Starting..." : "Start chat"}
              </button>
              <button
                type="button"
                onClick={onOpenTicketCenter}
                className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl border border-white/15 bg-[#111827] px-4 text-sm font-semibold text-[#E6EAF2]"
              >
                <Ticket className="h-4 w-4" /> Create ticket
              </button>
            </div>
          </article>
        </section>
      </main>
    );
  }

  return (
    <main className="relative h-[100dvh] overflow-hidden bg-[#070B14] text-white">
      <header className="fixed inset-x-0 top-0 z-40 border-b border-white/10 bg-[#0A0F1D]/95 backdrop-blur" style={{ paddingTop: "env(safe-area-inset-top)" }}>
        <div className="mx-auto flex w-full max-w-4xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
          <div className="flex min-w-0 items-center gap-3">
            <button type="button" onClick={onBack} className="rounded-xl border border-white/15 bg-[#111827] p-2 text-[#E6EAF2]">
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div className="min-w-0">
              <h1 className="truncate text-base font-semibold text-[#F8F1DE]">Support chat</h1>
              <p className="truncate text-xs text-[#9CA3AF]">{conversation.conversation_number || "Persisted conversation"} · {conversation.status}</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button type="button" onClick={replay} className="rounded-xl border border-white/15 bg-[#111827] p-2 text-[#D7DDEA]" aria-label="Refresh messages">
              <RefreshCcw className="h-4 w-4" />
            </button>
            <button type="button" onClick={endChat} className="rounded-xl border border-white/15 bg-[#111827] px-3 py-2 text-xs text-[#D7DDEA]">
              End
            </button>
          </div>
        </div>
      </header>

      <section ref={chatBodyRef} className="mx-auto h-full w-full max-w-4xl overflow-y-auto px-4 pb-[140px] pt-[92px] sm:px-6" style={{ paddingBottom: "calc(136px + env(safe-area-inset-bottom))" }}>
        <div className="mb-3 inline-flex items-center gap-2 rounded-lg border border-[#D4AF37]/25 bg-[#D4AF37]/10 px-2.5 py-1.5 text-xs text-[#F8F1DE]">
          <Shield className="h-3.5 w-3.5" /> ExaEarn will never ask for your password, MFA secret or private key.
        </div>
        {error ? <p className="mb-3 rounded-xl border border-red-400/25 bg-red-500/10 p-3 text-sm text-red-100">{error}</p> : null}
        <div className="space-y-3">
          {messages.map((message) => {
            const isUser = message.sender === "USER";
            const isSystem = message.sender === "SYSTEM";
            if (isSystem) {
              return <p key={message.id} className="text-center text-xs text-[#7F8796]">{message.body}</p>;
            }
            return (
              <div key={message.id} className={`flex ${isUser ? "justify-end" : "justify-start"}`}>
                <div className={`flex max-w-[82%] flex-col ${isUser ? "items-end" : "items-start"}`}>
                  <div className={`rounded-2xl px-3 py-2.5 text-sm ${isUser ? "bg-[#D4AF37] text-[#111827]" : "border border-white/10 bg-[#131B2A] text-[#E5EAF2]"}`}>
                    {message.body}
                  </div>
                  <p className="mt-1 text-[11px] text-[#8B94A4]">#{message.sequence} · {nowLabel(message.createdAt)}</p>
                </div>
              </div>
            );
          })}
          {!messages.length ? <p className="rounded-xl border border-white/10 bg-white/[0.03] p-4 text-sm text-[#AAB4C4]">No messages yet.</p> : null}
        </div>
      </section>

      <section className="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-[#0A0F1D]/96 backdrop-blur" style={{ paddingBottom: "env(safe-area-inset-bottom)" }}>
        <div className="mx-auto flex w-full max-w-4xl gap-2 px-4 py-3 sm:px-6">
          <input
            value={inputValue}
            onChange={(event) => setInputValue(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === "Enter") {
                event.preventDefault();
                void sendMessage();
              }
            }}
            placeholder={conversation.status === "ENDED" ? "Conversation ended" : "Type your message..."}
            disabled={conversation.status === "ENDED"}
            className="h-11 flex-1 rounded-xl border border-white/15 bg-[#111827] px-3 text-sm text-white placeholder:text-[#9CA3AF] outline-none focus:border-[#D4AF37]/60 disabled:opacity-50"
          />
          <button
            type="button"
            onClick={sendMessage}
            disabled={!inputValue.trim() || sending || conversation.status === "ENDED"}
            className="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-[#D4AF37] text-[#111827] disabled:cursor-not-allowed disabled:opacity-45"
            aria-label="Send message"
          >
            {sending ? <LifeBuoy className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
          </button>
        </div>
      </section>
    </main>
  );
}

export default LiveSupportChat;
