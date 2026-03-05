import { Coins, CreditCard, Wallet } from "lucide-react";

function PaymentPanel({ selectedMethod, onMethodChange }) {
  const methods = [
    { id: "balance", label: "Platform Balance", icon: <Wallet className="h-4 w-4" aria-hidden="true" />, detail: "Available: 1,240.00 USDT" },
    { id: "crypto", label: "Crypto Wallet", icon: <Coins className="h-4 w-4" aria-hidden="true" />, detail: "Connected: 0x4a...0c51" },
    { id: "token", label: "ExaToken", icon: <CreditCard className="h-4 w-4" aria-hidden="true" />, detail: "Balance: 9,845.00 EXA" },
  ];

  return (
    <article className="buy-card rounded-2xl p-5 sm:p-6">
      <h2 className="font-['Sora'] text-2xl font-semibold text-violet-50">Payment Panel</h2>
      <p className="mt-2 text-sm text-violet-100/70">Choose your payment source for this purchase.</p>
      <div className="mt-5 grid gap-3">
        {methods.map((method) => {
          const active = selectedMethod === method.id;
          return (
            <button
              type="button"
              key={method.id}
              onClick={() => onMethodChange(method.id)}
              className={`buy-method w-full rounded-xl border px-4 py-3 text-left transition-all duration-300 ${
                active ? "border-auric-300/75 bg-auric-300/10 shadow-button-glow" : "border-violet-300/25 bg-cosmic-900/55"
              }`}
            >
              <div className="flex items-center justify-between gap-3">
                <span className="inline-flex items-center gap-2 text-sm font-semibold text-violet-50">
                  <span className="inline-flex h-7 w-7 items-center justify-center rounded-full border border-auric-300/45 bg-cosmic-800/80 text-auric-300">
                    {method.icon}
                  </span>
                  {method.label}
                </span>
                {active ? <span className="text-xs font-semibold text-auric-300">Selected</span> : null}
              </div>
              <p className="mt-2 text-xs text-violet-100/70">{method.detail}</p>
            </button>
          );
        })}
      </div>
    </article>
  );
}

export default PaymentPanel;
