import { Coins, Wallet } from "lucide-react";

function PaymentPanel({ selectedMethod, onMethodChange }) {
  const methods = [
    { id: "balance", label: "Platform Balance", icon: <Wallet className="h-4 w-4" aria-hidden="true" />, detail: "Available: 1,240.00 USDT" },
    { id: "crypto", label: "Crypto Wallet", icon: <Coins className="h-4 w-4" aria-hidden="true" />, detail: "Connected: 0x4a...0c51" },
  ];

  return (
    <article className="buy-card rounded-2xl p-5 sm:p-6">
      <h2 className="font-['Sora'] text-2xl font-semibold text-[var(--exa-text-primary)]">Payment Panel</h2>
      <p className="mt-2 text-sm text-[var(--exa-text-secondary)]">Choose your payment source for this purchase.</p>
      <div className="mt-5 grid gap-3">
        {methods.map((method) => {
          const active = selectedMethod === method.id;
          return (
            <button
              type="button"
              key={method.id}
              onClick={() => onMethodChange(method.id)}
              className={`buy-method w-full rounded-xl border px-4 py-3 text-left transition-all duration-300 ${
                active ? "border-[var(--exa-border-active)] bg-[var(--exa-gold-surface)] shadow-[var(--exa-shadow-gold)]" : "border-[var(--exa-border)] bg-[var(--exa-surface-elevated)]"
              }`}
            >
              <div className="flex items-center justify-between gap-3">
                <span className="inline-flex items-center gap-2 text-sm font-semibold text-[var(--exa-text-primary)]">
                  <span className="inline-flex h-7 w-7 items-center justify-center rounded-full border border-[var(--exa-border-active)] bg-[var(--exa-surface-elevated)] text-[var(--exa-gold-light)]">
                    {method.icon}
                  </span>
                  {method.label}
                </span>
                {active ? <span className="text-xs font-semibold text-[var(--exa-gold-light)]">Selected</span> : null}
              </div>
              <p className="mt-2 text-xs text-[var(--exa-text-secondary)]">{method.detail}</p>
            </button>
          );
        })}
      </div>
    </article>
  );
}

export default PaymentPanel;
