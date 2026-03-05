import { useEffect, useMemo, useRef, useState } from "react";
import {
  ArrowLeft,
  BadgeCheck,
  Paperclip,
  Send,
  Shield,
  Star,
  Wifi,
  WifiOff,
  X,
} from "lucide-react";

const SOCKET_URL = "wss://echo.websocket.events";
const AVG_RESPONSE = "2m 38s";

const initialMessages = [
  { id: "sys-1", type: "system", text: "Chat encrypted & secured", timestamp: nowTime() },
  { id: "sys-2", type: "system", text: "Support Agent joined the chat", timestamp: nowTime() },
  {
    id: "sup-1",
    type: "support",
    text: "Hi, welcome to ExaEarn Support. I am Ada, your support specialist. How can I assist you today?",
    timestamp: nowTime(),
  },
];

function nowTime() {
  return new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

function LiveSupportChat({ onBack }) {
  const [messages, setMessages] = useState(initialMessages);
  const [inputValue, setInputValue] = useState("");
  const [attachments, setAttachments] = useState([]);
  const [isTyping, setIsTyping] = useState(false);
  const [connectionStatus, setConnectionStatus] = useState("connecting");
  const [showRatingModal, setShowRatingModal] = useState(false);
  const [selectedRating, setSelectedRating] = useState(0);
  const [ratingSubmitted, setRatingSubmitted] = useState(false);

  const wsRef = useRef(null);
  const reconnectRef = useRef(null);
  const chatBodyRef = useRef(null);

  const canSend = useMemo(() => inputValue.trim().length > 0 || attachments.length > 0, [inputValue, attachments]);

  useEffect(() => {
    const connect = () => {
      setConnectionStatus("connecting");
      try {
        const socket = new WebSocket(SOCKET_URL);
        wsRef.current = socket;

        socket.onopen = () => {
          setConnectionStatus("online");
        };

        socket.onclose = () => {
          setConnectionStatus("connecting");
          reconnectRef.current = setTimeout(connect, 2200);
        };

        socket.onerror = () => {
          setConnectionStatus("connecting");
          socket.close();
        };
      } catch (error) {
        setConnectionStatus("connecting");
        reconnectRef.current = setTimeout(connect, 2500);
      }
    };

    connect();

    return () => {
      if (reconnectRef.current) {
        clearTimeout(reconnectRef.current);
      }
      wsRef.current?.close();
    };
  }, []);

  useEffect(() => {
    if (!chatBodyRef.current) return;
    chatBodyRef.current.scrollTo({
      top: chatBodyRef.current.scrollHeight,
      behavior: "smooth",
    });
  }, [messages, isTyping]);

  const sendMessage = () => {
    if (!canSend) return;

    const newMessageId = `usr-${Date.now()}`;
    const payload = {
      id: newMessageId,
      type: "user",
      text: inputValue.trim(),
      attachments,
      timestamp: nowTime(),
      delivery: "Sent",
    };

    setMessages((prev) => [...prev, payload]);
    setInputValue("");
    setAttachments([]);

    if (wsRef.current?.readyState === WebSocket.OPEN) {
      wsRef.current.send(JSON.stringify({ text: payload.text, timestamp: payload.timestamp }));
    }

    setTimeout(() => {
      setMessages((prev) =>
        prev.map((message) => (message.id === newMessageId ? { ...message, delivery: "Delivered" } : message))
      );
    }, 600);

    setIsTyping(true);
    setTimeout(() => {
      setIsTyping(false);
      setMessages((prev) => [
        ...prev.map((message) => (message.id === newMessageId ? { ...message, delivery: "Seen" } : message)),
        {
          id: `sup-${Date.now()}`,
          type: "support",
          text: "Thanks for the details. I am checking this now and will guide you with the next best step.",
          timestamp: nowTime(),
        },
      ]);
    }, 1600);
  };

  const onPickFile = (event) => {
    const files = Array.from(event.target.files || []);
    const fileItems = files.map((file, index) => ({
      id: `${Date.now()}-${index}`,
      name: file.name,
    }));
    setAttachments((prev) => [...prev, ...fileItems]);
    event.target.value = "";
  };

  const removeAttachment = (id) => {
    setAttachments((prev) => prev.filter((item) => item.id !== id));
  };

  const statusText = connectionStatus === "online" ? "Online" : "Connecting...";
  const statusTone = connectionStatus === "online" ? "text-[#7CF3B2]" : "text-[#FDE68A]";

  return (
    <main className="relative h-[100dvh] overflow-hidden bg-[#070B14] text-white">
      <header
        className="fixed inset-x-0 top-0 z-40 border-b border-[#D4AF37]/25 bg-gradient-to-r from-[#121A2A]/96 via-[#0E1524]/96 to-[#0A0F1D]/96 backdrop-blur"
        style={{ paddingTop: "env(safe-area-inset-top)" }}
      >
        <div className="mx-auto flex w-full max-w-4xl items-center justify-between gap-2 px-4 py-3 sm:px-6">
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={onBack}
              className="rounded-xl border border-white/15 bg-[#111827] p-2 text-[#E6EAF2] hover:border-[#D4AF37]/60"
            >
              <ArrowLeft className="h-4 w-4" />
            </button>
            <div>
              <h1 className="text-base font-semibold text-[#F8F1DE] sm:text-lg">Live Support</h1>
              <div className="flex items-center gap-2 text-xs">
                <span className={`inline-flex items-center gap-1 ${statusTone}`}>
                  <span className="h-2 w-2 rounded-full bg-current" />
                  {statusText}
                </span>
                <span className="text-[#9CA3AF]">Avg response {AVG_RESPONSE}</span>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <span className="rounded-full border border-[#22C55E]/35 bg-[#22C55E]/10 px-2.5 py-1 text-[11px] font-semibold text-[#9CF4BD]">
              24/7 Support
            </span>
            <button
              type="button"
              onClick={() => setShowRatingModal(true)}
              className="rounded-lg border border-white/15 bg-[#111827] px-2 py-1 text-xs text-[#D7DDEA]"
            >
              End Chat
            </button>
          </div>
        </div>
      </header>

      <section
        ref={chatBodyRef}
        className="mx-auto h-full w-full max-w-4xl overflow-y-auto px-4 pb-[160px] pt-[92px] sm:px-6"
        style={{ paddingBottom: "calc(152px + env(safe-area-inset-bottom))" }}
      >
        {connectionStatus !== "online" ? (
          <div className="mb-3 inline-flex items-center gap-2 rounded-lg border border-[#F59E0B]/30 bg-[#F59E0B]/10 px-2.5 py-1.5 text-xs text-[#FDE68A]">
            <WifiOff className="h-3.5 w-3.5" />
            Reconnecting to support channel...
          </div>
        ) : (
          <div className="mb-3 inline-flex items-center gap-2 rounded-lg border border-[#22C55E]/25 bg-[#22C55E]/10 px-2.5 py-1.5 text-xs text-[#9CF4BD]">
            <Wifi className="h-3.5 w-3.5" />
            Secure support connection active
          </div>
        )}

        <div className="space-y-3">
          {messages.map((message) => {
            if (message.type === "system") {
              return (
                <p key={message.id} className="text-center text-xs text-[#7F8796]">
                  {message.text}
                </p>
              );
            }

            const isUser = message.type === "user";

            return (
              <div key={message.id} className={`flex ${isUser ? "justify-end" : "justify-start"}`}>
                {!isUser ? (
                  <div className="mr-2 mt-0.5">
                    <div className="relative inline-flex h-8 w-8 items-center justify-center rounded-full border border-[#D4AF37]/40 bg-[#111827] text-xs font-semibold text-[#F8F1DE]">
                      AE
                      <BadgeCheck className="absolute -bottom-1 -right-1 h-3.5 w-3.5 rounded-full bg-[#111827] text-[#D4AF37]" />
                    </div>
                  </div>
                ) : null}
                <div className={`max-w-[80%] ${isUser ? "items-end" : "items-start"} flex flex-col`}>
                  <div
                    className={`rounded-2xl px-3 py-2.5 text-sm shadow-[0_8px_18px_rgba(0,0,0,0.28)] ${
                      isUser
                        ? "bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] text-[#111827]"
                        : "border border-white/10 bg-[#131B2A] text-[#E5EAF2]"
                    }`}
                  >
                    {message.text ? <p>{message.text}</p> : null}
                    {message.attachments?.length ? (
                      <div className="mt-2 space-y-1">
                        {message.attachments.map((item) => (
                          <div key={item.id} className={`rounded-lg px-2 py-1 text-xs ${isUser ? "bg-[#FFFFFF]/45" : "bg-[#0B1220]"}`}>
                            Attachment: {item.name}
                          </div>
                        ))}
                      </div>
                    ) : null}
                  </div>
                  <p className="mt-1 text-[11px] text-[#8B94A4]">
                    {message.timestamp}
                    {isUser && message.delivery ? ` • ${message.delivery}` : ""}
                  </p>
                </div>
              </div>
            );
          })}

          {isTyping ? (
            <div className="flex justify-start">
              <div className="mr-2 mt-0.5">
                <div className="relative inline-flex h-8 w-8 items-center justify-center rounded-full border border-[#D4AF37]/40 bg-[#111827] text-xs font-semibold text-[#F8F1DE]">
                  AE
                  <BadgeCheck className="absolute -bottom-1 -right-1 h-3.5 w-3.5 rounded-full bg-[#111827] text-[#D4AF37]" />
                </div>
              </div>
              <div className="rounded-2xl border border-white/10 bg-[#131B2A] px-3 py-2.5">
                <div className="flex items-center gap-1">
                  <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-[#D4AF37]" />
                  <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-[#D4AF37] [animation-delay:120ms]" />
                  <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-[#D4AF37] [animation-delay:240ms]" />
                </div>
              </div>
            </div>
          ) : null}
        </div>
      </section>

      <section
        className="fixed inset-x-0 bottom-0 z-40 border-t border-[#D4AF37]/25 bg-[#0A0F1D]/96 backdrop-blur"
        style={{ paddingBottom: "env(safe-area-inset-bottom)" }}
      >
        <div className="mx-auto w-full max-w-4xl px-4 pb-3 pt-2 sm:px-6">
          <p className="mb-2 flex items-center gap-1 text-[11px] text-[#9CA3AF]">
            <Shield className="h-3.5 w-3.5 text-[#D4AF37]" />
            ExaEarn will never ask for your password or private key.
          </p>

          {attachments.length ? (
            <div className="mb-2 flex flex-wrap gap-2">
              {attachments.map((item) => (
                <div key={item.id} className="inline-flex items-center gap-1 rounded-lg border border-white/15 bg-[#111827] px-2 py-1 text-xs text-[#D7DDEA]">
                  {item.name}
                  <button type="button" onClick={() => removeAttachment(item.id)} className="text-[#9CA3AF] hover:text-white">
                    <X className="h-3.5 w-3.5" />
                  </button>
                </div>
              ))}
            </div>
          ) : null}

          <div className="flex items-center gap-2">
            <label className="inline-flex cursor-pointer items-center justify-center rounded-xl border border-white/15 bg-[#111827] p-2 text-[#D7DDEA] hover:border-[#D4AF37]/50">
              <Paperclip className="h-4 w-4" />
              <input type="file" accept="image/*" multiple className="hidden" onChange={onPickFile} />
            </label>

            <input
              type="text"
              value={inputValue}
              onChange={(event) => setInputValue(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === "Enter") {
                  event.preventDefault();
                  sendMessage();
                }
              }}
              placeholder="Type your message..."
              className="h-10 flex-1 rounded-xl border border-white/15 bg-[#111827] px-3 text-sm text-white placeholder:text-[#9CA3AF] outline-none focus:border-[#D4AF37]/60"
            />

            <button
              type="button"
              onClick={sendMessage}
              disabled={!canSend}
              className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] text-[#111827] shadow-[0_8px_16px_rgba(212,175,55,0.28)] disabled:cursor-not-allowed disabled:opacity-45"
            >
              <Send className="h-4 w-4" />
            </button>
          </div>
        </div>
      </section>

      {showRatingModal ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="w-full max-w-sm rounded-2xl border border-[#D4AF37]/30 bg-[#0E1524] p-5">
            <h3 className="text-base font-semibold text-[#F8F1DE]">Rate Support Experience</h3>
            <p className="mt-1 text-sm text-[#B6C0D0]">How satisfied are you with this support session?</p>
            <div className="mt-4 flex items-center justify-center gap-2">
              {[1, 2, 3, 4, 5].map((value) => (
                <button key={value} type="button" onClick={() => setSelectedRating(value)}>
                  <Star className={`h-7 w-7 ${value <= selectedRating ? "fill-[#D4AF37] text-[#D4AF37]" : "text-[#556070]"}`} />
                </button>
              ))}
            </div>
            {ratingSubmitted ? <p className="mt-3 text-center text-sm text-[#9CF4BD]">Thanks for your feedback.</p> : null}
            <div className="mt-4 grid grid-cols-2 gap-2">
              <button
                type="button"
                onClick={() => setShowRatingModal(false)}
                className="rounded-xl border border-white/15 bg-[#111827] py-2 text-sm text-[#D7DDEA]"
              >
                Close
              </button>
              <button
                type="button"
                disabled={!selectedRating}
                onClick={() => setRatingSubmitted(true)}
                className="rounded-xl bg-gradient-to-r from-[#D4AF37] via-[#E7C96C] to-[#D4AF37] py-2 text-sm font-semibold text-[#111827] disabled:opacity-45"
              >
                Submit
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </main>
  );
}

export default LiveSupportChat;
