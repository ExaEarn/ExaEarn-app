import { ArrowLeft } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useAuth } from "../../context/AuthContext";
import {
  activateStrategy,
  chatWithAssistant,
  createStrategy,
  deactivateStrategy,
  generateAiSignal,
  getAiProfile,
  getAiSignals,
  getAssistantConversation,
  getAssistantConversations,
  getRiskAssessment,
  getStrategies,
  updateAiProfile,
  validateTrade,
} from "../../services/aiApi";
import AIAssistantChat from "../../components/ai/AIAssistantChat";
import SignalDashboard from "../../components/ai/SignalDashboard";
import RiskAlertPanel from "../../components/ai/RiskAlertPanel";
import TradeSuggestionCard from "../../components/ai/TradeSuggestionCard";
import AutoStrategyManager from "../../components/ai/AutoStrategyManager";
import UserProfileForm from "../../components/ai/UserProfileForm";

function normalizeConversationMessages(conversation) {
  const source = conversation?.messages || conversation?.data?.messages || [];
  if (!Array.isArray(source)) return [];

  return source.map((message, index) => ({
    id: message?.id || `m-${index}`,
    role: String(message?.role || message?.sender || "assistant").toLowerCase(),
    text: String(message?.content || message?.message || message?.text || ""),
    createdAt: message?.created_at || message?.createdAt || null,
  }));
}

function formatTime(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return date.toLocaleString();
}

export default function AITradingAssistantPage({ onBack }) {
  const { apiBaseUrl, token } = useAuth();
  const [profile, setProfile] = useState(null);
  const [tradeModes, setTradeModes] = useState([
    { key: "manual", label: "Trade Without AI" },
    { key: "assist", label: "AI Suggestions Only" },
    { key: "auto", label: "Allow AI Auto-Trading" },
  ]);
  const [signals, setSignals] = useState([]);
  const [risk, setRisk] = useState(null);
  const [strategies, setStrategies] = useState([]);
  const [suggestion, setSuggestion] = useState(null);
  const [conversations, setConversations] = useState([]);
  const [selectedConversation, setSelectedConversation] = useState(null);
  const [status, setStatus] = useState({ type: "", text: "" });
  const [loadingChat, setLoadingChat] = useState(false);
  const [loadingData, setLoadingData] = useState(true);

  const loadAll = async () => {
    setLoadingData(true);
    try {
      const [p, s, r, st, conv] = await Promise.all([
        getAiProfile({ apiBaseUrl, token }),
        getAiSignals({ apiBaseUrl, token }),
        getRiskAssessment({ apiBaseUrl, token }),
        getStrategies({ apiBaseUrl, token }),
        getAssistantConversations({ apiBaseUrl, token }),
      ]);
      setProfile(p?.profile || p?.data || p);
      setTradeModes(p?.trade_modes || tradeModes);
      setSignals(s?.signals || s?.data || []);
      setRisk(r?.data || r);
      setStrategies(st?.strategies || st?.data || []);
      setConversations(conv?.conversations || conv?.data || []);
    } catch (e) {
      setStatus({ type: "error", text: e.message || "Unable to load AI assistant data." });
    } finally {
      setLoadingData(false);
    }
  };

  useEffect(() => {
    loadAll();
  }, [apiBaseUrl, token]);

  const riskMessage = useMemo(() => {
    if (!risk) return "Risk checks and warnings appear here.";
    return risk?.message || "Risk model active.";
  }, [risk]);
  const conversationMessages = useMemo(
    () => normalizeConversationMessages(selectedConversation),
    [selectedConversation]
  );

  const handleChat = async (message) => {
    setLoadingChat(true);
    setStatus({ type: "", text: "" });
    try {
      const res = await chatWithAssistant({ apiBaseUrl, token, body: { message } });
      const responseText = res?.data?.response || res?.response || "AI response received.";
      setStatus({ type: "success", text: responseText });
      await loadAll();
    } catch (e) {
      setStatus({ type: "error", text: e.message });
    } finally {
      setLoadingChat(false);
    }
  };

  const handleSuggestion = async () => {
    try {
      const signal = await generateAiSignal({ apiBaseUrl, token, body: { symbol: "BTCUSDT", timeframe: "1h" } });
      const validated = await validateTrade({ apiBaseUrl, token, body: { symbol: "BTCUSDT", side: "buy", amount: 100, leverage: 2 } });
      setSuggestion({
        entry: signal?.data?.entry || "market",
        stop: signal?.data?.stop_loss || validated?.data?.stop_loss,
        targets: signal?.data?.targets || validated?.data?.targets || [],
      });
      setStatus({ type: "success", text: "Fresh trade suggestion generated." });
      await loadAll();
    } catch (e) {
      setStatus({ type: "error", text: e.message });
    }
  };

  const handleSaveProfile = async () => {
    try {
      await updateAiProfile({ apiBaseUrl, token, body: { risk_tolerance: profile?.risk_tolerance || "medium" } });
      setStatus({ type: "success", text: "Profile synced." });
      await loadAll();
    } catch (e) {
      setStatus({ type: "error", text: e.message });
    }
  };

  const handleModeChange = async (mode) => {
    try {
      await updateAiProfile({ apiBaseUrl, token, body: { ai_trade_mode: mode } });
      setStatus({
        type: "success",
        text: mode === "manual"
          ? "Manual mode enabled. AI trading disabled."
          : mode === "assist"
          ? "Assist mode enabled. AI suggestions active."
          : "Auto mode enabled. AI can execute strategies within limits.",
      });
      await loadAll();
    } catch (e) {
      setStatus({ type: "error", text: e.message });
    }
  };

  const handleCreateStrategy = async () => {
    try {
      await createStrategy({ apiBaseUrl, token, body: { name: "Trend Auto", strategy_type: "trend_following", risk_limit: 2 } });
      setStatus({ type: "success", text: "Strategy created." });
      await loadAll();
    } catch (e) {
      setStatus({ type: "error", text: e.message });
    }
  };

  const openConversation = async (id) => {
    try {
      const payload = await getAssistantConversation({ apiBaseUrl, token, id });
      setSelectedConversation(payload?.data || payload);
    } catch (e) {
      setStatus({ type: "error", text: e.message });
    }
  };

  return (
    <main className="min-h-screen bg-[#0a0f1d] text-white">
      <header className="sticky top-0 z-20 border-b border-white/10 bg-[#0a0f1d]/95 backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center gap-3 px-4 py-4">
          <button type="button" onClick={onBack} className="rounded-full border border-white/20 p-2"><ArrowLeft className="h-4 w-4" /></button>
          <h1 className="text-lg font-semibold">AI Trading Assistant</h1>
          <button type="button" onClick={handleSuggestion} className="ml-auto rounded bg-[#D4AF37] px-3 py-2 text-xs font-semibold text-black">Generate Suggestion</button>
          <button type="button" onClick={handleCreateStrategy} className="rounded bg-emerald-500 px-3 py-2 text-xs font-semibold text-white">New Strategy</button>
        </div>
      </header>

      <section className="mx-auto max-w-6xl px-4 pt-4">
        {status.text ? (
          <div className={`rounded-lg border px-3 py-2 text-xs ${status.type === "error" ? "border-red-400/40 bg-red-500/10 text-red-200" : "border-emerald-400/40 bg-emerald-500/10 text-emerald-200"}`}>
            {status.text}
          </div>
        ) : null}
      </section>

      <section className="mx-auto grid max-w-6xl gap-4 px-4 py-6 md:grid-cols-2">
        <section className="rounded-2xl border border-white/10 bg-[#0f172a] p-4 md:col-span-2">
          <h3 className="text-sm font-semibold text-white">Trading Mode</h3>
          <p className="mt-1 text-xs text-slate-400">Choose whether to trade manually, use AI guidance, or allow AI auto-trading.</p>
          <div className="mt-3 flex flex-wrap gap-2">
            {tradeModes.map((mode) => {
              const active = (profile?.ai_trade_mode || "assist") === mode.key;
              return (
                <button
                  key={mode.key}
                  type="button"
                  onClick={() => handleModeChange(mode.key)}
                  className={`rounded-lg border px-3 py-2 text-xs font-semibold ${
                    active
                      ? "border-[#D4AF37]/70 bg-[#D4AF37]/20 text-[#F7EBC3]"
                      : "border-white/20 bg-[#111827] text-slate-200 hover:border-[#D4AF37]/40"
                  }`}
                >
                  {mode.label}
                </button>
              );
            })}
          </div>
        </section>
        <AIAssistantChat onSend={handleChat} loading={loadingChat} />
        <RiskAlertPanel risk={{ ...risk, message: riskMessage }} />
        {loadingData ? <div className="rounded-2xl border border-white/10 bg-[#0f172a] p-4 text-xs text-slate-400">Loading signals...</div> : <SignalDashboard signals={signals} />}
        <TradeSuggestionCard suggestion={suggestion} />
        <AutoStrategyManager
          strategies={strategies}
          onActivate={async (id) => { await activateStrategy({ apiBaseUrl, token, strategyId: id }); await loadAll(); }}
          onDeactivate={async (id) => { await deactivateStrategy({ apiBaseUrl, token, strategyId: id }); await loadAll(); }}
        />
        <UserProfileForm profile={profile} onSave={handleSaveProfile} />

        <section className="rounded-2xl border border-white/10 bg-[#0f172a] p-4 md:col-span-2">
          <h3 className="text-sm font-semibold">Conversation History</h3>
          <div className="mt-3 grid gap-2 md:grid-cols-2">
            <div className="space-y-2">
              {(conversations || []).slice(0, 10).map((c) => (
                <button key={c.id} type="button" onClick={() => openConversation(c.id)} className="w-full rounded-lg border border-white/10 bg-[#111827] px-3 py-2 text-left text-xs text-slate-200 hover:border-[#D4AF37]/50">
                  {c.title || c.subject || `Conversation ${c.id}`}
                </button>
              ))}
              {!conversations.length ? <p className="text-xs text-slate-400">No conversations yet.</p> : null}
            </div>
            <div className="rounded-lg border border-white/10 bg-[#111827] p-3 text-xs text-slate-200">
              {selectedConversation ? (
                <div className="space-y-3">
                  {conversationMessages.length ? (
                    conversationMessages.map((message) => {
                      const isUser = message.role === "user";
                      return (
                        <div key={message.id} className={`flex ${isUser ? "justify-end" : "justify-start"}`}>
                          <div
                            className={`max-w-[85%] rounded-2xl px-3 py-2 ${
                              isUser
                                ? "border border-[#D4AF37]/40 bg-[#D4AF37]/15 text-[#F7EBC3]"
                                : "border border-white/10 bg-[#0f172a] text-slate-200"
                            }`}
                          >
                            <p className="text-[11px] leading-relaxed">{message.text || "..."}</p>
                            {message.createdAt ? (
                              <p className="mt-1 text-[10px] text-slate-400">{formatTime(message.createdAt)}</p>
                            ) : null}
                          </div>
                        </div>
                      );
                    })
                  ) : (
                    <p>No message timeline available for this conversation.</p>
                  )}
                </div>
              ) : (
                <p>Select a conversation to view details.</p>
              )}
            </div>
          </div>
        </section>
      </section>
    </main>
  );
}
