import { useState } from "react";

export default function AIAssistantChat({ onSend, loading }) {
  const [message, setMessage] = useState("");

  return (
    <section className="rounded-2xl border border-[var(--exa-border)] bg-[var(--exa-surface)] p-4">
      <h3 className="text-sm font-semibold text-[var(--exa-text-primary)]">AI Assistant Chat</h3>
      <div className="mt-3 flex gap-2">
        <input
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          placeholder="Ask about setup, risk, or entries..."
          className="w-full rounded-lg border border-[var(--exa-border)] bg-[var(--exa-surface-elevated)] px-3 py-2 text-sm text-[var(--exa-text-primary)] outline-none"
        />
        <button
          type="button"
          disabled={loading || !message.trim()}
          onClick={() => {
            onSend(message);
            setMessage("");
          }}
          className="rounded-lg bg-[var(--exa-gold)] px-3 py-2 text-sm font-semibold text-[var(--exa-gold-contrast)] disabled:opacity-60"
        >
          Send
        </button>
      </div>
    </section>
  );
}
