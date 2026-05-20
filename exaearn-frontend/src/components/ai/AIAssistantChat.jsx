import { useState } from "react";

export default function AIAssistantChat({ onSend, loading }) {
  const [message, setMessage] = useState("");

  return (
    <section className="rounded-2xl border border-white/10 bg-[#0f172a] p-4">
      <h3 className="text-sm font-semibold text-white">AI Assistant Chat</h3>
      <div className="mt-3 flex gap-2">
        <input
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          placeholder="Ask about setup, risk, or entries..."
          className="w-full rounded-lg border border-white/20 bg-[#111827] px-3 py-2 text-sm text-white outline-none"
        />
        <button
          type="button"
          disabled={loading || !message.trim()}
          onClick={() => {
            onSend(message);
            setMessage("");
          }}
          className="rounded-lg bg-[#D4AF37] px-3 py-2 text-sm font-semibold text-black disabled:opacity-60"
        >
          Send
        </button>
      </div>
    </section>
  );
}
