import { useMemo, useState } from "react";
import {
  ArrowLeft,
  ArrowRight,
  ArrowUpRight,
  Bolt,
  Link2,
  LoaderCircle,
  ShieldCheck,
  UserRound,
  Wallet,
  X,
} from "lucide-react";

const transferMethods = [
  {
    id: "exa-user",
    title: "Send to ExaEarn User",
    description: "Internal transfer using username, email, or wallet ID.",
    badge: "Instant & Zero Fees",
    icon: UserRound,
    iconAlt: Bolt,
  },
  {
    id: "external-wallet",
    title: "Send to External Wallet",
    description: "Transfer crypto to another blockchain wallet.",
    badge: "Network Fee Applies",
    icon: ArrowUpRight,
  },
  {
    id: "cross-chain",
    title: "Cross-Chain Transfer",
    description: "Bridge assets across supported blockchains.",
    badge: "Multi-Chain",
    icon: Link2,
  },
];

function Send({ onBack, onAddFunds }) {
  const [selectedMethod, setSelectedMethod] = useState("exa-user");
  const [asset, setAsset] = useState("XRP");
  const [recipient, setRecipient] = useState("");
  const [network, setNetwork] = useState("XRP Ledger");
  const [amount, setAmount] = useState("");
  const [memo, setMemo] = useState("");
  const [showConfirm, setShowConfirm] = useState(false);
  const [hasBalance] = useState(true);

  const balances = {
    XRP: { total: 1245, usd: 3410.55, available: 1245, fee: 0.25 },
    USDT: { total: 760, usd: 760.0, available: 760, fee: 1.0 },
    EXA: { total: 18220, usd: 4020.45, available: 18220, fee: 4.5 },
  };

  const currentAsset = balances[asset];
  const parsedAmount = Number.parseFloat(amount || "0");
  const safeAmount = Number.isFinite(parsedAmount) ? parsedAmount : 0;
  const fee = selectedMethod === "exa-user" ? 0 : currentAsset.fee;
  const totalDeduction = safeAmount + fee;
  const remainingBalance = Math.max(currentAsset.available - totalDeduction, 0);
  const estimatedArrival =
    selectedMethod === "exa-user" ? "Instant" : selectedMethod === "cross-chain" ? "3-10 minutes" : "1-5 minutes";
  const needsNetwork = selectedMethod !== "exa-user";

  const isFormComplete = recipient.trim() && safeAmount > 0 && (!needsNetwork || network);

  const summaryRows = useMemo(
    () => [
      { label: "Amount", value: `${safeAmount || 0} ${asset}` },
      { label: "Network Fee", value: `${fee} ${asset}` },
      { label: "Total Deduction", value: `${totalDeduction.toFixed(4)} ${asset}` },
      { label: "Remaining Balance", value: `${remainingBalance.toFixed(4)} ${asset}` },
    ],
    [safeAmount, fee, totalDeduction, remainingBalance, asset]
  );

  if (!hasBalance) {
    return (
      <main className="min-h-screen bg-gradient-to-br from-[#05070d] via-[#0c111b] to-[#16161d] px-4 py-8 text-[#f5f1e6]">
        <section className="mx-auto w-full max-w-xl rounded-2xl border border-[#3a3425] bg-[#13161f]/90 p-6 text-center shadow-[0_24px_60px_rgba(0,0,0,0.45)]">
          <Wallet className="mx-auto h-10 w-10 text-[#c8a45a]" />
          <h1 className="mt-4 font-['Sora'] text-xl font-semibold">No funds available.</h1>
          <p className="mt-1 text-sm text-[#b0aa98]">Add funds to start sending crypto.</p>
          <button
            type="button"
            onClick={onAddFunds}
            className="mt-5 rounded-xl bg-gradient-to-r from-[#b68a36] via-[#d2ab55] to-[#f0cf84] px-5 py-2.5 font-semibold text-[#111] transition hover:shadow-[0_0_28px_rgba(210,171,85,0.45)]"
          >
            Add Funds
          </button>
        </section>
      </main>
    );
  }

  return (
    <main className="relative min-h-screen overflow-hidden bg-gradient-to-br from-[#05070d] via-[#0c111b] to-[#16161d] px-4 py-8 text-[#f5f1e6] sm:px-6 sm:py-10">
      <div className="pointer-events-none absolute -left-24 top-10 h-64 w-64 rounded-full bg-[#b68a36]/10 blur-3xl" />
      <div className="pointer-events-none absolute right-0 top-1/3 h-72 w-72 rounded-full bg-[#8a6a2c]/10 blur-3xl" />

      <section className="mx-auto w-full max-w-7xl rounded-2xl border border-[#2a303d] bg-[#121722]/92 p-4 shadow-[0_20px_60px_rgba(0,0,0,0.45)] backdrop-blur-xl sm:p-6">
        <header className="rounded-2xl border border-[#2f3545] bg-gradient-to-r from-[#151b29] via-[#151d2a] to-[#1b1f2a] px-4 py-4 sm:px-5">
          <div className="flex items-start justify-between gap-3">
            <div>
              <button
                type="button"
                onClick={onBack}
                className="mb-3 inline-flex items-center gap-2 rounded-xl border border-[#3a4152] bg-[#1a2233] px-3 py-2 text-xs font-semibold text-[#ded8c8] transition hover:border-[#c9a458] hover:text-[#f5e2b9]"
              >
                <ArrowLeft className="h-4 w-4" />
                Back
              </button>
              <h1 className="font-['Sora'] text-2xl font-semibold sm:text-3xl">Send Crypto</h1>
              <p className="mt-1 text-sm text-[#b3ad9e]">
                Transfer digital assets securely across the ExaEarn network or external wallets.
              </p>
            </div>
            <div className="hidden items-center gap-2 rounded-xl border border-[#3b3629] bg-[#1d1a14] px-3 py-2 text-xs text-[#e2c88a] md:inline-flex">
              <ShieldCheck className="h-4 w-4" />
              Secure Transfer
            </div>
          </div>
          <div className="mt-4 h-px w-full bg-gradient-to-r from-transparent via-[#796439] to-transparent" />
        </header>

        <div className="mt-5 grid gap-5 lg:grid-cols-[1.45fr,1fr]">
          <div className="space-y-5">
            <section className="rounded-2xl border border-[#2f3645] bg-[#171d2a] p-4 shadow-[0_12px_30px_rgba(0,0,0,0.35)] sm:p-5">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="text-xs uppercase tracking-[0.12em] text-[#9f9887]">Total Balance</p>
                  <p className="mt-1 font-['Sora'] text-3xl font-semibold text-[#f0cc84] sm:text-4xl">
                    {currentAsset.total.toLocaleString()} {asset}
                  </p>
                  <p className="mt-1 text-sm text-[#a9a293]">${currentAsset.usd.toLocaleString()}</p>
                </div>
                <div className="flex items-center gap-2">
                  <select
                    value={asset}
                    onChange={(event) => setAsset(event.target.value)}
                    className="rounded-lg border border-[#40475a] bg-[#111827] px-2 py-1.5 text-xs font-semibold text-[#ece5d2] outline-none transition focus:border-[#c9a458]"
                  >
                    <option value="XRP">XRP</option>
                    <option value="USDT">USDT</option>
                    <option value="EXA">EXA</option>
                  </select>
                  <button
                    type="button"
                    onClick={() => setAmount(String(currentAsset.available))}
                    className="rounded-lg border border-[#5a4a2a] bg-[#241f14] px-3 py-1.5 text-xs font-semibold text-[#e7cb8f] transition hover:border-[#d1ad64]"
                  >
                    Max
                  </button>
                </div>
              </div>
              <div className="mt-4 h-12 rounded-xl border border-[#2b313f] bg-gradient-to-r from-[#111723] via-[#151e2e] to-[#111723] px-3 py-2">
                <div className="flex h-full items-end gap-1.5">
                  {[16, 24, 14, 20, 30, 18, 33, 27, 36, 30, 38, 34].map((h, idx) => (
                    <span key={idx} className="w-full rounded-t bg-gradient-to-t from-[#7a5c2d] to-[#d5b16a]/90" style={{ height: `${h}%` }} />
                  ))}
                </div>
              </div>
            </section>

            <section className="space-y-3">
              <h2 className="font-['Sora'] text-lg font-semibold">Select Transfer Method</h2>
              {transferMethods.map((method) => {
                const Icon = method.icon;
                const IconAlt = method.iconAlt;
                const active = selectedMethod === method.id;
                return (
                  <button
                    type="button"
                    key={method.id}
                    onClick={() => setSelectedMethod(method.id)}
                    className={`group w-full rounded-2xl border p-4 text-left transition-all duration-300 hover:-translate-y-0.5 ${
                      active
                        ? "border-[#c9a458] bg-[#1d2330] shadow-[0_0_24px_rgba(201,164,88,0.25)]"
                        : "border-[#313847] bg-[#171d2a] hover:border-[#7f6b44]"
                    }`}
                  >
                    <div className="flex items-center justify-between gap-3">
                      <div className="flex items-center gap-3">
                        <span className="flex h-11 w-11 items-center justify-center rounded-xl border border-[#49412f] bg-[#251f14] text-[#d8b56f]">
                          <Icon className="h-5 w-5" />
                          {IconAlt ? <IconAlt className="ml-1 h-3.5 w-3.5" /> : null}
                        </span>
                        <span>
                          <span className="block text-sm font-semibold">{method.title}</span>
                          <span className="mt-0.5 block text-xs text-[#aaa394]">{method.description}</span>
                        </span>
                      </div>
                      <ArrowRight className={`h-4 w-4 transition-all ${active ? "text-[#d6b26a]" : "text-[#8d8677] group-hover:text-[#d6b26a]"}`} />
                    </div>
                    <span className="mt-3 inline-flex rounded-full border border-[#4a412f] bg-[#241f14] px-2.5 py-1 text-[11px] font-semibold text-[#e2c88a]">
                      {method.badge}
                    </span>
                  </button>
                );
              })}
            </section>

            <section className="rounded-2xl border border-[#2f3645] bg-[#171d2a] p-4 sm:p-5">
              <h3 className="font-['Sora'] text-base font-semibold">Send Details</h3>
              <div className="mt-4 grid gap-3">
                <Field label={selectedMethod === "exa-user" ? "Recipient Username / Email / Wallet ID" : "Recipient Address"}>
                  <input
                    value={recipient}
                    onChange={(event) => setRecipient(event.target.value)}
                    placeholder={selectedMethod === "exa-user" ? "e.g. exa_user_001" : "Paste wallet address"}
                    className="w-full rounded-xl border border-[#3a4252] bg-[#111827] px-3 py-2.5 text-sm text-[#ece5d2] placeholder:text-[#7d7a72] outline-none transition focus:border-[#c9a458] focus:ring-2 focus:ring-[#c9a458]/25"
                  />
                </Field>

                {needsNetwork ? (
                  <Field label="Network">
                    <select
                      value={network}
                      onChange={(event) => setNetwork(event.target.value)}
                      className="w-full rounded-xl border border-[#3a4252] bg-[#111827] px-3 py-2.5 text-sm text-[#ece5d2] outline-none transition focus:border-[#c9a458] focus:ring-2 focus:ring-[#c9a458]/25"
                    >
                      <option>XRP Ledger</option>
                      <option>BSC</option>
                      <option>Ethereum</option>
                      <option>Polygon</option>
                    </select>
                  </Field>
                ) : null}

                <Field label="Amount">
                  <div className="flex items-center gap-2 rounded-xl border border-[#3a4252] bg-[#111827] px-3 py-1.5 focus-within:border-[#c9a458] focus-within:ring-2 focus-within:ring-[#c9a458]/25">
                    <input
                      value={amount}
                      onChange={(event) => setAmount(event.target.value)}
                      placeholder="0.00"
                      type="number"
                      className="w-full bg-transparent py-1 text-sm text-[#ece5d2] placeholder:text-[#7d7a72] outline-none"
                    />
                    <button
                      type="button"
                      onClick={() => setAmount(String(currentAsset.available))}
                      className="rounded-lg border border-[#5a4a2a] bg-[#241f14] px-2.5 py-1 text-xs font-semibold text-[#e7cb8f] transition hover:border-[#d1ad64]"
                    >
                      Max
                    </button>
                  </div>
                </Field>

                <Field label="Fee Display">
                  <div className="rounded-xl border border-[#2f3645] bg-[#141a26] px-3 py-2.5 text-sm text-[#b6b09f]">{fee} {asset}</div>
                </Field>

                <Field label="Estimated Arrival Time">
                  <div className="rounded-xl border border-[#2f3645] bg-[#141a26] px-3 py-2.5 text-sm text-[#b6b09f]">{estimatedArrival}</div>
                </Field>

                <Field label="Memo (Optional)">
                  <input
                    value={memo}
                    onChange={(event) => setMemo(event.target.value)}
                    placeholder="Add a note"
                    className="w-full rounded-xl border border-[#3a4252] bg-[#111827] px-3 py-2.5 text-sm text-[#ece5d2] placeholder:text-[#7d7a72] outline-none transition focus:border-[#c9a458] focus:ring-2 focus:ring-[#c9a458]/25"
                  />
                </Field>
              </div>
            </section>
          </div>

          <div className="space-y-5">
            <section className="rounded-2xl border border-[#2f3645] bg-[#171d2a] p-4 sm:p-5">
              <h3 className="font-['Sora'] text-base font-semibold">Transaction Summary</h3>
              <div className="mt-3 divide-y divide-[#2a3140]">
                {summaryRows.map((row) => (
                  <div key={row.label} className="flex items-center justify-between py-2.5 text-sm">
                    <span className="text-[#a8a192]">{row.label}</span>
                    <span className="font-semibold text-[#efe7d3]">{row.value}</span>
                  </div>
                ))}
              </div>
            </section>
          </div>
        </div>
      </section>

      <div className="fixed inset-x-0 bottom-0 z-20 border-t border-[#2b3240] bg-[#121722]/95 p-3 backdrop-blur md:static md:mx-auto md:mt-5 md:max-w-7xl md:border-0 md:bg-transparent md:p-0">
        <button
          type="button"
          disabled={!isFormComplete}
          onClick={() => setShowConfirm(true)}
          className={`w-full rounded-xl px-4 py-3.5 text-sm font-semibold transition-all duration-300 ${
            isFormComplete
              ? "bg-gradient-to-r from-[#b68a36] via-[#d2ab55] to-[#f0cf84] text-[#111] hover:shadow-[0_0_30px_rgba(210,171,85,0.45)]"
              : "cursor-not-allowed border border-[#364052] bg-[#1a2233] text-[#7f8797]"
          }`}
        >
          Send Securely
        </button>
      </div>

      {showConfirm ? (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/70 px-4">
          <div className="w-full max-w-md animate-[fade-in_200ms_ease-out] rounded-2xl border border-[#4a3f2a] bg-[#161b26] p-5 shadow-[0_20px_50px_rgba(0,0,0,0.55)]">
            <div className="mb-3 flex items-start justify-between">
              <div>
                <h4 className="font-['Sora'] text-lg font-semibold text-[#f3ead7]">Confirm Transfer</h4>
                <p className="mt-1 text-xs text-[#a9a292]">Review transaction details before submitting.</p>
              </div>
              <button
                type="button"
                onClick={() => setShowConfirm(false)}
                className="rounded-lg border border-[#3b4252] bg-[#1b2232] p-1.5 text-[#bbb4a5] transition hover:border-[#c9a458] hover:text-[#e8d2a2]"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="rounded-xl border border-[#2d3444] bg-[#121826] p-3 text-sm">
              <p className="flex justify-between py-1"><span className="text-[#9f9888]">Method</span><span>{transferMethods.find((item) => item.id === selectedMethod)?.title}</span></p>
              <p className="flex justify-between py-1"><span className="text-[#9f9888]">Recipient</span><span>{recipient || "-"}</span></p>
              <p className="flex justify-between py-1"><span className="text-[#9f9888]">Amount</span><span>{safeAmount || 0} {asset}</span></p>
              <p className="flex justify-between py-1"><span className="text-[#9f9888]">Fee</span><span>{fee} {asset}</span></p>
              <p className="flex justify-between py-1 font-semibold"><span className="text-[#9f9888]">Total</span><span>{totalDeduction.toFixed(4)} {asset}</span></p>
            </div>

            <p className="mt-3 flex items-start gap-2 text-xs text-[#c9c1ad]">
              <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-[#d1ac63]" />
              All transfers are protected by encrypted smart contract verification.
            </p>

            <div className="mt-4 grid grid-cols-2 gap-2">
              <button
                type="button"
                onClick={() => setShowConfirm(false)}
                className="rounded-xl border border-[#3c4455] bg-[#1a2233] px-3 py-2.5 text-sm font-semibold text-[#d5cebc] transition hover:border-[#8a7750]"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={() => setShowConfirm(false)}
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#b68a36] via-[#d2ab55] to-[#f0cf84] px-3 py-2.5 text-sm font-semibold text-[#111] transition hover:shadow-[0_0_24px_rgba(210,171,85,0.45)]"
              >
                <LoaderCircle className="h-4 w-4" />
                Confirm
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </main>
  );
}

function Field({ label, children }) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-xs font-semibold uppercase tracking-[0.11em] text-[#9f9888]">{label}</span>
      {children}
    </label>
  );
}

export default Send;
